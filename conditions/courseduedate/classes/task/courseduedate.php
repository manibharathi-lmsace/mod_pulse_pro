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
        mtrace("Processing instance {$instance->id} for course {$instance->courseid}");

        // Get all enrolled users in the course.
        $context = \context_course::instance($instance->courseid);
        $enrolledusers = get_enrolled_users($context, '', 0, 'u.id, u.username');

        if (empty($enrolledusers)) {
            mtrace("No enrolled users found for course {$instance->courseid}");
            return;
        }

        $conditionform = new \pulsecondition_courseduedate\conditionform();
        $instancedata = \mod_pulse\automation\instances::create($instance->instanceid)->get_instance_data();

        $alreadynotified = $this->get_already_notified_users($instance->instanceid);

        $triggeredcount = 0;

        foreach ($enrolledusers as $user) {
            if (isset($alreadynotified[$user->id])) {
                continue;
            }
            // Check if this user meets the course due date condition.
            $condition = $conditionform->is_user_completed($instancedata, $user->id);
            if ($condition) {
                // Trigger the automation instance for this user.
                $conditionform->trigger_instance($instance->instanceid, $user->id, null);
                $triggeredcount++;
                mtrace("Triggered automation for user {$user->username} (ID: {$user->id})");
            }
        }

        mtrace("Triggered automation for {$triggeredcount} users in instance {$instance->id}");
    }

    /**
     * Get a list of user IDs that have already been notified for a given automation instance.
     *
     * @param int $instanceid Automation instance id
     * @return array userid => true
     */
    private function get_already_notified_users(int $instanceid): array {
        global $DB;

        $notificationinstanceid = $DB->get_field(
            'pulseaction_notification_ins',
            'id',
            ['instanceid' => $instanceid]
        );

        if (!$notificationinstanceid) {
            return [];
        }

        $userids = $DB->get_fieldset_select(
            'pulseaction_notification_sch',
            'userid',
            'instanceid = :instanceid AND status = :sent AND notifiedtime > 0',
            [
                'instanceid' => $notificationinstanceid,
                'sent' => \pulseaction_notification\notification::STATUS_SENT,
            ]
        );

        return $userids ? array_flip($userids) : [];
    }
}
