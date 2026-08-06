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
 * Strings for "Enrolment conditions", language 'en'.
 *
 * @package   pulsecondition_enrolment
 * @copyright 2023, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$string['enrolment'] = 'User enrolment';
$string['enrolment_help'] = '<b>User Enrolment:</b> Triggers when a user is enrolled in the course.

<b>Disabled:</b> Condition is off.

<b>All:</b> Triggers for all enrolled users, including those enrolled before the instance was created.
<i>Example: Instance created Jan 15. User enrolled on Jan 10. → Triggers immediately.</i>

<b>Upcoming:</b> Only triggers for users enrolled after the instance is created.
<i>Example: Instance created Jan 15. User enrolled on Jan 10. → No trigger. User enrolls on Jan 20. → Triggers.</i>';
$string['pluginname'] = 'User enrolment';
$string['privacy:metadata'] = 'The User enrolment condition plugin does not store any personal user data.';
