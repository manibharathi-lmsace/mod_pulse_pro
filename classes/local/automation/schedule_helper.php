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
 * Schedule helper trait.
 *
 * @package   mod_pulse
 * @copyright 2025, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pulse\local\automation;

defined('MOODLE_INTERNAL') || die();

use mod_pulse\automation\helper;
use mod_pulse\automation\instances;
use stdClass;

require_once($CFG->dirroot . '/mod/pulse/automation/automationlib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/lib/moodlelib.php');

/**
 * Schedule helper trait.
 */
trait schedule_helper {
    /**
     * The current schedule record.
     * @var stdClass
     */
    public $schedule;

    /**
     * Get schedule table name.
     */
    abstract protected function get_schedule_table(): string;

    /**
     * Get the action instance table name.
     */
    abstract protected function get_action_instance_table(): string;

    /**
     * Get the action table name.
     */
    abstract protected function get_action_table(): string;

    /**
     * Get the list of tables and their columns to be used in the schedule fetching query.
     *
     * @return array An associative array where keys are table aliases and values are arrays of column names.
     */
    protected function get_tables_list() {
        global $DB;

        // ...TODO: Fetch only used columsch. Fetching all the fields in a query will make double the time of query result.
        $tables = [
            'pai'   => $DB->get_columns('pulse_autoinstances'),
            'pat'   => $DB->get_columns('pulse_autotemplates'),
            'pati'  => $DB->get_columns('pulse_autotemplates_ins'),
            'con'   => array_fill_keys(["status", "additional", "isoverridden"], ""),
            'ue'    => $DB->get_columns('user'),
            'c'     => $DB->get_columns('course'),
            'ctx'   => $DB->get_columns('context'),
        ];

        $tables['sch'] = $DB->get_columns($this->get_schedule_table());
        $tables['actins'] = $DB->get_columns($this->get_action_instance_table());
        $tables['act'] = $DB->get_columns($this->get_action_table());

        return $tables;
    }


    /**
     * Fetch the queued schedules for the user.
     *
     * @param int|null $userid (Optional) The ID of the user. If provided, schedules are fetched and inti the credits sent.
     *
     * @return array Array of schedule records.
     */
    protected function get_scheduled_records($userid = null) {
        global $DB;

        $select[] = 'sch.id AS id'; // Set the schdule id as unique column.

        // Get columns not increase table queries.
        // ...TODO: Fetch only used columsch. Fetching all the fields in a query will make double the time of query result.
        $tables = $this->get_tables_list();

        foreach ($tables as $prefix => $table) {
            $columns = array_keys($table);
            // Columns.
            array_walk($columns, function (&$value, $key, $prefix) {
                $value = "$prefix.$value AS " . $prefix . "_$value";
            }, $prefix);

            $select = array_merge($select, $columns);
        }

        // Number of credits to send in this que.
        $limit = get_config('pulse', 'schedulecount') ?: 100;

        // Trigger the schedules for sepecied users.
        $userwhere = $userid ? ' AND sch.userid =:userid ' : '';
        $userparam = $userid ? ['userid' => $userid] : [];

        $conditionleftjoins = '';
        $plugins = \mod_pulse\plugininfo\pulsecondition::instance()->get_plugins_base();
        foreach ($plugins as $component => $pluginbase) {
            [$fields, $join] = $pluginbase->schedule_override_join();
            if (empty($fields)) {
                continue;
            }
            $conditionleftjoins .= $join;
            $select[] = $fields;
        }

        // Final list of select columns, convert to sql mode.
        $select = implode(', ', $select);

        $scheduletable = '{' . $this->get_schedule_table() . '}';

        $actionjoin = "
            JOIN {" . $this->get_action_instance_table() . "} actins ON actins.instanceid = sch.instanceid
            JOIN {" . $this->get_action_table() . "} act ON act.templateid = pai.templateid";

        // Fetch the schedule which is status as 1 and nextrun not empty and not greater than now.
        $sql = "SELECT $select FROM $scheduletable sch
            JOIN {pulse_autoinstances} pai ON pai.id = sch.instanceid
            JOIN {pulse_autotemplates} pat ON pat.id = pai.templateid
            JOIN {pulse_autotemplates_ins} pati ON pati.instanceid = pai.id
            $actionjoin
            JOIN {user} ue ON ue.id = sch.userid
            JOIN {course} c ON c.id = pai.courseid
            JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
            LEFT JOIN {pulse_condition_overrides} con ON con.instanceid = pati.instanceid AND con.triggercondition = 'session'
            $conditionleftjoins
            JOIN (
                SELECT DISTINCT eu1_u.id, ej1_e.courseid, COUNT(ej1_ue.enrolid) AS activeenrolment
                    FROM {user} eu1_u
                    JOIN {user_enrolments} ej1_ue ON ej1_ue.userid = eu1_u.id
                    JOIN {enrol} ej1_e ON (ej1_e.id = ej1_ue.enrolid)
                WHERE 1 = 1 AND ej1_ue.status = 0
                AND (ej1_ue.timestart = 0 OR ej1_ue.timestart <= :timestart)
                AND (ej1_ue.timeend = 0 OR ej1_ue.timeend > :timeend)
                GROUP BY eu1_u.id, ej1_e.courseid
            ) active_enrols ON active_enrols.id = ue.id AND active_enrols.courseid = c.id
            WHERE sch.status = :status AND pai.status <> 0
            AND active_enrols.activeenrolment <> 0
            AND c.visible = 1
            AND c.startdate <= :startdate AND (c.enddate = 0 OR c.enddate >= :enddate)
            AND ue.deleted = 0 AND ue.suspended = 0
            AND sch.suppressreached = 0 AND sch.scheduletime <= :current_timestamp $userwhere ORDER BY sch.timecreated ASC";

        $params = [
            'status' => schedule::STATUS_QUEUED,
            'current_timestamp' => time(),
            'timestart' => time(), 'timeend' => time(),
            'startdate' => time(), 'enddate' => time(),
        ] + $userparam;

        $schedules = $DB->get_records_sql($sql, $params, 0, $limit);

        return $schedules;
    }

    /**
     * Builds the values for a given schedule.
     *
     * @param object $schedule The schedule object containing information about the automation instance.
     *
     * @return void
     */
    protected function build_schedule_values($schedule) {
        global $DB;

        $this->schedulerecord = $schedule;

        // Prepare templates instance data.
        $templatedata = helper::filter_record_byprefix($schedule, 'pat');
        $templateinsdata = helper::filter_record_byprefix($schedule, 'pati');
        $templateinsdata = (object) helper::merge_instance_overrides($templateinsdata, $templatedata);

        $templateinsdata->id = $templatedata['id'];
        $templateinsdata->condition = $DB->get_records('pulse_condition', ['templateid' => $templateinsdata->id]);

        $templateinsdata->condition = array_combine(
            array_column($templateinsdata->condition, 'triggercondition'),
            array_values($templateinsdata->condition)
        );
        array_walk($templateinsdata->condition, function (&$condition) {
            $condition->additional = json_decode($condition->additional);
            $condition = array_merge((array) $condition, (array) $condition->additional);
            $condition = (array) $condition;
        });

        $templateinsdata->triggerconditions = $templateinsdata->condition;

        // Prepare the instance data.
        $instancedata = (object) helper::filter_record_byprefix($schedule, 'pai');
        // Merge the template data to instance.
        $instancedata->template = $templateinsdata;
        unset($templateinsdata->id);
        $instancedata = (object) array_merge((array) $instancedata, (array) $templateinsdata);
        $this->instancedata = $instancedata; // Auomtaion instance data.

        if (isset($this->conditions[$instancedata->id])) {
            // Include the condition for this instance if already created for this cron use it.
            $this->instancedata->condition = $this->conditions[$instancedata->id];
        } else {
            $condition = (new instances($instancedata->id))->include_conditions_data($this->instancedata);
            $this->conditions[$instancedata->id] = $condition;
            $this->instancedata->condition = $condition;
        }

        // Schedule data.
        $this->schedule = (object) helper::filter_record_byprefix($schedule, 'sch');
        // Course data.
        $this->course = (object) helper::filter_record_byprefix($schedule, 'c');
        // User data.
        $this->user = (object) helper::filter_record_byprefix($schedule, 'ue');
        // Course context data.
        $context = (object) helper::filter_record_byprefix($schedule, 'ctx');
        // Conver the context data to moodle context.
        $this->coursecontext = \mod_pulse_context_course::create_instance_fromrecord($context);
        // Filter the action data by its prefix.
        $actionrecord = helper::filter_record_byprefix($schedule, 'act');
        // Filter the action instance data by its prefix.
        $actioninstancerecord = helper::filter_record_byprefix($schedule, 'actins');
        // Merge the action overrides data and its action data.
        $this->actiondata = (object) helper::merge_instance_overrides($actioninstancerecord, $actionrecord);
        // Filter the action instance overrided values list.
        $this->actionoverrides = array_filter((array) $actioninstancerecord, function ($value) {
            return $value !== null;
        });
    }
}
