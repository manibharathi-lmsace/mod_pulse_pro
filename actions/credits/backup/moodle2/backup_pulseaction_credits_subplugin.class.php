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
 * This file contains the class for backup of this pulse action credits plugin.
 *
 * @package   pulseaction_credits
 * @copyright 2025, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Provides the information to backup of the pulse action credits.
 */
class backup_pulseaction_credits_subplugin extends backup_subplugin {
    /**
     * Returns the subplugin information to attach to submission element
     * @return backup_subplugin_element
     */
    protected function define_pulse_autoinstances_subplugin_structure() {

        // Create XML elements.
        $subplugin = $this->get_subplugin_element();

        // Credits action template.
        $action = new \backup_nested_element('creditsaction');
        $actionfields = new \backup_nested_element('pulseaction_credits', ['id'], [
            "templateid", "actionstatus", "credits", "allocationmethod", "notifyinterval", "intervaltype",
            "basedatetype", "fixeddate", "recipients", "timecreated", "timemodified",
        ]);

        $actionins = new \backup_nested_element('creditsactionins');
        $actioninsfields = new \backup_nested_element('pulseaction_credits_ins', ['id'], [
            "instanceid", "actionstatus", "credits", "allocationmethod", "notifyinterval", "intervaltype",
            "basedatetype", "fixeddate", "recipients", "timecreated", "timemodified",
        ]);

        $subplugin->add_child($action);
        $action->add_child($actionfields);

        $subplugin->add_child($actionins);
        $actionins->add_child($actioninsfields);

        // Credits template data source query.
        $actionfields->set_source_sql('
            SELECT pc.*
            FROM {pulseaction_credits} pc
            JOIN {pulse_autotemplates} at ON at.id = pc.templateid
            WHERE at.id IN (
                SELECT templateid
                FROM {pulse_autoinstances}
                WHERE courseid = :courseid
            )
        ', ['courseid' => backup::VAR_COURSEID]);

        $actioninsfields->set_source_table('pulseaction_credits_ins', ['instanceid' => \backup::VAR_PARENTID]);

        return $subplugin;
    }
}
