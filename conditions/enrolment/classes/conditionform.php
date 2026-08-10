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
 * Conditions - Pulse condition class for the "Enrolment Completion".
 *
 * @package   pulsecondition_enrolment
 * @copyright 2023, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace pulsecondition_enrolment;

use mod_pulse\automation\condition_base;

/**
 * Pulse automation conditions form and basic details.
 */
class conditionform extends \mod_pulse\automation\condition_base {
    /**
     * Verify the user is enroled in the course which is configured in the conditions for the notification.
     *
     * @param stdclass $instancedata
     * @param int $userid
     * @param \completion_info|null $completion
     * @return bool
     */
    public function is_user_completed($instancedata, int $userid, ?\completion_info $completion = null) {
        $courseid = $instancedata->courseid;
        $context = \context_course::instance($courseid);

        return is_enrolled($context, $userid);
    }

    /**
     * Include data to action.
     *
     * @param array $option
     * @return void
     */
    public function include_condition(&$option) {
        $option['enrolment'] = get_string('enrolment', 'pulsecondition_enrolment');
    }

    /**
     * Loads the form elements for activity condition in template.
     *
     * @param MoodleQuickForm $mform The form object.
     * @param object $forminstance The form instance.
     */
    public function load_template_form(&$mform, $forminstance) {
        global $PAGE;

        $completionstr = get_string('enrolment', 'pulsecondition_enrolment');
        $mform->addElement('select', 'condition[enrolment][status]', $completionstr, $this->get_options());
        $mform->addHelpButton('condition[enrolment][status]', 'enrolment', 'pulsecondition_enrolment');
    }

    /**
     * Loads the form elements for enrolment condition.
     *
     * @param MoodleQuickForm $mform The form object.
     * @param object $forminstance The form instance.
     */
    public function load_instance_form(&$mform, $forminstance) {

        $completionstr = get_string('enrolment', 'pulsecondition_enrolment');
        $mform->addElement('select', 'condition[enrolment][status]', $completionstr, $this->get_options());
        $mform->addHelpButton('condition[enrolment][status]', 'enrolment', 'pulsecondition_enrolment');
    }

    /**
     * SQL filter for the in-form preview: user has an active enrolment in the instance course.
     *
     * @param array $config
     * @param object $instancedata
     * @return array
     */
    public function candidate_filter_sql(array $config, $instancedata): array {
        if (empty($config['status']) || empty($instancedata->courseid)) {
            return ['join' => '', 'where' => '', 'params' => []];
        }
        $now = time();
        $params = [
            'pchenr_cid' => (int) $instancedata->courseid,
            'pchenr_now1' => $now,
            'pchenr_now2' => $now,
        ];
        $upcoming = '';
        if (!empty($config['upcomingtime'])) {
            $upcoming = ' AND ue.timecreated >= :pchenr_upc';
            $params['pchenr_upc'] = (int) $config['upcomingtime'];
        }
        $where = "EXISTS (SELECT 1 FROM {user_enrolments} ue
                            JOIN {enrol} e ON e.id = ue.enrolid
                           WHERE ue.userid = u.id AND ue.status = 0
                             AND e.courseid = :pchenr_cid
                             AND (ue.timestart = 0 OR ue.timestart <= :pchenr_now1)
                             AND (ue.timeend = 0 OR ue.timeend > :pchenr_now2) $upcoming)";
        return ['join' => '', 'where' => $where, 'params' => $params];
    }

    /**
     * Returns the timestamp when the user was enrolled in the course.
     *
     * @param int $userid The user ID.
     * @param object $instancedata The automation instance data.
     * @return int The Unix timestamp of the user's earliest enrolment.
     */
    public function get_condition_satisfied_time(int $userid, $instancedata) {
        global $DB;

        $courseid = $instancedata->courseid;

        $sql = "SELECT MIN(ue.timecreated) AS enroltime
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid AND e.courseid = :courseid";
        $enroltime = $DB->get_field_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);

        return $enroltime ? (int) $enroltime : time();
    }

    /**
     * User enrolled event observer. Triggeres the instance actions when user enrolled in the course.
     *
     * @param stdclass $eventdata
     * @return void
     */
    public static function user_enrolled($eventdata) {

        $data = $eventdata->get_data();
        $courseid = $data['courseid'];
        $relateduserid = $data['relateduserid'] ?: $data['userid'];

        self::trigger_user_enrolled($courseid, $relateduserid);
    }

    /**
     * Trigger the actions for the instances that are configured user enrolment.
     *
     * @param int $courseid Course id.
     * @param int $relateduserid User id.
     *
     * @return bool
     */
    protected static function trigger_user_enrolled(int $courseid, int $relateduserid) {
        global $DB;

        // Trigger the instances, this will trigger its related actions for this user.
        $sql = "SELECT ai.id, ai.id AS instanceid
                  FROM {pulse_autoinstances} ai
                  JOIN {pulse_autotemplates} pat ON pat.id = ai.templateid
             LEFT JOIN {pulse_condition_overrides} co ON co.instanceid = ai.id AND co.triggercondition = 'enrolment'
                 WHERE ai.courseid = :courseid
                   AND (co.status > 0 OR (co.status IS NULL AND ai.templateid IN (
                           SELECT c.templateid FROM {pulse_condition} c WHERE c.triggercondition = 'enrolment'
                       )
                   ))";

        $params = ['courseid' => $courseid];
        $instances = $DB->get_records_sql($sql, $params);
        foreach ($instances as $key => $instance) {
            $condition = (new self())->trigger_instance($instance->instanceid, $relateduserid, null, true);
        }
        return true;
    }

    /**
     * User enrolled event observer. Triggeres the instance actions when user enrolled in the course.
     *
     * @param \core\event\user_enrolment_created $event
     * @return void
     */
    public static function user_enrolment_created($event) {
        $userid = $event->relateduserid;
        $courseid = $event->courseid;
        $context = \context_course::instance($courseid);

        if (get_user_roles($context, $userid, false)) {
            return;
        }

        self::set_recently_enrolled_userid($userid, 'add');
    }

    /**
     * Maintain the recently enrolled users waiting for a role assignment.
     *
     * @param int $userid The user ID.
     * @param string $action The action to perform: 'add' to register, 'pop' to consume.
     * @return bool returns true if the user was registered and consumed, false otherwise.
     */
    protected static function set_recently_enrolled_userid(int $userid, string $action): bool {
        static $enrolled = [];

        if ($action === 'add') {
            $enrolled[$userid] = true;
            return true;
        }

        if (isset($enrolled[$userid])) {
            unset($enrolled[$userid]);
            return true;
        }

        return false;
    }

    /**
     * User assigned in the role.
     *
     * Verify the event related user is enrolled in the course recently, with recent stored user.
     * Trigger the schedule instance if recent enrolled user.
     *
     * @param \core\event\role_assigned $event
     * @return void
     */
    public static function user_role_assigned($event) {
        $context = $event->get_context();

        // Ignore role assignments that are not at the course context level.
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $userid = $event->relateduserid;

        if (self::set_recently_enrolled_userid($userid, 'pop')) {
            self::trigger_user_enrolled($context->instanceid, $userid);
        }
    }
}
