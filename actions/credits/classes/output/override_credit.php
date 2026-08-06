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
 * Credits pulse action override inplace editor.
 *
 * @package   pulseaction_credits
 * @copyright 2025 bdecent GmbH <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulseaction_credits\output;

use core\output\inplace_editable;
use mod_pulse\local\automation\schedule;
use pulseaction_credits\local\override_manager;
use pulseaction_credits\local\credits;

/**
 * Credits override output class.
 */
class override_credit extends inplace_editable {
    /**
     * Constructor.
     *
     * @param stdClass $schedule Schedule record data.
     */
    public function __construct($schedule) {

        $currentcredits = $schedule->overridecredit ?: '';
        $isoverride = !empty($schedule->overridecredit);

        // Only allow editing for planned allocations.
        $editable = ($schedule->status == schedule::STATUS_QUEUED);

        $displayvalue = $currentcredits;
        if ($isoverride) {
            $displayvalue .= ' (' . get_string('overridden', 'pulseaction_credits') . ')';
        } else {
            $displayvalue = get_string('nooverride', 'pulseaction_credits');
        }

        parent::__construct(
            'pulseaction_credits',
            'overridecredit',
            $schedule->id,
            $editable,
            $displayvalue,
            $currentcredits,
            get_string('editoverridecredit', 'pulseaction_credits'),
            get_string('editoverridecredit_help', 'pulseaction_credits')
        );
    }

    /**
     * Updates the overridden credits for the schedule.
     *
     * @param int $itemid Schedule ID
     * @param mixed $newvalue New override credits
     * @return static
     */
    public static function update($itemid, $newvalue) {
        global $DB, $PAGE;

        // Get schedule record.
        $schedule = $DB->get_record('pulseaction_credits_sch', ['id' => $itemid], '*', MUST_EXIST);
        $courseid = $DB->get_field('pulse_autoinstances', 'courseid', ['id' => $schedule->instanceid], MUST_EXIST);

        $PAGE->set_context(\context_course::instance($courseid));

        // Check permissions.
        if (!override_manager::can_override_credits($courseid)) {
            throw new \moodle_exception('nopermissions', 'error', '', 'pulseaction/credits:override');
        }

        // If the new value is empty, consider it as removing the override.
        if ($newvalue == '') {
            $result = override_manager::delete_override($itemid);
            if (!$result) {
                throw new \moodle_exception('cannotremoveoverride', 'pulseaction_credits');
            }
        } else {
            // Validate new value.
            $newvalue = (float) clean_param($newvalue, PARAM_FLOAT);

            if ($newvalue < 0 || credits::verify_is_validcredits($newvalue) === false) {
                \core\notification::error(get_string('invalidoverridecredit', 'pulseaction_credits'));
            } else {
                // Create or update override.
                $success = override_manager::create_override($itemid, $newvalue);
                if (!$success) {
                    throw new \moodle_exception('cannotupdateoverride', 'pulseaction_credits');
                }
            }
        }

        // Get updated schedule with override data.
        $sql = "SELECT pcs.*, pco.overridecredit, pco.id as overrideid
                FROM {pulseaction_credits_sch} pcs
                LEFT JOIN {pulseaction_credits_override} pco ON pco.scheduleid = pcs.id
                WHERE pcs.id = :id";

        $updatedschedule = $DB->get_record_sql($sql, ['id' => $itemid], MUST_EXIST);

        return new static($updatedschedule);
    }
}
