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
 * Common methods for pulse action generators.
 *
 * @package   mod_pulse
 * @copyright 2025 bdecent GmbH <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_pulse\automation\condition_base;
use mod_pulse\local\automation\schedule;

/**
 * This class containing common methods for pulse action Behat generators.
 */
trait behat_pulseaction_generator_trait {
    /**
     * Get the template ID from reference or title.
     *
     * @param string $reference Template reference or title
     * @return int Template ID
     * @throws dml_missing_record_exception
     */
    protected function get_template_id_from_reference(string $reference): int {
        global $DB;
        return $DB->get_field_select(
            'pulse_autotemplates',
            'id',
            'title = :title OR reference = :reference',
            ['title' => $reference, 'reference' => $reference],
            MUST_EXIST
        );
    }

    /**
     * Get the instance ID from reference.
     *
     * @param string $reference Instance reference
     * @return int Instance ID
     * @throws dml_missing_record_exception
     */
    protected function get_instance_id_from_reference(string $reference): int {
        global $DB;
        return $DB->get_field('pulse_autotemplates_ins', 'instanceid', ['insreference' => $reference], MUST_EXIST);
    }

    /**
     * Convert the string values to boolean format.
     *
     * @param mixed $value
     * @return bool converted value in boolean
     */
    protected function normalize_boolean($value): bool {
        if (is_bool($value)) {
            return $value;
        }
        $lowercasevalue = strtolower(trim((string)$value));
        return in_array($lowercasevalue, ['1', 'true', 'yes', 'on', 'enable', 'enabled'], true);
    }

    /**
     * Parse recipients string into role ids.
     *
     * @param string $recipients Comma-separated role shortnames
     * @return array
     */
    protected function parse_recipients(string $recipients): array {
        if (empty($recipients)) {
            return [];
        }

        $roleids = [];
        $roles = array_map('trim', explode(',', $recipients));

        foreach ($roles as $roleshortname) {
            if (!empty($roleshortname)) {
                $roleids[] = $this->get_role_id_from_shortname($roleshortname);
            }
        }

        return $roleids;
    }

    /**
     * Normalize interval string to interval.
     *
     * @param string $interval Interval string (once, daily, weekly, yearly)
     * @return int
     */
    protected function normalize_interval(string $interval): int {
        $intervalmap = [
            'once' => schedule::INTERVALONCE,
            'daily' => schedule::INTERVALDAILY,
            'weekly' => schedule::INTERVALWEEKLY,
            'monthly' => schedule::INTERVALMONTHLY,
            'yearly' => schedule::INTERVALYEARLY,
            'custom' => schedule::INTERVALCUSTOM,
        ];

        $key = strtolower(trim($interval));
        if (!isset($intervalmap[$key])) {
            throw new coding_exception("Invalid interval: $interval");
        }

        return $intervalmap[$key];
    }

    /**
     * Get role ID from shortname.
     *
     * @param string $shortname Role shortname
     * @return int Role ID
     */
    protected function get_role_id_from_shortname(string $shortname): int {
        global $DB;
        return $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }


    /**
     * Get the current timestamp.
     *
     * @return int Current timestamp
     */
    protected function get_current_timestamp(): int {
        return time();
    }

    /**
     * Convert date string to timestamp.
     *
     * @param string $datestr Date string
     * @return int Timestamp
     */
    protected function date_to_timestamp(string $datestr): int {
        $timestamp = strtotime($datestr);
        if ($timestamp === false) {
            throw new coding_exception("Invalid date format: $datestr");
        }
        return $timestamp;
    }

    /**
     * Get pulse action generator.
     *
     * @param string $action Action name.
     * @return component_generator_base|null Generator instance or null if not found
     */
    protected function get_action_generator(string $action): ?component_generator_base {
        return testing_util::get_data_generator()->get_plugin_generator("pulseaction_$action");
    }

    /**
     * Get pulse module generator.
     *
     * @return component_generator_base|null Pulse module generator instance
     */
    protected function get_pulse_generator(): ?component_generator_base {
        return testing_util::get_data_generator()->get_plugin_generator('mod_pulse');
    }

    /**
     * Convert condition status string.
     *
     * @param string $status Condition status string (all, upcoming, disabled)
     * @return int Condition status
     */
    private function condition_status(string $status): int {
        $statusmap = [
            'all' => \mod_pulse\automation\condition_base::ALL,
            'upcoming' => \mod_pulse\automation\condition_base::FUTURE,
            'disabled' => \mod_pulse\automation\condition_base::DISABLED,
        ];

        $key = strtolower(trim($status));
        if (!isset($statusmap[$key])) {
            throw new coding_exception("Invalid condition status: $status");
        }

        return $statusmap[$key];
    }

    /**
     * Create a condition record.
     *
     * @param int $templateid Template ID
     * @param array $condition Condition data
     * @return object Created condition record
     * @throws coding_exception
     */
    protected function create_condition(int $templateid, array $condition): object {
        global $DB;

        if (empty($condition['type'])) {
            throw new coding_exception('Condition type is required to create condition.');
        }

        if (!array_key_exists('status', $condition)) {
            $condition['status'] = 1;
        } else {
            $condition['status'] = $this->condition_status($condition['status']);
        }

        $record = (object) [
            'triggercondition' => $condition['type'],
            'templateid' => $templateid,
            'status' => $condition['status'] ?: 1, // Upcoming or All.
            'upcomingtime' => $condition['status'] == condition_base::FUTURE ? time() : 0,
            'additional' => json_encode('[]'),

        ];

        if (
            $existing = $DB->get_record('pulse_condition', [
            'templateid' => $templateid,
            'triggercondition' => $record->triggercondition,
            ])
        ) {
            $record->id = $existing->id;
            $DB->update_record('pulse_condition', $record);
        } else {
            $DB->insert_record('pulse_condition', $record);
        }

        return $record;
    }

    /**
     * Create a condition override for an instance.
     *
     * @param int $instanceid Instance ID
     * @param int $templateid Template ID
     * @param array $condition Condition data
     * @return void
     * @throws coding_exception
     */
    protected function create_instance_condition(int $instanceid, int $templateid, array $condition): void {
        global $DB;

        $record = $this->create_condition($templateid, $condition);

        $record->instanceid = $instanceid;
        $record->isoverridden = 0;

        // Pulse condition for instance.
        if (
            $existing = $DB->get_record('pulse_condition_overrides', [
            'instanceid' => $instanceid,
            'triggercondition' => $record->triggercondition,
            ])
        ) {
            $record->id = $existing->id;
            $DB->update_record('pulse_condition_overrides', $record);
        } else {
            $DB->insert_record('pulse_condition_overrides', $record);
        }
    }


    /**
     * Build notify interval JSON based on interval type and configuration.
     *
     * @param array $data Raw data containing interval configuration
     * @param int $intervaltype Interval type
     * @return string JSON encoded interval configuration
     */
    protected function build_interval_config(array $data, int $intervaltype): string {
        $config = ['interval' => (string)$intervaltype];
        $parts = preg_split('/\s+/', trim($data['intervaltime'] ?? ''));

        switch ($intervaltype) {
            case schedule::INTERVALYEARLY:
                // Yearly -Jan 10 11:30.
                $config['month'] = isset($parts[0]) ? $this->normalize_month($parts[0]) : '1';
                $config['day'] = $parts[1] ?? '1';
                $config['time'] = $parts[2] ?? '00:00';
                break;

            case schedule::INTERVALMONTHLY:
                // Monthly-(day time).
                $config['day'] = $parts[0] ?? '1';
                $config['time'] = $parts[1] ?? '00:00';
                break;

            case schedule::INTERVALWEEKLY:
                // Weekly (dayofweek time).
                $config['dayofweek'] = isset($parts[0]) ? $this->normalize_dayofweek($parts[0]) : '0';
                $config['time'] = $parts[1] ?? '00:00';
                break;

            case schedule::INTERVALDAILY:
                // Daily (time).
                $config['time'] = $parts[0] ?? '00:00';
                break;

            case schedule::INTERVALCUSTOM:
                // Custom cron fields.
                $cronfields = ['cron_minute', 'cron_hour', 'cron_day', 'cron_month', 'cron_dayofweek'];
                foreach ($cronfields as $field) {
                    if (isset($data[$field])) {
                        $config[$field] = $data[$field];
                    }
                }
                break;

            case schedule::INTERVALONCE:
            default:
                break;
        }

        return json_encode($config);
    }

    /**
     * Normalize month name to string.
     *
     * @param string $month Month name or number
     * @return string Month number (1-12)
     */
    protected function normalize_month(string $month): string {
        $months = [
            'jan' => '1', 'january' => '1',
            'feb' => '2', 'february' => '2',
            'mar' => '3', 'march' => '3',
            'apr' => '4', 'april' => '4',
            'may' => '5',
            'jun' => '6', 'june' => '6',
            'jul' => '7', 'july' => '7',
            'aug' => '8', 'august' => '8',
            'sep' => '9', 'september' => '9',
            'oct' => '10', 'october' => '10',
            'nov' => '11', 'november' => '11',
            'dec' => '12', 'december' => '12',
        ];

        $key = strtolower(trim($month));
        return $months[$key] ?? $month;
    }

    /**
     * Normalize day of week name to numeric.
     *
     * @param string $day
     * @return string
     */
    protected function normalize_dayofweek(string $day): string {
        $days = [
            'sun' => '0', 'sunday' => '0',
            'mon' => '1', 'monday' => '1',
            'tue' => '2', 'tuesday' => '2',
            'wed' => '3', 'wednesday' => '3',
            'thu' => '4', 'thursday' => '4',
            'fri' => '5', 'friday' => '5',
            'sat' => '6', 'saturday' => '6',
        ];

        $key = strtolower(trim($day));
        return $days[$key] ?? $day;
    }
}
