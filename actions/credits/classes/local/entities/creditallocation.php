<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Pulse credit allocation entities for report builder.
 *
 * @package   pulseaction_credits
 * @copyright 2025 bdecent GmbH <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace pulseaction_credits\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\report\{column, filter};
use core_reportbuilder\local\filters\{date, number, select};
use core_reportbuilder\local\helpers\format;
use pulseaction_credits\local\credits;
use lang_string;
use mod_pulse\local\automation\schedule;

/**
 * Pulse credit allocation entity base for report source.
 */
class creditallocation extends base {
    /**
     * Database tables that this entity uses.
     *
     * @return array
     */
    protected function get_default_tables(): array {

        return [
            'pulseaction_credits_sch',
            'pulseaction_credits_ins',
            'pulseaction_credits',
            'pulseaction_credits_override',
        ];
    }

    /**
     * Database tables that this entity uses and their default aliases.
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {

        return [
            'pulseaction_credits_sch' => 'pcasch',
            'pulseaction_credits_ins' => 'pcacins',
            'pulseaction_credits' => 'pcac_tmpl',
            'pulseaction_credits_override' => 'pcaco',
        ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('creditshedulereport', 'pulseaction_credits');
    }

    /**
     * Initialise the credit allocation datasource columns and filter, conditions.
     *
     * @return base
     */
    public function initialise(): base {

        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        [$filters, $conditions] = $this->get_all_filters();
        foreach ($filters as $filter) {
            $this->add_filter($filter);
        }

        foreach ($conditions as $condition) {
            $this->add_condition($condition);
        }

        return $this;
    }

    /**
     * List of columns available for this credit allocation datasource.
     *
     * @return array
     */
    protected function get_all_columns(): array {

        $creditsschalias = $this->get_table_alias('pulseaction_credits_sch');

        $columns = [];

        // Time the schedule is created.
        $columns[] = (new column(
            'timecreated',
            new lang_string('timecreated', 'pulseaction_credits'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$creditsschalias}.timecreated")
        ->add_callback(static function ($value): string {
            return userdate($value);
        });

        // Schedule time to allocate credits.
        $columns[] = (new column(
            'scheduletime',
            new lang_string('scheduledtime', 'pulseaction_credits'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$creditsschalias}.scheduletime")
        ->add_callback(static function ($value): string {
            return userdate($value);
        });

        // Allocated time.
        $columns[] = (new column(
            'allocatedtime',
            new lang_string('allocatedtime', 'pulseaction_credits'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$creditsschalias}.completedtime", 'completedtime')
        ->add_callback(static function ($value, $row): string {
            return $row->completedtime ? userdate($row->completedtime) : '-';
        });

        // Instance id.
        $columns[] = (new column(
            'instanceid',
            new lang_string('instanceid', 'pulseaction_credits'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$creditsschalias}.instanceid");

        // Credits.
        $columns[] = (new column(
            'credits',
            new lang_string('scheduledcredits', 'pulseaction_credits'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$creditsschalias}.credits");

        // Allocation method.
        $columns[] = (new column(
            'allocationmethod',
            new lang_string('allocationmethod', 'pulseaction_credits'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$creditsschalias}.allocationmethod")
        ->add_callback(static function ($value): string {
            switch ($value) {
                case credits::ALLOCATION_ADD:
                    return get_string('addcredits', 'pulseaction_credits');
                case credits::ALLOCATION_REPLACE:
                    return get_string('replacecredits', 'pulseaction_credits');
                default:
                    return '-';
            }
        });

        // Status of the schedule.
        $columns[] = (new column(
            'status',
            new lang_string('status', 'pulseaction_credits'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$creditsschalias}.status")
        ->add_callback(static function ($value): string {
            // Update the status strings.
            switch ($value) {
                case schedule::STATUS_QUEUED:
                    return get_string('planned', 'pulseaction_credits');
                case schedule::STATUS_COMPLETED:
                    return get_string('allocated', 'pulseaction_credits');
                case schedule::STATUS_FAILED:
                    return get_string('failed', 'pulseaction_credits');
                case schedule::STATUS_DISABLED:
                    return get_string('onhold', 'pulseaction_credits');
                case schedule::STATUS_PROCESSING:
                    return get_string('sending', 'pulseaction_credits');
                default:
                    return get_string('unknown', 'pulseaction_credits');
            }
        });

        return $columns;
    }

    /**
     * Defined filters for the credit allocation entities.
     *
     * @return array
     */
    protected function get_all_filters(): array {
        global $DB;

        $creditsschalias = $this->get_table_alias('pulseaction_credits_sch');

        $filters = [];
        $conditions = [];

        // Status of the schedule.
        $schedulestatus = (new filter(
            select::class,
            'status',
            new lang_string('status', 'pulseaction_credits'),
            $this->get_entity_name(),
            "{$creditsschalias}.status",
        ))->set_options([
            schedule::STATUS_QUEUED => get_string('planned', 'pulseaction_credits'),
            schedule::STATUS_DISABLED => get_string('onhold', 'pulseaction_credits'),
            schedule::STATUS_COMPLETED => get_string('allocated', 'pulseaction_credits'),
            schedule::STATUS_FAILED => get_string('failed', 'pulseaction_credits'),
            schedule::STATUS_PROCESSING => get_string('sending', 'pulseaction_credits'),
        ]);
        $filters[] = $schedulestatus;
        $conditions[] = $schedulestatus;

        // Allocation method filter.
        $allocationmethod = (new filter(
            select::class,
            'allocationmethod',
            new lang_string('allocationmethod', 'pulseaction_credits'),
            $this->get_entity_name(),
            "{$creditsschalias}.allocationmethod",
        ))->set_options([
            credits::ALLOCATION_ADD => get_string('addcredits', 'pulseaction_credits'),
            credits::ALLOCATION_REPLACE => get_string('replacecredits', 'pulseaction_credits'),
        ]);

        $filters[] = $allocationmethod;
        $conditions[] = $allocationmethod;

        // Credits filter.
        $schcredits = (new filter(
            number::class,
            'credits',
            new lang_string('credits', 'pulseaction_credits'),
            $this->get_entity_name(),
            "{$creditsschalias}.credits"
        ));
        $filters[] = $schcredits;
        $conditions[] = $schcredits;

        // Scheduled time date filter.
        $timecreated = (new filter(
            date::class,
            'timecreated',
            new lang_string('schedulecreatedtime', 'pulseaction_credits'),
            $this->get_entity_name(),
            "{$creditsschalias}.timecreated"
        ));
        $filters[] = $timecreated;
        $conditions[] = $timecreated;

        // Filter by the schedule time.
        $scheduletime = (new filter(
            date::class,
            'scheduletime',
            new lang_string('scheduledtime', 'pulseaction_credits'),
            $this->get_entity_name(),
            "{$creditsschalias}.scheduletime"
        ));
        $filters[] = $scheduletime;
        $conditions[] = $scheduletime;

        // Filter by allocated time.
        $filters[] = (new filter(
            date::class,
            'allocatedtime',
            new lang_string('allocatedtime', 'pulseaction_credits'),
            $this->get_entity_name(),
            "{$creditsschalias}.completedtime"
        ));

        return [$filters, $conditions];
    }
}
