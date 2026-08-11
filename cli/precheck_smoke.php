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
 * CLI smoke test: exercise the candidate_filter_sql composer end-to-end against a saved instance
 * and compare with the per-user engine path.
 *
 * @package   mod_pulse
 * @copyright 2026, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options] = cli_get_params(['instanceid' => null], ['i' => 'instanceid']);
if (empty($options['instanceid'])) {
    cli_writeln("Usage: php precheck_smoke.php --instanceid=<id>");
    exit(0);
}
$instanceid = (int) $options['instanceid'];

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

$joins = $wheres = $params = $fallback = [];
foreach ($enabled as $name => $cfg) {
    $plugin = \mod_pulse\plugininfo\pulsecondition::instance()->get_plugin($name);
    $frag = $plugin->candidate_filter_sql($cfg, $instancedata);
    if (!empty($frag['where']) || !empty($frag['join'])) {
        if (!empty($frag['join'])) {
            $joins[] = $frag['join'];
        }
        if (!empty($frag['where'])) {
            $wheres[] = $frag['where'];
        }
        $params += $frag['params'] ?? [];
    } else {
        $fallback[$name] = $cfg;
    }
}

$isall = (int) ($instancedata->triggeroperator ?? 0) !== \mod_pulse\automation\condition_base::OPERATOR_ANY;
$op = $isall ? ' AND ' : ' OR ';

$sql = 'SELECT u.id FROM {user} u ' . implode(' ', $joins) .
       ' WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1 AND u.id <> :guestid';
$params['guestid'] = $CFG->siteguest ?? 1;
if (!empty($wheres)) {
    $sql .= ' AND (' . implode($op, $wheres) . ')';
}

cli_writeln("Operator:      " . ($isall ? 'ALL' : 'ANY'));
cli_writeln("SQL conditions: " . (empty($wheres) ? '(none)' : implode(', ', array_keys($enabled))));
cli_writeln("Fallback:      " . (empty($fallback) ? '(none)' : implode(', ', array_keys($fallback))));
cli_writeln('');
cli_writeln('Composed SQL:');
cli_writeln($sql);
cli_writeln('');
$ids = $DB->get_fieldset_sql($sql, $params);
cli_writeln('SQL pre-filter result: ' . count($ids));
