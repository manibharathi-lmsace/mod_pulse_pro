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
 * Pulse notification action upgrade steps.
 *
 * @package   pulseaction_notification
 * @copyright 2023, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Pulse notification action upgrade steps.
 *
 * @param  mixed $oldversion Previous version.
 * @return void
 */
function xmldb_pulseaction_notification_upgrade($oldversion) {
    global $CFG, $DB;

    // Inital plugin release - v1.0.

    $dbman = $DB->get_manager();

    // Auto templates instance.
    $instable = new xmldb_table('pulseaction_notification');
    $timemodified = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '11', null, null, null, null);
    // Verify field exists.
    if ($dbman->field_exists($instable, $timemodified)) {
        // Change the field.
        $dbman->change_field_precision($instable, $timemodified);
    }

    // Update the templates table timemodified.
    $temptable = new xmldb_table('pulseaction_notification_ins');
    if ($dbman->field_exists($temptable, $timemodified)) {
        // Change the field.
        $dbman->change_field_precision($temptable, $timemodified);
    }

    // Update the type of dynamic content.
    $instable = new xmldb_table('pulseaction_notification');
    $dynamiccontent = new xmldb_field('dynamiccontent', XMLDB_TYPE_INTEGER, '11', null, null, null, null);
    // Verify field exists.
    if ($dbman->field_exists($instable, $dynamiccontent)) {
        // Change the field.
        $dbman->change_field_precision($instable, $dynamiccontent);
    }

    // Update the templates table dynamiccontent.
    $temptable = new xmldb_table('pulseaction_notification_ins');
    if ($dbman->field_exists($temptable, $dynamiccontent)) {
        // Change the field.
        $dbman->change_field_precision($temptable, $dynamiccontent);
    }

    if ($oldversion < 2024122703) {
        // Define field suppresscourse to be added to pulseaction_notification.
        $table = new xmldb_table('pulseaction_notification');
        $field = new xmldb_field('suppresscourse', XMLDB_TYPE_INTEGER, '4', null, null, null, '0', 'notifylimit');

        // Conditionally launch add field suppresscourse.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add field to templates table.
        $field = new xmldb_field('suppresscourse', XMLDB_TYPE_INTEGER, '4', null, null, null, null, 'suppressoperator');
        $temptable = new xmldb_table('pulseaction_notification_ins');
        if (!$dbman->field_exists($temptable, $field)) {
            $dbman->add_field($temptable, $field);
        }

        // Define field frequencycount for notification table.
        // Add field to templates table.
        $temptable = new xmldb_table('pulseaction_notification_sch');
        $field = new xmldb_field('frequencycount', XMLDB_TYPE_INTEGER, '9', null, null, null, null, 'notifiedtime');
        if (!$dbman->field_exists($temptable, $field)) {
            $dbman->add_field($temptable, $field);
        }

        // Set all to the frequency count to 1.
        $DB->set_field_select(
            'pulseaction_notification_sch',
            'frequencycount',
            1,
            'frequencycount IS NULL OR frequencycount = 0',
            []
        );

        upgrade_plugin_savepoint(true, 2024122703, 'pulseaction', 'notification');
    }

    // Include the status field to the instance table.
    if ($oldversion < 2024122709) {
        $table = new xmldb_table('pulseaction_notification');
        $field = new xmldb_field('actionstatus', XMLDB_TYPE_INTEGER, '4', null, null, null, '0', 'templateid');

        // Conditionally launch add field status.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('pulseaction_notification_ins');
        $field = new xmldb_field('actionstatus', XMLDB_TYPE_INTEGER, '4', null, null, null, null, 'instanceid');

        // Conditionally launch add field status.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $DB->set_field_select('pulseaction_notification', 'actionstatus', 1, "recipients <> '' AND recipients <> '[]'", []);
        $DB->set_field_select('pulseaction_notification_ins', 'actionstatus', 1, "recipients <> '' AND recipients <> '[]'", []);

        upgrade_plugin_savepoint(true, 2024122709, 'pulseaction', 'notification');
    }

    if ($oldversion < 2024122713) {
        // Define field scheduleuser to be added to pulseaction_notification_sch.
        $table = new xmldb_table('pulseaction_notification_sch');
        $field = new xmldb_field('relateduserid', XMLDB_TYPE_INTEGER, '18', null, null, null, null, 'userid');

        // Conditionally launch add field scheduleuser.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024122713, 'pulseaction', 'notification');
    }

    if ($oldversion < 2025010504) {
        // Changing precision of field name on table pulseaction_notification to 1333.
        $table = new xmldb_table('pulseaction_notification');
        $tableins = new xmldb_table('pulseaction_notification_ins');

        $field = new xmldb_field('subject', XMLDB_TYPE_CHAR, '1333');

        // Conditionally launch changing precision subject.
        if ($dbman->field_exists($table, $field)) {
            // Change the field.
            $dbman->change_field_precision($table, $field);
        }

        // Conditionally launch changing precision subject on instance table.
        if ($dbman->field_exists($tableins, $field)) {
            // Change the field.
            $dbman->change_field_precision($tableins, $field);
        }

        upgrade_plugin_savepoint(true, 2025010504, 'pulseaction', 'notification');
    }

    if ($oldversion < 2026022000) {
        // Add delaybase field to template table.
        $table = new xmldb_table('pulseaction_notification');
        $field = new xmldb_field('delaybase', XMLDB_TYPE_INTEGER, '2', null, null, null, '0', 'delayduration');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add delaybase field to instance table.
        $table = new xmldb_table('pulseaction_notification_ins');
        $field = new xmldb_field('delaybase', XMLDB_TYPE_INTEGER, '2', null, null, null, null, 'delayduration');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026022000, 'pulseaction', 'notification');
    }

    if ($oldversion < 2026022002) {
        $table = new xmldb_table('pulseaction_notification_ins');
        $field = new xmldb_field('delaybase', XMLDB_TYPE_INTEGER, '2', null, null, null, null, 'delayduration');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_default($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026022002, 'pulseaction', 'notification');
    }

    return true;
}
