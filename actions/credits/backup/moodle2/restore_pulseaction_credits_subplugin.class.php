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
 * This file contains the class for restore of this pulse action credits plugin
 *
 * @package   pulseaction_credits
 * @copyright 2025, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore pulse action subplugin class.
 *
 */
class restore_pulseaction_credits_subplugin extends restore_subplugin {
    /**
     * Returns the paths to be handled by the subplugin.
     * @return array
     */
    protected function define_pulse_autoinstances_subplugin_structure() {

        $paths = [];

        // We used get_recommended_name() so this works.
        $paths[] = new restore_path_element(
            'pulseaction_credits',
            '/activity/pulse_autoinstances/creditsaction/pulseaction_credits'
        );

        $paths[] = new restore_path_element(
            'pulseaction_credits_ins',
            '/activity/pulse_autoinstances/creditsactionins/pulseaction_credits_ins'
        );

        return $paths;
    }

    /**
     * Processes one pulseaction_credits element
     * @param mixed $data
     * @return void
     */
    public function process_pulseaction_credits($data) {
        global $DB;

        $data = (object) $data;
        // Get the new template id.
        $data->templateid = $this->get_mappingid('pulse_autotemplates', $data->templateid);

        // If already credits is created for template then no need to include again.
        if (!$DB->record_exists('pulseaction_credits', ['templateid' => $data->templateid])) {
            $DB->insert_record('pulseaction_credits', $data);
        }
    }

    /**
     * Processes one pulseaction_credits_ins element
     * @param mixed $data
     * @return void
     */
    public function process_pulseaction_credits_ins($data) {
        global $DB;

        $data = (object)$data;

        $data->instanceid = $this->get_new_parentid('pulse_autoinstances');

        if (!$DB->record_exists('pulseaction_credits_ins', ['instanceid' => $data->instanceid])) {
            $DB->insert_record('pulseaction_credits_ins', $data);
        }
    }

    /**
     * Decode contents.
     *
     * @param array $contents
     * @return void;
     */
    public static function decode_contents(&$contents) {
    }
}
