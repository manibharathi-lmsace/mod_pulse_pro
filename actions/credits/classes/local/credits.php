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
 * Credits pulse action local library, handles the process of allocating credits to users.
 *
 * @package    pulseaction_credits
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace pulseaction_credits\local;

defined('MOODLE_INTERNAL') || die(' No direct access ');

use mod_pulse\local\automation\schedule;
use stdClass;
use mod_pulse\local\automation\schedule_helper;

// Credits library functions.
require_once($CFG->dirroot . '/mod/pulse/actions/credits/lib.php');

/**
 * Credits action local library class, handles the process of allocating credits to users.
 */
class credits {
    // Include schedule helper trait for common schedule functions.
    // @see \mod_pulse\local\automation\schedule_helper.
    use schedule_helper;

    /**
     * Credit allocation method - Add credits.
     * @var int
     */
    const ALLOCATION_ADD = 1;

    /**
     * Credit allocation method - Replace credits.
     * @var int
     */
    const ALLOCATION_REPLACE = 2;

    /**
     * Base date type fixed.
     * @var string
     */
    const BASEDATEFIXED = 'fixed';

    /**
     * Base date type relative.
     * @var string
     */
    const BASEDATERELATIVE = 'relative';

    /**
     * Credit action status - disabled.
     * @var int
     */
    const ACTION_CREDITS_DISABLED = 0;

    /**
     * Credits action status - enabled.
     * @var int
     */
    const ACTION_CREDITS_ENABLED = 1;

    /**
     * Cache for conditions to avoid repeated database queries.
     * @var array
     */
    protected $conditions = [];

    /**
     * Current schedule record being processed.
     * @var stdClass
     */
    protected $schedulerecord;

    /**
     * Current instance data.
     * @var stdClass
     */
    protected $instancedata;

    /**
     * Current action data.
     * @var stdClass
     */
    protected $actiondata;

    /**
     * Current user data.
     * @var stdClass
     */
    protected $user;

    /**
     * Current course data.
     * @var stdClass
     */
    protected $course;

    /**
     * Current course context.
     * @var \context_course
     */
    protected $coursecontext;

    /**
     * Action overrides.
     * @var array
     */
    protected $actionoverrides;

    /**
     * Credits schedule object.
     * @var credits_schedule
     */
    protected $creditschedule;

    /**
     * Get the schedule table name.
     *
     * @return string
     */
    public function get_schedule_table(): string {
        return 'pulseaction_credits_sch';
    }

    /**
     * Get the action table name.
     *
     * @return string
     */
    public function get_action_table(): string {
        return 'pulseaction_credits';
    }

    /**
     * Get the action instance table name.
     *
     * @return string
     */
    public function get_action_instance_table(): string {
        return 'pulseaction_credits_ins';
    }

    /**
     * Allocate credits to a specific user.
     *
     * @param int|null $userid User ID to allocate credits to
     * @return void
     */
    public function allocate_credits(?int $userid = null) {
        global $DB;

        $records = $this->get_scheduled_records($userid);

        foreach ($records as $schedule) {
            // Atomically claim this row before doing any work on it - an event-driven
            // allocation (right after the schedule was created) can otherwise race the
            // per-minute cron allocation for the same row and double-credit the user.
            if (!$this->claim_schedule($schedule->id)) {
                continue; // Already claimed or completed by a concurrent process.
            }

            try {
                $this->process_credits_allocation($schedule);
            } catch (\Throwable $e) {
                // Release the claim so the schedule isn't stuck at PROCESSING and can be retried.
                // (process_credits_allocation() already handles \Exception internally - this is
                // a safety net for anything else, e.g. a \Error, that would otherwise propagate.)
                $DB->set_field('pulseaction_credits_sch', 'status', schedule::STATUS_QUEUED, ['id' => $schedule->id]);
            }
        }
    }

    /**
     * Atomically claim a schedule row - flips it from QUEUED to PROCESSING only if it
     * is still QUEUED. Uses Moodle's Lock API (portable across every DB driver Moodle
     * supports, unlike a raw "SELECT ... FOR UPDATE") to guarantee that a concurrent
     * claim attempt on the same row (event-driven allocation racing the cron
     * allocation) cannot both win. The lock is only held for the instant of the claim
     * itself, not for the duration of the actual credit allocation.
     *
     * @param int $id pulseaction_credits_sch.id
     * @return bool True if this call claimed the row, false if it was already claimed/completed.
     */
    protected function claim_schedule(int $id): bool {
        global $DB;

        $lockfactory = \core\lock\lock_config::get_lock_factory('mod_pulse');
        // Non-blocking - if another process already holds this row's lock, someone else
        // is already claiming/allocating it, so give up immediately rather than waiting.
        $lock = $lockfactory->get_lock('pulseaction_credits_sch_' . $id, 0);
        if (!$lock) {
            return false;
        }

        try {
            $current = $DB->get_record('pulseaction_credits_sch', ['id' => $id]);
            $claimed = $current && $current->status == schedule::STATUS_QUEUED;
            if ($claimed) {
                $DB->set_field('pulseaction_credits_sch', 'status', schedule::STATUS_PROCESSING, ['id' => $id]);
            }
            return $claimed;
        } finally {
            $lock->release();
        }
    }

    /**
     * Process credits allocation for a single schedule record.
     *
     * @param stdClass $schedule Schedule record from database
     * @return bool Success status
     */
    protected function process_credits_allocation(stdClass $schedule): bool {
        global $DB;

        try {
            // Build schedule values and context.
            $this->build_schedule_values($schedule);

            $this->creditschedule = new credits_schedule($this->instancedata->id, $this->instancedata);
            $this->creditschedule->set_action_data($this->actiondata, $this->instancedata);

            // Get credit configuration.
            $credits = $this->schedule->credits ?? 0;
            $allocationmethod = $this->schedule->allocationmethod ?? self::ALLOCATION_ADD;

            if ($credits < 0) {
                $this->mark_schedule_failed($schedule->id, 'Invalid credits');
                return false;
            }

            // Allocate credits to the user.
            $success = $this->allocate_credits_to_user($this->user->id, $credits, $allocationmethod, $this->course->id);

            if ($success) {
                $this->mark_schedule_completed($schedule->id);
                // Create and trigger the credit allocated event.
                $this->schedule->courseid = $this->course->id ?: SITEID;

                $event = \pulseaction_credits\event\credit_allocated::create_from_schedule($this->schedule);
                $event->trigger();

                return true;
            }

            // Allocation attempt failed without an exception - release the claim for a retry.
            $DB->set_field('pulseaction_credits_sch', 'status', schedule::STATUS_QUEUED, ['id' => $schedule->id]);
            return false;
        } catch (\Exception $e) {
            // Release the claim so the schedule isn't stuck at PROCESSING and can be retried.
            $DB->set_field('pulseaction_credits_sch', 'status', schedule::STATUS_QUEUED, ['id' => $schedule->id]);
            return false;
        }
    }

    /**
     * Allocate credits to a user using the configured profile field.
     *
     * @param int $userid UserID
     * @param float $credits Credits to allocate
     * @param int $method Allocation method (add or replace)
     * @param int $courseid CourseID for context
     *
     * @return bool Success status
     */
    protected function allocate_credits_to_user(int $userid, float $credits, int $method, int $courseid): bool {
        global $DB;

        // Get the credit profile field configuration.
        $creditfield = \pulseaction_credits_get_configured_creditfield_id();
        if (empty($creditfield)) {
            throw new \moodle_exception('nocreditprofilefield', 'pulseaction_credits');
        }

        // Get current user profile field data.
        $sql = "SELECT upd.*
                FROM {user_info_data} upd
                JOIN {user_info_field} uif ON uif.id = upd.fieldid
                WHERE upd.userid = :userid AND uif.id = :fieldid";

        $params = ['userid' => $userid, 'fieldid' => $creditfield];
        $currentdata = $DB->get_record_sql($sql, $params);

        $currentcredits = $currentdata ? $currentdata->data : 0;
        $currentcredits = (float) $currentcredits;

        // Calculate new credits.
        switch ($method) {
            case self::ALLOCATION_ADD:
                $newcredits = $currentcredits + $credits;
                break;
            case self::ALLOCATION_REPLACE:
                $newcredits = $credits;
                break;
            default:
                throw new \moodle_exception('invalidallocationmethod', 'pulseaction_credits');
        }

        // Update or insert the profile field data.
        if ($currentdata) {
            $currentdata->data = $newcredits;
            $currentdata->timemodified = time();
            $status = $DB->update_record('user_info_data', $currentdata);
        } else {
            // Get the field ID.
            $field = $DB->get_record('user_info_field', ['id' => $creditfield], 'id', MUST_EXIST);

            $newdata = new stdClass();
            $newdata->userid = $userid;
            $newdata->fieldid = $field->id;
            $newdata->data = $newcredits;
            $newdata->dataformat = 0;
            $status = $DB->insert_record('user_info_data', $newdata);
        }

        return ($status) ? true : false;
    }

    /**
     * Mark a schedule as completed. And regenerate if recurring.
     *
     * @param int $scheduleid Schedule ID
     */
    protected function mark_schedule_completed(int $scheduleid) {
        global $DB;

        $allocatedtime = time();

        $notifycount = $this->schedule->notifycount + 1;

        $updatedata = new stdClass();
        $updatedata->id = $scheduleid;
        $updatedata->status = \mod_pulse\local\automation\schedule::STATUS_COMPLETED;
        $updatedata->notifycount = $notifycount;
        $updatedata->completedtime = $allocatedtime;
        $updatedata->timemodified = time();

        $DB->update_record('pulseaction_credits_sch', $updatedata);

        if (
            !empty($this->creditschedule->get_actiondata()->notifyinterval)
            && $this->creditschedule->get_actiondata()->notifyinterval['interval'] != schedule::INTERVALONCE
        ) {
            $newschedule = true;
            $previousbased = $this->schedule->id;
            $this->creditschedule->create_schedule_foruser(
                $this->schedule->userid,
                $allocatedtime,
                $notifycount,
                null,
                null,
                $newschedule,
                false,
                $previousbased
            );
        }
    }

    /**
     * Mark a schedule as failed.
     *
     * @param int $scheduleid Schedule ID
     * @param string $error Error message
     */
    protected function mark_schedule_failed(int $scheduleid, string $error) {
        global $DB;

        $updatedata = new stdClass();
        $updatedata->id = $scheduleid;
        $updatedata->status = \mod_pulse\local\automation\schedule::STATUS_FAILED;
        $updatedata->timemodified = time();

        $DB->update_record('pulseaction_credits_sch', $updatedata);
    }

    /**
     * Get the current user credit balance.
     *
     * @param int $userid User ID
     * @return int Current credit balance
     */
    public function get_user_credits(int $userid): float {
        global $DB;

        $creditfield = \pulseaction_credits_get_configured_creditfield_id();
        if (empty($creditfield)) {
            return 0;
        }

        $sql = "SELECT upd.data
                FROM {user_info_data} upd
                JOIN {user_info_field} uif ON uif.id = upd.fieldid
                WHERE upd.userid = :userid AND uif.id = :fieldname";

        $params = ['userid' => $userid, 'fieldname' => $creditfield];
        $result = $DB->get_field_sql($sql, $params);

        return $result ? (float) $result : 0;
    }

    /**
     * Override the user credits.
     *
     * @param int $userid User ID
     * @param float $credits New credit value
     * @param int $method Allocation method (ADD or REPLACE)
     * @return bool Success status
     */
    public function update_user_credits(int $userid, float $credits, int $method): bool {
        $result = $this->allocate_credits_to_user($userid, $credits, $method, SITEID);
        return $result;
    }

    /**
     * Get allocation method options for forms.
     *
     * @return array
     */
    public static function get_allocation_methods(): array {
        return [
            self::ALLOCATION_ADD => get_string('addcredits', 'pulseaction_credits'),
            self::ALLOCATION_REPLACE => get_string('replacecredits', 'pulseaction_credits'),
        ];
    }

    /**
     * Get base date type options for forms.
     *
     * @return array
     */
    public static function get_basedatetypes(): array {
        return [
            self::BASEDATEFIXED => get_string('basedatefixed', 'pulseaction_credits'),
            self::BASEDATERELATIVE => get_string('basedaterelative', 'pulseaction_credits'),
        ];
    }

    /**
     * Verify if the provided credits value is valid. Confirm it is non-negative and within acceptable range.
     *
     * @param float $credits Credits value to verify
     * @return bool
     */
    public static function verify_is_validcredits(float $credits): bool {
        $range = 8;
        return $credits >= 0 && strlen((string) round($credits)) <= $range;
    }
}
