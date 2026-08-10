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
 * Pulse instance test instance generate defined.
 *
 * @package   mod_pulse
 * @copyright 2021, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/behat_pulseaction_generator_trait.php');

/**
 * Pulse module instance generator.
 */
class mod_pulse_generator extends testing_module_generator {
    use behat_pulseaction_generator_trait;

    /**
     * Create pulse module instance.
     *
     * @param  mixed $record Module instance data.
     * @param  array|null $defaultoptions Default options.
     * @return void
     */
    public function create_instance($record = null, ?array $defaultoptions = null) {
        global $CFG;

        $record = (object) $record;
        $record->showdescription = 1;
        $record->pulse = 1;

        if (!isset($record->diff_pulse)) {
            $record->diff_pulse = 0;
        }
        if (!isset($record->completionbtn_content_editor)) {
            $record->completionbtn_content_editor = ['text' => '', 'format' => FORMAT_HTML];
        }

        $plugins = mod_pulse\plugininfo\pulseaddon::get_enabled_addons();
        foreach ($plugins as $plugin => $version) {
            if (!file_exists($CFG->dirroot . '/mod/pulse/addons/' . $plugin . '/tests/generator/lib.php')) {
                continue;
            }
            require_once($CFG->dirroot . '/mod/pulse/addons/' . $plugin . '/tests/generator/lib.php');
            $classname = 'pulseaddon_' . $plugin . '_generator';
            if (class_exists($classname) && method_exists($classname, 'default_value')) {
                $options = $classname::default_value();
                $record = (object) array_merge((array) $record, $options);
            }
        }

        $record = (object) array_merge((array) $record, $defaultoptions);
        return parent::create_instance($record, $defaultoptions);
    }

    /**
     * Create automation template.
     *
     * @param array $data
     * @throws \moodle_exception
     *
     */
    public function create_automation_template($data) {
        global $DB, $CFG;

        if ($DB->record_exists('pulse_autotemplates', ['reference' => $data['reference']])) {
            throw new \moodle_exception('Automation template with reference "' . $data['reference'] . '" already exists.');
        }

        $templatedata = array_filter((array) $data, function ($key) {
            return strpos($key, 'action') !== 0 && strpos($key, 'condition') !== 0;
        }, ARRAY_FILTER_USE_KEY);

        $templatedata['timemodified'] = time();
        $templatedata['categories'] = isset($data['categories']) ? json_encode($data['categories']) : json_encode([]);

        $templateid = $DB->insert_record('pulse_autotemplates', (object) $templatedata);

        // Process conditions.
        if (isset($data['condition'])) {
            $data['condition_type'] = $data['condition'];
            unset($data['condition']);
            $conditiondata = mod_pulse\automation\helper::filter_record_byprefix($data, 'condition');
            if (!empty($conditiondata)) {
                $this->create_condition($templateid, $conditiondata);
            }
        }
    }

    /**
     * Create automation instance.
     *
     * @param array $data
     * @throws \moodle_exception
     *
     */
    public function create_automation_instance($data) {
        global $DB;

        if ($DB->record_exists('pulse_autotemplates_ins', ['insreference' => $data['reference']])) {
            throw new \moodle_exception('Automation instance with reference "' . $data['reference'] . '" already exists.');
        }

        $insdata = ['templateid' => $data['templateid'], 'courseid' => $data['courseid'], 'timemodified' => time(), 'status' => 1];
        $instanceid = $DB->insert_record('pulse_autoinstances', (object) $insdata);

        $instancedata = array_filter((array) $data, function ($key) {
            return strpos($key, 'action') !== 0 && strpos($key, 'condition') !== 0;
        }, ARRAY_FILTER_USE_KEY);

        $instancedata['timemodified'] = time();
        $instancedata['insreference'] = $data['reference'];

        $DB->insert_record('pulse_autotemplates_ins', (object) array_merge($instancedata, ['instanceid' => $instanceid]));
    }
}
