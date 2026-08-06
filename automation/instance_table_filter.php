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
 * Notification pulse action - Automation lib - Manage instance table filter.
 *
 * @package   mod_pulse
 * @copyright 2023, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Filter form for the instance management table.
 */
class manage_instance_table_filter extends \moodleform {
    /**
     * Filter form elements defined.
     *
     * @return void
     */
    public function definition() {
        global $DB;
        $mform =& $this->_form;

        // Set the id of template.
        $mform->addElement('hidden', 'id', $this->_customdata['id'] ?? 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('html', html_writer::tag('h3', get_string('filter')));
        $list = [0 => get_string('all')] + core_course_category::make_categories_list();
        $mform->addElement('autocomplete', 'category', get_string('category'), $list);

        $courses = $DB->get_records_sql('SELECT id, fullname FROM {course} WHERE id <> 1 AND visible != 0', []);
        foreach ($courses as $id => $course) {
            $courselist[$id] = $course->fullname;
        }
        $all = [0 => get_string('all')];
        $courselists = (!empty($courselist)) ? $all + $courselist : $all;
        $mform->addElement('autocomplete', 'course', get_string('coursename', 'pulse'), $courselists);

        $mform->addElement('text', 'numberofinstance', get_string('numberofinstance', 'pulse'));
        $mform->setType('numberofinstance', PARAM_ALPHANUM); // To use 0 for filter not used param_int.
        $mform->setDefault('numberofinstace', '');

        // Number of overrides.
        $mform->addElement('text', 'numberofoverrides', get_string('numberofoverrides', 'pulse'));
        $mform->setType('numberofoverrides', PARAM_ALPHANUM); // To use 0 for filter not used param_int.
        $mform->setDefault('numberofoverrides', '');

        $this->add_action_buttons(false, get_string('filter'));
    }
}
