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
 * Pulse automation instance entity for report builder.
 *
 * @package   mod_pulse
 * @copyright 2025, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pulse\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\report\{column, filter};
use core_reportbuilder\local\filters\{date, number, select, text};
use core_reportbuilder\local\helpers\format;
use lang_string;

/**
 * Pulse automation instance entity for report builder.
 */
class automation_instance extends base {
    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_tables(): array {
        return [
            'pulse_autoinstances',
            'pulse_autotemplates',
            'pulse_autotemplates_ins',
        ];
    }

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return [
            'pulse_autoinstances' => 'pai',
            'pulse_autotemplates' => 'pat',
            'pulse_autotemplates_ins' => 'pati',
        ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('automationinstance', 'mod_pulse');
    }

    /**
     * Initialise the automation instance entity.
     *
     * @return base
     */
    public function initialise(): base {

        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $column->add_joins($this->get_joins());
            $this->add_column($column);
        }

        [$filters, $conditions] = $this->get_all_filters();
        foreach ($filters as $filter) {
            $filter->add_joins($this->get_joins());
            $this->add_filter($filter);
        }

        foreach ($conditions as $condition) {
            $condition->add_joins($this->get_joins());
            $this->add_condition($condition);
        }

        return $this;
    }

    /**
     * List of columns available for automation instance.
     *
     * @return array
     */
    protected function get_all_columns(): array {
        global $DB;

        $instancealias = $this->get_table_alias('pulse_autoinstances');
        $templatealias = $this->get_table_alias('pulse_autotemplates');
        $templatesinsalias = $this->get_table_alias('pulse_autotemplates_ins');

        $columns = [];

        $templatejoin = "JOIN {pulse_autotemplates} {$templatealias} ON {$templatealias}.id = {$instancealias}.templateid";

        // Instance ID.
        $columns[] = (new column(
            'id',
            new lang_string('instanceid', 'mod_pulse'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$instancealias}.id");

        // Instance title.
        $columns[] = (new column(
            'title',
            new lang_string('institle', 'mod_pulse'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("COALESCE({$templatesinsalias}.title, {$templatealias}.title)", 'institle')
        ->add_join($templatejoin)
        ->add_callback(static function ($value, $row): string {
            $val = $row->institle ?: '';
            if ($val && isset($row->templatetitle)) {
                $val .= $row->templatetitle;
            }
            return format_string($val);
        });

        $concat = $DB->sql_concat("COALESCE({$templatealias}.reference, '')", "{$templatesinsalias}.insreference");
        // Instance reference.
        $columns[] = (new column(
            'reference',
            new lang_string('insreference', 'mod_pulse'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$concat}", "insreference")
        ->add_join($templatejoin)
        ->add_callback(static function ($value, $row): string {
            $val = $row->insreference ?: '';
            return format_string($val);
        });

        // Instance internal notes.
        $columns[] = (new column(
            'internalnotes',
            new lang_string('internalnotes', 'mod_pulse'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(false)
        ->add_field("{$templatesinsalias}.notes")
        ->add_callback(static function ($value, $row): string {
            return $value ? format_text($value, FORMAT_HTML) : '';
        });

        // Instance status.
        $columns[] = (new column(
            'status',
            new lang_string('status', 'mod_pulse'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$instancealias}.status")
        ->add_callback(static function ($value, $row): string {
            return $value ? get_string('enabled', 'mod_pulse') : get_string('disabled', 'mod_pulse');
        });

        // Instance course ID.
        $columns[] = (new column(
            'courseid',
            new lang_string('courseid', 'mod_pulse'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$instancealias}.courseid");

        // Time modified.
        $columns[] = (new column(
            'timemodified',
            new lang_string('timemodified', 'mod_pulse'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$instancealias}.timemodified")
        ->add_callback(static function ($value, $row): string {
            return userdate($value);
        });

        return $columns;
    }

    /**
     * Defined filters for the automation instance entity.
     *
     * @return array
     */
    protected function get_all_filters(): array {

        $instancealias = $this->get_table_alias('pulse_autoinstances');
        $templatesinsalias = $this->get_table_alias('pulse_autotemplates_ins');

        $filters = [];
        $conditions = [];

        // Instance ID filter.
        $conditions[] = (new filter(
            number::class,
            'id',
            new lang_string('instanceid', 'mod_pulse'),
            $this->get_entity_name(),
            "{$instancealias}.id"
        ));

        // Instance title filter.
        $filters[] = (new filter(
            text::class,
            'title',
            new lang_string('institle', 'mod_pulse'),
            $this->get_entity_name(),
            "{$templatesinsalias}.title"
        ));

        // Instance reference filter.
        $filters[] = (new filter(
            text::class,
            'reference',
            new lang_string('insreference', 'mod_pulse'),
            $this->get_entity_name(),
            "{$templatesinsalias}.insreference"
        ));

        // Instance status filter.
        $filters[] = (new filter(
            select::class,
            'status',
            new lang_string('status', 'mod_pulse'),
            $this->get_entity_name(),
            "{$instancealias}.status"
        ))->set_options([
            0 => get_string('disabled', 'mod_pulse'),
            1 => get_string('enabled', 'mod_pulse'),
        ]);

        // Time modified filter.
        $filters[] = (new filter(
            date::class,
            'timemodified',
            new lang_string('timemodified', 'mod_pulse'),
            $this->get_entity_name(),
            "{$instancealias}.timemodified"
        ));

        return [$filters, $conditions];
    }

    /**
     * Get the join SQL for linking instance to template instance data.
     *
     * @param string $instancealias The alias for the main table
     * @return string
     */
    public function get_instance_join(string $instancealias): string {
        $templatesinsalias = $this->get_table_alias('pulse_autotemplates_ins');

        return "LEFT JOIN {pulse_autotemplates_ins} {$templatesinsalias}
                ON {$templatesinsalias}.instanceid = {$instancealias}.id";
    }
}
