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

namespace pulseaction_notification;

/**
 * Tests for recompletion notification action.
 *
 * @package   pulseaction_notification
 * @copyright 2023, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class recompletion_test extends \advanced_testcase {
    /**
     * Course instance data.
     *
     * @var \stdClass
     */
    public $course;

    /**
     * Module intro content.
     *
     * @var string
     */
    public $intro = 'Pulse test notification';

    /**
     * Set up testing cases.
     *
     * @return void
     */
    public function setUp(): void {
        global $CFG;
        parent::setUp();

        if (!\core_plugin_manager::instance()->get_plugin_info('local_recompletion')) {
            $this->markTestSkipped('Requires local_recompletion plugin.');
        }

        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->mtrace_wrapper = 'mod_pulse_remove_mtrace_output';
        $this->course = $this->getDataGenerator()->create_course();
    }
    /**
     * Test local recompletion resets the user availbility in pulse.
     *
     * @covers ::pulseaction_notification_recompletion_reset
     * @return void
     */
    public function test_pulseaction_notification_recompletion_reset(): void {
        global $DB;

        $students[] = $this->getDataGenerator()->create_and_enrol($this->course, 'student', [
            'email' => 'student1@test.com',
            'username' => 'student1',
        ]);

        $students[] = $this->getDataGenerator()->create_and_enrol($this->course, 'student', [
            'email' => 'student2@test.com',
            'username' => 'student2',
        ]);

        $course2 = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('pulse', [
            'course' => $course2->id, 'intro' => $this->intro,
        ], []);

        $this->getDataGenerator()->enrol_user($students[0]->id, $course2->id);
        $this->getDataGenerator()->enrol_user($students[1]->id, $course2->id);

        $data = [
            'reference' => 'test_recompletion',
            'title' => 'Recompletion Test Template',
            'condition' => 'enrolment',
        ];
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_pulse')->create_automation_template($data);

        $templateid = $DB->get_field('pulse_autotemplates', 'id', ['reference' => 'test_recompletion']);

        $notification = [
            'templateid' => $templateid,
            'actionstatus' => 1,
            'notifyinterval' => json_encode(['interval' => 1]),
            'recipients' => [],
            'cc' => json_encode([]),
            'bcc' => json_encode([]),
        ];

        $roles = get_users_roles(\context_course::instance($this->course->id), [$students[0]->id]);
        foreach ($roles as $role) {
            $notification['recipients'][] = current($role)->roleid;
        }

        $notification['recipients'] = json_encode($notification['recipients']);

        $DB->insert_record('pulseaction_notification', $notification);

        $data['templateid'] = $templateid;
        $data['courseid'] = $this->course->id;
        $data['reference'] = 'test_recompletion_instance';
        $instanceid = $this->getDataGenerator()->get_plugin_generator('mod_pulse')->create_automation_instance($data);

        // Instance id fetch for debug.
        $instanceid = $DB->get_field('pulse_autotemplates_ins', 'instanceid', ['insreference' => $data['reference']]);

        $DB->insert_record('pulseaction_notification_ins', [
            'instanceid' => $instanceid,
        ]);

        foreach ($students as $student) {
            $userid = $student->id;
            \mod_pulse\automation\instances::create($instanceid)->trigger_action($userid, null, true);
        }

        $this->preventResetByRollback();
        $slink = $this->redirectMessages();
        // SEnd all the notifications.
        \pulseaction_notification\schedule::instance()->send_scheduled_notification();

        // Confirm the user enrolment in the course 1.
        $selectsql = 'userid IN (:userid1, :userid2) AND instanceid = :instanceid AND status = 3';
        $params = ['userid1' => $students[0]->id, 'userid2' => $students[1]->id, 'instanceid' => $instanceid];

        $records = $DB->get_records_select('pulseaction_notification_sch', $selectsql, $params);
        $this->assertCount(2, $records);

        // Reset the completion.
        $config = new \stdClass();
        $config->pulse = LOCAL_RECOMPLETION_DELETE;
        foreach ($students as $student) {
            \mod_pulse\helper::local_recompletion_reset($student->id, $this->course, $config);
        }

        // Confirm the user enrolment in the course 1.
        $selectsql = 'userid IN (:userid1, :userid2) AND instanceid = :instanceid AND status = 3';
        $params = ['userid1' => $students[0]->id, 'userid2' => $students[1]->id, 'instanceid' => $instanceid];

        $records = $DB->get_records_select('pulseaction_notification_sch', $selectsql, $params);
        $this->assertCount(0, $records);
    }
}
