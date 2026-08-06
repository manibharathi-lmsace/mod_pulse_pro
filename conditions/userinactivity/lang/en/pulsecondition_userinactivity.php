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
 * Language strings for user inactivity condition.
 *
 * @package   pulsecondition_userinactivity
 * @copyright 2025, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activityperiod'] = 'Activity period';
$string['activityperiod_help'] = 'Requires any activity from the user in this duration period. Only available if "Require previous activity" is enabled.';
$string['allactivities'] = 'All activities';
$string['basedonaccess'] = 'Based on access';
$string['basedonaccess_help'] = 'Triggers if the student has not opened the course during the time specified below.';
$string['basedoncompletion'] = 'Based on activity completion';
$string['basedoncompletion_help'] = 'Triggers if the student has not completed any activity in the time specified below.';
$string['basedoncompletionconditions'] = 'Based on activity completion conditions';
$string['basedoncompletionconditions_help'] = 'Triggers if the student has not completed any activity completion condition in the time specified below.';
$string['completionrelevantactivities'] = 'Completion relevant activities';
$string['completionrelevantactivities_help'] = 'Only completion of activities that are part of course completion conditions count towards the activity.';
$string['disabled'] = 'Disabled';
$string['inactivityperiod'] = 'Inactivity period';
$string['inactivityperiod_help'] = 'Determines how long the student has to be inactive before the condition triggers an action.';
$string['includedactivities'] = 'Included activities';
$string['includedactivities_help'] = 'Determines which activities count for the inactivity monitoring.';
$string['pluginname'] = 'User inactivity';
$string['privacy:metadata'] = 'The user inactivity condition plugin does not store any personal user data.';
$string['requirepreviousactivity'] = 'Require previous activity';
$string['requirepreviousactivity_help'] = 'If enabled, the condition will only trigger if the user had been active previously within the activity period defined below.';
$string['taskuserinactivity'] = 'Check user inactivity conditions';
$string['userinactivity'] = 'User inactivity';
$string['userinactivity_help'] = 'This condition triggers when a user has been inactive for a specified period. You can configure different types of inactivity monitoring.';
