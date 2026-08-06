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
 * Override management for credit allocations.
 *
 * @package   pulseaction_credits
 * @copyright 2025 bdecent GmbH <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulseaction_credits\local;

use mod_pulse\local\automation\schedule;
use pulseaction_credits\event\credit_overridden;
use pulseaction_credits\local\credits;

/**
 * Override manager class for handling credit allocation overrides.
 */
class override_manager {
    /**
     * Create or update a override for a scheduled allocation.
     *
     * @param int $scheduleid Schedule ID
     * @param float $overridecredit New credits
     * @return bool Success status
     */
    public static function create_override($scheduleid, $overridecredit) {
        global $DB, $USER;

        // Get the schedule record.
        $schedule = $DB->get_record('pulseaction_credits_sch', ['id' => $scheduleid], '*', MUST_EXIST);

        // Only allow overrides for planned allocations.
        if ($schedule->status != schedule::STATUS_QUEUED) {
            throw new \moodle_exception('cannotoverrideprocessed', 'pulseaction_credits');
        }

        // Validate override credits.
        if ($overridecredit < 0) {
            throw new \moodle_exception('invalidoverridecredit', 'pulseaction_credits');
        }

        // Check if override already exists.
        $existingoverride = $DB->get_record('pulseaction_credits_override', ['scheduleid' => $scheduleid]);

        $override = new \stdClass();
        $override->scheduleid = $scheduleid;
        $override->userid = $schedule->userid;
        $override->overridecredit = $overridecredit;
        $override->overriddenby = $USER->id;

        if ($existingoverride) {
            // Update existing override.
            $override->id = $existingoverride->id;
            $override->timemodified = time();
            $success = $DB->update_record('pulseaction_credits_override', $override);
        } else {
            // Create new override.
            $override->timecreated = time();
            $override->status = 1; // Active.
            $override->scheduledcredit = $schedule->credits;
            $override->id = $DB->insert_record('pulseaction_credits_override', $override);
            $success = (bool) $override->id;
        }

        if ($success) {
            // Update the schedule record with the override credit.
            $DB->update_record('pulseaction_credits_sch', (object) [
                'id' => $scheduleid, 'credits' => $overridecredit]);

            // Trigger event for overriden.
            $event = credit_overridden::create_from_override($override, $schedule);
            $event->trigger();
        }

        return $success;
    }

    /**
     * Edit user credits directly, overriding any existing value.
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @param float $credits Credits to set
     * @param string $note Optional note for the override
     * @return bool Success status
     */
    public static function edit_user_credits($userid, $courseid, $credits, $note = '') {
        global $DB, $USER;

        // Validate credits.
        if ($credits < 0) {
            throw new \moodle_exception('invalidcredits', 'pulseaction_credits');
        }

        $creditsobj = new credits();
        // Get current credits for comparison.
        $currentcredits = $creditsobj->get_user_credits($userid);

        // Set credits using replace method.
        $success = $creditsobj->update_user_credits($userid, $credits, credits::ALLOCATION_REPLACE);

        if ($success) {
            // Log the user credit override.
            $override = new \stdClass();
            $override->userid = $userid;
            $override->courseid = $courseid;
            $override->oldcredits = $currentcredits;
            $override->newcredits = $credits;
            $override->overriddenby = $USER->id;
            $override->timecreated = time();
            $override->note = $note;

            $overrideid = $DB->insert_record('pulseaction_credits_user_override', $override);

            if ($overrideid) {
                // Trigger credit allocated event.
                $event = \pulseaction_credits\event\credit_allocated::create_from_user_override($override, $courseid);
                $event->trigger();
            }
        }

        return $success;
    }

    /**
     * Delete an override, reverting to original amount.
     *
     * @param int $scheduleid Schedule ID
     * @return bool Success status
     */
    public static function delete_override($scheduleid) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        try {
            $override = $DB->get_record('pulseaction_credits_override', ['scheduleid' => $scheduleid], '*', IGNORE_MISSING);

            if (!$override) {
                return true;
            }

            // Update the schedule record with the scheduled credit.
            $DB->update_record('pulseaction_credits_sch', (object) ['id' => $scheduleid, 'credits' => $override->scheduledcredit]);

            // Remove the override record.
            $DB->delete_records('pulseaction_credits_override', ['scheduleid' => $scheduleid]);
        } catch (\Exception $e) {
            $transaction->rollback($e);
        }

        $transaction->allow_commit();

        return true;
    }

    /**
     * Check if user can override credits in a course.
     *
     * @param int $courseid Course ID
     * @param int $userid User ID (optional, defaults to current user)
     * @return bool Permission status
     */
    public static function can_override_credits($courseid, $userid = null) {
        $context = \context_course::instance($courseid);
        return has_capability('pulseaction/credits:override', $context, $userid);
    }

    /**
     * Remove all overrides for a user in the shared instance.
     *
     * @param int $userid User ID
     * @param int|null $instanceid Instance ID (optional)
     *
     * @return void
     */
    public static function remove_user_overrides($userid, $instanceid = null) {
        global $DB;

        $select = "instanceid = :instanceid AND userid = :userid AND (status = :disabledstatus  OR status = :queued)";

        $params = [
            'instanceid' => $instanceid, 'userid' => $userid,
            'disabledstatus' => schedule::STATUS_DISABLED, 'queued' => schedule::STATUS_QUEUED,
        ];

        $records = $DB->get_records_select('pulseaction_credits_sch', $select, $params, 'id ASC', 'id');

        if (empty($records)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($records), SQL_PARAMS_NAMED, 'sched');

        $DB->delete_records_select('pulseaction_credits_override', 'scheduleid ' . $insql, $inparams);
    }
}
