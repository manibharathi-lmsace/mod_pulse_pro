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
 * Definition backup-steps - Course structure step.
 *
 * @package   mod_pulse
 * @copyright 2021, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_pulse\automation\action_base;
use mod_pulse\plugininfo\pulseaction;
use mod_pulse\plugininfo\pulsecondition;

/**
 * Define the complete pulse structure for backup, with file and id annotations.
 */
class backup_pulse_course_structure_step extends backup_activity_structure_step {
    /**
     * Define the pulse course structure steps.
     */
    protected function define_structure() {

        $automationinstance = new backup_nested_element('automationinstance');
        $instances = new backup_nested_element('pulse_autoinstances', ['id'], [
            'templateid', 'courseid', 'status', 'timemodified',
        ]);

        // Automation templates.
        $automation = new backup_nested_element('automationtemplates');
        $templates = new backup_nested_element('pulse_autotemplates', ['id'], [
            'title', 'reference', 'visible', 'notes', 'status', 'tags', 'tenants',
            'categories', 'triggerconditions', 'triggeroperator', 'timemodified',
        ]);

        $automationtempinstance = new backup_nested_element('automationtemplateinstance');
        $tempinstances = new backup_nested_element('pulse_autotemplates_ins', ['id'], [
            'instanceid', 'title', 'insreference', 'notes', 'tags', 'tenants',
            'categories', 'triggerconditions', 'triggeroperator', 'timemodified',
        ]);

        $pulseconditionoverrides = new backup_nested_element('pulseconditionoverrides');
        $overrides = new backup_nested_element('pulse_condition_overrides', ['id'], [
            'instanceid', 'triggercondition', 'status', 'upcomingtime', 'additional', 'isoverridden',
        ]);

        // Automation template.
        $instances->add_child($automation);
        $automation->add_child($templates);

        // Automation template instance.
        $instances->add_child($automationtempinstance);
        $automationtempinstance->add_child($tempinstances);

        // Condition overrides.
        $instances->add_child($pulseconditionoverrides);
        $pulseconditionoverrides->add_child($overrides);

        // Pulse instance.
        $instances->set_source_table('pulse_autoinstances', ['courseid' => backup::VAR_COURSEID]);
        $tempinstances->set_source_table('pulse_autotemplates_ins', ['instanceid' => backup::VAR_PARENTID]);
        $overrides->set_source_table('pulse_condition_overrides', ['instanceid' => backup::VAR_PARENTID]);

        // Pulse autotemplates.
        $templates->set_source_sql('
            SELECT *
            FROM {pulse_autotemplates} at
            WHERE at.id IN (
                SELECT templateid
                FROM {pulse_autoinstances}
                WHERE courseid = :courseid AND id =:instanceid
            )
        ', ['courseid' => backup::VAR_COURSEID, 'instanceid' => backup::VAR_PARENTID]);

        // Include the backup steps for actions and conditions.
        $this->add_subplugin_structure('pulseaction', $instances, true);
        $this->add_subplugin_structure('pulsecondition', $instances, true);

        // Return the root element (data), wrapped into standard activity structure.
        return $this->prepare_activity_structure($instances);
    }
}
