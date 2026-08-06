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
 * Pulse credits datasource for the credit allocation schedules.
 *
 * @package   pulseaction_credits
 * @copyright 2025 bdecent GmbH <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulseaction_credits\reportbuilder\datasource;

use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\local\entities\user;
use mod_pulse\local\entities\automation_instance;
use mod_pulse\local\entities\automation_template;
use pulseaction_credits\local\entities\creditreport;

/**
 * Credits datasource definition for the list of schedules.
 */
class credits extends datasource {
    /**
     * Return user friendly name of the datasource.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('creditshedulereport', 'pulseaction_credits');
    }

    /**
     * Initialise report.
     */
    protected function initialise(): void {

        // Main credits entity.
        $creditsentity = new \pulseaction_credits\local\entities\creditallocation();
        $creditsschalias = $creditsentity->get_table_alias('pulseaction_credits_sch');
        $this->set_main_table('pulseaction_credits_sch', $creditsschalias);
        $this->add_entity($creditsentity);

        // Add core automation instance entity.
        $instanceentity = new automation_instance();
        $instancealias = $instanceentity->get_table_alias('pulse_autoinstances');
        $instancejoin = "JOIN {pulse_autoinstances} {$instancealias} ON {$instancealias}.id = {$creditsschalias}.instanceid";
        $this->add_entity($instanceentity->add_join($instancejoin));
        $this->add_join($instancejoin);

        // Add instance template join.
        $this->add_join($instanceentity->get_instance_join($instancealias));

        // Add core automation template entity.
        $templateentity = new automation_template();
        $templatealias = $templateentity->get_table_alias('pulse_autotemplates');
        $templatejoin = "JOIN {pulse_autotemplates} {$templatealias} ON {$templatealias}.id = {$instancealias}.templateid";
        $this->add_entity($templateentity->add_join($templatejoin));

        // Add creditreport entity for Credits configuration fields.
        $creditreportentity = new creditreport();
        $creditstemplatealias = $creditreportentity->get_table_alias('pulseaction_credits');
        $creditsinstancealias = $creditreportentity->get_table_alias('pulseaction_credits_ins');

        // Join to credits template configuration.
        $creditstemplatejoin = "LEFT JOIN {pulseaction_credits}
            {$creditstemplatealias} ON {$creditstemplatealias}.templateid = {$templatealias}.id";

        // Join to credits instance configuration.
        $creditsinstancejoin = "LEFT JOIN {pulseaction_credits_ins}
            {$creditsinstancealias} ON {$creditsinstancealias}.instanceid = {$instancealias}.id";

        $creditreportentity->add_join($templatejoin);
        $creditreportentity->add_join($instancejoin);
        $creditreportentity->add_join($creditstemplatejoin);
        $creditreportentity->add_join($creditsinstancejoin);
        $this->add_entity($creditreportentity);

        // Add core user join.
        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $userjoin = "JOIN {user} {$useralias} ON {$useralias}.id = {$creditsschalias}.userid";
        $this->add_entity($userentity->add_join($userjoin));

        // Add core course join.
        $coursentity = new course();
        $coursealias = $coursentity->get_table_alias('course');
        $coursejoin = "JOIN {course} {$coursealias} ON {$coursealias}.id = {$instancealias}.courseid";
        $this->add_entity($coursentity->add_join($coursejoin));

        $instance = (int) $this->get_report_persistent()->get('itemid');
        if ($instance) {
            $this->add_base_condition_simple("{$creditsschalias}.instanceid", $instance);
        }

        // Support for 4.2.
        if (method_exists($this, 'add_all_from_entities')) {
            $this->add_all_from_entities();
        } else {
            // Add all the entities used in credits datasource. moodle 4.0 support.
            $this->add_columns_from_entity($creditsentity->get_entity_name());
            $this->add_filters_from_entity($creditsentity->get_entity_name());
            $this->add_conditions_from_entity($creditsentity->get_entity_name());

            $this->add_columns_from_entity($instanceentity->get_entity_name());
            $this->add_filters_from_entity($instanceentity->get_entity_name());
            $this->add_conditions_from_entity($instanceentity->get_entity_name());

            $this->add_columns_from_entity($templateentity->get_entity_name());
            $this->add_filters_from_entity($templateentity->get_entity_name());
            $this->add_conditions_from_entity($templateentity->get_entity_name());

            $this->add_columns_from_entity($userentity->get_entity_name());
            $this->add_filters_from_entity($userentity->get_entity_name());
            $this->add_conditions_from_entity($userentity->get_entity_name());

            $this->add_columns_from_entity($coursentity->get_entity_name());
            $this->add_filters_from_entity($coursentity->get_entity_name());
            $this->add_conditions_from_entity($coursentity->get_entity_name());

            // Add creditreport entity columns and filters.
            $this->add_columns_from_entity($creditreportentity->get_entity_name());
            $this->add_filters_from_entity($creditreportentity->get_entity_name());
            $this->add_conditions_from_entity($creditreportentity->get_entity_name());
        }
    }

    /**
     * Default columns that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_columns(): array {

        return [
            'course:coursefullnamewithlink',
            'user:fullnamewithlink',
            'automation_instance:reference',
            'creditallocation:credits',
            'creditallocation:scheduletime',
            'creditallocation:allocatedtime',
            'creditallocation:status',
            'creditallocation:allocationmethod',
        ];
    }

    /**
     * List of filters that will be added to the report once is created
     *
     * @return array
     */
    public function get_default_filters(): array {
        return [
            'automation_instance:reference',
            'automation_template:title',
            'creditallocation:status',
            'creditallocation:scheduletime',
            'creditallocation:allocationmethod',
        ];
    }

    /**
     * Return the conditions that will be added to the report once is created
     *
     * @return array
     */
    public function get_default_conditions(): array {

        return [
            'creditallocation:status',
        ];
    }
}
