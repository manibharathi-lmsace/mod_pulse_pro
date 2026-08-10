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
 * AJAX endpoint: evaluate the live condition form payload against current user data and return
 * the count (and optional sample) of users that would currently match.
 *
 * Composes per-condition SQL fragments where available, then falls back to evaluating
 * `is_user_completed()` per candidate user for any condition that does not expose SQL. The
 * source of truth for correctness is the engine method `find_user_completion_conditions`.
 *
 * @package   mod_pulse
 * @copyright 2026, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');

use mod_pulse\automation\action_base;
use mod_pulse\automation\condition_base;
use mod_pulse\plugininfo\pulsecondition;

require_login();
require_sesskey();

$courseid        = optional_param('courseid', 0, PARAM_INT);
$instanceid      = optional_param('instanceid', 0, PARAM_INT);
$triggeroperator = optional_param('triggeroperator', action_base::OPERATOR_ALL, PARAM_INT);
$conditionsjson  = required_param('conditions', PARAM_RAW);
$includesample   = optional_param('sample', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);

// Limits.
$samplelimit   = 20;
$maxcandidates = (int) (get_config('mod_pulse', 'precheckmaxcandidates') ?: 5000);

// Capability gate matches the form's gate.
$context = $courseid ? context_course::instance($courseid) : context_system::instance();
require_capability('mod/pulse:addtemplateinstance', $context);

header('Content-Type: application/json; charset=utf-8');

$conditions = json_decode($conditionsjson, true);
if (!is_array($conditions)) {
    echo json_encode(['error' => 'invalid_conditions']);
    exit;
}

/**
 * Normalise composite Moodle form values that arrive from the client as nested objects:
 *   - `duration` element  -> `{number, timeunit}`         -> integer seconds (number*timeunit)
 *   - `date_time_selector` -> `{day,month,year,hour,minute[,enabled]}` -> unix timestamp
 *   - `date_selector`      -> `{day,month,year[,enabled]}`             -> unix timestamp
 *
 * The engine expects a single scalar (int seconds / unix timestamp) so the per-user check and
 * the SQL pre-filter both work. Walks recursively so it normalises any field at any depth.
 *
 * @param array $data
 * @return array
 */
function pulse_precheck_normalise(array $data): array {
    foreach ($data as $k => $v) {
        if (!is_array($v)) {
            continue;
        }
        // Optional date selectors carry an `enabled` flag; treat unchecked as 0.
        if (array_key_exists('enabled', $v) && empty($v['enabled'])) {
            $data[$k] = 0;
            continue;
        }
        if (array_key_exists('number', $v) && array_key_exists('timeunit', $v)
                && is_numeric($v['number']) && is_numeric($v['timeunit'])) {
            // Moodle duration element: total seconds = number * timeunit.
            $data[$k] = (int) round(((float) $v['number']) * ((float) $v['timeunit']));
            continue;
        }
        if (array_key_exists('day', $v) && array_key_exists('month', $v) && array_key_exists('year', $v)) {
            // date_selector / date_time_selector -> unix timestamp.
            $hour   = isset($v['hour'])   ? (int) $v['hour']   : 0;
            $minute = isset($v['minute']) ? (int) $v['minute'] : 0;
            $data[$k] = make_timestamp(
                (int) $v['year'], (int) $v['month'], (int) $v['day'],
                $hour, $minute, 0, 99, true
            );
            continue;
        }
        $data[$k] = pulse_precheck_normalise($v);
    }
    return $data;
}
$conditions = pulse_precheck_normalise($conditions);

// Keep only the enabled subset and discard anything that is not an array (e.g. raw "0").
// Also mirror condition_base::process_save: when status != FUTURE, upcomingtime must be 0,
// otherwise the date_time_selector's value (which defaults to "now") would filter out every
// existing user even though the user only meant to enable the condition for "All".
$enabled = [];
foreach ($conditions as $name => $cfg) {
    $status = is_array($cfg) ? ($cfg['status'] ?? 0) : $cfg;
    if ((int) $status > 0 && is_array($cfg)) {
        if ((int) $status !== condition_base::FUTURE) {
            $cfg['upcomingtime'] = 0;
        }
        $enabled[$name] = $cfg;
    }
}

if (empty($enabled)) {
    echo json_encode([
        'total'     => 0,
        'sample'    => [],
        'truncated' => false,
        'fallback'  => false,
        'message'   => 'no_enabled_conditions',
    ]);
    exit;
}

// Build the synthetic instancedata the engine expects.
$instancedata                 = new stdClass();
$instancedata->courseid       = (int) ($courseid ?: SITEID);
$instancedata->triggeroperator = (int) $triggeroperator;
$instancedata->condition      = $enabled;
$instancedata->triggerconditions = array_fill_keys(array_keys($enabled), 1);
try {
    $instancedata->course = get_course($instancedata->courseid);
} catch (Throwable $e) {
    // Fall back to site course if the given id no longer resolves.
    $instancedata->course   = get_course(SITEID);
    $instancedata->courseid = SITEID;
}

// Compose SQL fragments from conditions that support it; mark the rest as fallback.
$joins    = [];
$wheres   = [];
$params   = [];
$fallback = [];
$used     = [];

foreach ($enabled as $name => $cfg) {
    $plugin = pulsecondition::instance()->get_plugin($name);
    if (!$plugin) {
        continue;
    }
    $frag = method_exists($plugin, 'candidate_filter_sql')
        ? $plugin->candidate_filter_sql($cfg, $instancedata)
        : ['join' => '', 'where' => '', 'params' => []];

    if (!empty($frag['where']) || !empty($frag['join'])) {
        if (!empty($frag['join'])) {
            $joins[] = $frag['join'];
        }
        if (!empty($frag['where'])) {
            $wheres[] = $frag['where'];
        }
        if (!empty($frag['params'])) {
            $params = array_merge($params, $frag['params']);
        }
        $used[$name] = true;
    } else {
        $fallback[$name] = $cfg;
    }
}

// Compose the candidate query. The ANY operator can only be honoured purely in SQL if every
// enabled condition exposes a fragment; otherwise, drop the SQL pre-filter so we don't exclude
// users who would match a fallback-only condition.
$isany       = ((int) $triggeroperator === condition_base::OPERATOR_ANY);
$useprefilter = !empty($wheres) && (!$isany || empty($fallback));
$op          = $isany ? ' OR ' : ' AND ';

$basesql  = 'SELECT u.id FROM {user} u ' . implode(' ', $joins);
$basesql .= ' WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1 AND u.id <> :pch_guestid';
$params['pch_guestid'] = $CFG->siteguest ?? 1;

// Check if any enabled plugin requires skipping the enrolment gate.
// If no plugin skips the gate and this isn't the site course, add a SQL filter
// ...to ensure the user has an active enrolment in the course.
$skipenrolgate = false;
foreach ($enabled as $name => $cfg) {
    $plugin = pulsecondition::instance()->get_plugin($name);
    if ($plugin && method_exists($plugin, 'schedule_skip_enrolment_gate') && $plugin->schedule_skip_enrolment_gate()) {
        $skipenrolgate = true;
        break;
    }
}
if (!$skipenrolgate && $instancedata->courseid != SITEID) {
    $basesql .= ' AND EXISTS (SELECT 1 FROM {user_enrolments} pch_ue
                                JOIN {enrol} pch_e ON pch_e.id = pch_ue.enrolid
                               WHERE pch_ue.userid = u.id AND pch_ue.status = 0
                                 AND pch_e.courseid = :pch_courseid
                                 AND (pch_ue.timestart = 0 OR pch_ue.timestart <= :pch_now1)
                                 AND (pch_ue.timeend = 0 OR pch_ue.timeend > :pch_now2))';
    $params['pch_courseid'] = $instancedata->courseid;
    $params['pch_now1'] = time();
    $params['pch_now2'] = time();
}

if ($useprefilter) {
    $basesql .= ' AND (' . implode($op, $wheres) . ')';
}
$basesql .= ' ORDER BY u.id ASC';

$userids = $DB->get_fieldset_sql($basesql, $params, 0, $maxcandidates + 1);
$truncated = count($userids) > $maxcandidates;
if ($truncated) {
    $userids = array_slice($userids, 0, $maxcandidates);
}

// Fallback evaluation: any condition without SQL must be checked in PHP. If the operator is ALL
// and we used a pre-filter, fallback conditions must additionally hold; if the operator is ANY,
// we drop the pre-filter (above) and evaluate the operator engine-side. In both cases the engine
// method is the source of truth.
$needsphpeval = !empty($fallback) || $isany;
$kept = $userids;
if ($needsphpeval) {
    require_once($CFG->dirroot . '/lib/completionlib.php');
    $kept = [];
    foreach ($userids as $uid) {
        if (precheck_user_matches($enabled, $instancedata, (int) $uid)) {
            $kept[] = (int) $uid;
        }
    }
}

$total = count($kept);
$totalpages = $total > 0 ? (int) ceil($total / $samplelimit) : 0;
$page = max(0, min($page, $totalpages - 1));
$sample = [];
if ($includesample && !empty($kept)) {
    $offset = $page * $samplelimit;
    $sampleids = array_slice($kept, $offset, $samplelimit);
    [$insql, $inparams] = $DB->get_in_or_equal($sampleids, SQL_PARAMS_NAMED);
    $records = $DB->get_records_sql(
        "SELECT id, firstname, lastname, email FROM {user} WHERE id $insql ORDER BY id",
        $inparams
    );
    foreach ($records as $r) {
        $sample[] = [
            'id'       => (int) $r->id,
            'fullname' => trim(fullname($r)),
            'email'    => $r->email,
        ];
    }
}

echo json_encode([
    'total'        => $total,
    'sample'       => $sample,
    'samplelimit'  => $samplelimit,
    'page'         => $page,
    'totalpages'   => $totalpages,
    'truncated'    => $truncated,
    'usedsql'      => array_keys($used),
    'fallback'     => array_keys($fallback),
    'scanned'      => count($userids),
    'maxcandidates' => $maxcandidates,
]);
exit;

/**
 * Reimplements the per-user portion of `instances::find_user_completion_conditions` so we don't
 * need a persisted instance record (the template form runs before save). The behaviour mirrors
 * the engine, minus the FUTURE/enrolment-time short-circuit that only applies to live triggers.
 *
 * @param array  $enabled       Enabled condition configs keyed by component name.
 * @param object $instancedata  Synthetic instance data.
 * @param int    $userid        Candidate user id.
 * @return bool                 True if the user satisfies the operator over enabled conditions.
 */
function precheck_user_matches(array $enabled, $instancedata, int $userid): bool {
    $completion = new \completion_info($instancedata->course);
    $isall = ((int) $instancedata->triggeroperator !== condition_base::OPERATOR_ANY);
    $matched = 0;
    $count = 0;
    foreach ($enabled as $name => $cfg) {
        $plugin = pulsecondition::instance()->get_plugin($name);
        if (!$plugin) {
            continue;
        }
        $count++;
        if ($plugin->is_user_completed($instancedata, $userid, $completion)) {
            $matched++;
            if (!$isall) {
                return true; // ANY operator short-circuits on first match.
            }
        }
    }
    return $count > 0 && $matched === $count;
}
