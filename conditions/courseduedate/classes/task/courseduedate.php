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
 * Course due date check scheduled task.
 *
 * @package   pulsecondition_courseduedate
 * @copyright 2025, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulsecondition_courseduedate\task;

/**
 * Scheduled task to check course due dates and trigger automation instances.
 */
class courseduedate extends \core\task\scheduled_task {
    /**
     * Get the name of the task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskname', 'pulsecondition_courseduedate');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        // Check if timetable tool is installed.
        $helper = \mod_pulse\automation\helper::create();
        if (!$helper->timetable_installed()) {
            mtrace('Timetable tool is not installed. Skipping course due date check.');
            return;
        }

        mtrace('Starting course due date automation check...');

        // Get all active automation instances that use course due date condition.
        $sql = "SELECT ai.*, ai.id as instanceid
                FROM {pulse_autoinstances} ai
                JOIN {pulse_autotemplates} pat ON pat.id = ai.templateid
                LEFT JOIN {pulse_condition_overrides} co ON co.instanceid = ai.id AND co.triggercondition = 'courseduedate'
                WHERE ai.status = 1
                AND (co.status > 0 OR (co.status IS NULL AND ai.templateid IN (
                    SELECT c.templateid FROM {pulse_condition} c WHERE c.triggercondition = 'courseduedate' AND c.status > 0
                )))";

        $instances = $DB->get_records_sql($sql);

        if (empty($instances)) {
            mtrace('No active course due date automation instances found.');
            return;
        }

        mtrace('Found ' . count($instances) . ' active course due date automation instances.');

        foreach ($instances as $instance) {
            $this->process_instance($instance);
        }

        mtrace('Course due date automation check completed.');
    }

    /**
     * Process a single automation instance.
     *
     * @param object $instance The automation instance
     */
    private function process_instance($instance) {
        global $DB;

        mtrace("Processing instance {$instance->id} for course {$instance->courseid}");

        $conditionform = new \pulsecondition_courseduedate\conditionform();
        $instanceobj   = \mod_pulse\automation\instances::create($instance->instanceid);
        $instancedata  = $instanceobj->get_instance_data();

        $notificationinstanceid = (int)$DB->get_field(
            'pulseaction_notification_ins', 'id', ['instanceid' => $instance->instanceid]
        );

        $status   = (int)($instancedata->condition['courseduedate']['status'] ?? 0);
        $isFuture = ($status === \mod_pulse\automation\condition_base::FUTURE);

        // Users who already received a notification. Used to prevent re-triggering
        // on every task run for users whose due date has not changed.
        $alreadynotified = $this->get_already_notified_users($notificationinstanceid);

        // All three are hoisted so every user in the loop below reuses the same
        // time_management instance and course-level lookups, instead of each
        // get_course_due_date() call rebuilding/re-querying/re-fetching them.
        $timemanagement = new \tool_timetable\time_management($instance->courseid);
        $hastimetablecourse = $DB->record_exists('tool_timetable_course', ['course' => $instance->courseid]);
        $enroldatesbyuser = $this->get_enrolment_dates_by_user($instance->courseid);

        $users = $this->get_enrolled_users_recordset($instance->courseid);
        $triggeredcount = 0;

        foreach ($users as $user) {
            $alreadydone = isset($alreadynotified[$user->id]);

            // Enrolment dates fetched in bulk above; feeding them directly avoids
            // time_management::get_course_user_enrollment()'s per-user
            // course_enrolment_manager construction (a heavy Moodle core class).
            // Falls back to null (letting get_course_due_date() look it up itself)
            // for the rare case a user isn't resolved in the bulk map.
            $enrollinfo = isset($enroldatesbyuser[$user->id]) ? [$enroldatesbyuser[$user->id]] : null;

            if ($isFuture) {
                $newduedate = (int)$conditionform->get_course_due_date(
                    $instancedata, $user->id, $timemanagement, $enrollinfo, $hastimetablecourse
                );
                if (!$newduedate) {
                    continue;
                }

                if ($newduedate < time()) {
                    // Due date has already passed.
                    // Only re-notify when previously notified AND due date changed.
                    if ($alreadydone && $notificationinstanceid
                        && $this->force_reschedule_if_due_date_changed(
                            $notificationinstanceid, $user->id, $instancedata, $newduedate
                        )
                    ) {
                        $triggeredcount++;
                    }
                    // Not yet notified + past date → Bug 1 guard, skip silently.
                    continue;
                }

                // Due date is in the future.
                if ($alreadydone) {
                    // Previously notified: only re-trigger when due date changed.
                    if ($notificationinstanceid && $this->clear_stale_schedule_if_due_date_changed(
                        $notificationinstanceid, $user->id, $conditionform, $instancedata,
                        $timemanagement, $enrollinfo, $hastimetablecourse
                    )) {
                        $instanceobj->trigger_action((int)$user->id, null, false);
                        $triggeredcount++;
                    }
                    continue;
                }

                // Not yet notified and due date is in the future: trigger normally.
                $instanceobj->trigger_action((int)$user->id, null, false);
                $triggeredcount++;

            } else {
                // ALL (or other) status: skip already-notified users.
                if ($alreadydone) {
                    continue;
                }
                if (!$conditionform->is_user_completed(
                    $instancedata, $user->id, null, $timemanagement, $enrollinfo, $hastimetablecourse
                )) {
                    continue;
                }
                $instanceobj->trigger_action((int)$user->id, null, false);
                $triggeredcount++;
            }
        }

        $users->close();
        mtrace("Triggered automation for {$triggeredcount} users in instance {$instance->id}");
    }

    /**
     * Stream enrolled users as a recordset to avoid loading all rows into memory.
     *
     * @param int $courseid
     * @return \moodle_recordset
     */
    private function get_enrolled_users_recordset(int $courseid): \moodle_recordset {
        global $DB;
        $sql = "SELECT DISTINCT u.id, u.username
                  FROM {user} u
                  JOIN {user_enrolments} ue ON ue.userid = u.id
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid = :courseid AND ue.status = 0 AND u.deleted = 0";
        return $DB->get_recordset_sql($sql, ['courseid' => $courseid]);
    }

    /**
     * Bulk-fetch one enrolment's start/end dates per user for the course, so the per-user
     * loop doesn't have to ask tool_timetable's course_enrolment_manager-backed lookup for
     * each user individually.
     *
     * A user can be enrolled through more than one enrolment method; to match
     * time_management::find_current_enrollment()'s tie-break, this prefers a currently
     * "in-window" enrolment (timestart passed, timeend not yet reached) and otherwise the
     * earliest one. Users with no rows here (should not normally happen) simply fall back
     * to the slower per-user lookup in get_course_due_date().
     *
     * @param int $courseid
     * @return array userid => ['timestart' => int, 'timeend' => int]
     */
    private function get_enrolment_dates_by_user(int $courseid): array {
        global $DB;

        $now = time();
        $sql = "SELECT ue.id, ue.userid, ue.timestart, ue.timeend, ue.timecreated
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid = :courseid AND ue.status = 0
              ORDER BY ue.userid ASC,
                       CASE WHEN (ue.timestart = 0 OR ue.timestart <= :now1)
                             AND (ue.timeend = 0 OR ue.timeend >= :now2)
                            THEN 0 ELSE 1 END ASC,
                       ue.timecreated ASC";

        $rs = $DB->get_recordset_sql($sql, ['courseid' => $courseid, 'now1' => $now, 'now2' => $now]);

        $dates = [];
        foreach ($rs as $ue) {
            // ORDER BY already put each user's best-matching row first; keep only that one.
            if (isset($dates[$ue->userid])) {
                continue;
            }
            $dates[$ue->userid] = [
                'timestart' => !empty($ue->timestart) ? (int) $ue->timestart : (int) $ue->timecreated,
                'timeend'   => (int) $ue->timeend,
            ];
        }
        $rs->close();

        return $dates;
    }

    /**
     * Get a list of user IDs that have already been notified for a given automation instance.
     *
     * @param int $notificationinstanceid pulseaction_notification_ins.id
     * @return array userid => true
     */
    private function get_already_notified_users(int $notificationinstanceid): array {
        global $DB;

        if (!$notificationinstanceid) {
            return [];
        }

        $userids = $DB->get_fieldset_select(
            'pulseaction_notification_sch',
            'userid',
            'instanceid = :instanceid AND status = :sent AND notifiedtime > 0',
            [
                'instanceid' => $notificationinstanceid,
                'sent'       => \pulseaction_notification\notification::STATUS_SENT,
            ]
        );

        return $userids ? array_flip($userids) : [];
    }

    /**
     * Delete the most recent STATUS_SENT row if the due date changed.
     *
     * Returns true when the row was deleted (date changed), false when unchanged.
     * Compares expected scheduletime (duedate ± notifydelay) to avoid false positives
     * when a notification delay is configured.
     *
     * @param int $notificationinstanceid
     * @param int $userid
     * @param \pulsecondition_courseduedate\conditionform $conditionform
     * @param object $instancedata
     * @param \tool_timetable\time_management|null $timemanagement Hoisted from process_instance().
     * @param array|null $enrollinfo Bulk-fetched enrolment dates for this user.
     * @param bool|null $hastimetablecourse Hoisted from process_instance().
     * @return bool true if SENT row was deleted
     */
    private function clear_stale_schedule_if_due_date_changed(
        int $notificationinstanceid,
        int $userid,
        \pulsecondition_courseduedate\conditionform $conditionform,
        object $instancedata,
        ?\tool_timetable\time_management $timemanagement = null,
        ?array $enrollinfo = null,
        ?bool $hastimetablecourse = null
    ): bool {
        global $DB;

        $newduedate = (int)$conditionform->get_course_due_date(
            $instancedata, $userid, $timemanagement, $enrollinfo, $hastimetablecourse
        );
        if (!$newduedate) {
            return false;
        }

        $sentrow = $DB->get_record_sql(
            "SELECT * FROM {pulseaction_notification_sch}
              WHERE instanceid = :instanceid AND userid = :userid AND status = :sent
              ORDER BY id DESC",
            [
                'instanceid' => $notificationinstanceid,
                'userid'     => $userid,
                'sent'       => \pulseaction_notification\notification::STATUS_SENT,
            ],
            IGNORE_MULTIPLE
        );

        if (!$sentrow) {
            return false;
        }

        $expectedscheduletime = $this->compute_expected_scheduletime($instancedata, $newduedate);
        if ($expectedscheduletime === null) {
            return false; // Non-instance delay base — skip.
        }

        if (abs((int)$sentrow->scheduletime - $expectedscheduletime) <= 60) {
            return false; // Date unchanged.
        }

        $DB->delete_records('pulseaction_notification_sch', ['id' => $sentrow->id]);
        return true;
    }

    /**
     * Re-schedule when the due date changed AND the new date has already passed.
     *
     * Directly inserts a QUEUED row so notify_users picks it up immediately,
     * bypassing create_schedule_foruser() which would gate on is_instance_completed()
     * (which requires time() <= duedate and fails for a just-passed date).
     *
     * @param int $notificationinstanceid
     * @param int $userid
     * @param object $instancedata
     * @param int $newduedate raw due date timestamp
     * @return bool true if a new QUEUED row was created
     */
    private function force_reschedule_if_due_date_changed(
        int $notificationinstanceid,
        int $userid,
        object $instancedata,
        int $newduedate
    ): bool {
        global $DB;

        $expectedscheduletime = $this->compute_expected_scheduletime($instancedata, $newduedate);
        if ($expectedscheduletime === null) {
            return false;
        }

        $sentrow = $DB->get_record_sql(
            "SELECT * FROM {pulseaction_notification_sch}
              WHERE instanceid = :instanceid AND userid = :userid AND status = :sent
              ORDER BY id DESC",
            [
                'instanceid' => $notificationinstanceid,
                'userid'     => $userid,
                'sent'       => \pulseaction_notification\notification::STATUS_SENT,
            ],
            IGNORE_MULTIPLE
        );

        if (!$sentrow) {
            return false;
        }

        if (abs((int)$sentrow->scheduletime - $expectedscheduletime) <= 60) {
            return false; // Date unchanged.
        }

        $sentfc = (int)$sentrow->frequencycount;
        $DB->delete_records('pulseaction_notification_sch', ['id' => $sentrow->id]);

        // Avoid inserting a duplicate if this task already ran for this cycle.
        if ($DB->record_exists('pulseaction_notification_sch', [
            'instanceid' => $notificationinstanceid,
            'userid'     => $userid,
            'status'     => \pulseaction_notification\notification::STATUS_QUEUED,
        ])) {
            return true;
        }

        // Insert QUEUED directly; notify_users will send it since scheduletime is past.
        $DB->insert_record('pulseaction_notification_sch', [
            'instanceid'      => $notificationinstanceid,
            'userid'          => $userid,
            'timecreated'     => time(),
            'scheduletime'    => $expectedscheduletime,
            'status'          => \pulseaction_notification\notification::STATUS_QUEUED,
            'notifycount'     => 0,
            'notifiedtime'    => 0,
            'frequencycount'  => $sentfc + 1,
            'relateduserid'   => '',
            'suppressreached' => 0,
        ]);

        return true;
    }

    /**
     * Compute the expected scheduletime for a given due date by applying the same
     * delay offset that notification::generate_the_scheduletime() uses.
     *
     * Returns null when the delay base is not DELAYBASE_INSTANCE (scheduletime is
     * then anchored to enrolment or last-condition time, not the due date).
     *
     * @param object $instancedata
     * @param int $duedate raw due date timestamp
     * @return int|null expected scheduletime, or null if not determinable
     */
    private function compute_expected_scheduletime(object $instancedata, int $duedate): ?int {
        $notifyaction  = $instancedata->actions['notification'] ?? [];
        $delaybase     = (int)($notifyaction['delaybase']     ?? \pulseaction_notification\notification::DELAYBASE_INSTANCE);
        $delaymode     = (int)($notifyaction['notifydelay']   ?? \pulseaction_notification\notification::DELAYNONE);
        $delayduration = (int)($notifyaction['delayduration'] ?? 0);

        if ($delaybase !== \pulseaction_notification\notification::DELAYBASE_INSTANCE) {
            return null;
        }

        if ($delaymode === \pulseaction_notification\notification::DELAYAFTER && $delayduration > 0) {
            return $duedate + $delayduration;
        }
        if ($delaymode === \pulseaction_notification\notification::DELAYBEFORE && $delayduration > 0) {
            return $duedate - $delayduration;
        }
        return $duedate;
    }
}
