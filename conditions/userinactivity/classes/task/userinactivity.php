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
 * Scheduled task for user inactivity condition monitoring.
 *
 * @package   pulsecondition_userinactivity
 * @copyright 2025, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulsecondition_userinactivity\task;

/**
 * Scheduled task to check for user inactivity and trigger automation conditions.
 *
 * Uses a set-based SQL precheck (one query per instance) instead of a per-user
 * PHP loop, so the task scales linearly with the number of matching users rather
 * than (users x instances x conditions).
 */
class userinactivity extends \core\task\scheduled_task {

    /** Number of triggered users between progress mtraces. */
    const PROGRESS_INTERVAL = 500;

    /**
     * Get the name of this task.
     *
     * @return string The task name.
     */
    public function get_name() {
        return get_string('taskuserinactivity', 'pulsecondition_userinactivity');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        mtrace('Starting user inactivity automation check...');

        $sql = "SELECT ai.id AS instanceid, ai.courseid, ai.templateid
                  FROM {pulse_autoinstances} ai
                  JOIN {pulse_autotemplates} pat ON pat.id = ai.templateid
             LEFT JOIN {pulse_condition_overrides} co
                    ON co.instanceid = ai.id AND co.triggercondition = 'userinactivity'
                 WHERE ai.status = 1
                   AND (co.status > 0 OR (co.status IS NULL AND ai.templateid IN (
                       SELECT c.templateid FROM {pulse_condition} c
                        WHERE c.triggercondition = 'userinactivity' AND c.status > 0
                   )))
              ORDER BY ai.courseid, ai.id";

        $instances = $DB->get_records_sql($sql);

        if (empty($instances)) {
            mtrace('No active user inactivity automation instances found.');
            return;
        }

        mtrace('Found ' . count($instances) . ' active user inactivity automation instances.');

        $instancesbycourse = [];
        foreach ($instances as $row) {
            $instancesbycourse[$row->courseid][] = $row;
        }

        foreach ($instancesbycourse as $courseid => $courseinstances) {
            try {
                $course = get_course($courseid);
            } catch (\Exception $e) {
                mtrace("Skipping course {$courseid}: " . $e->getMessage());
                continue;
            }

            mtrace("Course {$courseid}: processing " . count($courseinstances) . " instance(s).");

            foreach ($courseinstances as $row) {
                $this->process_instance($row->instanceid, $course);
            }
        }

        mtrace('User inactivity automation check completed.');
    }

    /**
     * Process a single automation instance.
     *
     * @param int $instanceid The automation instance id.
     * @param \stdClass $course The course record (shared across instances in the same course).
     */
    protected function process_instance($instanceid, $course) {
        mtrace("Processing instance {$instanceid} for course {$course->id}");

        try {
            $instanceobj = \mod_pulse\automation\instances::create($instanceid);
            $instancedata = $instanceobj->get_instance_data();

            if (empty($instancedata->condition['userinactivity'])) {
                mtrace("User inactivity condition not configured for instance {$instanceid}");
                return;
            }

            $triggercondition = $instancedata->condition['userinactivity'];
            if ((int) ($triggercondition['status'] ?? 0) <= 0) {
                mtrace("User inactivity condition disabled for instance {$instanceid}");
                return;
            }

            $conditionform = new \pulsecondition_userinactivity\conditionform();
            $rs = $conditionform->get_matching_users_recordset($course, $triggercondition);

            if ($rs === null) {
                mtrace("Nothing to evaluate for instance {$instanceid} (no period set or no relevant activities).");
                return;
            }

            // Get users who have already been notified and have not re-accessed the course since.
            $alreadynotified = $this->get_already_notified_users($instanceid, $course->id);

            $triggeredcount = 0;
            try {
                foreach ($rs as $record) {
                    if (isset($alreadynotified[$record->userid])) {
                        continue;
                    }
                    $instanceobj->trigger_action((int) $record->userid, null, false);
                    $triggeredcount++;
                    if ($triggeredcount % self::PROGRESS_INTERVAL === 0) {
                        mtrace("  instance {$instanceid}: triggered {$triggeredcount} users so far...");
                    }
                }
            } finally {
                $rs->close();
            }

            mtrace("Triggered automation for {$triggeredcount} users in instance {$instanceid}");
        } catch (\Exception $e) {
            mtrace('Error processing user inactivity instance ' . $instanceid . ': ' . $e->getMessage());
        }
    }

    /**
     * Get users who have already been notified for this instance and have not re-accessed the course since.
     *
     * @param int $instanceid Automation instance id
     * @param int $courseid Course id.
     * @return array userid => true
     */
    protected function get_already_notified_users(int $instanceid, int $courseid): array {
        global $DB;

        $notificationinstanceid = $DB->get_field(
            'pulseaction_notification_ins',
            'id',
            ['instanceid' => $instanceid]
        );

        if (!$notificationinstanceid) {
            return [];
        }

        $sql = "SELECT latest.userid
                  FROM (
                      SELECT userid, MAX(notifiedtime) AS lastnotified
                        FROM {pulseaction_notification_sch}
                       WHERE instanceid = :notificationinstanceid
                         AND status = :sent
                         AND notifiedtime > 0
                       GROUP BY userid
                  ) latest
             LEFT JOIN {user_lastaccess} ul
                    ON ul.userid = latest.userid AND ul.courseid = :courseid
                 WHERE ul.timeaccess IS NULL
                    OR ul.timeaccess = 0
                    OR ul.timeaccess <= latest.lastnotified";

        $params = [
            'notificationinstanceid' => $notificationinstanceid,
            'sent' => \pulseaction_notification\notification::STATUS_SENT,
            'courseid' => $courseid,
        ];

        $userids = $DB->get_fieldset_sql($sql, $params);
        return $userids ? array_flip($userids) : [];
    }
}
