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
 * Scheduled cron task to send pulse.
 *
 * @package   pulseaction_notification
 * @copyright 2023, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulseaction_notification\task;

use pulseaction_notification\schedule;
use pulseaction_notification\notification;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/pulse/lib.php');

/**
 * Send notification to users - scheduled task execution observer.
 */
class notify_users extends \core\task\scheduled_task {
    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name() {
        return get_string('notifyusers', 'pulseaction_notification');
    }

    /**
     * Cron execution to send the available pulses.
     *
     * @return void
     */
    public function execute() {
        schedule::instance()->send_scheduled_notification();
    }

    /**
     * Module completion event observer.
     * Find the notification which configured with this activity and disable the schedules for this user.
     *
     * @param stdclass $eventdata
     * @return void
     */
    public static function module_completed($eventdata) {
        global $DB;

        // Event data.
        $data = $eventdata->get_data();

        $cmid = $data['contextinstanceid'];
        $userid = $data['userid'];

        // Get the info for the context.
        [$context, $course, $cm] = get_context_info_array($data['contextid']);

        // Course completion info.
        $completion = new \completion_info($course);

        // Get all the notification instance configures the suppress with this activity.
        $notifications = self::get_suppress_notifications($cmid);

        foreach ($notifications as $notification) {
            // Update suppress reached for all queued shedules.
            self::is_suppress_reached($notification, $userid, $course, $completion);
        }
    }

    /**
     * Course completion event observer.
     * Find the notification which configured with course completion and disable the schedules for this user.
     *
     * @param stdclass $eventdata
     * @return void
     */
    public static function course_completed($eventdata) {
        global $DB;

        // Event data.
        $data = $eventdata->get_data();

        $userid = $data['relateduserid'];

        // Get the info for the context.
        [$context, $course, $cm] = get_context_info_array($data['contextid']);

        // Course completion info.
        $completion = new \completion_info($course);
        if (!$completion->is_course_complete($userid)) {
            return;
        }

        // Get all the notification instance configures the suppress with course completion.
        $sql = "SELECT ni.* FROM {pulseaction_notification_ins} ni
                JOIN {pulse_autoinstances} ai ON ni.instanceid = ai.id
                JOIN {pulse_autotemplates} pat ON ai.templateid = pat.id
                JOIN {pulseaction_notification} na ON pat.id = na.templateid
                WHERE (ni.suppresscourse = 1 OR (ni.suppresscourse IS NULL AND na.suppresscourse = 1))
                AND ai.courseid = :courseid";

        $params = ['suppresscourse' => 1, 'courseid' => $course->id];

        if ($notifications = $DB->get_records_sql($sql, $params)) {
            foreach ($notifications as $notification) {
                // Update suppress reached for all queued shedules.
                self::update_suppress_reached($notification->instanceid, $userid);
            }
        }
    }

    /**
     * Find the scheduled notification instance supress conditions are reached for the user.
     *
     * @param object $notification List of notification to verify the suppress.
     * @param int $userid User ID to verify for.
     * @param stdclass $course Instance Course record.
     * @param \completion_info $completion Instance course completion info.
     *
     * @return bool True if the user is reached the suppress conditions for the instance. Otherwise False.
     */
    public static function is_suppress_reached($notification, $userid, $course, $completion = null) {
        global $DB;

        $completion = $completion ?: new \completion_info($course);

        // Confim the course completion suppress is enabled and reached.
        $coursesuppress = $notification->suppresscourse ?? false;
        if ($coursesuppress) {
            if ($completion->is_course_complete($userid)) {
                // Update suppress reached for all queued shedules.
                self::update_suppress_reached($notification->instanceid, $userid);
                // Course completion reached.
                return notification::SUPPRESSREACHED;
            }
        }

        // Get the notification suppres module ids.
        $suppress = $notification->suppress && is_string($notification->suppress)
            ? json_decode($notification->suppress) : $notification->suppress;

        if (!empty($suppress)) {
            $result = [];
            // Find the completion status for all this suppress modules.
            foreach ($suppress as $cmid) {
                if (method_exists($completion, 'get_completion_data')) {
                    $modulecompletion = $completion->get_completion_data($cmid, $userid, []);
                } else {
                    $cminfo = get_coursemodule_from_id('', $cmid);
                    $modulecompletion = (array) $completion->get_data($cminfo, false, $userid);
                }
                if (isset($modulecompletion['completionstate']) && $modulecompletion['completionstate'] == COMPLETION_COMPLETE) {
                    $result[] = true;
                }
            }

            // If suppress operator set as all, check all the configures modules are completed.
            if ($notification->suppressoperator == \mod_pulse\automation\action_base::OPERATOR_ALL) {
                // Remove the schedule only if all the activites are completed.
                if (count($result) == count($suppress)) {
                    $remove = true;
                }
            } else {
                // If any one of the activity is completed then remove the schedule from the user.
                if (count($result) >= 1) {
                    $remove = true;
                }
            }

            // Update the flag to user schedules as suppress reached, it prevents the update of the schedule on notification.
            if (isset($remove) && $remove) {
                $remove = false; // Reset for the next notification test.

                $sql = "SELECT * FROM {pulseaction_notification_sch}
                        WHERE instanceid = :instanceid AND (userid = :userid OR relateduserid = :relateduserid)
                        AND (status = :disabledstatus  OR status = :queued)";

                $params = [
                    'instanceid' => $notification->instanceid, 'userid' => $userid, 'relateduserid' => $userid,
                    'disabledstatus' => notification::STATUS_DISABLED, 'queued' => notification::STATUS_QUEUED,
                ];

                if ($records = $DB->get_records_sql($sql, $params)) {
                    foreach ($records as $record) {
                        $DB->set_field(
                            'pulseaction_notification_sch',
                            'suppressreached',
                            notification::SUPPRESSREACHED,
                            ['id' => $record->id]
                        );
                        $DB->set_field(
                            'pulseaction_notification_sch',
                            'status',
                            notification::STATUS_DISABLED,
                            ['id' => $record->id]
                        );
                    }
                }

                return notification::SUPPRESSREACHED;
            }
        }
        return false;
    }

    /**
     * Update the suppress reached flag for the user schedules.
     *
     * @param int $notificationinstanceid Notification instance ID.
     * @param int $userid User ID.
     * @return void
     */
    protected static function update_suppress_reached(int $notificationinstanceid, int $userid) {
        global $DB;

        $sql = "SELECT id FROM {pulseaction_notification_sch}
                WHERE instanceid = :instanceid AND (userid = :userid OR relateduserid = :relateduserid)
                AND (status = :disabledstatus  OR status = :queued)";

        $params = [
            'instanceid' => $notificationinstanceid, 'userid' => $userid, 'relateduserid' => $userid,
            'disabledstatus' => notification::STATUS_DISABLED,
            'queued' => notification::STATUS_QUEUED,
        ];

        if ($records = $DB->get_records_sql($sql, $params)) {
            $schdules = array_keys($records);

            if (empty($schdules)) {
                return;
            }

            [$insql, $inparams] = $DB->get_in_or_equal($schdules, SQL_PARAMS_NAMED, 'schdules');

            $DB->set_field_select(
                'pulseaction_notification_sch',
                'suppressreached',
                notification::SUPPRESSREACHED,
                "id $insql",
                $inparams
            );
            $DB->set_field_select(
                'pulseaction_notification_sch',
                'status',
                notification::STATUS_DISABLED,
                "id $insql",
                $inparams
            );
        }
    }

    /**
     * Retrieves notifications with suppression value containing a specific ID.
     *
     * @param int $id The ID to search for within the suppression values.
     *
     * @return array An array of notification records matching the suppression criteria.
     */
    public static function get_suppress_notifications($id) {
        global $DB;

        $like = $DB->sql_like('suppress', ':value');
        $sql = "SELECT * FROM {pulseaction_notification_ins} WHERE $like";
        $params = ['value' => '%"' . $id . '"%'];

        $records = $DB->get_records_sql($sql, $params);

        return $records;
    }
}
