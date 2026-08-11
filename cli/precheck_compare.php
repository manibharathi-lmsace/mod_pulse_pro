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
 * CLI: compare precheck count vs notification schedule rows for an automation instance.
 *
 * @package   mod_pulse
 * @copyright 2026, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$opts] = cli_get_params(['instanceid' => null], ['i' => 'instanceid']);
$instanceid = (int) ($opts['instanceid'] ?? 0);
if (!$instanceid) {
    cli_writeln('Usage: php precheck_compare.php --instanceid=<id>');
    exit(0);
}

global $DB;

$instance = $DB->get_record('pulse_autoinstances', ['id' => $instanceid], '*', MUST_EXIST);
$instancedata = (object) \mod_pulse\automation\instances::create($instanceid)->get_instance_data();
$enabled = [];
foreach ((array) ($instancedata->condition ?? []) as $name => $cfg) {
    $status = is_array($cfg) ? ($cfg['status'] ?? 0) : $cfg;
    if ((int) $status > 0 && is_array($cfg)) {
        $enabled[$name] = $cfg;
    }
}

cli_writeln("=== Instance #{$instanceid} (courseid={$instance->courseid}) ===");
cli_writeln('Saved enabled conditions: ' . implode(', ', array_keys($enabled)));
cli_writeln('Saved triggeroperator:    ' . (((int)($instancedata->triggeroperator ?? 0)) === 1 ? 'ANY' : 'ALL'));
cli_writeln('');

// 1. Live-engine count (what the precheck shows when form == saved state).
$instances = \mod_pulse\automation\instances::create($instanceid);
$candsql = 'SELECT id FROM {user} WHERE deleted=0 AND suspended=0 AND confirmed=1 AND id <> :guestid';
$candparams = ['guestid' => $CFG->siteguest ?? 1];
$candidates = $DB->get_fieldset_sql($candsql, $candparams);
$enginematches = 0;
foreach ($candidates as $uid) {
    if ($instances->find_user_completion_conditions($enabled, $instancedata, (int) $uid, false)) {
        $enginematches++;
    }
}
cli_writeln("Engine match count (precheck logic): {$enginematches}");

// 2. Per-condition counts.
foreach ($enabled as $name => $cfg) {
    $plugin = \mod_pulse\plugininfo\pulsecondition::instance()->get_plugin($name);
    $n = 0;
    foreach ($candidates as $uid) {
        if ($plugin->is_user_completed($instancedata, (int) $uid, null)) {
            $n++;
        }
    }
    cli_writeln("  per-condition matches[$name]: $n");
}
cli_writeln('');

// 3. Schedule table reality.
$counts = $DB->get_records_sql(
    "SELECT status, COUNT(*) AS n FROM {pulseaction_notification_sch}
      WHERE instanceid = :iid GROUP BY status",
    ['iid' => $instanceid]
);
$total = 0;
foreach ($counts as $r) {
    cli_writeln("Schedule rows status={$r->status}: {$r->n}");
    $total += $r->n;
}
cli_writeln("Schedule rows total:                 {$total}");

// 4. Who is in the schedule table.
$rows = $DB->get_records_sql(
    "SELECT ns.id, ns.userid, ns.status, ns.scheduletime, ns.notifycount,
            u.firstname, u.lastname, u.email
       FROM {pulseaction_notification_sch} ns
       JOIN {user} u ON u.id = ns.userid
      WHERE ns.instanceid = :iid
      ORDER BY ns.id ASC",
    ['iid' => $instanceid]
);
if ($rows) {
    cli_writeln('');
    cli_writeln('Schedule contents:');
    foreach ($rows as $r) {
        cli_writeln(sprintf(
            '  sch#%d user#%-4d status=%d schedtime=%s notifycount=%d  %s <%s>',
            $r->id,
            $r->userid,
            $r->status,
            $r->scheduletime ? userdate($r->scheduletime, '%Y-%m-%d %H:%M') : '-',
            $r->notifycount,
            trim($r->firstname . ' ' . $r->lastname),
            $r->email
        ));
        // Re-check each scheduled user against the conditions now.
        $matches = $instances->find_user_completion_conditions($enabled, $instancedata, (int) $r->userid, false);
        cli_writeln('       still matches engine conditions now? ' . ($matches ? 'YES' : 'NO'));
    }
}
