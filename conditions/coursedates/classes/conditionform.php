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
 * Conditions - Pulse condition class for the "Course dates".
 *
 * @package   pulsecondition_coursedates
 * @copyright 2025, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulsecondition_coursedates;

use mod_pulse\automation\instances;

/**
 * Automation course dates condition form.
 */
class conditionform extends \mod_pulse\automation\condition_base {
    /**
     * Delay None.
     */
    const DELAYNONE = 0;

    /**
     * Delay Before.
    */
    const DELAYBEFORE = 1;

    /**
     * Delay After.
     */
    const DELAYAFTER = 2;

    /**
     * Verify if the course date condition is met for the user.
     *
     * @param object $instancedata The instance data.
     * @param int $userid The user ID.
     * @param \completion_info|null $completion The completion information.
     * @return bool True if condition is met, false otherwise.
     */
    public function is_user_completed($instancedata, int $userid, ?\completion_info $completion = null) {
        global $DB;

        $courseid = $instancedata->courseid;
        $datetype = $instancedata->condition['coursedates']['type'] ?? 'start';
        $targetdate = $this->get_coursedates_with_delay($instancedata);

        $sql = "SELECT ue.timecreated
            FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid
            WHERE ue.userid = :userid
            AND e.courseid = :courseid
        ";

        // Add end-date condition.
        if ($datetype == 'end') {
            $sql .= " AND ue.status = 0";
        }

        $sql .= "
            ORDER BY ue.timecreated ASC
            LIMIT 1
        ";

        $enrolment = $DB->get_record_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);

        if (!empty($enrolment->timecreated) && $enrolment->timecreated <= $targetdate) {
            return true;
        }

        return false;
    }

    public function is_instance_completed(\stdClass $instancedata, int $userid, $completion = null) {
        try {
            if (isset($instancedata->condition['coursedates'])) {
                $inscondition = $instancedata->condition['coursedates'];
                $datetype = $inscondition['type'] ?? 'start';

                $conditiontime = $instancedata->course->{$datetype . 'date'} ?? 0;
                if ($inscondition['upcomingtime'] <= $conditiontime) {
                    return true;
                }
            }

        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * Get the course date (start or end) for a specific course.
     *
     * @param object $instancedata The instance data
     * @return int|false The course date timestamp or false if not available
     */
    public function get_course_date($instancedata) {
        return self::get_coursedates_time(null, $instancedata);
    }

    /**
     * Get the course date timestamp for delay calculations.
     *
     * @param object|null $data The automation data
     * @param object $instancedata The instance data
     * @return int|false The course date timestamp or false if not available
     */
    public static function get_coursedates_time($data, $instancedata) {
        $courseid = $instancedata->courseid;
        $course = get_course($courseid);
        $datetype = $instancedata->condition['coursedates']['type'] ?? 'start';
        if ($datetype == 'end') {
            return $course->enddate ?: false;
        } else {
            return $course->startdate ?: false;
        }
    }

    /**
     * Include condition
     *
     * @param array $option
     * @return void
     */
    public function include_condition(&$option) {
        $option['coursedates'] = get_string('coursedates', 'pulsecondition_coursedates');
    }

    /**
     * Loads the form elements for course dates condition in template.
     *
     * @param MoodleQuickForm $mform The form object.
     * @param object $forminstance The form instance.
     */
    public function load_template_form(&$mform, $forminstance) {
        $coursedatestr = get_string('coursedates', 'pulsecondition_coursedates');
        $mform->addElement('select', 'condition[coursedates][status]', $coursedatestr, $this->get_options());
        $mform->addHelpButton('condition[coursedates][status]', 'coursedates', 'pulsecondition_coursedates');
    }

    /**
     * Loads the form elements for course dates condition.
     *
     * @param MoodleQuickForm $mform The form object.
     * @param object $forminstance The form instance.
     */
    public function load_instance_form(&$mform, $forminstance) {
        $coursedatestr = get_string('coursedates', 'pulsecondition_coursedates');

        $mform->addElement('select', 'condition[coursedates][status]', $coursedatestr, $this->get_options());
        $mform->addHelpButton('condition[coursedates][status]', 'coursedates', 'pulsecondition_coursedates');

        $mform->addElement(
            'select',
            'condition[coursedates][type]',
            get_string('coursedates_type', 'pulsecondition_coursedates'),
            [
                'start' => get_string('coursedates_start', 'pulsecondition_coursedates'),
                'end' => get_string('coursedates_end', 'pulsecondition_coursedates'),
            ]
        );

        $mform->hideIf('condition[coursedates][type]', 'condition[coursedates][status]', 'eq', self::DISABLED);
    }

    /**
     * Get Course date with delay support.
     *
     * @param object $instancedata The instance data
     * @return int|false The course date timestamp with delay applied or false if not available
     */
    public function get_coursedates_with_delay($instancedata) {
        $coursedate = $this->get_course_date($instancedata);
        $delay = $instancedata->actions['notification']['notifydelay'] ?? self::DELAYNONE;
        $delayduration = $instancedata->actions['notification']['delayduration'] ?? 0;

        if ($delay == self::DELAYAFTER && $delayduration > 0) {
            return $coursedate + $delayduration;
        } else if ($delay == self::DELAYBEFORE && $delayduration > 0) {
            return $coursedate - $delayduration;
        } else {
            return $coursedate;
        }
    }

    /**
     * Course dates condition is not based on user enrolment timing.
     *
     * @return bool
     */
    public function is_user_enrolment_based() {
        return false;
    }

    /**
     * Bulk precheck: return a recordset of userids enrolled in the course on or before the
     * (delay-adjusted) target date. Replaces a per-user is_user_completed() loop in the
     * scheduled task with a single set-based query.
     *
     * Only counts active enrolments through enabled enrolment methods, and excludes deleted
     * user accounts — mirroring what get_enrolled_users() would have filtered.
     *
     * @param int $courseid The course id.
     * @param int $targetdate The (delay-adjusted) target timestamp; users enrolled at or before this match.
     * @param string $datetype 'start' or 'end' — 'end' additionally requires the enrolment to still be active.
     * @return \moodle_recordset Recordset of objects with ->id (userid) and ->username.
     */
    public function get_matching_users_recordset(int $courseid, int $targetdate, string $datetype): \moodle_recordset {
        global $DB;

        $sql = "SELECT DISTINCT u.id, u.username
                  FROM {user} u
                  JOIN {user_enrolments} ue ON ue.userid = u.id
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.status = :enrolenabled
                 WHERE e.courseid = :courseid
                   AND ue.timecreated <= :targetdate
                   AND u.deleted = 0";

        $params = [
            'courseid' => $courseid,
            'targetdate' => $targetdate,
            'enrolenabled' => ENROL_INSTANCE_ENABLED,
        ];

        if ($datetype === 'end') {
            $sql .= " AND ue.status = 0";
        }

        return $DB->get_recordset_sql($sql, $params);
    }

    /**
     * Indicates that this condition supports delay/schedule functionality.
     *
     * @return bool
     */
    public function delay_support_plugins() {
        return true;
    }

    /**
     * Include the conditions where query with the course dates sql used to fetch the schedule record.
     *
     * @return string
     */
    public static function schedule_override_coursedates() {
        return '(c.startdate = 0 OR c.startdate <> 0) AND (c.enddate = 0 OR c.enddate <> 0)';
    }
}
