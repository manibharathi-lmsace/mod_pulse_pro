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
 * Credits override management page.
 *
 * @package   pulseaction_credits
 * @copyright 2025 bdecent GmbH <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use core\context\course;
use core\context\system;
use core_reportbuilder\system_report_factory;
use pulseaction_credits\systemreports\schedule;
use pulseaction_credits\local\override_manager;

$courseid = optional_param('courseid', 0, PARAM_INT);
$scheduleid = optional_param('scheduleid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$overridecredit = optional_param('overridecredit', '', PARAM_FLOAT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

// Set up page context and authentication.
if ($courseid > 0) {
    $course = get_course($courseid);
    require_login($course);
    $context = course::instance($courseid);
    $PAGE->set_context($context);
    $PAGE->set_course($course);
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->set_title(get_string('overridecredit', 'pulseaction_credits') . ": " . format_string($course->fullname));
    $pageurl = new moodle_url('/mod/pulse/actions/credits/override.php', ['courseid' => $courseid]);
} else {
    require_login();
    $course = get_course(SITEID);
    $context = system::instance();
    $PAGE->set_context($context);
    $PAGE->set_course($course);
    $PAGE->set_heading(get_string('overridecredit', 'pulseaction_credits'));
    $PAGE->set_title(get_string('overridecredit', 'pulseaction_credits'));
    $pageurl = new moodle_url('/mod/pulse/actions/credits/override.php');
}

// Check permissions.
if (!override_manager::can_override_credits($courseid ?: SITEID)) {
    throw new moodle_exception('nopermissions', 'error', '', 'pulseaction/credits:override');
}

$PAGE->set_url($pageurl);

$PAGE->navbar->add(get_string('overridecredit', 'pulseaction_credits'));

// Handle actions.
if ($action && confirm_sesskey()) {
    switch ($action) {
        case 'override':
            if ($scheduleid && $overridecredit !== '') {
                try {
                    override_manager::create_override($scheduleid, $overridecredit);
                    \core\notification::success(get_string('overridesaved', 'pulseaction_credits'));
                } catch (Exception $e) {
                    \core\notification::error($e->getMessage());
                }
            }
            break;
    }

    redirect($returnurl ?: $pageurl);
}

echo $OUTPUT->header();

// Display course title.
if ($courseid > 0) {
    echo $OUTPUT->heading(get_string('overridecredit', 'pulseaction_credits'), 3);
}

// Render the system report.
$report = system_report_factory::create(
    schedule::class,
    $context,
    'pulseaction_credits',
    '',
    0,
    ['courseid' => $courseid]
);

echo $report->output();

// Initialize the edit user credits functionality.
$PAGE->requires->js_call_amd('pulseaction_credits/edit_user_credits', 'init', [
    'title' => get_string('editusercredits', 'pulseaction_credits')]);

echo $OUTPUT->footer();
