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

namespace pulsecondition_userinactivity;

/**
 * Tests for the bulk set-based SQL used by the user inactivity scheduled task.
 *
 * @package   pulsecondition_userinactivity
 * @copyright 2026, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \pulsecondition_userinactivity\conditionform::get_matching_users_recordset
 */
final class conditionform_test extends \advanced_testcase {

    /**
     * Set up testing cases.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Build a minimal course object with a course start date well in the past, so
     * enrolment-baseline flooring never masks the behaviour under test.
     *
     * @return \stdClass
     */
    protected function create_course_with_old_startdate(): \stdClass {
        $course = $this->getDataGenerator()->create_course(['startdate' => time() - (10 * YEARSECS)]);
        return $course;
    }

    /**
     * Enrol a user and backdate the enrolment's timecreated so it clears the
     * "not enrolled long enough" baseline gate.
     *
     * @param \stdClass $course
     * @param int $status ENROL_USER_ACTIVE or ENROL_USER_SUSPENDED.
     * @return \stdClass The enrolled user.
     */
    protected function create_backdated_student(\stdClass $course, int $status = ENROL_USER_ACTIVE): \stdClass {
        global $DB;

        static $counter = 0;
        $counter++;

        $user = $this->getDataGenerator()->create_user(['username' => 'uinactivity_student' . $counter]);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'manual', 0, 0, $status);

        $DB->set_field('user_enrolments', 'timecreated', time() - (5 * YEARSECS), [
            'userid' => $user->id,
        ]);

        return $user;
    }

    /**
     * Fetch the matching userids as a plain array, closing the recordset.
     *
     * @param conditionform $conditionform
     * @param \stdClass $course
     * @param array $triggercondition
     * @return int[]
     */
    protected function matching_userids(conditionform $conditionform, \stdClass $course, array $triggercondition): array {
        $rs = $conditionform->get_matching_users_recordset($course, $triggercondition);
        if ($rs === null) {
            return [];
        }
        $userids = [];
        foreach ($rs as $record) {
            $userids[] = (int) $record->userid;
        }
        $rs->close();
        return $userids;
    }

    /**
     * A normal, active, long-enrolled student who has never accessed the course
     * must be flagged as inactive.
     */
    public function test_inactive_active_user_matches(): void {
        $course = $this->create_course_with_old_startdate();
        $student = $this->create_backdated_student($course);

        $triggercondition = [
            'status' => 1,
            'type' => conditionform::INACTIVITY_ACCESS,
            'inactivityperiod' => DAYSECS,
        ];

        $userids = $this->matching_userids(new conditionform(), $course, $triggercondition);

        $this->assertContains($student->id, $userids);
    }

    /**
     * A student who accessed the course within the inactivity window must not match.
     */
    public function test_recently_active_user_does_not_match(): void {
        global $DB;

        $course = $this->create_course_with_old_startdate();
        $student = $this->create_backdated_student($course);

        $DB->insert_record('user_lastaccess', (object) [
            'userid' => $student->id,
            'courseid' => $course->id,
            'timeaccess' => time(),
        ]);

        $triggercondition = [
            'status' => 1,
            'type' => conditionform::INACTIVITY_ACCESS,
            'inactivityperiod' => DAYSECS,
        ];

        $userids = $this->matching_userids(new conditionform(), $course, $triggercondition);

        $this->assertNotContains($student->id, $userids);
    }

    /**
     * A deleted user account must never be returned, even if a stale active
     * enrolment row still exists for them (regression test: the bulk SQL used
     * to query user_enrolments/enrol directly without joining {user}, unlike
     * the get_enrolled_users() call it replaced).
     */
    public function test_deleted_user_excluded(): void {
        global $DB;

        $course = $this->create_course_with_old_startdate();
        $student = $this->create_backdated_student($course);

        $DB->set_field('user', 'deleted', 1, ['id' => $student->id]);

        $triggercondition = [
            'status' => 1,
            'type' => conditionform::INACTIVITY_ACCESS,
            'inactivityperiod' => DAYSECS,
        ];

        $userids = $this->matching_userids(new conditionform(), $course, $triggercondition);

        $this->assertNotContains($student->id, $userids);
    }

    /**
     * A user enrolled only through a disabled enrolment method instance must not
     * be treated as enrolled (regression test: matches get_enrolled_users()
     * semantics, which excludes disabled enrolment instances).
     */
    public function test_disabled_enrol_instance_excluded(): void {
        global $DB;

        $course = $this->create_course_with_old_startdate();
        $student = $this->create_backdated_student($course);

        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, [
            'courseid' => $course->id,
            'enrol' => 'manual',
        ]);

        $triggercondition = [
            'status' => 1,
            'type' => conditionform::INACTIVITY_ACCESS,
            'inactivityperiod' => DAYSECS,
        ];

        $userids = $this->matching_userids(new conditionform(), $course, $triggercondition);

        $this->assertNotContains($student->id, $userids);
    }

    /**
     * A user with a suspended (not active) enrolment must not match.
     */
    public function test_suspended_enrolment_excluded(): void {
        $course = $this->create_course_with_old_startdate();
        $student = $this->create_backdated_student($course, ENROL_USER_SUSPENDED);

        $triggercondition = [
            'status' => 1,
            'type' => conditionform::INACTIVITY_ACCESS,
            'inactivityperiod' => DAYSECS,
        ];

        $userids = $this->matching_userids(new conditionform(), $course, $triggercondition);

        $this->assertNotContains($student->id, $userids);
    }

    /**
     * The activity-completion variant must respect the same deleted-user exclusion
     * as the access variant (both builder methods were patched).
     */
    public function test_deleted_user_excluded_for_completion_type(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course([
            'startdate' => time() - (10 * YEARSECS),
            'enablecompletion' => 1,
        ]);
        $student = $this->create_backdated_student($course);

        $DB->set_field('user', 'deleted', 1, ['id' => $student->id]);

        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $triggercondition = [
            'status' => 1,
            'type' => conditionform::INACTIVITY_COMPLETION,
            'includedactivities' => conditionform::ACTIVITIES_ALL,
            'inactivityperiod' => DAYSECS,
        ];

        $userids = $this->matching_userids(new conditionform(), $course, $triggercondition);

        $this->assertNotContains($student->id, $userids);
    }
}
