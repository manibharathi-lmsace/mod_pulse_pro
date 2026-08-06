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
 * Observer class for the "Course dates".
 *
 * @package   pulsecondition_coursedates
 * @copyright 2025, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulsecondition_coursedates;
use mod_pulse\local\automation\schedule;

/**
 * Event observer for course dates condition.
 */
class observer {
    /**
     * Handle course updated event to reschedule notifications.
     *
     * @param \core\event\course_updated $event
     */
    public static function course_updated(\core\event\course_updated $event) {
        global $DB;
        $courseid = $event->courseid;
        $course = get_course($courseid);

        // Get all pulse instances with coursedates condition for this course.
        $sql = "SELECT ai.*, ai.id as instanceid
                FROM {pulse_autoinstances} ai
                LEFT JOIN {pulse_condition_overrides} co ON co.instanceid = ai.id AND co.triggercondition = 'coursedates'
                WHERE ai.courseid = :courseid
                AND ai.status = 1
                AND (co.status > 0 OR (co.status IS NULL AND ai.templateid IN (
                    SELECT c.templateid FROM {pulse_condition} c WHERE c.triggercondition = 'coursedates' AND c.status > 0
                )))";

        $instances = $DB->get_records_sql($sql, ['courseid' => $courseid]);

        if (empty($instances)) {
            return;
        }

        foreach ($instances as $instance) {
            self::reschedule_instance($instance, $course);
        }
    }

    /**
     * Reschedule notifications for an instance when course dates change.
     *
     * @param object $instance The pulse instance
     * @param object $course The course object
     */
    protected static function reschedule_instance($instance, $course) {
        global $DB;

        $instancedata = \mod_pulse\automation\instances::create($instance->id)->get_instance_data();
        if (!isset($instancedata->condition['coursedates'])) {
            return;
        }

        $condition = $instancedata->condition['coursedates'];
        $datetype = $condition['type'] ?? 'start';
        // Get the reference date.
        $referencedate = ($datetype == 'start') ? $course->startdate : $course->enddate;
        if (!$referencedate) {
            return;
        }
        $notifydelay = $instancedata->notification['notifydelay '] ?? 0;
        $delayduration = $instancedata->notification['delayduration'] ?? 0;
        if ($notifydelay == 2) {
            $referencedate += $delayduration;
        } else if ($notifydelay == 1) {
            $referencedate -= $delayduration;
        }
        // Calculate new scheduled time.
        $newscheduletime = $referencedate;

        // Update all queued notifications for this instance.
        $sql = "UPDATE {pulseaction_notification_sch}
                SET scheduletime = :newtime
                WHERE instanceid = :instanceid AND status = :status"; // Only update pending notifications.

        $params = [
            'newtime' => $newscheduletime,
            'instanceid' => $instance->id,
            'status' => schedule::STATUS_QUEUED,
        ];

        $DB->execute($sql, $params);
    }
}
