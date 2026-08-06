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
 * Behat data generator for pulse action credits.
 *
 * @package   pulseaction_credits
 * @copyright 2024 bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/pulse/tests/generator/behat_mod_pulse_generator.php');
require_once(__DIR__ . '/../../../../tests/generator/behat_pulseaction_generator_trait.php');

use core\exception\moodle_exception;
use mod_pulse\automation\helper;
use pulseaction_credits\local\credits;
use mod_pulse\local\automation\schedule;

/**
 * Behat data generator class for pulse action credits.
 */
class behat_pulseaction_credits_generator extends behat_generator_base {
    use behat_pulseaction_generator_trait;

    /**
     * Get a list of the entities that can be created.
     *
     * @return array entity name => information about how to generate.
     */
    protected function get_creatable_entities(): array {

        return [
            'credits templates' => [
                'singular' => 'credits template',
                'datagenerator' => 'credits_template',
                'required' => ['template'],
                'switchids' => ['template' => 'templateid'],
            ],
            'credits instances' => [
                'singular' => 'credits instance',
                'datagenerator' => 'credits_instance',
                'required' => ['template', 'reference', 'course'],
                'switchids' => ['template' => 'templateid', 'course' => 'courseid'],
            ],
        ];
    }

    /**
     * Preprocess credits template data.
     *
     * @param array $data Raw data
     * @return array Processed data
     */
    protected function preprocess_credits_template(array $data): array {
        // Get template ID from reference.
        if (isset($data['template'])) {
            $data['templateid'] = $this->get_template_id_from_reference($data['template']);
            unset($data['template']);
        }

        // Normalize boolean fields.
        if (isset($data['status'])) {
            $data['actionstatus'] = $this->normalize_boolean($data['status']);
        }

        // Allocation method.
        if (isset($data['allocationmethod'])) {
            $mapping = [
                'add credits' => credits::ALLOCATION_ADD,
                'replace credits' => credits::ALLOCATION_REPLACE,
            ];
            $key = strtolower(trim($data['allocationmethod']));
            $data['allocationmethod'] = $mapping[$key] ?? credits::ALLOCATION_ADD;
        }

        // Normalize interval.
        if (isset($data['interval'])) {
            $data['intervaltype'] = $this->normalize_interval($data['interval']);

            if ($data['intervaltype'] !== schedule::INTERVALONCE && !isset($data['intervaltime'])) {
                throw new moodle_exception('Interval time must be specified for recurring intervals');
            }

            // Build notifyinterval based on interval type.
            $data['notifyinterval'] = $this->build_interval_config($data, $data['intervaltype']);
        }

        // Normalize base date.
        if (isset($data['basedate'])) {
            $data['basedatetype'] = $this->normalize_base_date($data['basedate']);
        }

        // Convert date strings to timestamps.
        if (isset($data['fixedbasedate'])) {
            $data['fixeddate'] = is_numeric($data['fixedbasedate'])
                ? $data['fixedbasedate'] : $this->date_to_timestamp($data['fixedbasedate']);
        }

        // Parse recipients.
        if (isset($data['recipients'])) {
            $data['recipients'] = json_encode($this->parse_recipients($data['recipients']));
        }

        // Process conditions.
        if (isset($data['condition'])) {
            $data['condition_type'] = $data['condition'];
            unset($data['condition']);
            $conditiondata = helper::filter_record_byprefix($data, 'condition');
            if (!empty($conditiondata)) {
                $this->create_condition($data['templateid'], $conditiondata);
            }
        }

        return $data;
    }

    /**
     * Preprocess credits instance data.
     *
     * @param array $data Raw data
     * @return array Processed data
     */
    protected function preprocess_credits_instance(array $data): array {
        global $DB;

        $data['status'] = $data['status'] ?? 'enable'; // Enabled by default.
        $data['actionstatus'] = $this->normalize_boolean($data['status']);

        // Get template ID from reference.
        if (isset($data['templateid'])) {
            $instance = $DB->get_record('pulse_autotemplates_ins', ['insreference' => $data['reference']]);
            if (empty($instance)) {
                $this->get_pulse_generator()->create_automation_instance([
                    'templateid' => $data['templateid'],
                    'courseid' => $data['courseid'],
                    'reference' => $data['reference'],
                ]);
                $data['instanceid'] = $this->get_instance_id_from_reference($data['reference']);
            } else {
                $data['instanceid'] = $instance->id;
            }
        }

        // Process conditions.
        if (isset($data['condition'])) {
            $data['condition_type'] = $data['condition'];
            unset($data['condition']);
        }
        $conditiondata = helper::filter_record_byprefix($data, 'condition');
        if (!empty($conditiondata)) {
            $this->create_instance_condition($data['instanceid'], $data['templateid'], $conditiondata);
        }

        return $data;
    }

    /**
     * Get template ID from reference (legacy support).
     *
     * @param string $reference Template reference
     * @return int Template ID
     */
    public function get_template_id(string $reference): int {
        return $this->get_template_id_from_reference($reference);
    }

    /**
     * Normalize base date method.
     *
     * @param string $method Base date method
     * @return int Normalized base date constant
     */
    public function normalize_base_date(string $method) {
        $method = strtolower(trim($method));
        $mapping = [
            'relative to enrollment' => \pulseaction_credits\local\credits::BASEDATERELATIVE,
            'fixed date' => \pulseaction_credits\local\credits::BASEDATEFIXED,
        ];

        return $mapping[$method] ?? \pulseaction_credits\local\credits::BASEDATERELATIVE;
    }
}
