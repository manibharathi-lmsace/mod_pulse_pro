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
 * Language strings for the credits pulse action plugin.
 *
 * @package   pulseaction_credits
 * @copyright 2025 bdecent GmbH <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actiondatanotsave'] = 'Could not save credits action data.';
$string['actionstatus'] = 'Credit Status';
$string['addcredits'] = 'Add credits';
$string['allocatecredits'] = 'Allocate credits';
$string['allocated'] = 'Allocated';
$string['allocatedtime'] = 'Allocated time';
$string['allocationdatetime'] = 'Allocation Date/Time';
$string['allocationmethod'] = 'Allocation method';
$string['allocationmethod_help'] = 'Choose whether to add credits to the existing balance or replace the current balance.';
$string['allocationstatus'] = 'Allocation status';
$string['applyoverride'] = 'Apply Override';
$string['automationinstance'] = 'Automation instance';
$string['automationreference'] = 'Automation reference';
$string['automationreportname'] = 'Credits allocations schedule report';
$string['automationtemplate'] = 'Automation template';
$string['automationtitle'] = 'Automation title';
$string['basedate'] = 'Base date';
$string['basedate_help'] = 'Choose whether to calculate dates relative to user enrollment or use a fixed date.';
$string['basedateconfig'] = 'Base Date Configuration';
$string['basedatefixed'] = 'Fixed date';
$string['basedaterelative'] = 'Relative to enrollment';
$string['bulkoverridecredit'] = 'Override credits';
$string['bulkoverrideerrors'] = '{$a} allocations could not be overridden';
$string['bulkoverridesuccess'] = 'Successfully overridden {$a} allocations';
$string['cannotoverrideprocessed'] = 'Cannot override allocations that have already been processed';
$string['cannotremoveoverride'] = 'Failed to remove override credits';
$string['cannotupdateoverride'] = 'Failed to update override credits';
$string['creditamount'] = 'Credit Amount';
$string['creditfield'] = 'Credit profile field';
$string['creditfield_desc'] = 'Select the user profile field that stores credit information.';
$string['creditfieldnotfound'] = 'Credit profile field not found. Please check your pulse settings.';
$string['creditinstance'] = 'Credit instance';
$string['creditreportdatasource'] = 'Credit Report Data';
$string['credits'] = 'Credits';
$string['credits:manage'] = 'Manage credit allocations';
$string['credits:override'] = 'Override credit allocations';
$string['credits:receivecredits'] = 'Receive credit allocations';
$string['credits_help'] = 'Number of credits to allocate to users.';
$string['creditsallocationschdule'] = 'Credits allocation schedule';
$string['creditsapplied'] = 'Credits applied';
$string['creditshedulereport'] = 'Credits schedules';
$string['creditsinstructions'] = 'You currently have {$a} credits available. If you want more, Please contact the site administrator.';
$string['creditsoverride'] = 'Credits override';
$string['creditsupated'] = 'Credits updated successfully';
$string['currentcredits'] = 'Current Credits';
$string['disabled'] = 'Disabled';
$string['editcreditsfor'] = 'Edit Credits for {$a}';
$string['editoverridecredit'] = 'Click to edit override credits';
$string['editoverridecredit_help'] = 'Change the credit credits for this allocation. Only planned allocations can be overridden.';
$string['editusercredits'] = 'Edit user credits';
$string['enabled'] = 'Enabled';
$string['errorlog'] = 'Error log';
$string['errorupdatingcredits'] = 'Error updating credits';
$string['event:creditallocated'] = 'Credit allocated to user';
$string['event:creditoverridden'] = 'Credit allocation overridden';
$string['failed'] = 'Failed';
$string['fixedbasedate'] = 'Fixed Base Date';
$string['fixeddate'] = 'Fixed date';
$string['formtab'] = 'Credits';
$string['hidden'] = 'Hidden';
$string['insreference'] = 'Instance reference';
$string['instanceid'] = 'Instance ID';
$string['institle'] = 'Instance title';
$string['internalnotes'] = 'Internal Notes';
$string['interval'] = 'Interval';
$string['interval_help'] = '
Choose the interval for allocation credits to users.:
<br><b>Once</b>: Allocate the credits only one time.<br><b>Daily</b>: Allocate the credits every day at the time selected below.
<br><b>Weekly</b>: Allocate the credits every week on the day of the week and time of below.<br><b>Monthly</b>: Allocate the credits every month on the day of the month and time of below.
<br><b>Custom (crontab) </b>:Use standard crontab syntax to define a custom schedule. Each field can contain:<ul>
<li><b>*</b> - Any value (wildcard)</li><li><b>Number</b> - Specific value (e.g., 5)</li><li><b>Range</b> - Range of values (e.g., 1-5)</li><li><b>List</b> - Multiple values separated by commas (e.g., 1,3,5)</li></ul>';
$string['intervalcustom'] = 'Custom (Crontab)';
$string['intervaldaily'] = 'Daily';
$string['intervalmonthly'] = 'Monthly';
$string['intervalonce'] = 'Once';
$string['intervalweekly'] = 'Weekly';
$string['intervalyearly'] = 'Yearly';
$string['invalidcredits'] = 'Invalid credits. Credits must be greater than or equal to zero';
$string['invalidcrontab'] = 'Invalid crontab syntax in {$a} field. Please check the format and value ranges.';
$string['invalidoverridecredit'] = 'Invalid override credits. Credits must be greater than or equal to zero';
$string['newcredits'] = 'New Credits';
$string['nocreditfield'] = 'No credit profile field configured. Please configure a credit field in the pulse settings.';
$string['nocreditprofilefield'] = 'Credit profile field is not configured. Please contact the site administrator.';
$string['nooverride'] = 'No override';
$string['notconfigured'] = 'Not Configured';
$string['note'] = 'Note';
$string['note_help'] = 'Optional note explaining the reason for this credit change.';
$string['noteplaceholder'] = 'Optional note explaining the reason for this credit change...';
$string['onhold'] = 'On Hold';
$string['overridden'] = 'Overridden';
$string['override'] = 'Override';
$string['overridecredit'] = 'Override credits';
$string['overridedeleted'] = 'Override deleted successfully';
$string['overridesaved'] = 'Override saved successfully';
$string['overridestatus'] = 'Override status';
$string['overridestatus_help'] = 'Shows whether this allocation has been overridden. Only planned allocations can be overridden.';
$string['planned'] = 'Planned';
$string['plugin:settingname'] = 'Credits display';
$string['pluginname'] = 'Credits';
$string['privacy:metadata:credits_override'] = 'Stores overrides applied to individual credit allocation schedules.';
$string['privacy:metadata:credits_override:overriddenby'] = 'The ID of the user who applied the override.';
$string['privacy:metadata:credits_override:overridecredit'] = 'The overridden credit amount.';
$string['privacy:metadata:credits_override:scheduledcredit'] = 'The original scheduled credit amount.';
$string['privacy:metadata:credits_override:scheduleid'] = 'The ID of the credit schedule that was overridden.';
$string['privacy:metadata:credits_override:timecreated'] = 'The time when the override was created.';
$string['privacy:metadata:credits_override:userid'] = 'The ID of the user whose allocation was overridden.';
$string['privacy:metadata:credits_sch'] = 'Stores per-user credit allocation schedules created by automation instances.';
$string['privacy:metadata:credits_sch:credits'] = 'The number of credits scheduled to be allocated.';
$string['privacy:metadata:credits_sch:instanceid'] = 'The ID of the automation instance that created this schedule.';
$string['privacy:metadata:credits_sch:scheduletime'] = 'The time when the credit allocation is scheduled.';
$string['privacy:metadata:credits_sch:status'] = 'The status of the credit allocation schedule.';
$string['privacy:metadata:credits_sch:timecreated'] = 'The time when the schedule record was created.';
$string['privacy:metadata:credits_sch:userid'] = 'The ID of the user receiving the credit allocation.';
$string['privacy:metadata:credits_user_override'] = 'Stores user-level credit overrides applied directly to a user\'s credit balance for a course.';
$string['privacy:metadata:credits_user_override:courseid'] = 'The ID of the course in which the override applies.';
$string['privacy:metadata:credits_user_override:newcredits'] = 'The credit balance after the override.';
$string['privacy:metadata:credits_user_override:oldcredits'] = 'The credit balance before the override.';
$string['privacy:metadata:credits_user_override:overriddenby'] = 'The ID of the user who applied the override.';
$string['privacy:metadata:credits_user_override:timecreated'] = 'The time when the override was applied.';
$string['privacy:metadata:credits_user_override:userid'] = 'The ID of the user whose credits were overridden.';
$string['recipients'] = 'Recipients';
$string['recipients_help'] = 'Select which user roles should receive credit allocations.';
$string['replacecredits'] = 'Replace credits';
$string['schedulecreatedtime'] = 'Schedule created time';
$string['scheduledcredits'] = 'Scheduled credits';
$string['scheduledtime'] = 'Scheduled time';
$string['schedulemanagement'] = 'Schedule Management';
$string['schedulemanagement_desc'] = 'View and override scheduled credit allocations';
$string['scheduleoverride'] = 'Credits override';
$string['schedulestats'] = 'Schedules: {$a->total} total, {$a->overrides} overrides, {$a->planned} planned, {$a->allocated} allocated, {$a->failed} failed';
$string['selectschedule'] = 'Select schedule';
$string['sending'] = 'Sending';
$string['showcredits'] = 'Show credits in popover menu';
$string['showcredits_desc'] = 'Display user credits in the Menu bar for all logged-in users.';
$string['status'] = 'Status';
$string['status_help'] = 'Enable or disable credit allocation for this automation rule.';
$string['statusallocated'] = 'Allocated';
$string['statusfailed'] = 'Failed';
$string['statusplanned'] = 'Planned';
$string['templatetitle'] = 'Template title';
$string['tempreference'] = 'Template reference';
$string['timecreated'] = 'Time created';
$string['unknown'] = 'Unknown';
$string['updatecredits'] = 'Update Credits';
$string['usercreditsallocation'] = 'User credits allocation';
$string['visibility'] = 'Visibility';
$string['visible'] = 'Visible';
