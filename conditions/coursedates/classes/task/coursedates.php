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
 * Course dates check scheduled task.
 *
 * @package   pulsecondition_coursedates
 * @copyright 2025, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulsecondition_coursedates\task;

/**
 * Scheduled task to check course dates and trigger automation instances.
 */
class coursedates extends \core\task\scheduled_task {
    /**
     * Get the name of the task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskname', 'pulsecondition_coursedates');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        mtrace('Starting course dates automation check...');

        // Get all active automation instances that use course dates condition.
        $sql = "SELECT ai.*, ai.id as instanceid
                FROM {pulse_autoinstances} ai
                JOIN {pulse_autotemplates} pat ON pat.id = ai.templateid
                LEFT JOIN {pulse_condition_overrides} co ON co.instanceid = ai.id AND co.triggercondition = 'coursedates'
                WHERE ai.status = 1
                AND (co.status > 0 OR (co.status IS NULL AND ai.templateid IN (
                    SELECT c.templateid FROM {pulse_condition} c WHERE c.triggercondition = 'coursedates' AND c.status > 0
                )))";

        $instances = $DB->get_records_sql($sql);

        if (empty($instances)) {
            mtrace('No active course dates automation instances found.');
            return;
        }

        mtrace('Found ' . count($instances) . ' active course dates automation instances.');

        foreach ($instances as $instance) {
            $this->process_instance($instance);
        }

        mtrace('Course dates automation check completed.');
    }

    /**
     * Process a single automation instance.
     *
     * @param object $instance The automation instance
     */
    private function process_instance($instance) {
        mtrace("Processing instance {$instance->id} for course {$instance->courseid}");

        $conditionform = new \pulsecondition_coursedates\conditionform();
        $instanceobj   = \mod_pulse\automation\instances::create($instance->id);
        $instancedata  = $instanceobj->get_instance_data();

        // Same for every user, so compute once outside the loop below.
        $targetdate = $conditionform->get_coursedates_with_delay($instancedata);
        $datetype   = $instancedata->condition['coursedates']['type'] ?? 'start';

        if (!$targetdate) {
            mtrace("No {$datetype} date set for course {$instance->courseid}");
            return;
        }

        $alreadynotified = $this->get_already_notified_users($instance->id);

        // Users enrolled on or before the (delay-adjusted) course date.
        $users = $conditionform->get_matching_users_recordset($instance->courseid, $targetdate, $datetype);

        $triggeredcount = 0;
        foreach ($users as $user) {
            if (isset($alreadynotified[$user->id])) {
                continue;
            }
            $instanceobj->trigger_action((int)$user->id, null, false);
            $triggeredcount++;
        }
        $users->close();

        mtrace("Triggered automation for {$triggeredcount} users in instance {$instance->id}");
    }

    /**
     * Get a list of user IDs that have already been notified for a given automation instance.
     *
     * @param int $instanceid Automation instance id.
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
