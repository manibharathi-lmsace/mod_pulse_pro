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
 * Conditions - Pulse condition class for the "Course Completion".
 *
 * @package   pulsecondition_events
 * @copyright 2023, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulsecondition_events;

use context_course;
use core\context\user;
use core\reportbuilder\local\entities\context;
use mod_pulse\automation\condition_base;

/**
 * Automation events completion condition form.
 */
class conditionform extends \mod_pulse\automation\condition_base {
    /**
     * Repersents the affected user receive the notification.
     * @var int
     */
    const AFFECTED_USER = 1;

    /**
     * Repersents the related user receive the notification.
     * @var int
     */
    const RELATED_USER = 2;

    /**
     * Repersents all users receive the notification.
     *
     * @var int
     */
    const ALL_USER = 3;

    /**
     * Events will be observe the own course event and core events.
     *
     * @var int
     */
    const EVENTSCONTEXT_NONE = 0;

    /**
     * Events will be observed everywhere, course and its acitivites.
     *
     * @var int
     */
    const EVENTSCONTEXT_EVERYWHERE = 1;

    /**
     * Events will be observed only in selected activities.
     *
     *  @var int
     */
    const EVENTSCONTEXT_SELECTED = 2;

    /**
     * Include condition
     *
     * @param array $option
     * @return void
     */
    public function include_condition(&$option) {
        $option['events'] = get_string('eventscompletion', 'pulsecondition_events');
    }

    /**
     * Status of the condition addon works based on the user enrolment.
     *
     * @return bool
     */
    public function is_user_enrolment_based() {
        return false;
    }

    /**
     * Returns the latest timestamp when the configured event was triggered for the user.
     *
     * @param int $userid The user ID.
     * @param object $instancedata The automation instance data.
     * @return int The Unix timestamp of the latest matching event.
     */
    public function get_condition_satisfied_time(int $userid, $instancedata) {
        global $DB;

        $eventdata = $instancedata->condition['events'] ?? [];
        if (empty($eventdata) || empty($eventdata['event'])) {
            return time();
        }

        $result = $this->generate_log_sql($eventdata, $userid, $instancedata, true);

        if (empty($result) || isset($result['modulecheck'])) {
            if (isset($result['modulecheck'])) {
                $modules = is_array($eventdata['modules']) ? $eventdata['modules'] : [$eventdata['modules']];
                $modules = array_filter($modules);
                if (!empty($modules)) {
                    [$insql, $params] = $DB->get_in_or_equal($modules, SQL_PARAMS_NAMED, 'module');
                    $params['eventname'] = $eventdata['event'];
                    $notifyuser = $eventdata['notifyuser'] ?? self::AFFECTED_USER;
                    $userfield = ($notifyuser == self::RELATED_USER) ? 'userid' : 'relateduserid';
                    $params['userid'] = $userid;

                    $sql = "SELECT MAX(timecreated) AS maxtime
                              FROM {logstore_standard_log}
                             WHERE eventname = :eventname
                               AND contextinstanceid $insql
                               AND $userfield = :userid";
                    $maxtime = $DB->get_field_sql($sql, $params);
                    return $maxtime ? (int) $maxtime : time();
                }
            }
            return time();
        }

        [$sql, $params] = $result;
        $maxtime = $DB->get_field_sql($sql, $params);

        return $maxtime ? (int) $maxtime : time();
    }

    /**
     * Gets available options. For events upcoming will be in top.
     *
     * @return array List of options.
     */
    public function get_options() {
        return [
            self::DISABLED => get_string('disable'),
            self::FUTURE => get_string('upcoming', 'pulse'),
            self::ALL => get_string('all'),
        ];
    }

    /**
     * Delete the records of condition for the custom instance.
     *
     * @param int $instanceid
     * @return void
     */
    public function delete_condition_instance(int $instanceid) {
        global $DB;

        if ($DB->delete_records('pulsecondition_events', ['instanceid' => $instanceid])) {
            purge_caches(['muc', 'other']);
            return true;
        }

        return false;
    }

    /**
     * Loads the form elements for activity condition in template.
     *
     * @param MoodleQuickForm $mform The form object.
     * @param object $forminstance The form instance.
     */
    public function load_template_form(&$mform, $forminstance) {
        global $PAGE;

        $completionstr = get_string('eventscompletion', 'pulsecondition_events');

        $mform->addElement('select', 'condition[events][status]', $completionstr, $this->get_options());
        $mform->addHelpButton('condition[events][status]', 'eventscompletion', 'pulsecondition_events');

        // Events list.
        $eventlist = get_config('pulsecondition_events', 'availableevents');
        $events = explode(',', $eventlist);
        $eventlist = self::get_default_events($events);

        $mform->addElement(
            'autocomplete',
            'condition[events][event]',
            get_string('selectevent', 'pulsecondition_events'),
            $eventlist
        );
        $mform->hideIf('condition[events][event]', 'condition[events][status]', 'eq', self::DISABLED);
        $mform->addHelpButton('condition[events][event]', 'selectevent', 'pulsecondition_events');

        // Select the which user has been recieve the notification.
        $option = [
            self::AFFECTED_USER => get_string('affecteduser', 'pulsecondition_events'),
            self::RELATED_USER => get_string('relateduser', 'pulsecondition_events'),
            self::ALL_USER => get_string('all'),
        ];
        $mform->addElement('select', 'condition[events][notifyuser]', get_string('notifyuser', 'pulsecondition_events'), $option);
        $mform->hideIf('condition[events][notifyuser]', 'condition[events][status]', 'eq', self::DISABLED);
        $mform->addHelpButton('condition[events][notifyuser]', 'notifyuser', 'pulsecondition_events');

        // Events context.
        $option = [
            self::EVENTSCONTEXT_NONE => get_string('system', 'pulsecondition_events'),
            self::EVENTSCONTEXT_EVERYWHERE => get_string('eventscontextseverywhere', 'pulsecondition_events'),
            self::EVENTSCONTEXT_SELECTED => get_string('eventscontextsmoduleonly', 'pulsecondition_events'),
        ];
        $mform->addElement(
            'select',
            'condition[events][eventscontext]',
            get_string('eventscontexts', 'pulsecondition_events'),
            $option
        );
        $mform->hideIf('condition[events][eventscontext]', 'condition[events][status]', 'eq', self::DISABLED);
        $mform->addHelpButton('condition[events][eventscontext]', 'eventscontexts', 'pulsecondition_events');
    }

    /**
     * Loads the form elements for events completion condition.
     *
     * @param MoodleQuickForm $mform The form object.
     * @param object $forminstance The form instance.
     */
    public function load_instance_form(&$mform, $forminstance) {

        $this->load_template_form($mform, $forminstance);

        $courseid = $forminstance->get_customdata('courseid') ?? '';
        $modinfo = get_fast_modinfo($courseid);
        $cmlist = $modinfo->get_cms();
        $cmlist = array_map(fn($cm) => $cm->get_name(), $cmlist);

        $mform->addElement(
            'autocomplete',
            'condition[events][modules]',
            get_string('eventmodule', 'pulsecondition_events'),
            $cmlist,
            ['multiple' => true]
        );
        $mform->addHelpButton('condition[events][modules]', 'eventmodule', 'pulsecondition_events');
        $mform->hideIf('condition[events][modules]', 'condition[events][status]', 'eq', self::DISABLED);
        $mform->hideIf('condition[events][modules]', 'condition[events][eventscontext]', 'neq', self::EVENTSCONTEXT_SELECTED);
        $mform->addElement('hidden', 'override[condition_events_modules]', 1);
        $mform->setType('override[condition_events_modules]', PARAM_INT);

        $matchtypes = [
            'any' => get_string('matchany', 'pulsecondition_events'),
            'all' => get_string('matchall', 'pulsecondition_events'),
        ];

        $mform->addElement(
            'select',
            'condition[events][matchtype]',
            get_string('eventmatchtype', 'pulsecondition_events'),
            $matchtypes
        );
        $mform->hideIf('condition[events][matchtype]', 'condition[events][status]', 'eq', self::DISABLED);
        $mform->hideIf('condition[events][matchtype]', 'condition[events][eventscontext]', 'neq', self::EVENTSCONTEXT_SELECTED);
        $mform->setType('override[condition_events_matchtype]', PARAM_TEXT);
        $mform->addHelpButton('condition[events][matchtype]', 'modulematchtype', 'pulsecondition_events');
    }

    /**
     * Get the all event list form the moodle events list generator.
     *
     * @return array Events list.
     */
    public static function eventslist() {

        $completelist = \report_eventlist_list_generator::get_all_events_list();

        $list = [];
        foreach ($completelist as $key => $event) {
            $list[$key] = $event['raweventname'];
        }
        return $list;
    }

    /**
     * Check if the user has completed the event condition.
     *
     * @param stdClass $instancedata The instance data containing condition configuration.
     * @param int $userid The user ID to check completion for.
     * @param ?\completion_info $completion Optional completion info object.
     * @return bool True if the user has completed the condition, false otherwise.
     */
    public function is_user_completed($instancedata, int $userid, ?\completion_info $completion = null) {
        global $DB;

        if (!isset($instancedata->condition['events']) || !$instancedata->condition['events']['status']) {
            return false;
        }

        $eventdata = $instancedata->condition['events'];

        $result = $this->generate_log_sql($eventdata, $userid, $instancedata);

        if (empty($result)) {
            return false;
        }

        // Check if this is an "All modules" check.
        if (isset($result['modulecheck']) && $result['modulecheck'] === true) {
            return $this->check_all_modules_completed($result['eventdata'], $result['userid'], $instancedata);
        }
        // Standard "Any" logic or course-level events.
        [$sql, $params] = $result;

        return $DB->record_exists_sql($sql, $params);
    }


    /**
     * Check if event occurred in ALL selected modules.
     *
     * @param array $eventdata Event configuration
     * @param int $userid User ID
     * @param stdClass $instancedata Instance data
     * @return bool True if event occurred in all modules
     */
    protected function check_all_modules_completed(array $eventdata, int $userid, $instancedata) {
        global $DB;

        $modules = is_array($eventdata['modules']) ? $eventdata['modules'] : [$eventdata['modules']];
        $modules = array_filter($modules); // Remove empty values.

        if (empty($modules)) {
            return false;
        }

        $eventname = $eventdata['event'] ?? '';
        $notifyuser = $eventdata['notifyuser'] ?? 1;

        // Check each module individually.
        foreach ($modules as $moduleid) {
            $sql = "SELECT COUNT(*)
                    FROM {logstore_standard_log}
                    WHERE eventname = :eventname
                    AND contextinstanceid = :moduleid ";

            $params = [
                'eventname' => $eventname,
                'moduleid' => $moduleid,
            ];

            // User role in the event.
            if ($notifyuser == self::AFFECTED_USER) {
                $sql .= " AND relateduserid = :userid ";
                $params['userid'] = $userid;
            } else if ($notifyuser == self::RELATED_USER) {
                $sql .= " AND userid = :userid ";
                $params['userid'] = $userid;
            }

            // Upcoming condition check.
            if (!empty($eventdata['upcomingtime'])) {
                $sql .= ' AND timecreated >= :upcomingtime ';
                $params['upcomingtime'] = $eventdata['upcomingtime'];
            }

            $count = $DB->count_records_sql($sql, $params);
            // If event doesn't exist in this module, user hasn't completed the condition.
            if ($count == 0) {
                return false;
            }
        }
        // Event occurred in all modules.
        return true;
    }

    /**
     * Generate the log sql to fetch the event for the triggered event.
     *
     * @param array $eventdata
     * @param int $userid
     * @param stdClass $instancedata
     * @param bool $foraggregate
     * @return array
     */
    protected function generate_log_sql(array $eventdata, int $userid, $instancedata, bool $foraggregate = false) {
        global $DB;

        if (empty($eventdata)) {
            return [];
        }

        // Configured event context.
        $contextconfigured = (int) ($eventdata['eventscontext'] ?? self::EVENTSCONTEXT_NONE);

        // If event context is "selected" but no module is selected return zero rows.
        if ($contextconfigured === self::EVENTSCONTEXT_SELECTED && empty($eventdata['modules'])) {
            return [];
        }
        // Get match type for multiple modules (any or all).
        $matchtype = $eventdata['matchtype'] ?? 'any';

        // For "all" logic with multiple modules, return special marker.
        if ($contextconfigured === self::EVENTSCONTEXT_SELECTED && !empty($eventdata['modules']) && $matchtype === 'all') {
            return ['modulecheck' => true, 'eventdata' => $eventdata, 'userid' => $userid];
        }

        // Generate the sql to fetch the event log.
        $selectclause = $foraggregate ? 'SELECT MAX(timecreated) AS maxtime' : 'SELECT *';
        $sql = "$selectclause
                FROM {logstore_standard_log}
                WHERE eventname = :eventname ";

        $params['eventname'] = $eventdata['event'] ?? '';
        $params['userid'] = $userid;

        $notifyuser = $eventdata['notifyuser'] ?? 1;

        // User role in the event.
        if ($notifyuser == self::AFFECTED_USER) {
            $sql .= " AND relateduserid = :userid ";
        } else if ($notifyuser == self::RELATED_USER) {
            $sql .= " AND userid = :userid ";
        }

        // Module configured.
        if (!empty($eventdata['modules'])) {
            $modules = is_array($eventdata['modules']) ? $eventdata['modules'] : [$eventdata['modules']];
            // Remove empty values.
            $modules = array_filter($modules);

            if (!empty($modules)) {
                [$insql, $inparams] = $DB->get_in_or_equal($modules, SQL_PARAMS_NAMED, 'module');
                $sql .= " AND contextinstanceid $insql ";
                $params = array_merge($params, $inparams);
            }
        }

        // Event context everywhere, means any events in the context of instance course or other context in the course.
        if ($contextconfigured === self::EVENTSCONTEXT_EVERYWHERE) {
            $sql .= " AND (contextinstanceid = :coursecontextid OR courseid = :courseid) ";
            $params['coursecontextid'] = context_course::instance($instancedata->courseid)->id;
            $params['courseid'] = $instancedata->courseid;
        }

        // Module not configured. then event should be core or the course id of the event is same as instance courseid.
        if (empty($eventdata['modules']) && $contextconfigured === self::EVENTSCONTEXT_NONE) {
            $sql .= " AND (component = :core OR courseid = :courseid) ";
            $params['courseid'] = $instancedata->courseid;
            $params['core'] = 'core';
        }

        // Upcoming condition check.
        if (!empty($eventdata['upcomingtime'])) {
            $sql .= ' AND timecreated >= :upcomingtime ';
            $params['upcomingtime'] = $eventdata['upcomingtime'];
        }

        if (!$foraggregate) {
            $sql .= ' ORDER BY id DESC ';
        }

        return [$sql, $params];
    }

    /**
     * Pulse event condition trigger.
     *
     * @param stdclasss $eventdata event data.
     * @return bool
     */
    public static function pulse_event_condition_trigger($eventdata) {
        global $DB, $USER;

        $data = $eventdata->get_data();
        // Commit the database transaction.
        \core\event\manager::database_transaction_commited();

        // Events are stored in the log using the shutdown manager, it store the data end of the script.
        // Unfortuanlty the log will be stored to verify the event log to confirm the user is completed this conditions.
        // To store the data before, trigger the conditions check.
        // Fetch the log manager callback from shutdown handler and triggers the dispose method to store the event log data to DB.
        $obj = new \core_shutdown_manager();
        $reflection = new \ReflectionClass($obj);
        $property = $reflection->getProperty('callbacks');
        $property->setAccessible(true);
        $callbacks = $property->getValue($obj);

        // Get the log manager and trigger the dispose method.
        foreach ($callbacks as $lists) {
            foreach ($lists as $methods) {
                if (empty($methods)) {
                    continue;
                }

                if (!is_array($methods) || count($methods) < 2) {
                    continue;
                }

                [$callback, $method] = $methods;

                if (!is_object($callback)) {
                    continue;
                }
                if ($callback instanceof \tool_log\log\manager) {
                    // Dispose the log manager to store the event entries.
                    $callback->dispose();
                    break 2;
                }
            }
        }

        $eventname = $eventdata->eventname ?? '';

        // Trigger the instances, this will trigger its related actions for this user.
        $instances = self::get_events_notifications($eventname);

        // Self condition instance.
        $condition = new self();

        foreach ($instances as $key => $instance) {
            $additional = $instance->additional ? json_decode($instance->additional, true) : [];
            $tempadditional = $instance->tempadditional ? json_decode($instance->tempadditional, true) : [];

            $additional = (object) array_merge((array) $tempadditional, (array) $additional);

            // Event context to trigger the instance.
            $contextconfigured = (int) ($additional->eventscontext ?? self::EVENTSCONTEXT_NONE);

            // Module(s) configured for this instance event - check if event is in any of the selected modules.
            if (
                property_exists($additional, 'modules')
                && !empty($additional->modules)
                && $data['contextlevel'] == CONTEXT_MODULE
                && $contextconfigured == self::EVENTSCONTEXT_SELECTED
            ) {
                $modules = is_array($additional->modules) ? $additional->modules : [$additional->modules];
                $modules = array_filter($modules); // Remove empty values.
                $matchtype = $additional->matchtype ?? 'any';

                // If modules are configured but the event context instance is not in the list, skip.
                if ($matchtype === 'any') {
                    if (!empty($modules) && !in_array($data['contextinstanceid'], $modules)) {
                        continue;
                    }
                }
            }

            // Events context configured for everywhere, but the event is not in this course
            // or its context, continue to next instance.
            if (
                (!property_exists($additional, 'modules') || !$additional->modules)
                && $contextconfigured === self::EVENTSCONTEXT_EVERYWHERE
                && $data['courseid'] != $instance->courseid
                && $data['contextinstanceid'] != context_course::instance($instance->courseid)->id
            ) {
                continue;
            }

            // Modules not configured, component of this event is not core, and the event course id is not this course.
            // Continue to next instance.
            if (
                (!property_exists($additional, 'modules') || !$additional->modules)
                && $contextconfigured !== self::EVENTSCONTEXT_NONE
                && $data['component'] == 'core' && $data['courseid'] != $instance->courseid
            ) {
                continue;
            }

            $notifyuser = $additional->notifyuser ?? 1;

            if ($notifyuser == self::AFFECTED_USER) {
                $userid = $data['relateduserid'] ?? $USER->id;
            } else if ($notifyuser == self::RELATED_USER) {
                $userid = $data['userid'] ?? $USER->id;
            } else if ($notifyuser == self::ALL_USER) {
                $userid = 0; // All users.
            }
            $condition->trigger_instance($instance->instanceid, $userid);
        }
        return true;
    }

    /**
     * Fetch the list of instances which is used the triggered event in the access rules for the given method.
     *
     * Find the instance which contains the given event in the access rule (events).
     *
     * @param string $eventname name of the triggered event.
     * @return array
     */
    public static function get_events_notifications($eventname) {
        global $DB;

        $name = stripslashes($eventname);

        $like = $DB->sql_like('eve.eventname', ':value'); // Like query to fetch the instances assigned this event.
        $templatelike = $DB->sql_like('pcn.additional', ':eventname');

        $sql = "SELECT *, ai.id as id, ai.id as instanceid, pcn.additional as tempadditional, co.additional as additional
                  FROM {pulse_autoinstances} ai
                  JOIN {pulse_autotemplates} pat ON pat.id = ai.templateid
                  JOIN {pulse_condition} pcn ON pcn.templateid = ai.templateid AND pcn.triggercondition = 'events'
             LEFT JOIN {pulse_condition_overrides} co ON co.instanceid = ai.id AND co.triggercondition = 'events'
             LEFT JOIN {pulsecondition_events} eve ON eve.instanceid = ai.id
                 WHERE ($like OR (eve.eventname IS NULL AND $templatelike) )
                   AND (
                        co.status > 0 OR (
                            co.status IS NULL AND ai.templateid IN (
                                SELECT c.templateid FROM {pulse_condition} c WHERE c.triggercondition = 'events'
                            )
                        )
                    )";

        $eventnameescaped = '%"eventname":"' . $name . '"%';
        $params = ['events' => '%"events"%', 'value' => $name, 'eventname' => $eventnameescaped];

        $records = $DB->get_records_sql($sql, $params);

        return $records;
    }

    /**
     * Fetch the events data form the condition overrides table.
     *
     * @return array $list event list
     */
    public static function get_events() {
        global $DB;

        $list = [];
        $events = []; // Events added for observe.

        $conditions = $DB->get_records('pulse_condition', ['triggercondition' => "events"]);
        $overrides = $DB->get_records('pulse_condition_overrides', ['triggercondition' => "events"]);

        $eventsconditiondata = array_merge(array_values($conditions), array_values($overrides));

        foreach ($eventsconditiondata as $data) {
            $additional = json_decode($data->additional);
            if (!isset($additional->event) || $additional->event == '') {
                continue;
            }

            // Verify the event is already observed in the pulse event condition, to prevent multiple observe of single event.
            if (in_array($additional->event, $events)) {
                continue;
            }

            $list[] = [
                'eventname' => $additional->event,
                'callback' => '\pulsecondition_events\conditionform::pulse_event_condition_trigger',
            ];

            // Prevent multiple event observer for one event.
            $events[] = $additional->event;
        }

        return $list ?: [];
    }

    /**
     * Schedule override join.
     *
     * @return array
     */
    public function schedule_override_join() {

        return [
            'event.status as event_status, event.additional as event_additional, event.isoverridden as event_isoverridden',
            "LEFT JOIN {pulse_condition_overrides} event ON event.instanceid = pati.instanceid AND
                event.triggercondition = 'events'",
        ];
    }

    /**
     * List of placeholders rendered form the events.
     *
     * @return array
     */
    public function get_email_placeholders() {

        $vars = [
            "Event_Name",
            "Event_Namelinked",
            "Event_Description",
            "Event_Time",
            "Event_Context",
            "Event_Contextlinked",
            "Event_Affecteduserfullname",
            "Event_Affecteduserfullnamelinked",
            "Event_Relateduserfullname",
            "Event_Relateduserfullnamelinked",
        ];
        return ['Event' => $vars];
    }

    /**
     * Get the latest event record for "all modules" match type.
     *
     * @param array $eventdata Event configuration
     * @param int $userid User ID
     * @param stdClass $instancedata Instance data
     * @return stdClass|false Event log record or false
     */
    protected function get_latest_event_for_all_modules(array $eventdata, int $userid, $instancedata) {
        global $DB;

        $modules = is_array($eventdata['modules']) ? $eventdata['modules'] : [$eventdata['modules']];
        $modules = array_filter($modules);

        if (empty($modules)) {
            return false;
        }

        $eventname = $eventdata['event'] ?? '';
        $notifyuser = $eventdata['notifyuser'] ?? 1;

        // Get the most recent event from any of the modules.
        [$insql, $inparams] = $DB->get_in_or_equal($modules, SQL_PARAMS_NAMED, 'module');

        $sql = "SELECT *
                FROM {logstore_standard_log}
                WHERE eventname = :eventname
                AND contextinstanceid $insql ";

        $params = array_merge(['eventname' => $eventname], $inparams);

        // User role in the event.
        if ($notifyuser == self::AFFECTED_USER) {
            $sql .= " AND relateduserid = :userid ";
            $params['userid'] = $userid;
        } else if ($notifyuser == self::RELATED_USER) {
            $sql .= " AND userid = :userid ";
            $params['userid'] = $userid;
        }

        // Upcoming condition check.
        if (!empty($eventdata['upcomingtime'])) {
            $sql .= ' AND timecreated >= :upcomingtime ';
            $params['upcomingtime'] = $eventdata['upcomingtime'];
        }

        $sql .= ' ORDER BY timecreated DESC, id DESC ';

        return $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);
    }

    /**
     * Update email custom vars.
     *
     * @param int $userid
     * @param stdClass $instancedata
     * @param stdClass $schedulerecord
     * @return void
     */
    public function update_email_customvars($userid, $instancedata, $schedulerecord) {
        global $DB, $OUTPUT;
        // Check the event condition are set for this notification. if its added then load the event data for placeholders.
        $eventin = in_array('events', (array) $instancedata->template->triggerconditions);
        $eventin = (property_exists($schedulerecord, 'event_isoverridden') && $schedulerecord->event_isoverridden == 1)
            ? $schedulerecord->event_status : $eventin;

        if ($eventin) {
            $eventdata = (array) json_decode($schedulerecord->event_additional);

            $result = $this->generate_log_sql($eventdata, $userid, $instancedata);

            if (isset($result['modulecheck']) && $result['modulecheck'] === true) {
                $record = $this->get_latest_event_for_all_modules($result['eventdata'], $result['userid'], $instancedata);
            } else {
                // Standard case - unpack SQL and params.
                if (!is_array($result) || count($result) < 2) {
                    return [];
                }

                [$sql, $params] = $result;
                $record = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);
            }

            if (empty($result)) {
                return [];
            }

            if (empty($record)) {
                return [];
            }

            $logmanager = get_log_manager();
            $event = (new \logstore_database\log\store($logmanager))->get_log_event($record);

            if ($event == null) {
                return [];
            }

            $vars = [];
            $vars['name'] = $event->get_name();

            // Only encode as an action link if we're not downloading.
            if ($url = $event->get_url()) {
                $link = new \action_link(
                    $url,
                    $vars['name'],
                    new \popup_action('click', $url, 'action', ['height' => 440, 'width' => 700])
                );
                $vars['namelinked'] = $OUTPUT->render($link);
            }

            $vars['description'] = $event->get_description();

            // Event time.
            $dateformat = get_string('strftimedatetimeaccurate', 'core_langconfig');
            $vars['time'] = userdate($event->timecreated, $dateformat);

            // Event_Context.
            if ($event->contextid) {
                // If context name was fetched before then return, else get one.
                $context = \context::instance_by_id($event->contextid, IGNORE_MISSING);
                $vars['context'] = ($context) ? $context->get_context_name(true) : get_string('other');

                // Event_Contextlinked.
                if ($context instanceof \context) {
                    if ($url = $context->get_url()) {
                        $vars['contextlinked'] = \html_writer::link($url, $vars['context']);
                    }
                }
            }
            // Event_Affecteduserfullname.
            if (!empty($event->relateduserid)) {
                $vars['affecteduserfullname'] = $this->get_user_fullname($event->relateduserid);
                $params = ['id' => $event->relateduserid];
                if ($event->courseid) {
                    $params['course'] = $event->courseid;
                }
                // Event_Affecteduserfullnamelinked.
                $vars['affecteduserfullnamelinked'] = \html_writer::link(
                    new \moodle_url('/user/view.php', $params),
                    $vars['affecteduserfullname']
                );
            }
            // Event_Relateduserfullname.
            if (!empty($event->userid) && $vars['relateduserfullname'] = $this->get_user_fullname($event->userid)) {
                $params = ['id' => $event->userid];
                if ($event->courseid) {
                    $params['course'] = $event->courseid;
                }
                $vars['relateduserfullnamelinked'] = \html_writer::link(
                    new \moodle_url('/user/view.php', $params),
                    $vars['relateduserfullname']
                );
            }

            return ['event' => $vars];
        }

        return [];
    }

    /**
     * Gets the user full name.
     *
     * This function is useful because, in the unlikely case that the user is
     * not already loaded in $this->userfullnames it will fetch it from db.
     *
     * @since Moodle 2.9
     * @param int $userid
     * @return string|false
     */
    protected function get_user_fullname($userid) {
        global $PAGE;

        if (empty($userid)) {
            return false;
        }

        // If we reach that point new users logs have been generated since the last users db query.
        $userfieldsapi = \core_user\fields::for_name();
        $fields = $userfieldsapi->get_sql('', false, '', '', false)->selects;
        if ($user = \core_user::get_user($userid, $fields)) {
            $userfullname = fullname($user, has_capability('moodle/site:viewfullnames', $PAGE->context));
        } else {
            $userfullname = false;
        }

        return $userfullname;
    }

    /**
     * Defined the strucure of tables for the backup.
     *
     * @param [type] $instances
     * @return void
     */
    public function backup_define_structure(&$instances) {
        global $DB;

         // Automation templates.
        $events = new \backup_nested_element('automationtemplates');
        $eventsfields = new \backup_nested_element('pulse_autotemplates', ['id'], [
            "instanceid", "eventname", "notifyuser",
        ]);

        $instances->add_child($events);
        $events->add_child($eventsfields);

        $eventsfields->set_source_table('pulsecondition_events', ['instanceid' => \backup::VAR_PARENTID]);
    }

    /**
     * Before save the condition form, clean up the event name for fetch instance.
     *
     * @param int $templateid
     * @param array $data
     * @return int
     */
    public function process_save($templateid, $data) {
        // Clean up event name, for fetch instance.
        if (!empty($data['event'])) {
            $data['eventname'] = stripslashes($data['event']);
        }

        return parent::process_save($templateid, $data);
    }

    /**
     * After save the condition form, clear the observers from cache and recreated the list.
     *
     * @param int $instanceid
     * @param object $data
     * @param object|null $templatedata Instance template record.
     *
     * @return void
     */
    public function process_instance_save($instanceid, $data, $templatedata = null) {
        global $DB;

        if (!empty($data['event'])) {
            $data['eventname'] = stripslashes($data['event']);
        }

        parent::process_instance_save($instanceid, $data, $templatedata);

        // Remove the event observers and recreate.
        if (class_exists('\core_cache\cache')) {
            $cache = \core_cache\cache::make('core', 'observers');
        } else {
            $cache = \cache::make('core', 'observers');
        }

        $cache->delete('all');
        // Build the observers again.
        $list = \core\event\manager::get_all_observers();
        $cache->set('all', $list);
        purge_caches(['muc', 'other']);

        if (empty($data['event'])) {
            return;
        }

        $eventrecord = [
            'instanceid' => $instanceid,
            'eventname' => stripslashes($data['event']),
            'notifyuser' => $data['notifyuser'] ?? '',
        ];

        if ($event = $DB->get_record('pulsecondition_events', ['instanceid' => $instanceid])) {
            $eventrecord['id'] = $event->id;
            // Update the record.
            $DB->update_record('pulsecondition_events', $eventrecord);
        } else {
            // Insert the record.
            $DB->insert_record('pulsecondition_events', $eventrecord);
        }
    }

    /**
     * Get the default events list.
     * @param array $events
     * @return array
     */
    public static function get_default_events($events = []) {
        $eventslist = self::eventslist();
        $default = $events ?: [
            '\core\event\course_viewed',
            '\core\event\course_completed',
            '\core\event\course_module_created',
            '\mod_assign\event\submission_created',
            '\mod_assign\event\submission_graded',
            '\mod_forum\event\discussion_created',
            '\mod_forum\event\post_created',
            '\mod_quiz\event\attempt_started',
            '\mod_quiz\event\attempt_submitted',
            '\core\event\user_enrolment_created',
            '\core\event\user_enrolment_deleted',
            '\core\event\course_module_completion_updated',
            '\core\event\user_graded',
        ];

        return array_intersect_key($eventslist, array_flip($default));
    }
}
