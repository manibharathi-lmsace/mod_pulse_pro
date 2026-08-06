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
 * Strings for "Activity conditions", language 'en'.
 *
 * @package   pulsecondition_activity
 * @copyright 2023, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$string['acompletionmethod'] = 'Method';
$string['acompletionmethod_count'] = 'Activity count';
$string['acompletionmethod_help'] = 'Choose how activities should be counted:<br>
    <b>Activity count:</b> The condition will trigger when a specified number of activities have been completed (regardless of which activities).<br>
    <b>Selected activities:</b> The condition will trigger based on specific activities you select.';
$string['acompletionmethod_selectactivity'] = 'Selected activities';
$string['activitycompletion'] = 'Activity completion';
$string['activitycompletion_help'] = '<b>Activity Completion:</b> Triggers when an activity is completed. Select the activity in the automation instance.

<b>Disabled:</b> Condition is off.

<b>All:</b> Triggers for everyone, including past completions.
<i>Example: Instance created Jan 15. User completed activity on Jan 10. → Triggers immediately.</i>

<b>Upcoming:</b> Only triggers for completions after the instance is created.
<i>Example: Instance created Jan 15. User completed activity on Jan 10. → No trigger. User completes another activity on Jan 20. → Triggers.</i>';
$string['activitycount'] = 'Number of activities';
$string['activitycount_help'] = 'Enter the number of activities that must be completed to trigger this condition. The condition will be met when the user completes this many activities (with completion enabled) in the course, regardless of which specific activities they complete.';
$string['activityoperator'] = 'Activities matching';
$string['activityoperator_all'] = 'ALL';
$string['activityoperator_any'] = 'ANY';
$string['activityoperator_help'] = 'Choose how the selected activities should be evaluated:<br>
    <b>ANY:</b> The condition will trigger when any one of the selected activities is completed.<br>
    <b>ALL:</b> The condition will trigger only when all of the selected activities are completed.';
$string['completionstatus'] = 'Completion status';
$string['completionstatus_completed'] = 'Completed';
$string['completionstatus_failed'] = 'Failed';
$string['completionstatus_help'] = 'Specify which completion status is required to trigger the condition:<br>
    <b>Completed:</b> All completion conditions of the activity must be met.<br>
    <b>Partially completed:</b> At least one but not all completion conditions are met.<br>
    <b>Failed:</b> The activity is completed but without achieving the passing grade.<br>
    <b>Passed:</b> The activity is completed with a passing grade.';
$string['completionstatus_partial'] = 'Partially completed';
$string['completionstatus_passed'] = 'Passed';
$string['pluginname'] = 'Activity completion';
$string['privacy:metadata'] = 'The Activity completion condition plugin does not store any personal user data.';
$string['selectactivity'] = 'Select activities';
$string['selectactivity_help'] = "You can configure the <b>Select Activities</b> setting when creating an instance on the course automation page.
    The Select Activities setting allows you to choose from all available activities within your course that have completion criteria configured.
    This selection determines which specific activities will trigger the automation when their completion conditions are met.";
$string['taskname'] = 'Activity partial-completion automation check';
