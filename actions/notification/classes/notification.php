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
 * Notification pulse action - Create and Manage notifications.
 *
 * This Controller create a schedule for users, verify their availability based on conditions.
 *
 * @package   pulseaction_notification
 * @copyright 2023, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulseaction_notification;

use book;
use DateTime;
use stdClass;
use core_user;
use moodle_url;
use html_writer;
use mod_pulse\automation\action_base;
use mod_pulse\automation\helper;
use mod_pulse\automation\instances;
use mod_pulse\helper as pulsehelper;
use mod_pulse\plugininfo\pulsecondition;
use mod_pulse\plugininfo\pulseaction;
use tool_dataprivacy\form\context_instance;
use pulseaction_notification\task\notify_users;

/**
 * Notification helper, Manage user schedules CRUD.
 */
class notification {
    /**
     * Represents a course teacher as type of notification sender.
     * @var int
     */
    const SENDERCOURSETEACHER = 1;

    /**
     * Represents a group teacher as type of notification sender.
     * @var int
     */
    const SENDERGROUPTEACHER = 2;

    /**
     * Represents a tenent role as type of notification sender.
     * @var int
     */
    const SENDERTENANTROLE = 3;

    /**
     * Represents a custom email as type of notification sender.
     * @var int
     */
    const SENDERCUSTOM = 4;

    /**
     * Represents a notification interval is once.
     * @var int
     */
    const INTERVALONCE = 1; // Once.

    /**
     * Represents a notification interval is Daily.
     * @var int
     */
    const INTERVALDAILY = 2; // Daily.

    /**
     * Represents a notification interval is weekly.
     * @var int
     */
    const INTERVALWEEKLY = 3; // Weekly.

    /**
     * Represents a notification interval is monthly.
     * @var int
     */
    const INTERVALMONTHLY = 4; // Montly.

    /**
     * Represents there is no delay to send the notification.
     * @var int
     */
    const DELAYNONE = 0;

    /**
     * Represents a delay before send the notifications.
     * @var int
     */
    const DELAYBEFORE = 1;

    /**
     * Represents a delay after some time to send the notifications.
     * @var int
     */
    const DELAYAFTER = 2;

    /**
     * Delay base: use instance activation time (default, current behavior).
     * @var int
     */
    const DELAYBASE_INSTANCE = 0;

    /**
     * Delay base: use user's actual enrolment time.
     * @var int
     */
    const DELAYBASE_ENROLMENT = 1;

    /**
     * Delay base: use the time the last condition was satisfied.
     * @var int
     */
    const DELAYBASE_LASTCONDITION = 2;

    /**
     * Recipient sentinel: the triggering user themselves.
     *
     * Used by site-level conditions (e.g. "not enrolled") whose target recipient has no course
     * role and therefore cannot be resolved through normal role-based recipient lookup. A
     * negative value avoids colliding with any real role id.
     * @var int
     */
    const RECIPIENT_TRIGGERUSER = -100;

    /**
     * Represents a length of the dynamic content is teaser.
     * @var int
     */
    const LENGTH_TEASER = 1;

    /**
     * Represents the dynamic content included the link to access the desired module.
     * @var int
     */
    const LENGTH_LINKED = 2;

    /**
     * Represents there is not links included with dynamic content.
     * @var int
     */
    const LENGTH_NOTLINKED = 3;

    /**
     * Represents the content of the dynamic module is only for placeholder.
     * @var int
     */
    const DYNAMIC_PLACEHOLDER = 0;

    /**
     * Represents the description of the dynamic module is included in the notification.
     * @var int
     */
    const DYNAMIC_DESCRIPTION = 1;

    /**
     * Represents the content of the dynamic module is included in the notification.
     * @var int
     */
    const DYNAMIC_CONTENT = 2;

    /**
     * Represents the notification schedule status is failed.
     * @var int
     */
    const STATUS_FAILED = 0;

    /**
     * Represents the notification schedule status is disabled.
     * @var int
     */
    const STATUS_DISABLED = 1;

    /**
     * Represents the notification schedule status is queued.
     * @var int
     */
    const STATUS_QUEUED = 2;

    /**
     * Represents the notification schedule status is sent.
     * @var int
     */
    const STATUS_SENT = 3;

    /**
     * Represents the notification schedule is currently claimed and being sent.
     * @var int
     */
    const STATUS_PROCESSING = 4;

    /**
     * Represents the user completed the suppress module.
     * @var int
     */
    const SUPPRESSREACHED = 1;

    /**
     * Represents the notification suppress by course completion enabled.
     * @var int
     */
    const SUPPRESSCOURSECOMPLETION = 1;

    /**
     * The record of the notification instance with templates and general conditions.
     *
     * @var stdclass
     */
    protected $instancedata;

    /**
     * The merged notification data based on instance overrides.
     *
     * @var stdclass
     */
    protected $notificationdata;

    /**
     * The ID of the action notification table.
     * @var int
     */
    protected $notificationid; // Notification table id.

    /**
     * Create the instance of the notification controller.
     *
     * @param int $notificationid Notification instance record id (pulseaction_notification_ins) NOT autoinstanceid.
     * @return notification
     */
    public static function instance($notificationid) {
        static $instance;

        if (!$instance || ($instance && $instance->notificationid != $notificationid)) {
            $instance = new self($notificationid);
        }

        return $instance;
    }

    /**
     * Contructor for this notification controller.
     *
     * @param int $notificationid  Notification table id.
     */
    protected function __construct(int $notificationid) {
        $this->notificationid = $notificationid;
    }

    /**
     * Create the notification instance and set the data to this class.
     *
     * @return void
     */
    protected function create_instance_data() {
        global $DB;

        if (empty($this->notificationid)) {
            return [];
        }

        $notification = $DB->get_record('pulseaction_notification_ins', ['id' => $this->notificationid]);

        if (empty($notification)) {
            throw new \moodle_exception('notificationinstancenotfound', 'pulse');
        }
        $instance = instances::create($notification->instanceid);
        $autoinstance = $instance->get_instance_data();

        $notificationdata = $autoinstance->actions['notification'];

        unset($autoinstance->actions['notification']); // Remove actions.

        $this->set_notification_data($notificationdata, $autoinstance);
    }

    /**
     * Set the notification data to global. Decode and do other structure updates for the data before setup.
     *
     * @param stdclass $notificationdata Contains notification data.
     * @param stdclass $instancedata Contains other than actions.
     * @return void
     */
    public function set_notification_data($notificationdata, $instancedata) {
        // Set the notification data.
        $notificationdata = (object) $notificationdata;
        $this->notificationdata = $this->update_data_structure($notificationdata);

        // Set the instance data.
        $instancedata = (object) $instancedata;
        // Instance not contains course then include course.
        if (!isset($instancedata->course)) {
            $instancedata->course = get_course($instancedata->courseid);
        }

        $this->instancedata = $instancedata;
    }

    /**
     * Decode the encoded json values to array, to further uses.
     *
     * @param stdclass $actiondata
     * @return stdclass $actiondata Updated action data.
     */
    public function update_data_structure($actiondata) {

        if (empty($actiondata) || !property_exists($actiondata, 'recipients')) {
            return $actiondata;
        }

        $actiondata->recipients = is_array($actiondata->recipients)
            ? $actiondata->recipients : json_decode($actiondata->recipients);

        $actiondata->bcc = is_array($actiondata->bcc) ? $actiondata->bcc : json_decode($actiondata->bcc ?: '');
        $actiondata->cc = is_array($actiondata->cc) ? $actiondata->cc : json_decode($actiondata->cc ?: '');

        if (empty($actiondata->notifyinterval)) {
            $actiondata->notifyinterval = ['interval' => self::INTERVALONCE];
        } else {
            $actiondata->notifyinterval = is_array($actiondata->notifyinterval)
                ? $actiondata->notifyinterval : json_decode($actiondata->notifyinterval ?: '', true);
        }

        return $actiondata;
    }

    /**
     * Generate the data set for the user to create schedule for this instance.
     *
     * @param int $userid ID of the user to create schedule.
     * @return array $record Record to insert into schdeule.
     */
    protected function generate_schedule_record(int $userid) {

        $record = [
            'instanceid' => $this->notificationdata->instanceid,
            'userid' => $userid,
            'type' => $this->notificationdata->notifyinterval['interval'] ?? self::INTERVALONCE,
            'status' => self::STATUS_QUEUED,
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        return $record;
    }

    /**
     * Insert the schedule to database, verify if the schedule is already in queue then override the schedule with given record.
     *
     * @param stdclass $data
     * @param bool $newschedule
     * @param int $newfrequency
     * @return int Inserted schedule ID.
     */
    protected function insert_schedule($data, $newschedule = false, $newfrequency = false) {
        global $DB;

        $sql = 'SELECT *
                FROM {pulseaction_notification_sch}
                WHERE instanceid = :instanceid
                AND userid = :userid AND frequencycount=:frequency
                AND (status = :disabledstatus  OR status = :queued)';

        $params = [
            'instanceid' => $data['instanceid'], 'userid' => $data['userid'], 'disabledstatus' => self::STATUS_DISABLED,
            'queued' => self::STATUS_QUEUED, 'frequency' => $data['frequencycount'] ?? 0,
        ];

        if (isset($data['relateduserid']) && empty($data['relateduserid'])) {
            $data['relateduserid'] = null;
            $sql .= ' AND relateduserid IS NULL';
        } else {
            $sql .= ' AND relateduserid = :relateduserid';
        }

        $params['relateduserid'] = $data['relateduserid'];

        if ($record = $DB->get_record_sql($sql, $params)) {
            $data['id'] = $record->id;
            unset($data['timecreated']);
            // Update the status to enable for notify.
            $DB->update_record('pulseaction_notification_sch', $data);
            return $record->id;
        }

        // Dont create new schedule for already notified users until is not new schedule.
        // It prevents creating new record for user during the update of instance interval.
        if (
            (!$newschedule && !$newfrequency) && $DB->record_exists('pulseaction_notification_sch', [
            'instanceid' => $data['instanceid'], 'userid' => $data['userid'], 'status' => self::STATUS_SENT,
            'relateduserid' => $data['relateduserid'] ?? null,
            ])
        ) {
            return false;
        }

        return $DB->insert_record('pulseaction_notification_sch', $data);
    }

    /**
     * Disable the queued schdule of the given user.
     *
     * @param int $userid
     * @param int|null $relateduserid
     * @return void
     */
    protected function disable_user_schedule($userid, ?int $relateduserid = null) {
        global $DB;

        $sql = "SELECT * FROM {pulseaction_notification_sch}
                WHERE instanceid = :instanceid AND userid = :userid AND (status = :disabledstatus  OR status = :queued)";

        $params = [
            'instanceid' => $this->notificationdata->instanceid, 'userid' => $userid, 'disabledstatus' => self::STATUS_DISABLED,
            'queued' => self::STATUS_QUEUED,
        ];

        // Disable the schedule for related user if provided.
        if ($relateduserid && $relateduserid != $userid) {
            $sql .= " AND relateduserid = :relateduserid";
            $params['relateduserid'] = $relateduserid;
        }

        if ($record = $DB->get_record_sql($sql, $params)) {
            $DB->set_field('pulseaction_notification_sch', 'status', self::STATUS_DISABLED, ['id' => $record->id]);
        }
    }

    /**
     * Remove the queued and disabled schedules of this user.
     *
     * @param int $userid
     * @return void
     */
    public function remove_user_schedules($userid) {
        global $DB;

        $sql = "SELECT * FROM {pulseaction_notification_sch}
                WHERE instanceid = :instanceid AND userid = :userid AND (status = :disabledstatus  OR status = :queued)";

        $params = [
            'instanceid' => $this->notificationdata->instanceid, 'userid' => $userid, 'disabledstatus' => self::STATUS_DISABLED,
            'queued' => self::STATUS_QUEUED,
        ];

        if ($record = $DB->get_record_sql($sql, $params)) {
            $DB->delete_records('pulseaction_notification_sch', ['id' => $record->id]);
        }
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
            $record = $DB->get_record('pulseaction_notification_sch', [
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
     * @param int|null $relateduserid Related user id for user context roles.
     * @return int|null Time of the last schedule notified to the user for the specific instance
     */
    protected function find_last_notifiedtime(int $userid, ?int $relateduserid = null) {
        global $DB;

        $id = $this->notificationdata->instanceid;

        // Get last notified schedule for this instance to the user.
        $condition = ['instanceid' => $id, 'userid' => $userid, 'status' => self::STATUS_SENT];

        if ($relateduserid && $relateduserid != $userid) {
            $condition += ['relateduserid' => $relateduserid];
        }

        $records = $DB->get_records('pulseaction_notification_sch', $condition, 'id DESC', '*', 0, 1);

        return !empty($records) ? current($records)->notifiedtime : '';
    }

    /**
     * Find a count of the schedules sent to the user for the current notification instance.
     *
     * @param int $userid ID of the user to fetch the counts
     * @return int|null Count of the schedules sent to the user
     */
    protected function find_notify_count($userid) {
        global $DB;

        $id = $this->notificationdata->instanceid;

        // Get last notified schedule for this instance to the user.
        $condition = ['instanceid' => $id, 'userid' => $userid, 'status' => self::STATUS_SENT];
        $records = $DB->get_records('pulseaction_notification_sch', $condition, 'id DESC', '*', 0, 1);

        $current = !empty($records) ? current($records) : [];

        return !empty($current) ? [$current->notifycount, $current->frequencycount] : [0, 0];
    }

    /**
     * Verify the user is already notified for this instance. It verify the lastrun is empty for the user record.
     *
     * Note: Use this method to verify the instance with interval once.
     *
     * @param int $userid
     * @param int|null $relateduserid Related user id for user context roles.
     * @return bool
     */
    protected function is_user_notified(int $userid, ?int $relateduserid = null) {
        global $DB;

        $id = $this->notificationdata->instanceid;
        $usercondition = "userid = :userid";
        $condition = ['instanceid' => $id, 'userid' => $userid, 'status' => self::STATUS_SENT];

        if ($relateduserid && $relateduserid != $userid) {
            $condition += ['relateduserid' => $relateduserid];
            $usercondition = "(userid = :userid AND relateduserid = :relateduserid) ";
        }

        $sql = "SELECT notifiedtime
                  FROM {pulseaction_notification_sch}
                 WHERE instanceid = :instanceid
                   AND $usercondition AND status = :status
              ORDER BY id DESC";

        if ($record = $DB->get_record_sql($sql, $condition, IGNORE_MULTIPLE)) {
            return $record->notifiedtime != null ? true : false;
        }

        return false;
    }

    /**
     * Remove the schdeduled notifications for this instance.
     *
     * @param int $status
     * @return void
     */
    protected function remove_schedules($status = self::STATUS_SENT) {
        global $DB;

        $DB->delete_records('pulseaction_notification_sch', ['instanceid' => $this->instancedata->id, 'status' => $status]);
    }

    /**
     * Disable the queued schdule for all users.
     *
     * @return void
     */
    protected function disable_schedules() {
        global $DB;

        if (!empty($this->notificationdata)) {
            $this->create_instance_data();
        }

        $params = [
            'instanceid' => $this->notificationdata->instanceid,
            'status' => self::STATUS_QUEUED,
        ];

        // Disable the queued schedules for this instance.
        $DB->set_field('pulseaction_notification_sch', 'status', self::STATUS_DISABLED, $params);
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
        // Recreate schedules without restarting frequency cycles for already-notified users.
        $this->create_schedule_forinstance(false, null, false, true);
    }

    /**
     * Create schdule for the instance.
     *
     * @param bool $newenrolment Is the schedule for new enrolments.
     * @param int|bool $newuserid User id, The schedule is only for this user.
     * @param bool $newfrequency Frequency of the schedule.
     * @param bool $fromsave If the schedule is created from save action.
     * @return void
     */
    public function create_schedule_forinstance(
        $newenrolment = false,
        $newuserid = null,
        $newfrequency = false,
        $fromsave = false
    ) {

        // Generate the notification instance data.
        if (empty($this->instancedata)) {
            $this->create_instance_data();
        }

        // No instance data found.
        if (!property_exists($this->notificationdata, 'actionstatus')) {
            return false;
        }

        // Confirm the instance is not disabled.
        if (!$this->instancedata->status || $this->notificationdata->actionstatus == 0) {
            $this->disable_schedules();
            return false;
        }

        // Course context.
        $context = \context_course::instance($this->instancedata->courseid);

        // Roles to receive the notifications.
        $roles = $this->notificationdata->recipients;

        // Detect the "triggering user" sentinel and strip it from the role id list so it isn't
        // treated as a real role. Used by site-level conditions where the recipient is the user
        // themselves (no course role to resolve).
        $selftrigger = in_array((string) self::RECIPIENT_TRIGGERUSER, array_map('strval', (array) $roles), true);
        $roles = array_filter((array) $roles, fn($v) => (string) $v !== (string) self::RECIPIENT_TRIGGERUSER);

        if (empty($roles) && !$selftrigger) {
            // No roles are defined to recieve notifications. Remove the schedules for this instance.
            $this->remove_schedules();
            return true; // No roles are defined to recieve notifications. Break the schedule creation.
        }

        // Clean up custom mails and roles from recipient roles.
        $custommails = array_filter($roles, fn($v) => validate_email($v));
        $roles = array_filter($roles, fn($v) => is_number($v));

        // Fetch the users with capability to receive notification.
        // Consider as student role users. They are relative to course and user context role users.
        $relativeusers = get_users_by_capability($context, 'pulseaction/notification:receivenotification', 'u.id');

        // Get the users for this receipents roles.
        // It returns the user id as instance id for the user role assignment of usercontext users.
        $users = !empty($roles) ? $this->get_users_withroles($roles, $context, $newuserid) : [];
        if (empty($users) && empty($custommails) && !$selftrigger) {
            // No users found with the given roles. Remove the schedules for this instance.
            return true;
        }
        // Group the users by thier context.
        $coursecontextusers = array_filter($users, fn($v) => $v->contextlevel == CONTEXT_COURSE && $v->permission != CAP_ALLOW);
        // Fix- PLS-1015.
        // User context users are the users with user context role assignment.
        $usercontextusers = array_filter($users, fn($v) => $v->contextlevel == CONTEXT_USER);
        // Student users are the users with course context role assignment with permission to receive notification.
        // It can be student role or any custom role with capability to receive notification.
        $studentusers = array_filter($users, fn($v) => $v->contextlevel == CONTEXT_COURSE && $v->permission == CAP_ALLOW);

        // The triggering user themselves. Scheduled via the same path as student recipients; the
        // only difference is they need not be course-enrolled (handled by the send-time gate).
        if ($selftrigger && $newuserid && ($selfuser = \core_user::get_user($newuserid))) {
            $studentusers[$newuserid] = $selfuser;
        }

        // .... Custom Email Support.
        // Verify the custom emails already exists as user.
        foreach ($custommails as $customemail) {
            if ($user = core_user::get_user_by_email($customemail)) {
                // If user found with the custom email, then include the user in student users.
                $customusers[$user->id] = $user;
            } else {
                // Create a dummy user object for custom email.
                $user = \pulseaction_notification\local\custom_mail::create_nologin_user_for_email($customemail);
                $customusers[$user->id] = $user;
            }
        }

        // Schedule for the new user or for specified user, Fetch the user record from receipent roles.
        if ($newuserid) {
            // Filter the student users only for the new user.
            // Notification will only be sent to the triggered user.
            $studentusers = array_filter($studentusers, function ($user) use ($newuserid) {
                return $newuserid == $user->id;
            });

            // Filter the relative users only for the new user.
            $relativeusers = array_filter($relativeusers, function ($user) use ($newuserid) {
                return $newuserid == $user->id;
            });

            // Filter the user context users only for the new user.
            $usercontextusers = array_filter($usercontextusers, function ($user) use ($newuserid) {
                // User CTX roles results contains the assigned user (child) id as instance id.
                return $newuserid == $user->instanceid;
            });

            // Groupfilter.
            $newusergroups = groups_get_user_groups($this->instancedata->courseid, $newuserid)[0] ?? [];
            if (!empty($newusergroups)) {
                $groupfilter = [];
                foreach ($coursecontextusers as $userid => $user) {
                    $usergroups = groups_get_user_groups($this->instancedata->courseid, $user->id)[0] ?? [];
                    if (array_intersect($newusergroups, $usergroups)) {
                        $groupfilter[$userid] = $user;
                    }
                }

                if (!empty($groupfilter)) {
                    $coursecontextusers = $groupfilter;
                }
            }
        }

        // Create a scheduels for user context level users, for the student users.
        foreach ($coursecontextusers as $userid => $user) {
            // Create schedule for each relative user.
            foreach ($relativeusers as $relateduserid => $suser) {
                $suppressreached = notify_users::is_suppress_reached(
                    $this->notificationdata,
                    $suser->id,
                    $this->instancedata->course,
                    null
                );

                if ($suppressreached) {
                    continue;
                }

                $this->create_schedule_foruser(
                    $user->id,
                    null,
                    0,
                    null,
                    $newenrolment,
                    false,
                    $newfrequency,
                    $suser->id,
                    $fromsave
                );
            }
        }
        // Create schedules for user context roles (ie.parent).
        foreach ($usercontextusers as $userid => $user) {
            $suppressreached = notify_users::is_suppress_reached(
                $this->notificationdata,
                $user->instanceid, // User CTX roles results contains the assigned user (child) id as instance id.
                $this->instancedata->course,
                null,
            );

            if ($suppressreached) {
                continue;
            }

            $this->create_schedule_foruser(
                $user->id,
                null,
                0,
                null,
                $newenrolment,
                false,
                $newfrequency,
                $user->instanceid,
                $fromsave
            );
        }

        // Send notification to custom email users, for all users.
        foreach ($customusers ?? [] as $userid => $user) {
            $effectiverelativeusers = $relativeusers;
            if (empty($effectiverelativeusers) && $newuserid && !is_siteadmin($newuserid)) {
                $fallbackuser = \core_user::get_user($newuserid);
                if ($fallbackuser) {
                    $effectiverelativeusers = [$newuserid => $fallbackuser];
                }
            }
            foreach ($effectiverelativeusers as $relateduserid => $suser) {
                $suppressreached = notify_users::is_suppress_reached(
                    $this->notificationdata,
                    $suser->id,
                    $this->instancedata->course,
                    null
                );

                if ($suppressreached) {
                    continue;
                }

                $this->create_schedule_foruser(
                    $user->id,
                    null,
                    0,
                    null,
                    $newenrolment,
                    false,
                    $newfrequency,
                    $suser->id,
                    $fromsave
                );
            }
        }

        // Create schedules for student users. if the student role is selected in the receipents.
        foreach ($studentusers as $userid => $user) {
            $suppressreached = notify_users::is_suppress_reached(
                $this->notificationdata,
                $user->id,
                $this->instancedata->course,
                null
            );

            if ($suppressreached) {
                continue;
            }
            $this->create_schedule_foruser(
                $user->id,
                null,
                0,
                null,
                $newenrolment,
                false,
                $newfrequency,
                null,
                $fromsave
            );
        }

        return true;
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
            if (!empty($values) && ($values['status'] || $values == 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create schedule for the user.
     *
     * @param int $scheduleuserid
     * @param string $lastrun
     * @param integer $notifycount
     * @param int $expectedruntime Timestamp of the time to run.
     * @param bool $isnewuser
     * @param bool $newschedule
     * @param bool $newfrequency
     * @param int|null $relateduserid Schedule user id, if the schdule is for different user than the given user id.
     * @param bool $fromsave If the schedule is created from save action.
     * @return int ID of the created schedule.
     */
    public function create_schedule_foruser(
        $scheduleuserid,
        $lastrun = '',
        $notifycount = 0,
        $expectedruntime = null,
        $isnewuser = false,
        $newschedule = false,
        $newfrequency = false,
        $relateduserid = null,
        $fromsave = false
    ) {

        if (empty($this->instancedata)) {
            $this->create_instance_data();
        }

        // Set the schedule user id. For empty schedule user id use the given user id.
        $relateduserid = $relateduserid ?: $scheduleuserid;

        // Instance should be configured with any of conditions. Otherwise stop creating instance (PLS-637).
        // Verify the user passed the instance condition.
        if (
            $this->notificationdata->actionstatus == 0 || !$this->verfiy_instance_contains_condition()
            || !instances::create($this->notificationdata->instanceid)->find_user_completion_conditions(
                $this->instancedata->condition,
                $this->instancedata,
                $relateduserid,
                $isnewuser
            )
            || ($newschedule
                && notify_users::is_suppress_reached($this->notificationdata, $relateduserid, $this->instancedata->course, null))
        ) {
            // Remove the user condition.
            $this->disable_user_schedule($scheduleuserid, $relateduserid);
            return true;
        }

        [$findnotifycount, $frequencycount] = $this->find_notify_count($scheduleuserid);

        // If the frequency limit is 0 then its unlimited. no need to consider the notification limit or interval.
        // If this schedule not for new frequency then verify the notification limit and interval.
        // Only need to consider the notifiction count and interval
        // if the frequency is not new one and the frequency limit is not unlimited.
        // If frequency is available then no need to consider the notification limit and interval.
        // create a schedule and increase the frequency count.
        if (
            !$newfrequency
            || ($this->instancedata->frequencylimit >= 1 && !$this->frequncy_available($scheduleuserid, $frequencycount))
        ) {
            $notifycount = $notifycount ?: $findnotifycount;
            // Verify the Limit is reached, if 0 then its unlimited.
            if ($this->notificationdata->notifylimit > 0 && ($notifycount >= $this->notificationdata->notifylimit)) {
                return false;
            }

            // If the interval is not set then its once.
            if (empty($this->notificationdata->notifyinterval)) {
                $this->notificationdata->notifyinterval['interval'] = self::INTERVALONCE;
            }

            // Notification interval is once per user, it already notified to the user. break the trigger here.
            if (
                $this->notificationdata->notifyinterval['interval'] == self::INTERVALONCE
                && $this->is_user_notified($scheduleuserid, $relateduserid)
            ) {
                // If the frequency limit is 0 then its unlimited. no need to consider the notification limit or interval.
                if (
                    !$fromsave && ($this->instancedata->frequencylimit == 0 ||
                    $this->frequncy_available($scheduleuserid, $frequencycount))
                ) {
                    $newfrequency = true;
                    $newschedule  = true;
                    $notifycount  = 0; // Reset count for the new frequency cycle.
                } else {
                    return true;
                }
            }
        }

        if ($newfrequency) {
            $frequencycount++;
        }

        $lastrun = $lastrun ?: $this->find_last_notifiedtime($scheduleuserid, $relateduserid);

        // Generate the schedule record.
        $data = $this->generate_schedule_record($scheduleuserid);
        // Set the notify count.
        $data['notifycount'] = $notifycount;
        // ...# Find the next run. Use the related user id to generate the schedule time.
        $nextrun = $this->generate_the_scheduletime($relateduserid, $lastrun, $expectedruntime);
        // Include the next run to schedule.
        $data['scheduletime'] = $nextrun;
        // Insert the new schedule or update schedule.
        $data['frequencycount'] = $frequencycount;

        $data['relateduserid'] = $scheduleuserid != $relateduserid ? $relateduserid : '';

        $scheduleid = $this->insert_schedule($data, $newschedule, $newfrequency);

        return $scheduleid;
    }

    /**
     * Frequency limit is reached for the user.
     *
     * @param int $userid
     * @param int $frequencycount
     *
     * @return bool
     */
    protected function frequncy_available($userid, $frequencycount) {

        if ($frequencycount >= $this->instancedata->frequencylimit - 1) {
            return false;
        }
        return true;
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

        $data = $this->notificationdata;
        $data->userid = $userid;
        $course = $this->instancedata->courseid;

        $now = new DateTime('now', \core_date::get_server_timezone_object());

        if ($expectedruntime) {
            $expectedruntime = $now->setTimestamp($expectedruntime);
        }

        $nextrun = $expectedruntime ?: $now;
        if (!empty($lastrun)) {
            $lastrun = ($lastrun instanceof DateTime)
                ?: (new DateTime('now', \core_date::get_server_timezone_object()))->setTimestamp($lastrun);
            $nextrun = $lastrun;
        }

        $interval = $data->notifyinterval['interval'] ?? self::INTERVALONCE;

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

            case self::INTERVALONCE:
                $nextrun = $expectedruntime ?: $now;
                break;
        }

        // Determine the delay base reference point.
        $delaybase = (int)($data->delaybase ?? self::DELAYBASE_INSTANCE);
        $delaymode = (int)($data->notifydelay ?? self::DELAYNONE);

        if ($delaymode !== self::DELAYNONE && $delaybase === self::DELAYBASE_ENROLMENT) {
            // Use the user's actual enrolment time as the base for delay calculation.
            $instance = instances::create($data->instanceid);
            $enroltime = $instance->get_user_enrolment_createtime($userid, get_course($course));
            if ($enroltime) {
                $nextrun->setTimestamp($enroltime);
            }
        } else if ($delaymode !== self::DELAYNONE && $delaybase === self::DELAYBASE_LASTCONDITION) {
            // Use the time the last condition was satisfied as the base for delay calculation.
            $lastconditiontime = $this->get_last_condition_time($userid);
            if ($lastconditiontime) {
                $nextrun->setTimestamp($lastconditiontime);
            }
        } else {
            // Instance base (default): check if any delay-supported condition provides a base time.
            $delaytime = $this->get_delay_supported_time($data);
            if (!empty($delaytime)) {
                $nextrun->setTimestamp($delaytime);
            }
        }

        // Apply delay offset based on delay mode.
        if ($delaymode === self::DELAYAFTER) {
            $delay = (int)($data->delayduration ?? 0);
            if ($delay > 0) {
                $nextrun->modify("+ $delay seconds");
            }
        } else if ($delaymode === self::DELAYBEFORE) {
            $delay = (int)($data->delayduration ?? 0);
            if ($delay > 0) {
                $nextrun->modify("- $delay seconds");
            }
        }

        return $nextrun->getTimestamp();
    }

    /**
     * Get delay supported time from enabled conditions.
     *
     * @param object $data The automation data
     * @return int|null The timestamp from delay-supported condition, or null if none found
     */
    protected function get_delay_supported_time($data) {
        $conditions = $this->instancedata->condition ?? [];

        $time = null;
        foreach ($conditions as $conditionname => $conditiondata) {
            if ($conditiondata == 0 || empty($conditiondata['status'])) {
                continue;
            }

            $conditionclass = "\\pulsecondition_{$conditionname}\\conditionform";
            if (!class_exists($conditionclass)) {
                continue;
            }

            $condition = new $conditionclass();
            if (!method_exists($condition, 'delay_support_plugins') || !$condition->delay_support_plugins()) {
                continue;
            }

            $method = 'get_' . $conditionname . '_time';
            if (method_exists($conditionclass, $method)) {
                $time = $conditionclass::$method($data, $this->instancedata);
                if (!empty($time)) {
                    break;
                }
            }
        }

        return $time;
    }

    /**
     * Get the condition satisfaction time used as delay base.
     *
     * @param int $userid The user ID.
     * @return int|null The condition satisfaction timestamp, or null if none found.
     */
    protected function get_last_condition_time($userid) {
        $conditions = $this->instancedata->condition ?? [];
        $targettime = null;
        $triggerstatus = (int)($this->instancedata->triggeroperator ?? action_base::OPERATOR_ALL) === action_base::OPERATOR_ANY;

        foreach ($conditions as $conditionname => $conditiondata) {
            if ($conditiondata == 0 || empty($conditiondata['status'])) {
                continue;
            }

            $conditionclass = "\\pulsecondition_{$conditionname}\\conditionform";
            if (!class_exists($conditionclass)) {
                continue;
            }

            $condition = new $conditionclass();
            $conditiontime = $condition->get_condition_satisfied_time($userid, $this->instancedata);

            if ($conditiontime) {
                if ($triggerstatus) {
                    if ($targettime === null || $conditiontime < $targettime) {
                        $targettime = $conditiontime;
                    }
                } else {
                    // ALL operator: delay is based on the last condition met.
                    if ($targettime === null || $conditiontime > $targettime) {
                        $targettime = $conditiontime;
                    }
                }
            }
        }

        return $targettime;
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
        $rolesql = "SELECT ra.id as assignid, u.*, ra.roleid, c.instanceid, c.contextlevel, rc.permission
                    FROM {role_assignments} ra
                    JOIN {user} u ON u.id = ra.userid
                    JOIN {role} r ON ra.roleid = r.id
                    JOIN {context} c ON ra.contextid = c.id
                    LEFT JOIN {role_names} rn ON (rn.contextid = :ctxid AND rn.roleid = r.id)
                    LEFT JOIN {role_capabilities} rc ON rc.roleid = r.id AND rc.capability = :capbility";

        // Fetch the parent users related to the child user.
        $childcontext = '';
        if ($childuserid) {
            $rolesql .= " JOIN {context} uctx ON uctx.instanceid=:childuserid AND uctx.contextlevel=" . CONTEXT_USER . " ";
            $childcontext = " OR ra.contextid = uctx.id ";
            $inparams['childuserid'] = $childuserid;
        } else {
            // Include the users assigned to the relative role of the users assigned in the course context.
            $childcontext = "OR (
                                ra.contextid IN (
                                    SELECT id FROM {context}
                                    WHERE contextlevel = " . CONTEXT_USER . "
                                    AND instanceid IN (
                                        SELECT userid FROM {role_assignments}
                                        WHERE contextid = :ctxid3
                                    )
                                )
                            )";
        }

        $rolesql .= " WHERE u.suspended <> 1 AND u.deleted <> 1
                    AND (ra.contextid = :ctxid2 $childcontext)
                    AND ra.roleid $insql
                    ORDER BY u.id";

        $params = ['ctxid' => $context->id, 'ctxid2' => $context->id, 'ctxid3' => $context->id] + $inparams;

        $params['capbility'] = 'pulseaction/notification:receivenotification';

        $users = $DB->get_records_sql($rolesql, $params);

        return $users;
    }

    /**
     * Build the notification content.
     *
     * @param stdClass|null $cm Course module
     * @param \context $context
     * @param array $overrides
     *
     * @return string Notification content.
     */
    public function build_notification_content(?stdClass $cm = null, $context = null, $overrides = []) {

        $syscontext = \context_system::instance();

        $headercontent = $this->notificationdata->headercontent;
        $staticcontent = $this->notificationdata->staticcontent;
        $footercontent = $this->notificationdata->footercontent;

        // Rewrite the plugin url for files in editors.
        foreach (['headercontent', 'staticcontent', 'footercontent'] as $editor) {
            $content = $$editor;

            $field = 'pulsenotification_' . $editor;
            $prefix = (isset($overrides[$editor]) || isset($overrides[$field]) || isset($overrides[$field . '_editor']))
                ? '_instance' : '';
            $id = (isset($overrides[$editor]) || isset($overrides[$field]) || isset($overrides[$field . '_editor']))
                ? $this->notificationdata->instanceid : $this->instancedata->templateid;

            $$editor = file_rewrite_pluginfile_urls(
                $content,
                'pluginfile.php',
                $syscontext->id,
                'mod_pulse',
                $field . $prefix,
                $id
            );
        }

        // Include the dynamic contents.
        $dynamiccontent = $this->notificationdata->dynamiccontent;
        if ($dynamiccontent) {
            if ($cm == null) {
                $module = get_coursemodule_from_id('', $dynamiccontent);
                $cm = (object) [
                    'instance' => $module->instance,
                    'modname' => $module->modname,
                    'id' => $module->id,
                ];
            }

            $modcontext = \context_module::instance($dynamiccontent);

            $staticcontent .= self::generate_dynamic_content(
                $this->notificationdata->contenttype,
                $this->notificationdata->contentlength,
                $this->notificationdata->chapterid ?? 0,
                $modcontext,
                $cm
            ); // Concat the dynamic content after static content.
        }

        $finalcontent = $headercontent . $staticcontent . $footercontent;

        return format_text($finalcontent, FORMAT_HTML, ['overflowdiv' => true]);
    }

    /**
     * Gernerate the content based on dynamic module to attach with the notification content.
     *
     * @param string $contenttype
     * @param int $contentlength
     * @param int $chapterid
     * @param \context $context
     * @param stdclass $cm
     *
     * @return string
     */
    public static function generate_dynamic_content($contenttype, $contentlength, $chapterid, $context, $cm) {
        global $CFG, $DB;

        // Include module libarary files.
        require_once($CFG->dirroot . '/lib/modinfolib.php');
        require_once($CFG->dirroot . '/mod/book/lib.php');
        require_once($CFG->libdir . '/filelib.php');

        // Content type is placholder, no need to include the content.
        if ($contenttype == self::DYNAMIC_PLACEHOLDER) {
            return '';
        }

        if ($contenttype == self::DYNAMIC_CONTENT && in_array($cm->modname, ['book', 'page'])) {
            if ($cm->modname == 'book') {
                $chapter = $DB->get_record('book_chapters', ['id' => $chapterid, 'bookid' => $cm->instance]);
                $chaptertext = \file_rewrite_pluginfile_urls(
                    $chapter->content,
                    'pluginfile.php',
                    $context->id,
                    'mod_book',
                    'chapter',
                    $chapter->id
                );

                $content = format_text($chaptertext, $chapter->contentformat, ['overflowdiv' => true]);
                $link = new moodle_url('/mod/book/view.php', ['id' => $cm->id, 'chapterid' => $chapterid]);
            } else if ($cm->modname == 'page') {
                $page = $DB->get_record('page', ['id' => $cm->instance], '*', MUST_EXIST);

                $content = file_rewrite_pluginfile_urls(
                    $page->content,
                    'pluginfile.php',
                    $context->id,
                    'mod_page',
                    'content',
                    $page->revision
                );
                $link = new moodle_url('/mod/page/view.php', ['id' => $cm->id]);
            }
        } else {
            // ...TODO: Need to cache module intro content.
            $activity = $DB->get_record("$cm->modname", ['id' => $cm->instance]);
            $content = format_module_intro($cm->modname, $activity, $cm->id, true);
            $link = new moodle_url("/mod/$cm->modname/view.php", ['id' => $cm->id]);
        }

        // Verify the contnet length.
        switch ($contentlength) {
            case self::LENGTH_TEASER:
                preg_match('/<p>(.*?)<\/p>/s', $content, $match);
                $content = $match[0] ?? $content;

                $content .= html_writer::link($link, get_string('readmore', 'pulseaction_notification'));
                break;

            case self::LENGTH_LINKED:
                $content .= html_writer::link($link, get_string('readmore', 'pulseaction_notification'));
                break;
        }

        return $content;
    }

    /**
     * Generate the details of the notification to send.
     *
     * @param stdclass $moddata
     * @param stdclass $user
     * @param \context $context
     * @param array $notificationoverrides
     * @return stdclass Basic details to send notification.
     */
    public function generate_notification_details($moddata, $user, $context, $notificationoverrides = []) {
        global $USER;

        // Find the cc and bcc users for this schedule.
        $roles = array_merge($this->notificationdata->cc, $this->notificationdata->bcc);

        // Clean up custom mails and roles from CC and BCC roles.
        $custommails = array_filter($roles, fn($v) => validate_email($v));
        $roles = array_filter($roles, fn($v) => is_number($v));

        // Get the users for this bcc and cc roles.
        $roleusers = $this->get_users_withroles($roles, $context, $user->id);

        // Filter the cc users for this instance.
        $cc = $this->notificationdata->cc;
        $ccusers = array_filter($roleusers, function ($value) use ($cc) {
            return in_array($value->roleid, $cc);
        });

        // Filter the bcc users for this instance.
        $bcc = $this->notificationdata->bcc;
        $bccusers = array_filter($roleusers, function ($value) use ($bcc) {
            return in_array($value->roleid, $bcc);
        });

        // .... Custom Email Support.
        // Verify the custom emails already exists as user.
        foreach ($custommails as $customemail) {
            if ($customuser = core_user::get_user_by_email($customemail)) {
                // If user found with the custom email, then include the user in student users.
                $customusers[$customuser->id] = $customuser;
            } else {
                // Create a dummy user object for custom email.
                $customuser = \pulseaction_notification\local\custom_mail::create_nologin_user_for_email($customemail);
                $customusers[$customuser->id] = $customuser;
            }
        }
        // Include the custom users in bcc users.
        if (!empty($customusers)) {
            foreach ($customusers as $cuserid => $customuser) {
                if (in_array($customuser->email, $this->notificationdata->cc)) {
                    $ccusers[$cuserid] = $customuser;
                    continue;
                }
                if (in_array($customuser->email, $this->notificationdata->bcc)) {
                    $bccusers[$cuserid] = $customuser;
                    continue;
                }
            }
        }

        // Set the recepient as session user for format content.
        try {
            $olduser = $USER;
            \core\session\manager::set_user($user);

            // Use the current user language to filter content.
            if ($user->lang != current_language()) {
                $oldforcelang = force_current_language($user->lang);
            }

            $result = [
                'recepient' => (object) $user,
                'cc'        => array_map(fn($user) => [$user->email, fullname($user)], $ccusers),
                'bcc'       => array_map(fn($user) => [$user->email, fullname($user)], $bccusers),
                'subject'   => format_string($this->notificationdata->subject),
                'content'   => $this->build_notification_content($moddata, $context, $notificationoverrides),
            ];

            // After format the message and subject return back to previous lang.
            if (isset($oldforcelang)) {
                force_current_language($oldforcelang);
                unset($oldforcelang);
            }
            // Return to normal current session user.
            \core\session\manager::set_user($olduser);
        } catch (\Exception $e) {
            // Return to normal current session user.
            \core\session\manager::set_user($olduser);
            throw $e;
        }

        return (object) $result;
    }

    /**
     * Get the tenant role sender user.
     *
     * @param stdclass $scheduledata
     * @return stdclass
     */
    public function get_tenantrole_sender($scheduledata) {
        // ...TODO: Tenant based sender fetch goes here.
        return (object) [];
    }

    /**
     * Find the sender users for the course context, fetched the teachers based on group assignment.
     *
     * @param \context $coursecontext
     * @param int $groupid
     * @return stdclass
     */
    protected static function get_sender_users($coursecontext, $groupid) {

        $withcapability = 'pulseaction/notification:sender';
        $sender = get_enrolled_users(
            $coursecontext,
            $withcapability,
            $groupid,
            'u.*',
            null,
            0,
            1,
            true
        );

        return !empty($sender) ? current($sender) : [];
    }

    /**
     * Load book chapters.
     *
     * @param int $cmid Course module id.
     * @return array
     */
    public static function load_book_chapters(int $cmid) {
        global $DB;
        $mod = get_coursemodule_from_id('', $cmid);

        $list = '';
        if ($mod->modname == 'book') {
            $chapters = $DB->get_records('book_chapters', ['bookid' => $mod->instance]);
            return $chapters;
        }
        return $list;
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
            return get_string('onhold', 'pulseaction_notification');
        } else if ($value == self::STATUS_QUEUED) {
            if (!$row->instancestatus) {
                return get_string('onhold', 'pulseaction_notification');
            }
            return get_string('queued', 'pulseaction_notification');
        } else if ($value == self::STATUS_SENT) {
            return get_string('sent', 'pulseaction_notification');
        } else if ($value == self::STATUS_PROCESSING) {
            return get_string('sending', 'pulseaction_notification');
        } else {
            return get_string('failed', 'pulseaction_notification');
        }
    }

    /**
     * Get the schedule subject to display in the reports.
     *
     * @param string $value Subject
     * @param stdclass $row
     * @return string
     */
    public static function get_schedule_subject($value, $row) {
        global $DB;
        static $module;

        $value = $row->instancesubject ?: $row->templatesubject; // Use templates subject if instance subject doesn't overrides.
        $sender = \core_user::get_support_user();
        $courseid = $DB->get_field('pulse_autoinstances', 'courseid', ['id' => $row->instanceid]);
        $course = get_course($courseid ?? SITEID);

        $relateduser = null;
        $scheduleuser = null;
        if (!empty($row->relateduserid)) {
            $relateduser = (object) \core_user::get_user($row->relateduserid);
            $scheduleuser = (object) \core_user::get_user($row->userid);
        } else {
            $relateduser = (object) \core_user::get_user($row->userid);
            $scheduleuser = null;
        }

        // Include mod data in the subject email vars.
        $cmdata = null;
        if (!empty($module) && isset($module[$row->instanceid])) {
            $cm = $module[$row->instanceid];
            $cmdata = get_coursemodule_from_id('', $cm);
        } else {
            $dynamicmodules = $DB->get_record('pulseaction_notification_ins', ['instanceid' => $row->instanceid]);
            $cm = $dynamicmodules->dynamiccontent;
            if (!empty($cm)) {
                $cmdata = get_coursemodule_from_id('', $cm);
                $module[$row->instanceid] = $cm;
            }
        }

        [$subject, $messagehtml] = \mod_pulse\helper::update_emailvars(
            '',
            $value,
            $course,
            $relateduser,
            $cmdata,
            $sender,
            [],
            'notification',
            $scheduleuser
        );

        return $subject . html_writer::link('javascript:void(0);', '<i class="fa fa-info"></i>', [
            'class' => 'pulse-automation-info-block',
            'data-target' => 'view-content',
            'data-instanceid' => $row->instanceid,
            'data-userid' => $row->userid,
            'data-relateduserid' => $row->relateduserid,
        ]);
    }

    /**
     * Get the list of modules data for the placholders. includes the metadata fields.
     *
     * @param array $modules List of modules.
     * @return array
     */
    public static function get_modules_data($modules) {
        global $DB, $CFG;

        if (file_exists($CFG->dirroot . '/local/metadata/lib.php')) {
            require_once($CFG->dirroot . '/local/metadata/lib.php');
        }

        $list = [];
        foreach ($modules as $modname => $instances) {
            $tablename = $DB->get_prefix() . $modname;
            [$insql, $inparams] = $DB->get_in_or_equal($instances, SQL_PARAMS_NAMED, 'md');

            $sql = "SELECT md.*, cm.id as cmid FROM $tablename md
            JOIN {modules} m ON m.name = '$modname'
            JOIN {course_modules} cm ON cm.instance = md.id AND cm.module = m.id
            WHERE md.id $insql";

            $records = $DB->get_records_sql($sql, $inparams);

            foreach ($records as $modid => $mod) {
                $mod->type = $modname;

                if (isset($list[$modname][$modid])) {
                    continue;
                }

                if (function_exists('local_metadata_load_data')) {
                    $newmod = (object) ['id' => $mod->cmid];
                    local_metadata_load_data($newmod, CONTEXT_MODULE);
                    unset($newmod->cmid);
                    foreach ($newmod as $name => $value) {
                        $key = str_replace('local_metadata_field_', 'metadata', $name);
                        $key = strtolower($key);
                        $mod->$key = $value;
                    }
                }

                $list[$modname][$modid] = $mod;
            }
        }

        return $list;
    }
}
