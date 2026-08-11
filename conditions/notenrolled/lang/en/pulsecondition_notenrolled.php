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
 * Strings for the "Not enrolled" condition, language 'en'.
 *
 * @package   pulsecondition_notenrolled
 * @copyright 2026, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$string['condition'] = 'Not enrolled in course';
$string['condition_help'] = '<b>Not enrolled:</b> Triggers when a user has no active enrolment in the configured course(s).

<b>Disabled:</b> Condition is off.

<b>All:</b> Applies to all users, including those who signed up before the instance was created.

<b>Upcoming:</b> Only applies to users who signed up after the instance was created.

Combine this condition with an account-age window to reach users who recently signed up but have not enrolled, or combine it with the Cohort or Course completion conditions for marketing and follow-up campaigns.';
$string['courses'] = 'Courses';
$string['courses_help'] = 'The course(s) the user must NOT be enrolled in for the condition to be satisfied. Only used when the enrolment scope is set to "Specific course(s)".';
$string['notenrolled:manage'] = 'Configure the "Not enrolled" automation condition';
$string['pluginname'] = 'Not enrolled';
$string['scope'] = 'Enrolment scope';
$string['scope_help'] = '<b>Any course:</b> The condition is satisfied only when the user has no active enrolment in any course on the site.

<b>Specific course(s):</b> The condition is satisfied when the user is not actively enrolled in the selected course(s). Use this for follow-up campaigns, e.g. "completed course A and not yet enrolled in course B".';
$string['scopeany'] = 'Any course';
$string['scopespecific'] = 'Specific course(s)';
$string['taskname'] = 'Pulse "Not enrolled" condition scan';
$string['window'] = 'Minimum account age';
$string['window_help'] = 'Only consider users whose account is at least this old. The condition is satisfied once <code>user.timecreated + minimum age &le; now</code>. Combine with the notification action&rsquo;s <em>Delay base = Last condition</em> to time the message at exactly signup + this duration (e.g. set this to 1 week to nudge users that still have not enrolled one week after signing up). Leave at zero to ignore account age entirely.';
