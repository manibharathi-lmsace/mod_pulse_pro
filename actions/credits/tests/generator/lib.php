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
 * Credits action test data generator.
 *
 * @package   pulseaction_credits
 * @copyright 2024 bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_pulse\local\automation\schedule;
use pulseaction_credits\local\credits;
use pulseaction_credits\local\credits_schedule;

/**
 * Credits action generator class.
 */
class pulseaction_credits_generator extends component_generator_base {
    /**
     * Create a credit action configuration for a template.
     *
     * @param array $data Action configuration data
     * @return stdClass The created action record
     */
    public function create_credits_template($data) {
        global $DB;

        $defaults = [
            'actionstatus' => 1,
            'credits' => 100,
            'allocationmethod' => credits::ALLOCATION_ADD,
            'intervaltype' => schedule::INTERVALONCE,
            'basedatetype' => credits::BASEDATERELATIVE,
            'recipients' => json_encode([]),
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        $record = (object) array_merge($defaults, $data);

        // Validate required fields.
        if (empty($record->templateid)) {
            throw new coding_exception('templateid is required for credits action');
        }

        // Ensure credit amount is valid.
        if ($record->credits < 0) {
            throw new coding_exception('Credit amount cannot be negative');
        }

        // Insert the credits action record.
        $record->id = $DB->insert_record('pulseaction_credits', $record);

        return $record;
    }

    /**
     * Create a credit instance for testing.
     *
     * @param array $data Instance data
     * @return stdClass The created instance record
     */
    public function create_credits_instance($data) {
        global $DB;

        $defaults = [
            'status' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        $record = (object) array_merge($defaults, $data);

        // Validate required fields.
        if (empty($record->instanceid)) {
            throw new coding_exception('instanceid is required for credit instance');
        }

        $record->id = $DB->insert_record('pulseaction_credits_ins', $record);

        return $record;
    }
}
