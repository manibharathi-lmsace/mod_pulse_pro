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
 * CLI: For a given automation instance, list candidate users and report which ones currently
 * satisfy the instance's trigger conditions (under the configured ANY/ALL operator).
 *
 * @package   mod_pulse
 * @copyright 2026, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(
    ['instanceid' => null, 'limit' => 0, 'help' => false],
    ['i' => 'instanceid', 'l' => 'limit', 'h' => 'help']
);

if ($options['help'] || empty($options['instanceid'])) {
    cli_writeln("Usage: php precheck_instance.php --instanceid=<id> [--limit=N]\n");
    cli_writeln("  Lists users whose current state satisfies the instance's trigger conditions.");
    cli_writeln("  Candidate set: active, confirmed, non-deleted, non-guest users.\n");
    exit(0);
}

global $DB;

$instanceid = (int) $options['instanceid'];
$limit = (int) $options['limit'];

$instance = $DB->get_record('pulse_autoinstances', ['id' => $instanceid], '*', MUST_EXIST);

// Load merged instance data (template + overrides) so condition config matches the engine.
$instancedata = (object) \mod_pulse\automation\instances::create($instanceid)->get_instance_data();
$instances = \mod_pulse\automation\instances::create($instanceid);

$conditions = (array) ($instancedata->condition ?? []);
$enabled = [];
foreach ($conditions as $name => $cfg) {
    $status = is_array($cfg) ? ($cfg['status'] ?? 0) : $cfg;
    if ((int) $status > 0) {
        $enabled[$name] = $cfg;
    }
}

cli_writeln("=== Automation instance #{$instanceid} ===");
cli_writeln("Course id:        {$instance->courseid}");
cli_writeln("Instance status:  " . ($instance->status ? 'enabled' : 'disabled'));
cli_writeln("Trigger operator: " . ((int) ($instancedata->triggeroperator ?? 0) === 1 ? 'ANY' : 'ALL'));
cli_writeln("Enabled conditions: " . (empty($enabled) ? '(none)' : implode(', ', array_keys($enabled))));
cli_writeln('');

if (empty($enabled)) {
    cli_writeln('No enabled conditions: every candidate user would match. Exiting.');
    exit(0);
}

// Candidate users: real, active accounts.
$candidatesql = 'SELECT id, username, firstname, lastname, email, timecreated
                   FROM {user}
                  WHERE deleted = 0 AND suspended = 0 AND confirmed = 1
                    AND id <> :guestid
               ORDER BY id ASC';
$params = ['guestid' => $CFG->siteguest ?? 1];
$users = $DB->get_records_sql($candidatesql, $params, 0, $limit ?: 0);
cli_writeln('Candidate users scanned: ' . count($users));

// Per-condition match counts.
$percondition = array_fill_keys(array_keys($enabled), 0);
$matches = [];
foreach ($users as $u) {
    foreach ($enabled as $name => $cfg) {
        $condition = \mod_pulse\plugininfo\pulsecondition::instance()->get_plugin($name);
        if ($condition && $condition->is_user_completed($instancedata, $u->id, null)) {
            $percondition[$name]++;
        }
    }
    if ($instances->find_user_completion_conditions($enabled, $instancedata, $u->id, false)) {
        $matches[$u->id] = $u;
    }
}

cli_writeln('Per-condition matches:');
foreach ($percondition as $name => $count) {
    cli_writeln(sprintf('  %-15s %d', $name, $count));
}
cli_writeln('');
cli_writeln('Users matching the combined operator: ' . count($matches));
cli_writeln('');
if (!empty($matches)) {
    cli_writeln(sprintf("%-6s  %-20s  %-30s  %s", 'id', 'username', 'fullname', 'email'));
    foreach ($matches as $u) {
        cli_writeln(sprintf(
            "%-6d  %-20s  %-30s  %s",
            $u->id,
            $u->username,
            trim($u->firstname . ' ' . $u->lastname),
            $u->email
        ));
    }
}
