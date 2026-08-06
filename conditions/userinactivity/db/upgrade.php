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
 * Conditions - Pulse condition class for "User inactivity" upgrade.
 *
 * @package   pulsecondition_userinactivity
 * @copyright 2025, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Pulse condition user inactivity upgrade steps.
 *
 * @param  mixed $oldversion Previous version.
 * @return bool
 */
function xmldb_pulsecondition_userinactivity_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025111301) {
        // Define table.
        $table = new xmldb_table('pulse_userinactivity_log');
        // Check if table exists.
        if ($dbman->table_exists($table)) {
            // Drop the table.
            $dbman->drop_table($table);
        }
        upgrade_plugin_savepoint(true, 2025111301, 'pulsecondition', 'userinactivity');
    }

    return true;
}
