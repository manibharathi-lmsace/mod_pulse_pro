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
 * Conditions - Pulse condition class for "User inactivity".
 *
 * @package   pulsecondition_userinactivity
 * @copyright 2025, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulsecondition_userinactivity;

use mod_pulse\automation\condition_base;

/**
 * Automation condition form for user inactivity.
 */
class conditionform extends \mod_pulse\automation\condition_base {
    /**
     * Inactivity based on course access.
     */
    const INACTIVITY_ACCESS = 'inactivity_access';

    /**
     * Inactivity based on activity completion.
     */
    const INACTIVITY_COMPLETION = 'inactivity_completion';

    /**
     * Inactivity based on activity completion conditions.
     */
    const INACTIVITY_COMPLETION_CONDITIONS = 'inactivity_completion_conditions';

    /**
     * All activities included.
     */
    const ACTIVITIES_ALL = 1;

    /**
     * Only completion relevant activities.
     */
    const ACTIVITIES_COMPLETION_RELEVANT = 2;


     /**
      * Verify if the course start or end date has been reached.
      *
      * @param object $instancedata The instance data.
      * @param int $userid The user ID.
      * @param \completion_info|null $completion The completion information.
      * @return bool True if condition is met, false otherwise.
      */
    public function is_user_completed($instancedata, int $userid, ?\completion_info $completion = null) {
        global $DB;
        $courseid = $instancedata->courseid;
        $course = get_course($courseid);

        $triggercondition = $instancedata->condition['userinactivity'];

        $type = $triggercondition['type'] ?? self::INACTIVITY_ACCESS;
        $includedactivities = $triggercondition['includedactivities'] ?? self::ACTIVITIES_ALL;
        $inactivityperiod = $triggercondition['inactivityperiod'] ?? 0;
        $requirepreviousactivity = !empty($triggercondition['requirepreviousactivity']);
        $activityperiod = $triggercondition['activityperiod'] ?? 0;
        $status = (int) ($triggercondition['status'] ?? 0);
        // In Upcoming mode, inactivity only starts accruing once the condition was enabled.
        $upcomingtime = ($status == self::FUTURE) ? (int) ($triggercondition['upcomingtime'] ?? 0) : 0;

        if ($inactivityperiod <= 0) {
            return false;
        }

        $currenttime = time();
        $inactivitythreshold = $currenttime - $inactivityperiod;

        // Check if user requires previous activity.
        if ($requirepreviousactivity && $activityperiod > 0) {
            $activitythreshold = $currenttime - $activityperiod;
            if (
                !$this->has_user_activity(
                    $userid, $course, $type, $includedactivities, $activitythreshold, $currenttime, true, 0
                )
            ) {
                return false; // User never had activity in the required period.
            }
        }

        // Check if user is currently inactive.
        return !$this->has_user_activity(
            $userid, $course, $type, $includedactivities, $inactivitythreshold, $currenttime, false, $upcomingtime
        );
    }

    /**
     * User inactivity is not based on user enrolment timing — Upcoming mode must evaluate
     * all enrolled users (old and new), not just future enrollees, so the generic
     * enrolment-createtime grandfathering in instances.php is bypassed for this condition.
     *
     * @return bool
     */
    public function is_user_enrolment_based() {
        return false;
    }

    /**
     * Include condition
     *
     * @param array $option
     * @return void
     */
    public function include_condition(&$option) {
        $option['userinactivity'] = get_string('userinactivity', 'pulsecondition_userinactivity');
    }

    /**
     * Loads the form elements for user inactivity condition.
     *
     * @param MoodleQuickForm $mform The form object.
     * @param object $forminstance The form instance.
     */
    public function load_instance_form(&$mform, $forminstance) {
        $this->load_form($mform, $forminstance);
    }

    /**
     * Loads the form elements for user inactivity condition in template.
     *
     * @param MoodleQuickForm $mform The form object.
     * @param object $forminstance The form instance.
     */
    public function load_template_form(&$mform, $forminstance) {
        $this->load_form($mform, $forminstance);
    }

    /**
     * Load the form elements for user inactivity condition.
     *
     * @param MoodleQuickForm $mform The form instance
     * @param stdClass $forminstance The form instance data
     */
    public function load_form(&$mform, $forminstance) {
        global $CFG;
         // Register custom duration form element.
        \MoodleQuickForm::registerElementType(
            'pulseconditionduration',
            $CFG->dirroot . '/mod/pulse/conditions/userinactivity/forms/duration.php',
            'moodlequickform_pulseconditionduration'
        );

        $mform->addElement('select', 'condition[userinactivity][status]', get_string('status', 'pulse'), $this->get_options());
        $mform->addHelpButton('condition[userinactivity][status]', 'userinactivity', 'pulsecondition_userinactivity');

        // User inactivity type.
        $inactivityoptions = [
            self::INACTIVITY_ACCESS => get_string('basedonaccess', 'pulsecondition_userinactivity'),
            self::INACTIVITY_COMPLETION => get_string('basedoncompletion', 'pulsecondition_userinactivity'),
        ];

        $mform->addElement(
            'select',
            'condition[userinactivity][type]',
            get_string('userinactivity', 'pulsecondition_userinactivity'),
            $inactivityoptions
        );
        $mform->addHelpButton('condition[userinactivity][type]', 'userinactivity', 'pulsecondition_userinactivity');
        $mform->hideIf('condition[userinactivity][type]', 'condition[userinactivity][status]', 'eq', self::DISABLED);

        // Included activities.
        $activityoptions = [
            self::ACTIVITIES_ALL => get_string('allactivities', 'pulsecondition_userinactivity'),
            self::ACTIVITIES_COMPLETION_RELEVANT => get_string('completionrelevantactivities', 'pulsecondition_userinactivity'),
        ];

        $mform->addElement(
            'select',
            'condition[userinactivity][includedactivities]',
            get_string('includedactivities', 'pulsecondition_userinactivity'),
            $activityoptions
        );
        $mform->addHelpButton(
            'condition[userinactivity][includedactivities]',
            'includedactivities',
            'pulsecondition_userinactivity'
        );
        $mform->hideIf('condition[userinactivity][includedactivities]', 'condition[userinactivity][status]', 'eq', self::DISABLED);
        $mform->hideIf(
            'condition[userinactivity][includedactivities]',
            'condition[userinactivity][type]',
            'eq',
            self::INACTIVITY_ACCESS
        );

        // Inactivity period.
        $mform->addElement(
            'pulseconditionduration',
            'condition[userinactivity][inactivityperiod]',
            get_string('inactivityperiod', 'pulsecondition_userinactivity')
        );
        $mform->addHelpButton('condition[userinactivity][inactivityperiod]', 'inactivityperiod', 'pulsecondition_userinactivity');
        $mform->hideIf('condition[userinactivity][inactivityperiod]', 'condition[userinactivity][status]', 'eq', self::DISABLED);

        // Require previous activity.
        $mform->addElement(
            'selectyesno',
            'condition[userinactivity][requirepreviousactivity]',
            get_string('requirepreviousactivity', 'pulsecondition_userinactivity'),
            null,
            null,
            [0, 1]
        );
        $mform->addHelpButton(
            'condition[userinactivity][requirepreviousactivity]',
            'requirepreviousactivity',
            'pulsecondition_userinactivity'
        );
        $mform->hideIf(
            'condition[userinactivity][requirepreviousactivity]',
            'condition[userinactivity][status]',
            'eq',
            self::DISABLED
        );

        // Activity period.
        $mform->addElement(
            'pulseconditionduration',
            'condition[userinactivity][activityperiod]',
            get_string('activityperiod', 'pulsecondition_userinactivity')
        );
        $mform->addHelpButton('condition[userinactivity][activityperiod]', 'activityperiod', 'pulsecondition_userinactivity');
        $mform->hideIf('condition[userinactivity][activityperiod]', 'condition[userinactivity][requirepreviousactivity]', 'eq', 0);
        $mform->hideIf('condition[userinactivity][activityperiod]', 'condition[userinactivity][status]', 'eq', self::DISABLED);
    }


    /**
     * Checks if user has activity based on the specified criteria.
     *
     * @param int $userid The user ID.
     * @param stdClass $course The course object.
     * @param int $type The inactivity type.
     * @param int $includedactivities Which activities to include.
     * @param int $fromtime The start time to check.
     * @param int $totime The end time to check.
     * @param bool $requact
     * @param int $upcomingtime When set (Upcoming mode only), floors the baseline so inactivity
     *                          time before the condition was enabled is ignored. Only ever applies
     *                          to the current-inactivity check ($requact = false) by construction.
     * @return bool True if user has activity, false otherwise.
     */
    protected function has_user_activity(
        $userid,
        $course,
        $type,
        $includedactivities,
        $fromtime,
        $totime,
        $requact = false,
        $upcomingtime = 0
    ) {
        global $DB;

        // Check when the user was enrolled in the course.
        $sql = "SELECT MIN(ue.timecreated) as enrolltime
                FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid
                WHERE ue.userid = :userid AND e.courseid = :courseid
                AND ue.status = :active";

        $params = [
            'userid' => $userid,
            'courseid' => $course->id,
            'active' => ENROL_USER_ACTIVE,
        ];

        $enrolment = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);

        // Determine the baseline time: course start date, enrolment time, and — in Upcoming
        // mode — the time the condition was enabled, whichever is latest.
        $baselinetime = max($enrolment ? $enrolment->enrolltime : 0, $course->startdate, $upcomingtime);

        // If user was enrolled after the fromtime threshold, they haven't been enrolled long enough
        // to be considered inactive yet.
        if ($enrolment && !$requact && $baselinetime > $fromtime) {
            return true; // Consider them as "active" (not eligible for inactivity trigger yet).
        }

        switch ($type) {
            case self::INACTIVITY_ACCESS:
                return $this->has_course_access($userid, $course->id, $fromtime, $totime);

            case self::INACTIVITY_COMPLETION:
                return $this->has_activity_completion($userid, $course, $includedactivities, $fromtime, $totime);
        }

        return false;
    }

    /**
     * Checks if user has accessed the course in the given time period.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @param int $fromtime The start time.
     * @param int $totime The end time.
     * @return bool True if user accessed the course.
     */
    protected function has_course_access($userid, $courseid, $fromtime, $totime) {
        global $DB;

        // Check in user last access table.
        $lastaccess = $DB->get_field('user_lastaccess', 'timeaccess', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        if ($lastaccess && $lastaccess >= $fromtime && $lastaccess <= $totime) {
            return true;
        }
        return false;
    }

    /**
     * Checks if user has completed activities in the given time period.
     *
     * @param int $userid The user ID.
     * @param stdClass $course The course object.
     * @param int $includedactivities Which activities to include.
     * @param int $fromtime The start time.
     * @param int $totime The end time.
     * @return bool True if user completed activities.
     */
    protected function has_activity_completion($userid, $course, $includedactivities, $fromtime, $totime) {
        global $DB;

        $completion = new \completion_info($course);
        if (!$completion->is_enabled()) {
            return true;
        }

        $activities = $this->get_relevant_activities($course, $includedactivities);
        if (empty($activities)) {
            return true;
        }

        $activityids = array_keys($activities);
        [$insql, $inparams] = $DB->get_in_or_equal($activityids, SQL_PARAMS_NAMED);

        $params = array_merge([
            'userid' => $userid,
            'fromtime' => $fromtime,
            'totime' => $totime,
        ], $inparams);

        $sql = "SELECT COUNT(id) FROM {course_modules_completion}
                WHERE userid = :userid AND coursemoduleid $insql
                AND completionstate > 0
                AND timemodified >= :fromtime AND timemodified <= :totime";

        return $DB->count_records_sql($sql, $params) > 0;
    }

    /**
     * Checks if user has met completion conditions in the given time period.
     *
     * @param int $userid The user ID.
     * @param stdClass $course The course object.
     * @param int $includedactivities Which activities to include.
     * @param int $fromtime The start time.
     * @param int $totime The end time.
     * @return bool True if user met completion conditions.
     */
    protected function has_completion_conditions_met($userid, $course, $includedactivities, $fromtime, $totime) {
        global $DB;

        $activities = $this->get_relevant_activities($course, $includedactivities);
        if (empty($activities)) {
            return true;
        }
        foreach ($activities as $cm) {
            $completioninfo = new \completion_info($course);
            $completiondata = new \core_completion\cm_completion_details($completioninfo, $cm, $userid, true);
            if (!$completiondata->is_automatic()) {
                $criteriarecord = $DB->get_record('course_completion_criteria', [
                    'course' => $course->id,
                    'moduleinstance' => $cm->id,
                    'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY,
                ]);
                if ($criteriarecord) {
                    $record = $DB->get_record('course_completion_crit_compl', [
                        'criteriaid' => $criteriarecord->id,
                        'userid' => $userid,
                        'course' => $course->id,
                    ]);
                    if (
                        $record &&
                        $record->timecompleted >= $fromtime &&
                        $record->timecompleted <= $totime
                    ) {
                        return true;
                    }
                }
            } else {
                return false;
            }
        }

        return false;
    }
    /**
     * Bulk precheck: return a recordset of userids in the course who match the
     * inactivity condition described by $triggercondition. Replaces the per-user
     * is_user_completed() loop used by the scheduled task.
     *
     * @param \stdClass $course The course object.
     * @param array $triggercondition The condition settings (status, type, inactivityperiod, ...).
     * @return \moodle_recordset|null Recordset of objects with ->userid, or null if not applicable.
     */
    public function get_matching_users_recordset($course, array $triggercondition) {
        global $DB;

        $inactivityperiod = (int) ($triggercondition['inactivityperiod'] ?? 0);
        if ($inactivityperiod <= 0) {
            return null;
        }

        $type = $triggercondition['type'] ?? self::INACTIVITY_ACCESS;
        $includedactivities = (int) ($triggercondition['includedactivities'] ?? self::ACTIVITIES_ALL);
        $requireprior = !empty($triggercondition['requirepreviousactivity']);
        $activityperiod = (int) ($triggercondition['activityperiod'] ?? 0);
        $status = (int) ($triggercondition['status'] ?? 0);
        // In Upcoming mode, inactivity only starts accruing once the condition was enabled.
        $upcomingtime = ($status == self::FUTURE) ? (int) ($triggercondition['upcomingtime'] ?? 0) : 0;

        $now = time();
        $inactivitythreshold = $now - $inactivityperiod;
        $activitythreshold = ($requireprior && $activityperiod > 0) ? ($now - $activityperiod) : 0;

        // Course start and upcomingtime are both scalar constants (not per-user), so they can be
        // folded here before the SQL is built, requiring no change to the SQL's shape.
        $flooredstart = max((int) ($course->startdate ?? 0), $upcomingtime);

        if ($type == self::INACTIVITY_ACCESS) {
            [$sql, $params] = $this->build_access_inactivity_sql(
                $course, $inactivitythreshold, $now, $requireprior, $activitythreshold, $flooredstart
            );
        } else if ($type == self::INACTIVITY_COMPLETION) {
            $cmids = $this->get_relevant_activity_ids_cached($course, $includedactivities);
            if (empty($cmids)) {
                return null;
            }
            [$sql, $params] = $this->build_completion_inactivity_sql(
                $course, $cmids, $inactivitythreshold, $now, $requireprior, $activitythreshold, $flooredstart
            );
        } else {
            return null;
        }

        return $DB->get_recordset_sql($sql, $params);
    }

    /**
     * Cache the relevant course-module ids per (course, includedactivities) for the
     * lifetime of the request, so the scheduled task does not recompute them per user.
     *
     * @param \stdClass $course
     * @param int $includedactivities
     * @return int[] List of cm ids.
     */
    protected function get_relevant_activity_ids_cached($course, $includedactivities) {
        static $cache = [];
        $key = $course->id . '_' . $includedactivities;
        if (!array_key_exists($key, $cache)) {
            $activities = $this->get_relevant_activities($course, $includedactivities);
            $cache[$key] = $activities ? array_keys($activities) : [];
        }
        return $cache[$key];
    }

    /**
     * Build SQL to find active-enrolled users who have not accessed the course
     * since the inactivity threshold. Users enrolled (or course started) after the
     * threshold are excluded so they aren't flagged before their window is up.
     *
     * @param \stdClass $course
     * @param int $inactivitythreshold UNIX time; users with lastaccess >= this are active.
     * @param int $now Current time.
     * @param bool $requireprior Require activity in a prior window.
     * @param int $activitythreshold Lower bound of the prior-activity window.
     * @param int|null $flooredstart Pre-floored course-start scalar (max of course start date and,
     *                                in Upcoming mode, the time the condition was enabled). Falls
     *                                back to the real course start date when null.
     * @return array [$sql, $params]
     */
    protected function build_access_inactivity_sql(
        $course,
        $inactivitythreshold,
        $now,
        $requireprior,
        $activitythreshold,
        $flooredstart = null
    ) {
        $coursestart = $flooredstart !== null ? (int) $flooredstart : (int) ($course->startdate ?? 0);
        $params = [
            'courseid'             => $course->id,
            'active'               => ENROL_USER_ACTIVE,
            'enrolenabled'         => ENROL_INSTANCE_ENABLED,
            'courseid_ul'          => $course->id,
            'inactivitythreshold1' => $inactivitythreshold,
            'inactivitythreshold2' => $inactivitythreshold,
            'coursestart1' => $coursestart,
            'coursestart2' => $coursestart,
        ];

        $sql = "SELECT ue_min.userid
                  FROM (
                      SELECT ue.userid, MIN(ue.timecreated) AS enrolltime
                        FROM {user_enrolments} ue
                        JOIN {enrol} e ON e.id = ue.enrolid AND e.status = :enrolenabled
                        JOIN {user} u ON u.id = ue.userid AND u.deleted = 0
                       WHERE e.courseid = :courseid AND ue.status = :active
                       GROUP BY ue.userid
                  ) ue_min
             LEFT JOIN {user_lastaccess} ul
                    ON ul.userid = ue_min.userid AND ul.courseid = :courseid_ul
                 WHERE CASE WHEN ue_min.enrolltime > :coursestart1
                            THEN ue_min.enrolltime
                            ELSE :coursestart2 END <= :inactivitythreshold1
                   AND (ul.timeaccess IS NULL OR ul.timeaccess = 0 OR ul.timeaccess < :inactivitythreshold2)";

        if ($requireprior && $activitythreshold > 0) {
            // Require that the user DID access the course during the prior window.
            $sql .= " AND EXISTS (
                          SELECT 1 FROM {user_lastaccess} ul2
                           WHERE ul2.userid = ue_min.userid
                             AND ul2.courseid = :courseid_ul2
                             AND ul2.timeaccess >= :priorfrom
                             AND ul2.timeaccess <= :priorto
                      )";
            $params['courseid_ul2'] = $course->id;
            $params['priorfrom']    = $activitythreshold;
            $params['priorto']      = $now;
        }

        return [$sql, $params];
    }

    /**
     * Build SQL to find active-enrolled users who have NOT completed any of the
     * given course-modules since the inactivity threshold.
     *
     * @param \stdClass $course
     * @param int[] $cmids Course-module ids to evaluate.
     * @param int $inactivitythreshold
     * @param int $now
     * @param bool $requireprior
     * @param int $activitythreshold
     * @param int|null $flooredstart Pre-floored course-start scalar (max of course start date and,
     *                                in Upcoming mode, the time the condition was enabled). Falls
     *                                back to the real course start date when null.
     * @return array [$sql, $params]
     */
    protected function build_completion_inactivity_sql($course, array $cmids, $inactivitythreshold, $now,
                                                       $requireprior, $activitythreshold, $flooredstart = null) {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'cm');
        [$insql_ever, $inparams_ever] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'evcm');
        $coursestart = $flooredstart !== null ? (int) $flooredstart : (int) ($course->startdate ?? 0);

        $params = array_merge([
            'courseid'             => $course->id,
            'active'               => ENROL_USER_ACTIVE,
            'enrolenabled'         => ENROL_INSTANCE_ENABLED,
            'inactivitythreshold1' => $inactivitythreshold,
            'inactivitythreshold2' => $inactivitythreshold,
            'coursestart1' => $coursestart,
            'coursestart2' => $coursestart,
        ], $inparams, $inparams_ever);

        $sql = "SELECT ue_min.userid
                  FROM (
                      SELECT ue.userid, MIN(ue.timecreated) AS enrolltime
                        FROM {user_enrolments} ue
                        JOIN {enrol} e ON e.id = ue.enrolid AND e.status = :enrolenabled
                        JOIN {user} u ON u.id = ue.userid AND u.deleted = 0
                       WHERE e.courseid = :courseid AND ue.status = :active
                       GROUP BY ue.userid
                  ) ue_min
                 WHERE CASE WHEN ue_min.enrolltime > :coursestart1
                            THEN ue_min.enrolltime
                            ELSE :coursestart2 END <= :inactivitythreshold1
                   AND NOT EXISTS (
                       SELECT 1 FROM {course_modules_completion} cmc
                        WHERE cmc.userid = ue_min.userid
                          AND cmc.coursemoduleid $insql
                          AND cmc.completionstate > 0
                          AND cmc.timemodified >= :inactivitythreshold2
                   )";

        if ($requireprior && $activitythreshold > 0) {
            // Require some completion in the prior window.
            [$insql2, $inparams2] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'pcm');
            $sql .= " AND EXISTS (
                          SELECT 1 FROM {course_modules_completion} cmc2
                           WHERE cmc2.userid = ue_min.userid
                             AND cmc2.coursemoduleid $insql2
                             AND cmc2.completionstate > 0
                             AND cmc2.timemodified >= :priorfrom
                             AND cmc2.timemodified <= :priorto
                      )";
            $params = array_merge($params, $inparams2, [
                'priorfrom' => $activitythreshold,
                'priorto'   => $now,
            ]);
        }

        return [$sql, $params];
    }

    /**
     * Gets relevant activities based on the inclusion criteria.
     *
     * @param stdClass $course The course object.
     * @param int $includedactivities Which activities to include.
     * @return array Array of course modules.
     */
    protected function get_relevant_activities($course, $includedactivities) {
        global $DB;
        $completion = new \completion_info($course);
        $activities = $completion->get_activities();

        if ($includedactivities == self::ACTIVITIES_COMPLETION_RELEVANT) {
            // Get activities that are part of course completion criteria.
            $sql = "SELECT DISTINCT cc.moduleinstance, cc.module
                    FROM {course_completion_criteria} cc
                    WHERE cc.course = :courseid
                    AND cc.criteriatype = :criteriatype";

            $params = [
                'courseid' => $course->id,
                'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY,
            ];

            $completioncriteria = $DB->get_records_sql($sql, $params);

            // Create a lookup array for faster checking.
            $criteriaactivities = [];
            foreach ($completioncriteria as $criteria) {
                $criteriaactivities[$criteria->moduleinstance] = true;
            }

            // Filter activities to only include those that are part of course completion.
            $activities = array_filter($activities, function ($cm) use ($criteriaactivities, $completion) {
                // Check if completion is enabled for this activity.
                if (!$completion->is_enabled($cm) || $cm->completion <= 0) {
                    return false;
                }

                // Check if this activity is in the course completion criteria.
                return isset($criteriaactivities[$cm->id]);
            });
        }
        return $activities;
    }
}
