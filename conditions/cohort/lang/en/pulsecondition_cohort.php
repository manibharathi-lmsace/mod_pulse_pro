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
 * Strings for "Cohort conditions", language 'en'.
 *
 * @package   pulsecondition_cohort
 * @copyright 2023, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$string['cohorts'] = 'Cohorts';
$string['cohorts_help'] = 'You can configure the <b>Cohorts</b> setting when creating an instance on the course automation page.
    The Cohorts setting allows you to select from all available cohorts within your system. This selection determines which specific
    cohort will trigger the automation when a user is added as a member of that cohort.';
$string['condition'] = 'Member in cohorts';
$string['condition_help'] = '<b>Cohort Membership:</b> Triggers if the user is a member of a selected cohort.

<b>Disabled:</b> Condition is off.

<b>All:</b> Triggers for all cohort members, including those added before the instance was created.
<i>Example: Instance created Jan 15. User joined cohort on Jan 10. → Triggers immediately.</i>

<b>Upcoming:</b> Only triggers for users added to the cohort after the instance is created.
<i>Example: Instance created Jan 15. User joined cohort on Jan 10. → No trigger. User joins cohort on Jan 20. → Triggers.</i>';
$string['pluginname'] = 'Cohort member';
$string['privacy:metadata'] = 'The Cohort member condition plugin does not store any personal user data.';
