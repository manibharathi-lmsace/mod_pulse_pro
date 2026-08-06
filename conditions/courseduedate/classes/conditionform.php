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
 * Conditions - Pulse condition class for the "Course Due Date".
 *
 * @package   pulsecondition_courseduedate
 * @copyright 2025, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace pulsecondition_courseduedate;

use mod_pulse\automation\condition_base;
use pulseaction_notification\notification;

/**
 * Pulse automation conditions form and basic details.
 */
class conditionform extends \mod_pulse\automation\condition_base {
    /**
     * Verify if the user has reached the course due date condition.
     *
     * @param object $instancedata The instance data.
     * @param int $userid The user ID.
     * @param \completion_info|null $completion The completion information.
     * @return bool True if condition is met, false otherwise.
     */
    public function get_user_course_duedate($instancedata, int $userid, ?\completion_info $completion = null) {
        return $this->get_course_due_date($instancedata, $userid) ?: false;
    }

    /**
     * Verify if the user has reached the course due date condition.
     *
     * @param object $instancedata The instance data.
     * @param int $userid The user ID.
     * @param \completion_info|null $completion The completion information.
     * @return bool True if condition is met, false otherwise.
     */
    public function is_user_completed(
        $instancedata,
        int $userid,
        ?\completion_info $completion = null,
        ?\tool_timetable\time_management $timemanagement = null,
        ?array $enrollinfo = null,
        ?bool $hastimetablecourse = null
    ) {
        $courseduedate = $this->get_course_due_date($instancedata, $userid, $timemanagement, $enrollinfo, $hastimetablecourse);

        if (!$courseduedate) {
            return false;
        }

        // For upcoming status: only trigger if the due date is still in the future.
        $status = $instancedata->condition['courseduedate']['status'] ?? 0;
        if ((int)$status === \mod_pulse\automation\condition_base::FUTURE && $courseduedate < time()) {
            return false;
        }

        return true;
    }

    /**
     * Verify if the instance has reached the course due date condition.
     */
    public function is_instance_completed(\stdClass $instancedata, int $userid, $completion = null) {
        try {
            $inscondition = $instancedata->condition['courseduedate'] ?? null;
            if (!$inscondition || ($inscondition['status'] ?? 0) < 1) {
                return false;
            }
            $courseduedate = $this->get_user_course_duedate($instancedata, $userid, $completion);
            if ($courseduedate && $inscondition['upcomingtime'] <= $courseduedate && time() <= $courseduedate) {
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }

    /**
     * Get the course date timestamp for delay calculations.
     *
     * @param object|null $data The automation data
     * @param object $instancedata The instance data
     * @return int|false The course date timestamp or false if not available
     */
    public static function get_courseduedate_time($data, $instancedata) {
        if (self::should_use_courseduedate($instancedata)) {
            if ($duedate = self::get_coursedue_timestamp($data->userid, $instancedata, $data)) {
                return $duedate;
            }
        }
        return false;
    }

    /**
     * Get the coursedue date timestamp.
     *
     * @param int $userid Userid
     * @param object $instancedata The instance data
     * @param object $data The data
     * @return int $final Final date timestamp
     */
    protected static function get_coursedue_timestamp(int $userid, $instancedata, $data): ?int {

        // Ensure condition plugin exists.
        if (!class_exists('\\pulsecondition_courseduedate\\conditionform')) {
            return null;
        }

        // Compute base due date from the condition's helper.
        $condform = new \pulsecondition_courseduedate\conditionform();
        $duedate = $condform->get_course_due_date($instancedata, $userid);
        if (empty($duedate)) {
            return null;
        }
        return (int) $duedate;
    }

    /**
     * Check the course due date.
     *
     * @param object $instancedata The instance data
     * @return bool
     */
    protected static function should_use_courseduedate($instancedata): bool {
        // Instancedata is prepared prior to scheduling; safest: check existence and non-empty status flag when present.
        if (!isset($instancedata) || empty($instancedata->condition)) {
            return false;
        }
        $cond = $instancedata->condition['courseduedate'] ?? null;
        if ($cond === null) {
            return false;
        }
        // If a status flag exists, respect it; otherwise treat presence as enabled.
        if (is_array($cond) && array_key_exists('status', $cond)) {
            return (bool)$cond['status'];
        }
        return !empty($cond);
    }

    /**
     * Get the course due date for a specific user and course.
     *
     * $timemanagement, $enrollinfo and $hastimetablecourse are course-level (or, for
     * $enrollinfo, cheaply bulk-fetchable) values that are identical for every user of
     * a given course/instance. Callers processing many users for the same course (e.g.
     * the scheduled task) should compute these once and pass them in, instead of paying
     * for a fresh time_management construction and course_enrolment_manager-backed
     * enrolment lookup on every single call.
     *
     * @param object $instancedata The instance data
     * @param int $userid The user ID
     * @param \tool_timetable\time_management|null $timemanagement Pre-built for this course, reused across users.
     * @param array|null $enrollinfo Pre-fetched [['timestart' => ..., 'timeend' => ...]] for this user,
     *                                bypassing time_management's own (expensive) enrolment lookup.
     * @param bool|null $hastimetablecourse Pre-checked existence of a tool_timetable_course row for this course.
     * @return int|false The course due date timestamp or false if not available
     */
    public function get_course_due_date(
        $instancedata,
        $userid,
        ?\tool_timetable\time_management $timemanagement = null,
        ?array $enrollinfo = null,
        ?bool $hastimetablecourse = null
    ) {
        global $DB;

        $helper = \mod_pulse\automation\helper::create();
        if (!$helper->timetable_installed()) {
            return false;
        }

        $courseid = $instancedata->courseid;
        $timemanagement = $timemanagement ?? new \tool_timetable\time_management($courseid);
        $hastimetablecourse = $hastimetablecourse ?? $DB->record_exists('tool_timetable_course', ['course' => $courseid]);
        $usercourseenrollinfo = $enrollinfo ?? $timemanagement->get_course_user_enrollment($userid);

        if (empty($usercourseenrollinfo)) {
            return false;
        }

        $startdate = $usercourseenrollinfo[0]['timestart'] ?? 0;
        $enddate = $usercourseenrollinfo[0]['timeend'] ?? 0;

        // Primary: Configuration record + any per-user overrides.
        if ($hastimetablecourse) {
            $duedate = $timemanagement->calculate_course_duedate($startdate, $enddate, $userid);
            if ($duedate) {
                return $duedate;
            }
        }

        // Fallback: Timetable > Assignments only (no Configuration record exists).
        return $timemanagement->get_user_course_due_date($startdate, $enddate, $userid) ?: false;
    }

    /**
     * Include data to action.
     *
     * @param array $option
     * @return void
     */
    public function include_condition(&$option) {
        $option['courseduedate'] = get_string('courseduedate', 'pulsecondition_courseduedate');
    }

    /**
     * Loads the form elements for activity condition in template.
     *
     * @param MoodleQuickForm $mform The form object.
     * @param object $forminstance The form instance.
     */
    public function load_template_form(&$mform, $forminstance) {

        $completionstr = get_string('courseduedate', 'pulsecondition_courseduedate');

        $mform->addElement('select', 'condition[courseduedate][status]', $completionstr, $this->get_options());
        $mform->addHelpButton('condition[courseduedate][status]', 'courseduedate', 'pulsecondition_courseduedate');
    }

    /**
     * Loads the form elements for enrolment condition.
     *
     * @param MoodleQuickForm $mform The form object.
     * @param object $forminstance The form instance.
     */
    public function load_instance_form(&$mform, $forminstance) {
        $this->load_template_form($mform, $forminstance);
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
     * Course due dates condition is not based on user enrolment timing.
     *
     * @return bool
     */
    public function is_user_enrolment_based() {
        return false;
    }
}
