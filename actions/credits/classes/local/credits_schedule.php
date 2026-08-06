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
 * Credits pulse action schedule class.
 *
 * @package    pulseaction_credits
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulseaction_credits\local;

use stdClass;
use mod_pulse\local\automation\schedule;
use mod_pulse\automation\instances;

/**
 * Credits pulse action schedule class , handles scheduling of credit allocations.
 */
class credits_schedule extends schedule {
    /**
     * Get the status string for completed schedules.
     *
     * @return string
     */
    protected static function get_completed_statusstring(): string {
        return get_string('creditsapplied', 'pulseaction_credits');
    }

    /**
     * Get the action instance data.
     *
     * @return stdClass|false
     */
    protected function get_actioninstance_data() {
        global $DB;

        return $DB->get_record('pulseaction_credits_ins', ['id' => $this->instanceid]);
    }

    /**
     * Get the schedule table name.
     *
     * @return string Table name.
     */
    protected function get_schedule_tablename(): string {
        return 'pulseaction_credits_sch';
    }

    /**
     * Get the action name.
     *
     * @return string Action name.
     */
    public function get_action_name(): string {
        return 'credits';
    }

    /**
     * Update the data structure if needed.
     *
     * @param stdclass $data The data to update.
     * @return stdclass The updated data.
     */
    public function update_data_structure($data) {

        $data->notifyinterval = is_string($data->notifyinterval)
            ? json_decode($data->notifyinterval, true) : $data->notifyinterval;

        return $data;
    }

    /**
     * Create schedule for the instance.
     *
     * @param bool $newenrolment Is the schedule for new enrolments.
     * @param int|bool $newuserid User id.
     * @param bool $newfrequency Frequency of the schedule.
     * @return bool Success status
     */
    public function create_schedule_forinstance($newenrolment = false, $newuserid = null, $newfrequency = false): bool {
        // Generate the notification instance data.
        if (empty($this->instancedata)) {
            $this->create_instance_data();
        }

        // Confirm the instance is not disabled.
        if (!$this->instancedata->status || !$this->actiondata->actionstatus) {
            $this->disable_schedules();
            return false;
        }

        // Course context.
        $context = \context_course::instance($this->instancedata->courseid);

        // Roles to receive the credits allocations.
        $roles = $this->actiondata->recipients;

        if (empty($roles)) {
            // No roles are defined to receive credits. Remove the schedules for this instance.
            $this->remove_schedules();
            return true; // No roles are defined to receive credits. Break the schedule creation.
        }

        // Get the users for this recipients roles.
        $users = $this->get_users_withroles($roles, $context);

        // Schedule for the new user or for specified user, Fetch the user record from recipient roles.
        if ($newuserid) {
            $users = array_filter($users, function ($user) use ($newuserid) {
                return $newuserid == $user->id;
            });
        }

        foreach ($users as $userid => $user) {
            $createschedulecheck = $this->verify_create_schedule_foruser($user->id);

            if ($createschedulecheck === false) {
                continue;
            }

            $this->create_schedule_foruser($user->id, null, 0, null, $newenrolment, false, (bool) $newfrequency);
        }

        return true;
    }

    /**
     * Check if the user has reached the suppress conditions for the instance.
     *
     * @param stdClass $actiondata Action data
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @param stdClass|null $schedule Schedule data
     * @return bool True if suppress conditions are reached
     */
    public function is_suppress_reached(stdClass $actiondata, int $userid, int $courseid, ?stdClass $schedule): bool {
        // Credits action doesn't use suppress conditions currently.
        // Can be extended in the future for maximum credits per user, etc.
        return false;
    }

    /**
     * Get the schedule record by ID.
     *
     * @param int $recordid Schedule record ID
     * @return stdClass|null The schedule record or null if not found
     */
    public function get_schedule_record($recordid): ?stdClass {
        global $DB;
        return $DB->get_record('pulseaction_credits_sch', ['id' => $recordid]);
    }

    /**
     * Create a credit allocation schedule for the user.
     *
     * @param int $userid User ID
     * @param string $lastrun Last run time
     * @param int $notifycount Notification count
     * @param int|null $expectedruntime Timestamp of the time to run
     * @param bool $isnewuser Is this a new user
     * @param bool $newschedule Is this a new schedule
     * @param bool $newfrequency Has frequency changed
     * @param int|bool $previousbased Previous schedule ID to base credits on
     *
     * @return bool|int ID of the created schedule or bool
     */
    public function create_schedule_foruser(
        $userid,
        $lastrun = '',
        $notifycount = 0,
        $expectedruntime = null,
        $isnewuser = false,
        $newschedule = false,
        $newfrequency = false,
        $previousbased = false
    ): bool|int {

        if (empty($this->instancedata)) {
            $this->create_instance_data();
        }

        // Instance should be configured with any of conditions. Otherwise stop creating instance.
        // Verify the user passed the instance condition.
        if (
            $this->actiondata->actionstatus == 0 || !$this->verfiy_instance_contains_condition()
            || !instances::create($this->actiondata->instanceid)->find_user_completion_conditions(
                $this->instancedata->condition,
                $this->instancedata,
                $userid,
                $isnewuser
            )
            || ($newschedule
                && $this->is_suppress_reached($this->actiondata, $userid, $this->instancedata->courseid, null))
        ) {
            $this->disable_user_schedule($userid);
            return true;
        }

        // Credit allocation interval is once per user, check if already allocated to the user.
        if ($this->actiondata->notifyinterval['interval'] == self::INTERVALONCE && $this->is_useraction_completed($userid)) {
            return true;
        }

        $lastrun = $lastrun ?: $this->find_last_action_runtime($userid);

        // Generate the schedule record.
        $data = $this->generate_schedule_record($userid);

        // Add credits-specific fields.
        $data['credits'] = $this->actiondata->credits ?? 0;

        // Use the override credits from previous schedule if applicable.
        if ($previousbased != null && $previousbased !== false) {
            $previusschdule = $this->get_schedule_record($previousbased);
            $data['credits'] = $previusschdule->credits ?: $data['credits'];
            $data['parentsch'] = $previousbased;
        }

        $data['allocationmethod'] = $this->actiondata->allocationmethod ?? credits::ALLOCATION_ADD;

        // Find the next run time.
        $nextrun = $this->generate_the_scheduletime($userid, $lastrun, $expectedruntime);

        // Include the next run to schedule.
        $data['scheduletime'] = $nextrun;

        $scheduleid = $this->insert_schedule($data, $newschedule, $newfrequency);

        return $scheduleid;
    }

    /**
     * Hook to perform any action before update the existing schedule.
     *
     * Update the credit allocation if any overrides are performed earlier for this schedule.
     *
     * @param array $data Data to update.
     * @param stdclass $record Existing schedule record.
     * @param bool $newschedule
     * @param int $newfrequency
     * @return void
     */
    protected function hook_before_update_schedule(array &$data, $record, $newschedule = false, $newfrequency = false) {
        global $DB;

        // Check if an override exists for this schedule.
        $override = $DB->get_record('pulseaction_credits_override', ['scheduleid' => $record->id]);

        if ($override) {
            // Apply the override credits to the update data.
            $data['credits'] = $override->overridecredit;
        }
    }

    /**
     * Override to generate schedule record with credits-specific fields.
     *
     * @param int $userid ID of the user to create schedule.
     * @return array Record to insert into schedule.
     */
    protected function generate_schedule_record(int $userid) {
        $record = parent::generate_schedule_record($userid);

        // Add credits-specific fields.
        $record['credits'] = $this->actiondata->credits ?? 0;
        $record['allocationmethod'] = $this->actiondata->allocationmethod ?? credits::ALLOCATION_ADD;
        $record['suppressreached'] = 0;
        $record['errorlog'] = '';

        return $record;
    }

    /**
     * Verify if we should create a schedule for this user.
     *
     * @param int $userid User ID
     * @return bool True if schedule should be created
     */
    public function verify_create_schedule_foruser($userid): bool {
        // Check if credits are enabled for this instance.
        if (empty($this->actiondata->actionstatus)) {
            return false;
        }

        // Check if credits is valid.
        if (empty($this->actiondata->credits) || $this->actiondata->credits < 0) {
            return false;
        }

        return true;
    }

    /**
     * Get the base datetime for schedule calculations.
     * Returns enrollment time if relative, fixed date if configured, otherwise current time.
     *
     * @param int $userid User ID
     * @return \DateTime Base datetime for scheduling
     */
    protected function get_base_datetime($userid) {
        // Check if configured for relative base date - enrollment-based.
        if (isset($this->actiondata->basedatetype) && $this->actiondata->basedatetype == credits::BASEDATERELATIVE) {
            return $this->get_enrollment_datetime($userid);
        } else {
            return $this->get_fixed_datetime();
        }

        // Default to current time.
        return parent::get_base_datetime($userid);
    }

    /**
     * Get the configured fixed datetime.
     *
     * @return \DateTime Fixed datetime from configuration
     */
    protected function get_fixed_datetime() {
        // Fixed date/time.
        $fixeddate = $this->actiondata->fixeddate ?? [];

        if (!empty($fixeddate)) {
            try {
                $fixeddate = date('Y-m-d H:i:s', $fixeddate);
                $datetime = new \DateTime($fixeddate, \core_date::get_server_timezone_object());
                return $datetime;
            } catch (\Exception $e) {
                // Invalid date format, fallback to current time.
                return new \DateTime('now', \core_date::get_server_timezone_object());
            }
        }

        // No fixed date configured, fallback to current time.
        return new \DateTime('now', \core_date::get_server_timezone_object());
    }

    /**
     * Get the user's enrollment datetime for this course.
     *
     * @param int $userid User ID
     * @return \DateTime Enrollment datetime or current time if not found
     */
    protected function get_enrollment_datetime($userid) {
        global $DB, $PAGE, $CFG;

        require_once($CFG->dirroot . '/enrol/locallib.php');

        $enrolmanager = new \course_enrolment_manager($PAGE, get_course($this->instancedata->courseid));
        $enrolments = $enrolmanager->get_user_enrolments($userid);

        if ($enrolments) {
            $enrolment = \mod_pulse\helper::get_latest_user_enrolment($enrolments);
            $enrolltime = $enrolment->timestart ?: $enrolment->timecreated;
            // Return enrollment time as datetime.
            return (new \DateTime())->setTimestamp($enrolltime);
        }

        // Fallback to current time, enrollment not found.
        return new \DateTime('now', \core_date::get_server_timezone_object());
    }
}
