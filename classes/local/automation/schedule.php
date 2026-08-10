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
 * Schedule class for action automation.
 *
 * @package   mod_pulse
 * @copyright 2025, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pulse\local\automation;

use moodle_exception;
use DateTime;
use DatePeriod;
use stdClass;
use xmldb_table;
use mod_pulse\automation\instances;

/**
 * Abstract class for action schedule.
 */
abstract class schedule {
    /**
     * Some of the fields are common in all the action schedule tables.
     *
     * id - autoincrement id of the record.
     * instanceid - The action instance id from action instance table.
     * userid - The user id for which the action is scheduled.
     * type - The interval type of the action.
     * status - The status of the action schedule.
     * timecreated - The time when the record is created.
     * timemodified - The time when the record is modified.
     * lastrun - The last time when the action is executed.
     * completedtime - The time when the action is completed.
     */

    /**
     * Represents a action interval is once.
     * @var int
     */
    public const INTERVALONCE = 1;

    /**
     * Represents a action interval is Daily.
     * @var int
     */
    public const INTERVALDAILY = 2;

    /**
     * Represents a action interval is weekly.
     * @var int
     */
    public const INTERVALWEEKLY = 3;

    /**
     * Represents a action interval is monthly.
     * @var int
     */
    public const INTERVALMONTHLY = 4;

    /**
     * Represents a action interval is yearly.
     * @var int
     */
    public const INTERVALYEARLY = 5;

    /**
     * Represents a action interval is custom -crontab.
     * @var int
     */
    public const INTERVALCUSTOM = 6;

    /**
     * Represents the action schedule status is failed.
     * @var int
     */
    const STATUS_FAILED = 0;

    /**
     * Represents the action schedule status is disabled.
     * @var int
     */
    const STATUS_DISABLED = 1;

    /**
     * Represents the action schedule status is queued.
     * @var int
     */
    const STATUS_QUEUED = 2;

    /**
     * Represents the action schedule status is sent.
     * @var int
     */
    const STATUS_COMPLETED = 3;

    /**
     * Represents the action schedule is currently claimed and being processed.
     * @var int
     */
    const STATUS_PROCESSING = 4;

    /**
     * The record of the action instance with templates and general conditions.
     *
     * @var stdclass
     */
    protected $instancedata;

    /**
     * The merged action data based on instance overrides (like actiondata, creditsdata).
     *
     * @var stdclass
     */
    protected $actiondata;

    /**
     * The ID of the action action table.
     * @var int
     */
    protected $instanceid; // Action table id.

    /**
     * The name of the schedule table.
     * @var string
     */
    protected $tablename;

    /**
     * The field name of the completed time in the schedule table.
     * @var string
     */
    protected $completedtime = 'completedtime';

    /**
     * String to show for completed status.
     * @var string
     */
    protected static $completedstatusstring = '';

    /**
     * Create the instance of the action controller.
     *
     * @param int $instanceid action instance record id (pulseaction_action_ins) NOT autoinstanceid.
     * @param stdClass|null $instancedata The instance data to set for schedule instance.
     * @return action
     */
    public static function instance($instanceid, ?stdClass $instancedata = null) {
        static $instance;

        if (!$instance || ($instance && $instance->instanceid != $instanceid)) {
            $instance = new static($instanceid, $instancedata);
        }

        return $instance;
    }

    /**
     * Create the action instance from the template instance id.
     *
     * @param int $templateinstanceid The template instance id.
     * @param stdClass|null $templateinstancedata The template instance data.
     */
    public static function create_from_templateinstance($templateinstanceid, $templateinstancedata = null) {
        global $DB;

        if ($record = $DB->get_record('pulseaction_credits_ins', ['instanceid' => $templateinstanceid])) {
            return static::instance($record->id, $templateinstancedata);
        }

        return null;
    }

    /**
     * Contructor for this action controller.
     *
     * @param int $instanceid  action table id.
     * @param stdClass|null $instancedata The instance data to set for schedule instance.
     */
    public function __construct(int $instanceid, ?stdClass $instancedata = null) {
        global $DB;

        $this->instanceid = $instanceid;
        $this->instancedata = $instancedata;

        $tablename = $this->get_schedule_tablename();
        $table = new xmldb_table($tablename);

        if (!$DB->get_manager()->table_exists($table)) {
            throw new moodle_exception('tablesnotcreated', 'mod_pulse', '', $tablename);
        }

        $this->tablename = $table->getName();
    }

    /**
     * Get the action instance data from the action instance table.
     * @return stdclass|null The action instance data.
     */
    abstract protected function get_actioninstance_data();

    /**
     * Get the schedule table name.
     * @return string The schedule table name.
     */
    abstract protected function get_schedule_tablename(): string;

    /**
     * Get the action name for this schedule.
     * @return string The action name.
     */
    abstract public function get_action_name(): string;

    /**
     * Create the schedule for all the qualified users for this instance.
     *
     * @param bool $newenrolment If true then create the schedule for new enrolments only.
     * @param int|null $newuserid If set then create the schedule for this user only.
     * @param bool|int $newfrequency If set then create the schedule with new frequency.
     * @return bool True if schedule created otherwise false.
     */
    abstract public function create_schedule_forinstance($newenrolment = false, $newuserid = null, $newfrequency = false): bool;

    /**
     * Create the schedule for the given user for this instance.
     *
     * @param int $userid ID of the user to create schedule.
     * @param int|string $lastrun The last time when the action is executed.
     * @param int $notifycount The number of times the action is notified to the user.
     * @param int|null $expectedruntime The expected time to run the action.
     * @param bool $isnewuser If true then create the schedule for new user only.
     * @param bool $newschedule If true then create the new schedule even if the user already notified.
     * @param bool|int $newfrequency If set then create the schedule with new frequency.
     * @param int|null $previousbased The previous schedule based, previous schedule id.
     *
     * @return bool|int Inserted schedule ID or false if not created.
     */
    abstract public function create_schedule_foruser(
        $userid,
        $lastrun = '',
        $notifycount = 0,
        $expectedruntime = null,
        $isnewuser = false,
        $newschedule = false,
        $newfrequency = false,
        $previousbased = null
    ): bool|int;

    /**
     * Create the action instance and set the data to this class.
     *
     * @return void
     */
    protected function create_instance_data() {
        global $DB;

        if (empty($this->instanceid)) {
            return [];
        }

        $action = $this->get_actioninstance_data();

        if (empty($action)) {
            throw new \moodle_exception('actioninstancenotfound', 'pulse');
        }

        $instance = instances::create($action->instanceid);
        $autoinstance = $instance->get_instance_data();

        $actiondata = $autoinstance->actions[$this->get_action_name()] ?? null;

        $this->set_action_data($actiondata, $autoinstance);
    }

    /**
     * Set the action data to global. Decode and do other structure updates for the data before setup.
     *
     * @param stdclass $actiondata Contains action data.
     * @param stdclass $instancedata Contains other than actions.
     * @return void
     */
    public function set_action_data($actiondata, $instancedata) {
        // Set the action data.
        $actiondata = (object) $actiondata;
        $this->actiondata = $this->update_data_structure($actiondata);

        // Set the instance data.
        $instancedata = (object) $instancedata;
        // Instance not contains course then include course.
        if (!isset($instancedata->course)) {
            $instancedata->course = get_course($instancedata->courseid);
        }

        $this->instancedata = $instancedata;
    }

    /**
     * Get the action data.
     * @return stdclass The action data.
     */
    public function get_actiondata() {
        return $this->actiondata;
    }

    /**
     * Update the data structure if needed.
     *
     * @param stdclass $data The data to update.
     * @return stdclass The updated data.
     */
    public function update_data_structure($data) {
        return $data;
    }

    /**
     * Generate the data set for the user to create schedule for this instance.
     *
     * @param int $userid ID of the user to create schedule.
     * @return array $record Record to insert into schdeule.
     */
    protected function generate_schedule_record(int $userid) {

        $record = [
            'instanceid' => $this->actiondata->instanceid,
            'userid' => $userid,
            'type' => $this->actiondata->notifyinterval['interval'],
            'status' => self::STATUS_QUEUED,
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        return $record;
    }

    /**
     * Hook to perform any action before update the existing schedule.
     *
     * @param array $data Data to update.
     * @param stdclass $record Existing schedule record.
     * @param bool $newschedule
     * @param int $newfrequency
     * @return void
     */
    protected function hook_before_update_schedule(array &$data, $record, $newschedule = false, $newfrequency = false) {
        // Can be overridden by child classes.
    }

    /**
     * Insert the schedule to database, verify if the schedule is already in queue then override the schedule with given record.
     *
     * @param array $data
     * @param bool $newschedule
     * @param int $newfrequency
     * @return int Inserted schedule ID.
     */
    protected function insert_schedule($data, $newschedule = false, $newfrequency = false) {
        global $DB;

        $sql = 'SELECT *
                FROM {' . $this->tablename . '}
                WHERE instanceid = :instanceid
                AND userid = :userid AND (status = :disabledstatus  OR status = :queued)';

        if (
            $record = $DB->get_record_sql($sql, [
                'instanceid' => $data['instanceid'], 'userid' => $data['userid'],
                'disabledstatus' => self::STATUS_DISABLED, 'queued' => self::STATUS_QUEUED])
        ) {
            $data['id'] = $record->id;

            $this->hook_before_update_schedule($data, $record, $newschedule, $newfrequency);

            // Update the status to enable for notify.
            $DB->update_record($this->tablename, $data);

            return $record->id;
        }

        // Dont create new schedule for already notified users until is not new schedule.
        // It prevents creating new record for user during the update of instance interval.
        if (
            !$newschedule && $DB->record_exists($this->tablename, [
            'instanceid' => $data['instanceid'], 'userid' => $data['userid'], 'status' => self::STATUS_COMPLETED,
            ])
        ) {
            return false;
        }

        return $DB->insert_record($this->tablename, $data);
    }


    /**
     * Disable the queued action schdule of the given user.
     *
     * @param int $userid
     * @return void
     */
    protected function disable_user_schedule($userid) {
        global $DB;

        $select = "instanceid = :instanceid AND userid = :userid AND (status = :disabledstatus  OR status = :queued)";

        $params = [
            'instanceid' => $this->actiondata->instanceid, 'userid' => $userid, 'disabledstatus' => self::STATUS_DISABLED,
            'queued' => self::STATUS_QUEUED,
        ];

        if ($record = $DB->get_record_select($this->tablename, $select, $params)) {
            $DB->set_field($this->tablename, 'status', self::STATUS_DISABLED, ['id' => $record->id]);
        }
    }

    /**
     * Remove the queued and disabled schedules of this user.
     *
     * @param int $userid
     * @param int $actioninstanceid pulseautomation instance id not action instanceid.
     * @return int|bool The removed schedule ID or false if not found.
     */
    public function remove_user_schedules($userid, $actioninstanceid = null) {
        global $DB;

        $select = "instanceid = :instanceid AND userid = :userid AND (status = :disabledstatus  OR status = :queued)";

        $params = [
            'instanceid' => $actioninstanceid ?: $this->actiondata->instanceid, 'userid' => $userid,
            'disabledstatus' => self::STATUS_DISABLED, 'queued' => self::STATUS_QUEUED,
        ];

        if ($record = $DB->get_record_select($this->tablename, $select, $params)) {
            $DB->delete_records($this->tablename, ['id' => $record->id]);
            return $record->id;
        }

        return false;
    }

    /**
     * Get the current schedule created for the user related to specific instance.
     *
     * @param stdclass $data Data with instance id and user id.
     * @return stdclass|null Record of the current schedule.
     */
    protected function get_schedule($data) {
        global $DB;

        if (
            $record = $DB->get_record($this->tablename, [
            'instanceid' => $data->instanceid, 'userid' => $data->userid,
            ])
        ) {
            return $record;
        }

        return false;
    }

    /**
     * Find the sent time of the last schedule to the user for the specific instance.
     *
     * @param int $userid
     * @return int|null Time of the last schedule notified to the user for the specific instance
     */
    protected function find_last_action_runtime($userid) {
        global $DB;

        $id = $this->actiondata->instanceid;

        // Get last notified schedule for this instance to the user.
        $condition = ['instanceid' => $id, 'userid' => $userid, 'status' => self::STATUS_COMPLETED];
        $records = $DB->get_records($this->tablename, $condition, 'id DESC', '*', 0, 1);

        return !empty($records) ? current($records)?->completedtime : '';
    }


    /**
     * Remove the schdeduled actions for this instance which has the given status.
     *
     * @param int $status
     * @return void
     */
    protected function remove_schedules($status = self::STATUS_COMPLETED) {
        global $DB;

        $DB->delete_records($this->tablename, ['instanceid' => $this->instancedata->id, 'status' => $status]);
    }

    /**
     * Disable the queued schdule for all users.
     *
     * @return void
     */
    protected function disable_schedules() {
        global $DB;

        if (!empty($this->actiondata)) {
            $this->create_instance_data();
        }

        $params = [
            'instanceid' => $this->actiondata->instanceid,
            'status' => self::STATUS_QUEUED,
        ];

        // Disable the queued schedules for this instance.
        $DB->set_field($this->tablename, 'status', self::STATUS_DISABLED, $params);
    }

    /**
     * Verify the action is completed for the user for this instance. It verify the lastrun is empty for the user record.
     *
     * Note: Use this method to verify the instance with interval once.
     *
     * @param int $userid
     * @return bool
     */
    protected function is_useraction_completed(int $userid) {
        global $DB;

        $id = $this->actiondata->instanceid;
        $condition = ['instanceid' => $id, 'userid' => $userid, 'status' => self::STATUS_COMPLETED];
        if ($record = $DB->get_record($this->tablename, $condition)) {
            return $record->{$this->completedtime} != null ? true : false;
        }
        return false;
    }

    /**
     * Verify the user is passed to receive the scheduled action.
     *
     * @param int $userid
     * @return bool
     */
    public function verify_create_schedule_foruser($userid): bool {
        return true;
    }

    /**
     * Removes the current queued schedules and recreate the schedule for all the qualified users.
     *
     * @return void
     */
    public function recreate_schedule_forinstance() {
        // Remove the current queued schedules.
        $this->create_instance_data();

        $this->remove_schedules(self::STATUS_QUEUED);
        // Create the schedules for all users.
        $this->create_schedule_forinstance();
    }

    /**
     * Verfiy the current instance configured any conditions.
     *
     * @return bool if configured any conditions return true otherwise returns flase.
     */
    protected function verfiy_instance_contains_condition() {

        if (!isset($this->instancedata->condition) || empty($this->instancedata->condition)) {
            return false;
        }

        // Verify the instance contains any enabled conditions.
        foreach ($this->instancedata->condition as $condition => $values) {
            if (!is_array($values)) {
                $values = (array) $values;
            }

            if (!empty($values) && ($values['status'] || $values == 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the base date time to calculate the schedule time.
     *
     * @param int $userid
     * @return DateTime
     */
    protected function get_base_datetime($userid) {
        return new DateTime('now', \core_date::get_server_timezone_object());
    }

    /**
     * Generate the schedule time for this notification.
     *
     * @param int $userid
     * @param int $lastrun
     * @param int $expectedruntime
     * @return int
     */
    protected function generate_the_scheduletime($userid, $lastrun = null, $expectedruntime = null) {

        $data = $this->actiondata;
        $data->userid = $userid;

        $basetime = $this->get_base_datetime($userid);
        $now = new DateTime('now', \core_date::get_server_timezone_object());

        if ($expectedruntime) {
            $expectedruntime = $basetime->setTimestamp($expectedruntime);
        }

        $nextrun = $expectedruntime ?: $basetime;

        if (!empty($lastrun)) {
            $lastrun = ($lastrun instanceof DateTime)
                ?: (new DateTime('now', \core_date::get_server_timezone_object()))->setTimestamp($lastrun);
            $nextrun = $lastrun;
        }

        $interval = $data->notifyinterval['interval'];

        switch ($interval) {
            case self::INTERVALDAILY:
                $time = $data->notifyinterval['time'];
                $nextrun->modify('+1 day'); // ...TODO: Change this to Dateinterval().
                $timeex = explode(':', $time);
                $nextrun->setTime(...$timeex);
                break;

            case self::INTERVALWEEKLY:
                $day = $data->notifyinterval['weekday'];
                $time = $data->notifyinterval['time'];
                $nextrun->modify("Next " . $day);
                $timeex = explode(':', $time);
                $nextrun->setTime(...$timeex);
                break;

            case self::INTERVALMONTHLY:
                $monthdate = $data->notifyinterval['monthdate'];
                if ($monthdate != 31) { // If the date is set as 31 then use the month end.
                    $nextrun->modify('first day of next month');
                    $date = $data->notifyinterval['monthdate']
                        ? $data->notifyinterval['monthdate'] - 1 : $data->notifyinterval['monthdate'];
                    $nextrun->modify("+$date day");
                } else {
                    $nextrun->modify('last day of next month');
                }

                $time = $data->notifyinterval['time'] ?? '0:00';
                $timeex = explode(':', $time);
                $nextrun->setTime(...$timeex);
                break;

            case self::INTERVALYEARLY:
                $month = $data->notifyinterval['month'] ?? 1;
                $monthdate = $data->notifyinterval['monthdate'] ?? 1;
                $time = $data->notifyinterval['time'] ?? '0:00';
                // Every nth year.
                $yearlyinterval = $data->notifyinterval['yearlyinterval'] ?? 1;
                $year = $nextrun->format('Y') + $yearlyinterval;

                $nextrun->setDate($year, $month, $monthdate);
                $timeex = explode(':', $time);
                $nextrun->setTime(...$timeex);

                break;

            case self::INTERVALCUSTOM:
                $cronconfig = [
                    'minute' => $data->notifyinterval['cron_minute'] ?: '*',
                    'hour' => $data->notifyinterval['cron_hour'] ?: '*',
                    'day' => $data->notifyinterval['cron_day'] ?: '*',
                    'month' => $data->notifyinterval['cron_month'] ?: '*',
                    'dayofweek' => $data->notifyinterval['cron_dayofweek'] ?: '*',
                ];
                $nextrun = $this->calculate_next_cron_runtime($lastrun ?: $now, $cronconfig);
                break;

            case self::INTERVALONCE:
                $basetime = $basetime > $now ? $basetime : $now;
                $nextrun = $expectedruntime ?: $basetime;
                break;
        }

        // Add additional schedule time modifications if needed in the child class.
        $this->additional_schedule_time($nextrun, $data, $expectedruntime);

        return $nextrun->getTimestamp();
    }

    /**
     * Add additional schedule time modifications if needed in the child class.
     *
     * @param DateTime $nextrun The next run date time object to modify.
     * @param stdclass $data The action data.
     * @param int|null $expectedruntime The expected time to run the action.
     *
     * @return void
     */
    public function additional_schedule_time(DateTime &$nextrun, $data, $expectedruntime = null) {
        // Include the additional schedule time modifications if needed in the child class.
    }

    /**
     * Get the users assigned in the roles.
     *
     * @param array $roles Role ids to fetch
     * @param \context $context
     * @param int $childuserid
     * @return array List of the users.
     */
    protected function get_users_withroles(array $roles, $context, $childuserid = null) {
        global $DB;

        // ...TODO: Cache the role users.
        if (empty($roles)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED, 'rle');

        // ...TODO: Define user fields, never get entire fields.
        $rolesql = "SELECT ra.id as assignid, u.*, ra.roleid
                    FROM {role_assignments} ra
                    JOIN {user} u ON u.id = ra.userid
                    JOIN {role} r ON ra.roleid = r.id
                LEFT JOIN {role_names} rn ON (rn.contextid = :ctxid AND rn.roleid = r.id) ";

        // Fetch the parent users related to the child user.
        $childcontext = '';
        if ($childuserid) {
            $rolesql .= " JOIN {context} uctx ON uctx.instanceid=:childuserid AND uctx.contextlevel=" . CONTEXT_USER . " ";
            $childcontext = " OR ra.contextid = uctx.id ";
            $inparams['childuserid'] = $childuserid;
        }

        $rolesql .= " WHERE u.suspended <> 1 AND u.deleted <> 1
                    AND (ra.contextid = :ctxid2 $childcontext) AND ra.roleid $insql ORDER BY u.id";

        $params = ['ctxid' => $context->id, 'ctxid2' => $context->id] + $inparams;

        $users = $DB->get_records_sql($rolesql, $params);

        return $users;
    }

    /**
     * Get the status of the schedule.
     *
     * @param int $value
     * @param stdclass $row
     * @return string
     */
    public static function get_schedule_status($value, $row) {

        if ($value == self::STATUS_DISABLED) {
            return get_string('onhold', 'pulse');
        } else if ($value == self::STATUS_QUEUED) {
            if (!$row->instancestatus) {
                return get_string('onhold', 'pulse');
            }
            return get_string('queued', 'pulse');
        } else if ($value == self::STATUS_COMPLETED) {
            return self::get_completed_statusstring();
        } else {
            return get_string('failed', 'pulse');
        }
    }

    /**
     * Get the action completed status string. ie. allocated, sent etc.
     *
     * @return string
     */
    protected static function get_completed_statusstring(): string {
        return get_string('completed', 'core');
    }

    /**
     * Include the interval fields in the form.
     *
     * @param \MoodleQuickForm $mform The form to add the fields.
     * @param string $key The key to use for the fields.
     * @param array $intervals The intervals to include.
     * @return void
     */
    public static function include_interval_fields(&$mform, $key, $intervals) {

        $interval = [];

        // Schedule Group.
        $intervaloptions = [
            self::INTERVALONCE => get_string('once', 'mod_pulse'),
            self::INTERVALDAILY => get_string('daily', 'mod_pulse'),
            self::INTERVALWEEKLY => get_string('weekly', 'mod_pulse'),
            self::INTERVALMONTHLY => get_string('monthly', 'mod_pulse'),
            self::INTERVALYEARLY => get_string('yearly', 'mod_pulse'),
            self::INTERVALCUSTOM => get_string('customcron', 'mod_pulse'),
        ];

        $intervaloptions = array_filter($intervaloptions, function ($k) use ($intervals) {
            return in_array($k, $intervals);
        }, ARRAY_FILTER_USE_KEY);

        $interval[] =& $mform->createElement(
            'select',
            $key . '[interval]',
            get_string('interval', 'mod_pulse'),
            $intervaloptions
        );

        // Add additional settings based on the selected interval.
        $dayweeks = [
            'monday' => get_string('monday', 'mod_pulse'),
            'tuesday' => get_string('tuesday', 'mod_pulse'),
            'wednesday' => get_string('wednesday', 'mod_pulse'),
            'thursday' => get_string('thursday', 'mod_pulse'),
            'friday' => get_string('friday', 'mod_pulse'),
            'saturday' => get_string('saturday', 'mod_pulse'),
            'sunday' => get_string('sunday', 'mod_pulse'),
        ];

        if (in_array(self::INTERVALWEEKLY, $intervals)) {
            // Add 'day_of_week' element if 'weekly' is selected in the 'interval' element.
            $interval[] =& $mform->createElement('select', $key . '[weekday]', get_string('interval', 'mod_pulse'), $dayweeks);
            $mform->hideIf($key . '[weekday]', $key . '[interval]', 'neq', self::INTERVALWEEKLY);
        }

        if (in_array(self::INTERVALYEARLY, $intervals)) {
            // Get the calendar type used - see MDL-18375.
            $calendartype = \core_calendar\type_factory::get_calendar_instance();
            $dateformat = $calendartype->get_date_order();
            $months = $dateformat['month'];
            // Add 'month_of_year' element if 'yearly' is selected in the 'interval' element.
            $interval[] =& $mform->createElement('select', $key . '[month]', get_string('interval', 'mod_pulse'), $months);
            $mform->hideIf($key . '[month]', $key . '[interval]', 'neq', self::INTERVALYEARLY);
        }

        if (in_array(self::INTERVALMONTHLY, $intervals) || in_array(self::INTERVALYEARLY, $intervals)) {
            $dates = range(1, 31);
            $dates = array_combine($dates, $dates);
            // Add 'day_of_month' element if 'monthly' is selected in the 'interval' element.
            $interval[] =& $mform->createElement('select', $key . '[monthdate]', get_string('interval', 'mod_pulse'), $dates);
            $mform->hideIf($key . '[monthdate]', $key . '[interval]', 'in', [
                self::INTERVALWEEKLY, self::INTERVALDAILY, self::INTERVALONCE, self::INTERVALCUSTOM]);
        }

        // Time to send action.
        $dates = self::get_times();
        // Add 'time_of_day' element.
        // Add 'day_of_month' element if 'monthly' is selected in the 'interval' element.
        $interval[] =& $mform->createElement('select', $key . '[time]', get_string('interval', 'mod_pulse'), $dates);
        $mform->hideIf($key . '[time]', $key . '[interval]', 'eq', self::INTERVALONCE);
        $mform->hideIf($key . '[time]', $key . '[interval]', 'eq', self::INTERVALCUSTOM);

        // Add the interval group to the every yearly.
        if (in_array(self::INTERVALYEARLY, $intervals)) {
            $interval[] =& $mform->createElement(
                'text',
                $key . '[yearlyinterval]',
                get_string('everyyear', 'mod_pulse'),
                [
                    'size' => 3, 'placeholder' => '1 ' . get_string('everyyear', 'mod_pulse'),
                    'title' => get_string('everyyear', 'mod_pulse'),
                ]
            );
            $mform->hideIf($key . '[yearlyinterval]', $key . '[interval]', 'neq', self::INTERVALYEARLY);
            $mform->setType($key . '[yearlyinterval]', PARAM_INT);
        }

        // Add crontab fields for custom interval.
        if (in_array(self::INTERVALCUSTOM, $intervals)) {
            $interval[] =& $mform->createElement(
                'text',
                $key . '[cron_minute]',
                get_string('minute', 'mod_pulse'),
                ['size' => 10, 'placeholder' => '* (' . get_string('minute', 'mod_pulse') . ')']
            );
            $interval[] =& $mform->createElement(
                'text',
                $key . '[cron_hour]',
                get_string('hour', 'mod_pulse'),
                ['size' => 10, 'placeholder' => '* (' . get_string('hour', 'mod_pulse') . ') ']
            );
            $interval[] =& $mform->createElement(
                'text',
                $key . '[cron_day]',
                get_string('day', 'mod_pulse'),
                ['size' => 10, 'placeholder' => '* (' . get_string('day', 'mod_pulse') . ') ']
            );
            $interval[] =& $mform->createElement(
                'text',
                $key . '[cron_month]',
                get_string('month', 'mod_pulse'),
                ['size' => 10, 'placeholder' => '* (' . get_string('month', 'mod_pulse') . ')']
            );
            $interval[] =& $mform->createElement(
                'text',
                $key . '[cron_dayofweek]',
                get_string('dayofweek', 'mod_pulse'),
                ['size' => 10, 'placeholder' => '* (' . get_string('dayofweek', 'mod_pulse') . ') ']
            );

            $mform->setType($key . '[cron_minute]', PARAM_TEXT);
            $mform->setType($key . '[cron_hour]', PARAM_TEXT);
            $mform->setType($key . '[cron_day]', PARAM_TEXT);
            $mform->setType($key . '[cron_month]', PARAM_TEXT);
            $mform->setType($key . '[cron_dayofweek]', PARAM_TEXT);

            foreach (['cron_minute', 'cron_hour', 'cron_day', 'cron_month', 'cron_dayofweek'] as $cronfield) {
                $mform->hideIf($key . "[$cronfield]", $key . '[interval]', 'neq', self::INTERVALCUSTOM);
            }
        }

        // Action interval button groups.
        $mform->addGroup($interval, $key, get_string('interval', 'mod_pulse'), [' '], false);
        $mform->addHelpButton($key, 'interval', 'mod_pulse');
    }

    /**
     * Calculate the next runtime based on crontab configuration.
     *
     * @param DateTime $currenttime The current datetime to calculate from
     * @param array $cronconfig Array with keys: minute, hour, day, month, dayofweek
     * @return DateTime The next scheduled runtime
     */
    protected function calculate_next_cron_runtime(DateTime $currenttime, array $cronconfig) {
        // Create anonymous scheduled task to use moodle cron calculation.
        $task = new \tool_task\scheduled_checker_task();

        // Set cron fields from config, with defaults to *.
        $task->set_minute($cronconfig['minute'] ?? '*');
        $task->set_hour($cronconfig['hour'] ?? '*');
        $task->set_day($cronconfig['day'] ?? '*');
        $task->set_month($cronconfig['month'] ?? '*');
        $task->set_day_of_week($cronconfig['dayofweek'] ?? '*');

        // Calculate next run time using moodle built-in cron calculation.
        $nextruntime = $task->get_next_scheduled_time($currenttime->getTimestamp());

        // Return as DateTime object.
        return (new DateTime())->setTimestamp($nextruntime);
    }

    /**
     * Get list of options in 30 mins timeinterval for 24 hrs.
     *
     * @return array
     */
    public static function get_times() {

        $starttime = new DateTime('00:00'); // Set the start time to midnight.
        $endtime = new DateTime('23:59');   // Set the end time to just before midnight of the next day.

        // Create an interval of 30 minutes.
        $interval = new \DateInterval('PT30M'); // PT30M represents 30 minutes.

        // Create a DatePeriod to iterate through the day with the specified interval.
        $timeperiod = new DatePeriod($starttime, $interval, $endtime);

        // Loop through the DatePeriod and add each timestamp to the array.
        $timelist = [];
        foreach ($timeperiod as $time) {
            $timelist[$time->format('H:i')] = $time->format('H:i'); // Format the timestamp as HH:MM.
        }

        return $timelist;
    }
}
