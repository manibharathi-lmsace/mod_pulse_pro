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
 * Dynamic form for editing user credits.
 *
 * @package   pulseaction_credits
 * @copyright 2025 bdecent GmbH <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulseaction_credits\form;

use core_form\dynamic_form;
use moodle_url;
use context;
use context_course;
use pulseaction_credits\local\override_manager;
use pulseaction_credits\local\credits;

/**
 * Dynamic form for editing user credits.
 */
class edit_user_credits extends dynamic_form {
    /**
     * Returns form context
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        $courseid = $this->optional_param('courseid', 0, PARAM_INT);
        if ($courseid) {
            return context_course::instance($courseid);
        }
        return \context_system::instance();
    }

    /**
     * Checks if current user has access to this form, otherwise throws exception
     *
     */
    protected function check_access_for_dynamic_submission(): void {
        $courseid = $this->optional_param('courseid', 0, PARAM_INT);
        $context = $courseid ? context_course::instance($courseid) : \context_system::instance();
        require_capability('pulseaction/credits:manage', $context);
    }

    /**
     * Process the form submission.
     *
     * @return mixed
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();

        try {
            $success = override_manager::edit_user_credits(
                $data->userid,
                $data->courseid ?: SITEID,
                $data->credits,
                $data->note
            );

            if ($success) {
                return [
                    'result' => true,
                    'message' => get_string('creditsupated', 'pulseaction_credits'),
                    'newcredits' => $data->credits,
                ];
            } else {
                return ['result' => false, 'message' => get_string('errorupdatingcredits', 'pulseaction_credits')];
            }
        } catch (\Exception $e) {
            return ['result' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Load in existing data as form defaults
     *
     */
    public function set_data_for_dynamic_submission(): void {
        $userid = $this->optional_param('userid', 0, PARAM_INT);
        $courseid = $this->optional_param('courseid', 0, PARAM_INT);

        if ($userid) {
            $creditsobj = new credits();
            $currentcredits = $creditsobj->get_user_credits($userid);

            $this->set_data([
                'userid' => $userid,
                'courseid' => $courseid,
                'credits' => $currentcredits,
                'currentcredits' => $currentcredits,
            ]);
        }
    }

    /**
     * Returns form URL
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        $courseid = $this->optional_param('courseid', 0, PARAM_INT);
        return new moodle_url('/mod/pulse/actions/credits/override.php', ['courseid' => $courseid]);
    }

    /**
     * Form definition
     */
    protected function definition() {
        $mform = $this->_form;
        $userid = $this->optional_param('userid', 0, PARAM_INT);
        $courseid = $this->optional_param('courseid', 0, PARAM_INT);

        // Get user information for display.
        if ($userid) {
            global $DB;
            $user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, email');
            if ($user) {
                $mform->addElement('static', 'userinfo', get_string('user'), fullname($user) . ' (' . $user->email . ')');
            }

            // Get current credits for display.
            $creditsobj = new credits();
            $currentcredits = $creditsobj->get_user_credits($userid);

            $mform->addElement(
                'static',
                'currentcredits_display',
                get_string('currentcredits', 'pulseaction_credits'),
                number_format($currentcredits, 2)
            );
        }

        // Credits.
        $mform->addElement('text', 'credits', get_string('newcredits', 'pulseaction_credits'));
        $mform->setType('credits', PARAM_FLOAT);
        $mform->addRule('credits', get_string('required'), 'required', null, 'client');
        $mform->addRule('credits', get_string('invalidcredits', 'pulseaction_credits'), 'numeric', null, 'client');

        // Notes.
        $mform->addElement(
            'textarea',
            'note',
            get_string('note', 'pulseaction_credits'),
            ['rows' => 3, 'cols' => 50]
        );
        $mform->setType('note', PARAM_TEXT);
        $mform->addHelpButton('note', 'note', 'pulseaction_credits');

        $mform->addElement('hidden', 'userid', $userid);
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'currentcredits');
        $mform->setType('currentcredits', PARAM_INT);
    }

    /**
     * Form validation
     *
     * @param array $data Array of submitted data.
     * @param array $files Array of uploaded files.
     *
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (isset($data['credits']) && ($data['credits'] < 0 || credits::verify_is_validcredits($data['credits']) === false)) {
            $errors['credits'] = get_string('invalidcredits', 'pulseaction_credits');
        }

        return $errors;
    }
}
