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
 * Credit instance report entity for report builder.
 *
 * @package   pulseaction_credits
 * @copyright 2025 bdecent GmbH <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace pulseaction_credits\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\report\{column, filter};
use core_reportbuilder\local\filters\{date, number, select, text};
use pulseaction_credits\local\credits;
use lang_string;
use mod_pulse\local\automation\schedule;

/**
 * Credit report entity focusing on Credits fields only.
 */
class creditreport extends base {
    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_tables(): array {
        return [
            'pulseaction_credits',
            'pulseaction_credits_ins',
        ];
    }

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return [
            'pulseaction_credits' => 'pcr_tmpl',
            'pulseaction_credits_ins' => 'pcr_ins',
        ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('creditinstance', 'pulseaction_credits');
    }

    /**
     * Initialise the credit report entity columns and filters.
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
     * List of columns available for this credit report entity.
     *
     * @return array
     */
    protected function get_all_columns(): array {
        $templatealias = $this->get_table_alias('pulseaction_credits');
        $instancealias = $this->get_table_alias('pulseaction_credits_ins');

        $columns = [];

        // Credits status.
        $columns[] = (new column(
            'actionstatus',
            new lang_string('status', 'pulseaction_credits'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$templatealias}.actionstatus")
        ->add_field("{$instancealias}.actionstatus", 'inscreditsstatus')
        ->add_callback(static function ($value, $row): string {
            $value = $value ?: $row->inscreditsstatus;
            return $value ? get_string('enabled', 'pulseaction_credits') : get_string('disabled', 'pulseaction_credits');
        });

        // Credit amount.
        $columns[] = (new column(
            'creditamount',
            new lang_string('credits', 'pulseaction_credits'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$instancealias}.credits")
        ->add_field("{$templatealias}.credits", 'tempcredits')
        ->add_callback(static function ($value, $row): string {
            return $value ?: $row->tempcredits;
        });

        // Allocation method.
        $columns[] = (new column(
            'allocationmethod',
            new lang_string('allocationmethod', 'pulseaction_credits'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$instancealias}.allocationmethod")
        ->add_field("{$templatealias}.allocationmethod", 'tempallocationmethod')
        ->add_callback(static function ($value, $row): string {

            $value = $value ?: $row->tempallocationmethod;

            $methods = credits::get_allocation_methods();
            switch ($value) {
                case credits::ALLOCATION_ADD:
                    return $methods[credits::ALLOCATION_ADD];
                case credits::ALLOCATION_REPLACE:
                    return $methods[credits::ALLOCATION_REPLACE];
                default:
                    return '-';
            }
        });

        // Interval.
        $columns[] = (new column(
            'interval',
            new lang_string('interval', 'pulseaction_credits'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$instancealias}.intervaltype")
        ->add_field("{$templatealias}.intervaltype", 'tempnotifyinterval')
        ->add_callback(static function ($value, $row): string {
            $value = $value ?: $row->tempnotifyinterval;
            switch ($value) {
                case schedule::INTERVALONCE:
                    return get_string('once', 'mod_pulse');
                case schedule::INTERVALDAILY:
                    return get_string('daily', 'mod_pulse');
                case schedule::INTERVALWEEKLY:
                    return get_string('weekly', 'mod_pulse');
                case schedule::INTERVALMONTHLY:
                    return get_string('monthly', 'mod_pulse');
                case schedule::INTERVALYEARLY:
                    return get_string('yearly', 'mod_pulse');
                case schedule::INTERVALCUSTOM:
                    return get_string('custom', 'mod_pulse');
                default:
                    return get_string('unknown', 'pulseaction_credits');
            }
        });

        // Base date for interval.
        $columns[] = (new column(
            'basedateinterval',
            new lang_string('basedate', 'pulseaction_credits'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("COALESCE({$instancealias}.basedatetype, {$templatealias}.basedatetype)", 'insbasedatetype')
        ->add_callback(static function ($value, $row): string {
            $date = credits::get_basedatetypes();
            switch ($value) {
                case credits::BASEDATEFIXED:
                    return $date[credits::BASEDATEFIXED];
                case credits::BASEDATERELATIVE:
                    return $date[credits::BASEDATERELATIVE];
                default:
                    return get_string('unknown', 'pulseaction_credits');
            }
        });

        // Set fixed base date.
        $columns[] = (new column(
            'fixedbasedate',
            new lang_string('fixeddate', 'pulseaction_credits'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$instancealias}.fixeddate")
        ->add_field("{$templatealias}.fixeddate", 'tempfixeddate')
        ->add_callback(static function ($value, $row): string {
            $value = $value ?: $row->tempfixeddate;
            if ($value) {
                return userdate($value);
            }
            return '-';
        });

        // Recipients.
        $columns[] = (new column(
            'recipients',
            new lang_string('recipients', 'pulseaction_credits'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(false)
        ->add_field("{$templatealias}.recipients")
        ->add_field("{$instancealias}.recipients", 'insrecipients')
        ->add_callback(static function ($value, $row): string {
            global $DB;
            $value = $row->insrecipients ?? $value;
            if ($value) {
                $recipients = json_decode($value, true);
                if (is_array($recipients)) {
                    [$insql, $params] = $DB->get_in_or_equal($recipients, SQL_PARAMS_NAMED);
                    $sql = "SELECT * FROM {role} WHERE id {$insql}";
                    $roles = $DB->get_records_sql($sql, $params);
                    $roles = \role_fix_names($roles);
                    return implode(', ', array_column($roles, 'localname'));
                }
            }
            return get_string('notconfigured', 'pulseaction_credits');
        });

        return $columns;
    }

    /**
     * Get all filters for this entity.
     *
     * @return array Filters and conditions lists
     */
    protected function get_all_filters(): array {

        $templatealias = $this->get_table_alias('pulseaction_credits');
        $instancealias = $this->get_table_alias('pulseaction_credits_ins');

        $filters = [];
        $conditions = [];

        // Credit status filter.
        $actionstatus = (new filter(
            select::class,
            'actionstatus',
            new lang_string('status', 'pulseaction_credits'),
            $this->get_entity_name(),
            "COALESCE({$instancealias}.actionstatus, {$templatealias}.actionstatus)"
        ))->set_options([
            1 => get_string('enabled', 'pulseaction_credits'),
            0 => get_string('disabled', 'pulseaction_credits'),
        ]);
        $filters[] = $actionstatus;
        $conditions[] = $actionstatus;

        // Credit filter.
        $creditamount = (new filter(
            number::class,
            'creditamount',
            new lang_string('credits', 'pulseaction_credits'),
            $this->get_entity_name(),
            "COALESCE({$instancealias}.credits, {$templatealias}.credits)"
        ));
        $filters[] = $creditamount;
        $conditions[] = $creditamount;

        // Allocation method filter.
        $allocationmethod = (new filter(
            select::class,
            'allocationmethod',
            new lang_string('allocationmethod', 'pulseaction_credits'),
            $this->get_entity_name(),
            "COALESCE({$instancealias}.allocationmethod, {$templatealias}.allocationmethod)"
        ))->set_options([
            credits::ALLOCATION_ADD => get_string('addcredits', 'pulseaction_credits'),
            credits::ALLOCATION_REPLACE => get_string('replacecredits', 'pulseaction_credits'),
        ]);
        $filters[] = $allocationmethod;
        $conditions[] = $allocationmethod;

        // Interval filter.
        $interval = (new filter(
            select::class,
            'interval',
            new lang_string('interval', 'pulseaction_credits'),
            $this->get_entity_name(),
            "COALESCE({$instancealias}.intervaltype, {$templatealias}.intervaltype)"
        ))->set_options([
            schedule::INTERVALONCE => get_string('once', 'mod_pulse'),
            schedule::INTERVALDAILY => get_string('daily', 'mod_pulse'),
            schedule::INTERVALWEEKLY => get_string('weekly', 'mod_pulse'),
            schedule::INTERVALMONTHLY => get_string('monthly', 'mod_pulse'),
            schedule::INTERVALYEARLY => get_string('yearly', 'mod_pulse'),
            schedule::INTERVALCUSTOM => get_string('intervalcustom', 'pulseaction_credits'),
        ]);
        $filters[] = $interval;
        $conditions[] = $interval;

        // Base date type filter.
        $basedateinterval = (new filter(
            select::class,
            'basedateinterval',
            new lang_string('basedate', 'pulseaction_credits'),
            $this->get_entity_name(),
            "COALESCE({$instancealias}.basedatetype, {$templatealias}.basedatetype)"
        ))->set_options([
            credits::BASEDATEFIXED => get_string('basedatefixed', 'pulseaction_credits'),
            credits::BASEDATERELATIVE => get_string('basedaterelative', 'pulseaction_credits'),
        ]);
        $filters[] = $basedateinterval;
        $conditions[] = $basedateinterval;

        return [$filters, $conditions];
    }
}
