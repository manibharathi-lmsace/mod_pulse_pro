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
 * Pulse module upgrade steps.
 *
 * @package   pulseaction_credits
 * @copyright 2021, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Pulse module upgrade steps.
 *
 * @param  mixed $oldversion Previous version.
 * @return void
 */
function xmldb_pulseaction_credits_upgrade($oldversion) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/mod/pulse/lib.php');

    $dbman = $DB->get_manager();

    // Include the status field to the instance table.
    if ($oldversion < 2025092609) {
        $table = new xmldb_table('pulseaction_credits');
        $field = new xmldb_field('creditstatus', XMLDB_TYPE_INTEGER, '4', null, null, null);
        $actionfield = new xmldb_field('actionstatus', XMLDB_TYPE_INTEGER, '4', null, null, null);

        // Conditionally launch add field status.
        if ($dbman->field_exists($table, $field) && !$dbman->field_exists($table, $actionfield)) {
            $dbman->rename_field($table, $field, 'actionstatus');
        }

        $table = new xmldb_table('pulseaction_credits_ins');
        $field = new xmldb_field('creditstatus', XMLDB_TYPE_INTEGER, '4', null, null, null);

        // Conditionally launch add field status.
        if ($dbman->field_exists($table, $field) && !$dbman->field_exists($table, $actionfield)) {
            $dbman->rename_field($table, $field, 'actionstatus');
        }

                $table = new xmldb_table('pulseaction_credits');
        $field = new xmldb_field('credits', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
        // Verify field exists.
        if ($dbman->field_exists($table, $field)) {
            // Change the field.
            $dbman->change_field_precision($table, $field);
        }

        $table = new xmldb_table('pulseaction_credits_ins');
        $field = new xmldb_field('credits', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
        // Verify field exists.
        if ($dbman->field_exists($table, $field)) {
            // Change the field.
            $dbman->change_field_precision($table, $field);
        }

        $table = new xmldb_table('pulseaction_credits_sch');
        $field = new xmldb_field('credits', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
        // Verify field exists.
        if ($dbman->field_exists($table, $field)) {
            // Change the field.
            $dbman->change_field_precision($table, $field);
        }

        // Update for override credits table.
        $table = new xmldb_table('pulseaction_credits_override');
        $field = new xmldb_field('overridecredit', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
        // Verify field exists.
        if ($dbman->field_exists($table, $field)) {
            // Change the field.
            $dbman->change_field_precision($table, $field);
        }

        $field = new xmldb_field('scheduledcredit', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
        // Verify field exists.
        if ($dbman->field_exists($table, $field)) {
            // Change the field.
            $dbman->change_field_precision($table, $field);
        }

        upgrade_plugin_savepoint(true, 2025092609, 'pulseaction', 'credits');
    }

    return true;
}
