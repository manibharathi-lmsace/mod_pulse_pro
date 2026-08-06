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

namespace pulsecondition_coursedates;

/**
 * Tests for the bulk set-based SQL used by the course dates scheduled task.
 *
 * @package   pulsecondition_coursedates
 * @copyright 2026, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \pulsecondition_coursedates\conditionform::get_matching_users_recordset
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
     * Enrol a user and pin the enrolment's timecreated to a known value.
     *
     * @param \stdClass $course
     * @param int $timecreated
     * @param int $status ENROL_USER_ACTIVE or ENROL_USER_SUSPENDED.
     * @return \stdClass The enrolled user.
     */
    protected function create_student_enrolled_at(\stdClass $course, int $timecreated, int $status = ENROL_USER_ACTIVE): \stdClass {
        global $DB;

        static $counter = 0;
        $counter++;

        $user = $this->getDataGenerator()->create_user(['username' => 'coursedates_student' . $counter]);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'manual', 0, 0, $status);

        $DB->set_field('user_enrolments', 'timecreated', $timecreated, ['userid' => $user->id]);

        return $user;
    }

    /**
     * Fetch the matching userids as a plain array, closing the recordset.
     *
     * @param conditionform $conditionform
     * @param int $courseid
     * @param int $targetdate
     * @param string $datetype
     * @return int[]
     */
    protected function matching_userids(conditionform $conditionform, int $courseid, int $targetdate, string $datetype): array {
        $rs = $conditionform->get_matching_users_recordset($courseid, $targetdate, $datetype);
        $userids = [];
        foreach ($rs as $record) {
            $userids[] = (int) $record->id;
        }
        $rs->close();
        return $userids;
    }

    /**
     * A user enrolled on or before the target date must match.
     */
    public function test_user_enrolled_before_targetdate_matches(): void {
        $course = $this->getDataGenerator()->create_course();
        $targetdate = time();
        $student = $this->create_student_enrolled_at($course, $targetdate - DAYSECS);

        $userids = $this->matching_userids(new conditionform(), $course->id, $targetdate, 'start');

        $this->assertContains($student->id, $userids);
    }

    /**
     * A user enrolled after the target date must not match.
     */
    public function test_user_enrolled_after_targetdate_does_not_match(): void {
        $course = $this->getDataGenerator()->create_course();
        $targetdate = time();
        $student = $this->create_student_enrolled_at($course, $targetdate + DAYSECS);

        $userids = $this->matching_userids(new conditionform(), $course->id, $targetdate, 'start');

        $this->assertNotContains($student->id, $userids);
    }

    /**
     * A deleted user account must never be returned, even if a stale active
     * enrolment row still exists for them.
     */
    public function test_deleted_user_excluded(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $targetdate = time();
        $student = $this->create_student_enrolled_at($course, $targetdate - DAYSECS);

        $DB->set_field('user', 'deleted', 1, ['id' => $student->id]);

        $userids = $this->matching_userids(new conditionform(), $course->id, $targetdate, 'start');

        $this->assertNotContains($student->id, $userids);
    }

    /**
     * A user enrolled only through a disabled enrolment method instance must not
     * be treated as enrolled (regression test: matches get_enrolled_users()
     * semantics, which excludes disabled enrolment instances).
     */
    public function test_disabled_enrol_instance_excluded(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $targetdate = time();
        $student = $this->create_student_enrolled_at($course, $targetdate - DAYSECS);

        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, [
            'courseid' => $course->id,
            'enrol' => 'manual',
        ]);

        $userids = $this->matching_userids(new conditionform(), $course->id, $targetdate, 'start');

        $this->assertNotContains($student->id, $userids);
    }

    /**
     * For the 'end' date type, a suspended enrolment must not match (matches
     * the existing ue.status = 0 clause applied only for 'end').
     */
    public function test_end_datetype_excludes_suspended_enrolment(): void {
        $course = $this->getDataGenerator()->create_course();
        $targetdate = time();
        $student = $this->create_student_enrolled_at($course, $targetdate - DAYSECS, ENROL_USER_SUSPENDED);

        $userids = $this->matching_userids(new conditionform(), $course->id, $targetdate, 'end');

        $this->assertNotContains($student->id, $userids);
    }
}
