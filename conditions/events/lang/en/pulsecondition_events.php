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
 * Strings for "events condition", language 'en'.
 *
 * @package   pulsecondition_events
 * @copyright 2023, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$string['affecteduser'] = 'Affected user';
$string['availableevents'] = 'Available events';
$string['availableeventsdesc'] = 'Select the events that should be available for pulse automation conditions. Only the selected events will be shown in the event selector when configuring pulse automation conditions.';
$string['eventcontext'] = 'Event context';
$string['eventcontext_help'] = 'Choose where the event should occur:<ul><li><strong>Course:</strong> The event can occur anywhere in the course</li><li><strong>Activity:</strong> The event must occur in specific activities (select below)</li></ul>';
$string['eventmatchtype'] = 'Event match type';
$string['eventmodule'] = 'Event module(s)';
$string['eventmodule_help'] = 'Select one or more activities where the selected event should trigger the automation. When multiple activities are selected, the event occurring in any one of them will trigger the condition. Leave empty to allow events from all activities.';
$string['eventscompletion'] = 'Events completion';
$string['eventscompletion_help'] = '<b>Events:</b> Triggers when a specific Moodle event occurs in the course.<b>Disabled:</b> Condition is off.<b>All:</b> Triggers for all matching events, including those that occurred before the instance was created.<i>Example: Instance created Jan 15. Event occurred on Jan 10. → Triggers immediately.</i><b>Upcoming:</b> Only triggers for events that occur after the instance is created.<i>Example: Instance created Jan 15. Event occurred on Jan 10. → No trigger. Event occurs on Jan 20. → Triggers.</i>';
$string['eventscontexts'] = 'Event contexts';
$string['eventscontexts_help'] = 'Choose where the selected events can trigger the pulse automation condition. <br><b>Everywhere:</b> Events that occur in the context of the course or any course activity trigger the condition. Note: This excludes events that occur at the system level or in other courses.<br><b>Selected activity:</b> Only events in the selected activities trigger the condition. If multiple activities are selected, use the "Module match type" setting to specify whether the event must occur in ANY (at least one) or ALL (every) selected activity.';
$string['eventscontextseverywhere'] = 'Course (including activities)';
$string['eventscontextsmoduleonly'] = 'Selected activities';
$string['matchall'] = 'All (event in all selected activities)';
$string['matchany'] = 'Any (event in at least one activity)';
$string['modulematchtype'] = 'Module match type';
$string['modulematchtype_help'] = 'Choose how the selected modules should be evaluated:<ul><li><strong>Any:</strong> The event needs to occur in at least ONE of the selected activities</li><li><strong>All:</strong> The event needs to occur in ALL of the selected activities</li></ul>';
$string['notifyuser'] = 'User';
$string['notifyuser_help'] = 'Choose which user context is used to evaluate the event.<br><b>Affected user:</b> Evaluates only the specific user who caused the event. This triggers a single action for that user.<br><b>Related user:</b> Evaluates a secondary user linked to the event action, if available.<br><b>All:</b> When any user triggers the event, the system performs the action for every enrolled user in the course individually.<br><b>Tip:</b> If you are unsure whether a user is the related or affected user of an event, you can find this information in the course or site logs for each tracked event.';
$string['pluginname'] = 'Events completion';
$string['privacy:metadata'] = 'The Events completion condition plugin does not store any personal user data.';
$string['relateduser'] = 'Related user';
$string['selectevent'] = 'Event';
$string['selectevent_help'] = 'Select the event from the available events on the Moodle site.';
$string['system'] = 'System';
