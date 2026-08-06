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
 * Definition restore structure steps.
 *
 * @package   mod_pulse
 * @copyright 2021, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step to restore pulse course.
 */
class restore_pulse_course_structure_step extends restore_activity_structure_step {
    /**
     * Restore steps structure definition.
     */
    protected function define_structure() {
        static $cache;

        $paths = [];
        // Restore path.

        if (isset($cache[$this->get_restoreid()]) && $cache[$this->get_restoreid()]) {
            return $paths;
        }

        $instances = new restore_path_element(
            'pulse_autoinstances',
            '/activity/pulse_autoinstances'
        );

        $paths[] = $instances;

        $paths[] = new restore_path_element(
            'pulse_autotemplates',
            '/activity/pulse_autoinstances/automationtemplates/pulse_autotemplates'
        );

        $paths[] = new restore_path_element(
            'pulse_autotemplates_ins',
            '/activity/pulse_autoinstances/automationtemplateinstance/pulse_autotemplates_ins'
        );
        $paths[] = new restore_path_element(
            'pulse_condition_overrides',
            '/activity/pulse_autoinstances/pulseconditionoverrides/pulse_condition_overrides'
        );

        $this->add_subplugin_structure('pulseaction', $instances);

        $cache[$this->get_restoreid()] = true;

        // Return the paths wrapped into standard activity structure.
        return $paths;
    }

    /**
     * Process activity pulse restore.
     * @param mixed $data restore pulse table data.
     */
    protected function process_pulse_autoinstances($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->courseid = $this->get_courseid();
        // All the status of the instance is disabled initialy.
        $data->status = \mod_pulse\automation\instances::STATUS_DISABLE;

        // Insert instance into Database.
        $newitemid = $DB->insert_record('pulse_autoinstances', $data);

        // Create a mapping for the instance.
        $this->set_mapping('pulse_autoinstances', $oldid, $newitemid, true);
    }

    /**
     * Process pulse users records.
     *
     * @param object $data The data in object form
     * @return void
     */
    protected function process_pulse_autotemplates($data) {
        global $DB;

        $data = (object) $data;
        $oldtemplateid = $data->id;

        $record = $DB->get_record('pulse_autotemplates', ['reference' => $data->reference]);
        if (empty($record)) {
            $templateid = $DB->insert_record('pulse_autotemplates', $data);
            $record = $data;
        } else {
            $templateid = $record->id;
        }

        // Update the parent instance templateid.
        if ($DB->record_exists('pulse_autoinstances', ['id' => $this->get_new_parentid('pulse_autoinstances')])) {
            $categories = json_decode($record->categories, true);

            // In array of categories.
            $category = get_course($this->get_courseid())->category;

            if (!empty($categories) && !in_array($category, $categories)) {
                $status = \mod_pulse\automation\instances::STATUS_ORPHANED;
                $DB->set_field('pulse_autoinstances', 'status', $status, ['id' => $this->get_new_parentid('pulse_autoinstances')]);
            }
        }

        $this->set_mapping('pulse_autotemplates', $oldtemplateid, $templateid);
    }

    /**
     * Process pulse users records.
     *
     * @param object $data The data in object form
     * @return void
     */
    protected function process_pulse_autotemplates_ins($data) {
        global $DB;

        $data = (object) $data;
        $data->instanceid = $this->get_mappingid('pulse_autoinstances', $data->instanceid);

        $DB->insert_record('pulse_autotemplates_ins', $data);
        // No need to save this mapping as far as nothing depend on it
        // (child paths, file areas nor links decoder).
    }

    /**
     * Process pulse pro features restore structures.
     * Pro feature.
     * @param  mixed $data
     * @return void
     */
    protected function process_pulse_condition_overrides($data) {
        global $DB;
        $data = (object) $data;

        $data->instanceid = $this->get_mappingid('pulse_autoinstances', $data->instanceid);

        $DB->insert_record('pulse_condition_overrides', $data);
    }

    /**
     * Update the files of editors after restore execution.
     *
     * @return void
     */
    protected function after_restore() {
        global $DB;

        $instances = $DB->get_records('backup_ids_temp', [
            'backupid' => $this->get_restoreid(), 'itemname' => 'pulse_autoinstances',
        ]);

        foreach ($instances as $instance) {
            $oldinstances = $DB->get_records('pulse_condition_overrides', ['instanceid' => $instance->newitemid]);
            foreach ($oldinstances as $ins) {
                $additional = $ins->additional ? json_decode($ins->additional, true) : [];
                $needsupdate = false;

                // Handle modules.
                if (isset($additional['modules']) && !empty($additional['modules'])) {
                    if (!is_array($additional['modules'])) {
                        $additional['modules'] = [$additional['modules']];
                    }
                    foreach ($additional['modules'] as $key => $moduleid) {
                        $newcmid = $ins->triggercondition == 'session'
                            ? $this->get_mappingid('facetoface', $moduleid, $moduleid)
                            : $this->get_mappingid('course_module', $moduleid, $moduleid);

                        $additional['modules'][$key] = $newcmid;
                    }
                    $additional['modules'] = $additional['modules'] ? array_unique($additional['modules']) : [];
                    $needsupdate = true;
                }

                // Handle groups.
                if (isset($additional['groups']) && !empty($additional['groups'])) {
                    if (!is_array($additional['groups'])) {
                        $additional['groups'] = [$additional['groups']];
                    }
                    foreach ($additional['groups'] as $key => $groupid) {
                        $newgroupid = $this->get_mappingid('group', $groupid, $groupid);
                        $additional['groups'][$key] = $newgroupid;
                    }
                    $additional['groups'] = $additional['groups'] ? array_unique($additional['groups']) : [];
                    $needsupdate = true;
                }

                // Handle groupings.
                if (isset($additional['groupings']) && !empty($additional['groupings'])) {
                    if (!is_array($additional['groupings'])) {
                        $additional['groupings'] = [$additional['groupings']];
                    }
                    foreach ($additional['groupings'] as $key => $groupingid) {
                        $newgroupingid = $this->get_mappingid('grouping', $groupingid, $groupingid);
                        $additional['groupings'][$key] = $newgroupingid;
                    }
                    $additional['groupings'] = $additional['groupings'] ? array_unique($additional['groupings']) : [];
                    $needsupdate = true;
                }

                if ($needsupdate) {
                    $additional = json_encode($additional);
                    $DB->set_field('pulse_condition_overrides', 'additional', $additional, ['id' => $ins->id]);
                }
            }

            // Update the new instance template ids.
            $autoinstance = $DB->get_record('pulse_autoinstances', ['id' => $instance->newitemid]);
            $newtemplateid = $this->get_mappingid('pulse_autotemplates', $autoinstance->templateid);
            $DB->set_field('pulse_autoinstances', 'templateid', $newtemplateid, ['id' => $autoinstance->id]);
        }
    }

    /**
     * Decode the pulse action restore contents.
     *
     * @param array $contents
     * @return void
     */
    public static function decode_contents(&$contents) {
        // Get all the restore path elements, looking across all the subplugin dirs.
        $subplugintype = 'pulseaction';
        $subpluginsdirs = core_component::get_plugin_list($subplugintype);
        foreach ($subpluginsdirs as $name => $subpluginsdir) {
            $classname = 'restore_' . $subplugintype . '_' . $name . '_subplugin';
            $restorefile = $subpluginsdir . '/backup/moodle2/' . $classname . '.class.php';
            if (file_exists($restorefile)) {
                require_once($restorefile);
                $classname::decode_contents($contents);
            }
        }
    }
}
