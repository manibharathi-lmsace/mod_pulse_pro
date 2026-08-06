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
 * Strings for "Course completion condition", language 'en'.
 *
 * @package   pulsecondition_course
 * @copyright 2023, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$string['coursecompletion'] = 'Course completion';
$string['coursecompletion_help'] = '<b>Course Completion:</b> Triggers when a user completes the course.

<b>Disabled:</b> Condition is off.

<b>All:</b> Triggers for everyone, including users who completed the course before the instance was created.
<i>Example: Instance created Jan 15. User completed course on Jan 10. → Triggers immediately.</i>

<b>Upcoming:</b> Only triggers for course completions after the instance is created.
<i>Example: Instance created Jan 15. User completed course on Jan 10. → No trigger. User completes course on Jan 20. → Triggers.</i>';
$string['pluginname'] = 'Course completion';
$string['privacy:metadata'] = 'The Course completion condition plugin does not store any personal user data.';
