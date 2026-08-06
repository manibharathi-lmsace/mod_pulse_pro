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
 * Strings for "Group conditions", language 'en'.
 *
 * @package   pulsecondition_coursegroup
 * @copyright 2025 bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

// Language strings for Course group condition.

$string['anygroup'] = 'Any group';
$string['coursegroup'] = 'Course group';
$string['coursegroup_help'] = '<b>Course Group:</b> Triggers based on the user\'s group membership in the course.

<b>Disabled:</b> Condition is off.

<b>All:</b> Triggers for all users matching the group criteria, including those added to groups before the instance was created.
<i>Example: Instance created Jan 15. User added to group on Jan 10. → Triggers immediately.</i>

<b>Upcoming:</b> Only triggers for users added to groups after the instance is created.
<i>Example: Instance created Jan 15. User added to group on Jan 10. → No trigger. User added to group on Jan 20. → Triggers.</i>

<b>Note:</b> For "No group" type with Upcoming, only newly enrolled users (after instance creation) who have no group will trigger.';
$string['grouptype'] = 'Course group type';
$string['grouptype_help'] = 'Select whether to target users without a group, in any group, or in selected groups/groupings.';
$string['nogroup'] = 'No group';
$string['nogroupings'] = 'No groupings';
$string['nogroups'] = 'No groups';
$string['pluginname'] = 'Course group';
$string['privacy:metadata'] = 'The Course group condition plugin does not store any personal user data.';
$string['selectedgroupings'] = 'Selected groupings';
$string['selectedgroups'] = 'Selected groups';
$string['selectgroupings'] = 'Select groupings';
$string['selectgroupings_help'] = 'Select one or more groupings of groups.';
$string['selectgroups'] = 'Select groups';
$string['selectgroups_help'] = 'Select one or more course groups.';
$string['type'] = 'Type';
$string['type_help'] = '<b>No group:</b> Triggers automation only for users who are not members of any group.<br><b>Any group:</b> Triggers automation for users who belong to any group in the course.<br><b>Select groups:</b> Triggers automation for users who are members of at least one selected group. (If no group is selected, it has no effect.)<br><b>Selected groupings:</b> Triggers automation for users who are members of at least one group within the selected groupings. (If no grouping is selected, it has no effec';
