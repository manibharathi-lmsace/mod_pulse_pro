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
 * Scheduled task that scans for users with no enrolment and triggers the "Not enrolled" condition.
 *
 * Unlike most condition triggers (which observe Moodle events), users who have never enrolled
 * generate no enrolment event, so this site-wide scan is the driver. For composite use cases
 * (e.g. cohort + not enrolled, or course completion + not enrolled) the other condition's event
 * observer also triggers the instance; either way, the action layer re-validates every enabled
 * condition before scheduling.
 *
 * @package   pulsecondition_notenrolled
 * @copyright 2026, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulsecondition_notenrolled\task;

use pulseaction_notification\notification;
use pulsecondition_notenrolled\conditionform;

/**
 * Scheduled task to find users with no enrolment and trigger automation conditions.
 */
class notenrolled_scan extends \core\task\scheduled_task {
    /**
     * Get the name of this task.
     *
     * @return string The task name.
     */
    public function get_name() {
        return get_string('taskname', 'pulsecondition_notenrolled');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        mtrace('Starting "Not enrolled" automation scan...');

        // Active instances using the notenrolled condition (template-level or instance override).
        $sql = "SELECT ai.*, ai.id AS instanceid
                  FROM {pulse_autoinstances} ai
                  JOIN {pulse_autotemplates} pat ON pat.id = ai.templateid
             LEFT JOIN {pulse_condition_overrides} co ON co.instanceid = ai.id AND co.triggercondition = 'notenrolled'
                 WHERE ai.status = 1
                   AND (co.status > 0 OR (co.status IS NULL AND ai.templateid IN (
                            SELECT c.templateid FROM {pulse_condition} c
                             WHERE c.triggercondition = 'notenrolled' AND c.status > 0
                       )))";

        $instances = $DB->get_records_sql($sql);

        if (empty($instances)) {
            mtrace('No active "Not enrolled" automation instances found.');
            return;
        }

        mtrace('Found ' . count($instances) . ' active "Not enrolled" automation instance(s).');

        foreach ($instances as $instance) {
            $this->process_instance($instance);
        }

        mtrace('"Not enrolled" automation scan completed.');
    }

    /**
     * Process a single automation instance.
     *
     * @param \stdClass $instance The automation instance.
     */
    protected function process_instance($instance) {
        global $DB, $CFG;

        try {
            $instancedata = \mod_pulse\automation\instances::create($instance->id)->get_instance_data();
            $config = $instancedata->condition['notenrolled'] ?? [];
            if (empty($config) || empty($config['status'])) {
                return;
            }

            $window = (int) ($config['window'] ?? 0);
            $scope = (int) ($config['scope'] ?? conditionform::SCOPE_ANY);

            $params = [
                'guestid' => $CFG->siteguest ?? 1,
                'now1' => time(),
                'now2' => time(),
                'instanceid' => $instance->id,
                'statussent' => notification::STATUS_SENT,
                'statusqueued' => notification::STATUS_QUEUED,
            ];

            // Candidate scope: courses the user must NOT be actively enrolled in.
            if ($scope == conditionform::SCOPE_SPECIFIC) {
                $courses = array_filter($config['courses'] ?? []);
                if (empty($courses)) {
                    mtrace("Instance {$instance->id}: specific scope with no courses configured, skipping.");
                    return;
                }
                [$insql, $inparams] = $DB->get_in_or_equal($courses, SQL_PARAMS_NAMED, 'crs');
                $coursewhere = "e.courseid $insql";
                $params += $inparams;
            } else {
                $coursewhere = 'e.courseid <> :siteid';
                $params['siteid'] = SITEID;
            }

            // Optional account-age window.
            $windowwhere = '';
            if ($window > 0) {
                $windowwhere = ' AND u.timecreated <= :threshold ';
                $params['threshold'] = time() - $window;
            }

            // Candidate users: real, active accounts with no active enrolment in scope, and not
            // already scheduled (queued or sent) for this instance. The action layer re-validates.
            $sql = "SELECT u.id
                      FROM {user} u
                     WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1 AND u.id <> :guestid
                       $windowwhere
                       AND NOT EXISTS (
                            SELECT 1 FROM {user_enrolments} ue
                              JOIN {enrol} e ON e.id = ue.enrolid
                             WHERE ue.userid = u.id AND ue.status = 0 AND $coursewhere
                               AND (ue.timestart = 0 OR ue.timestart <= :now1)
                               AND (ue.timeend = 0 OR ue.timeend > :now2)
                       )
                       AND NOT EXISTS (
                            SELECT 1 FROM {pulseaction_notification_sch} ns
                             WHERE ns.instanceid = :instanceid AND ns.userid = u.id
                               AND ns.status IN (:statusqueued, :statussent)
                       )";

            $condition = new conditionform();
            $rs = $DB->get_recordset_sql($sql, $params);
            $count = 0;
            foreach ($rs as $user) {
                $condition->trigger_instance($instance->id, $user->id, null, true);
                $count++;
            }
            $rs->close();

            mtrace("Instance {$instance->id}: triggered {$count} user(s).");
        } catch (\Exception $e) {
            mtrace('Error processing "Not enrolled" instance ' . $instance->id . ': ' . $e->getMessage());
        }
    }
}
