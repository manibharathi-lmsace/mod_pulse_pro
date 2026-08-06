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
 * Privacy implementation for pulseaction_credits
 *
 * @package   pulseaction_credits
 * @copyright 2025, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulseaction_credits\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\transform;
use core_user;

/**
 * Privacy provider for the pulseaction_credits subplugin.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Return the metadata about what user data this plugin stores.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table('pulseaction_credits_sch', [
            'userid'       => 'privacy:metadata:credits_sch:userid',
            'instanceid'   => 'privacy:metadata:credits_sch:instanceid',
            'credits'      => 'privacy:metadata:credits_sch:credits',
            'scheduletime' => 'privacy:metadata:credits_sch:scheduletime',
            'status'       => 'privacy:metadata:credits_sch:status',
            'timecreated'  => 'privacy:metadata:credits_sch:timecreated',
        ], 'privacy:metadata:credits_sch');

        $collection->add_database_table('pulseaction_credits_override', [
            'userid'          => 'privacy:metadata:credits_override:userid',
            'scheduleid'      => 'privacy:metadata:credits_override:scheduleid',
            'overridecredit'  => 'privacy:metadata:credits_override:overridecredit',
            'scheduledcredit' => 'privacy:metadata:credits_override:scheduledcredit',
            'overriddenby'    => 'privacy:metadata:credits_override:overriddenby',
            'timecreated'     => 'privacy:metadata:credits_override:timecreated',
        ], 'privacy:metadata:credits_override');

        $collection->add_database_table('pulseaction_credits_user_override', [
            'userid'       => 'privacy:metadata:credits_user_override:userid',
            'courseid'     => 'privacy:metadata:credits_user_override:courseid',
            'oldcredits'   => 'privacy:metadata:credits_user_override:oldcredits',
            'newcredits'   => 'privacy:metadata:credits_user_override:newcredits',
            'overriddenby' => 'privacy:metadata:credits_user_override:overriddenby',
            'timecreated'  => 'privacy:metadata:credits_user_override:timecreated',
        ], 'privacy:metadata:credits_user_override');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Credits schedule: via autoinstances.
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {pulse_autoinstances} ai ON ai.courseid = ctx.instanceid
                                               AND ctx.contextlevel = :contextlevel
                  JOIN {pulseaction_credits_sch} pcs ON pcs.instanceid = ai.id
                 WHERE pcs.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'userid'       => $userid,
        ]);

        // Credits override (overridden by): via autoinstances schedule.
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {pulse_autoinstances} ai ON ai.courseid = ctx.instanceid
                                               AND ctx.contextlevel = :contextlevel
                  JOIN {pulseaction_credits_sch} pcs ON pcs.instanceid = ai.id
                  JOIN {pulseaction_credits_override} pco ON pco.scheduleid = pcs.id
                 WHERE pco.userid = :userid OR pco.overriddenby = :overriddenby";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'userid'       => $userid,
            'overriddenby' => $userid,
        ]);

        // User-level credits override: direct courseid column.
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                 WHERE ctx.instanceid = (
                           SELECT courseid FROM {pulseaction_credits_user_override}
                            WHERE (userid = :userid OR overriddenby = :overriddenby)
                            LIMIT 1
                       )
                   AND ctx.contextlevel = :contextlevel";
        // Simpler per-row approach to avoid subquery portability issues.
        $sql2 = "SELECT DISTINCT ctx.id
                   FROM {context} ctx
                   JOIN {pulseaction_credits_user_override} pcuo
                        ON pcuo.courseid = ctx.instanceid
                       AND ctx.contextlevel = :contextlevel
                  WHERE pcuo.userid = :userid OR pcuo.overriddenby = :overriddenby";
        $contextlist->add_from_sql($sql2, [
            'contextlevel' => CONTEXT_COURSE,
            'userid'       => $userid,
            'overriddenby' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }

        $params = ['courseid' => $context->instanceid];

        // Schedule users.
        $sql = "SELECT pcs.userid
                  FROM {pulse_autoinstances} ai
                  JOIN {pulseaction_credits_sch} pcs ON pcs.instanceid = ai.id
                 WHERE ai.courseid = :courseid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Override: affected user.
        $sql = "SELECT pco.userid
                  FROM {pulse_autoinstances} ai
                  JOIN {pulseaction_credits_sch} pcs ON pcs.instanceid = ai.id
                  JOIN {pulseaction_credits_override} pco ON pco.scheduleid = pcs.id
                 WHERE ai.courseid = :courseid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Override: overriddenby user.
        $sql = "SELECT pco.overriddenby AS userid
                  FROM {pulse_autoinstances} ai
                  JOIN {pulseaction_credits_sch} pcs ON pcs.instanceid = ai.id
                  JOIN {pulseaction_credits_override} pco ON pco.scheduleid = pcs.id
                 WHERE ai.courseid = :courseid";
        $userlist->add_from_sql('userid', $sql, $params);

        // User-level override.
        $sql = "SELECT pcuo.userid
                  FROM {pulseaction_credits_user_override} pcuo
                 WHERE pcuo.courseid = :courseid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT pcuo.overriddenby AS userid
                  FROM {pulseaction_credits_user_override} pcuo
                 WHERE pcuo.courseid = :courseid";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all data for a user within the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            $courseid = $context->instanceid;
            $data     = [];

            // Schedule records.
            $sql = "SELECT pcs.*
                      FROM {pulse_autoinstances} ai
                      JOIN {pulseaction_credits_sch} pcs ON pcs.instanceid = ai.id
                     WHERE ai.courseid = :courseid AND pcs.userid = :userid";
            $creditschedulerecords = $DB->get_records_sql($sql, [
                'courseid' => $courseid,
                'userid' => $userid,
            ]);
            foreach ($creditschedulerecords as $creditschedule) {
                $data['schedules'][] = [
                    'credits'      => $creditschedule->credits,
                    'scheduletime' => $creditschedule->scheduletime ? transform::datetime($creditschedule->scheduletime) : '-',
                    'status'       => $creditschedule->status,
                ];
            }

            // Override records.
            $sql = "SELECT pco.*
                      FROM {pulse_autoinstances} ai
                      JOIN {pulseaction_credits_sch} pcs ON pcs.instanceid = ai.id
                      JOIN {pulseaction_credits_override} pco ON pco.scheduleid = pcs.id
                     WHERE ai.courseid = :courseid
                       AND (pco.userid = :userid OR pco.overriddenby = :overriddenby)";
            $creditoverriderecords = $DB->get_records_sql($sql, [
                'courseid' => $courseid,
                'userid' => $userid,
                'overriddenby' => $userid,
            ]);
            foreach ($creditoverriderecords as $creditoverride) {
                $data['overrides'][] = [
                    'overridecredit'  => $creditoverride->overridecredit,
                    'scheduledcredit' => $creditoverride->scheduledcredit,
                    'timecreated'     => $creditoverride->timecreated ? transform::datetime($creditoverride->timecreated) : '-',
                ];
            }

            // User-level override.
            $sql = "SELECT * FROM {pulseaction_credits_user_override}
                     WHERE courseid = :courseid AND (userid = :userid OR overriddenby = :overriddenby)";
            $records = $DB->get_records_sql($sql, [
                'courseid' => $courseid,
                'userid' => $userid,
                'overriddenby' => $userid,
            ]);

            foreach ($records as $rec) {
                $overriddenbyuser = $rec->overriddenby ? core_user::get_user($rec->overriddenby) : null;
                $data['user_overrides'][] = [
                    'oldcredits'  => $rec->oldcredits,
                    'newcredits'  => $rec->newcredits,
                    'overridenby' => $overriddenbyuser ? fullname($overriddenbyuser) : '-',
                    'timecreated' => $rec->timecreated ? transform::datetime($rec->timecreated) : '-',
                ];
            }

            if (empty($data)) {
                continue;
            }

            writer::with_context($context)->export_data([get_string('pluginname', 'pulseaction_credits')], (object) $data);
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_course) {
            return;
        }
        $courseid = $context->instanceid;

        $instanceids = $DB->get_fieldset_select(
            'pulse_autoinstances',
            'id',
            'courseid = :courseid',
            ['courseid' => $courseid]
        );
        if (!empty($instanceids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED);
            $scheduleids = $DB->get_fieldset_select('pulseaction_credits_sch', 'id', "instanceid {$insql}", $inparams);
            if (!empty($scheduleids)) {
                [$sinsql, $sinparams] = $DB->get_in_or_equal($scheduleids, SQL_PARAMS_NAMED);
                $DB->delete_records_select('pulseaction_credits_override', "scheduleid {$sinsql}", $sinparams);
            }
            $DB->delete_records_select('pulseaction_credits_sch', "instanceid {$insql}", $inparams);
        }

        $DB->delete_records('pulseaction_credits_user_override', ['courseid' => $courseid]);
    }

    /**
     * Delete all data for a user in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            $courseid = $context->instanceid;

            $instanceids = $DB->get_fieldset_select(
                'pulse_autoinstances',
                'id',
                'courseid = :courseid',
                ['courseid' => $courseid]
            );
            if (!empty($instanceids)) {
                [$insql, $inparams] = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED);
                $scheduleids = $DB->get_fieldset_select(
                    'pulseaction_credits_sch',
                    'id',
                    "instanceid {$insql} AND userid = :userid",
                    array_merge($inparams, ['userid' => $userid])
                );
                if (!empty($scheduleids)) {
                    [$sinsql, $sinparams] = $DB->get_in_or_equal($scheduleids, SQL_PARAMS_NAMED);
                    $DB->delete_records_select('pulseaction_credits_override', "scheduleid {$sinsql}", $sinparams);
                }
                $DB->delete_records_select(
                    'pulseaction_credits_sch',
                    "instanceid {$insql} AND userid = :userid",
                    array_merge($inparams, ['userid' => $userid])
                );
            }

            $DB->delete_records_select(
                'pulseaction_credits_user_override',
                'courseid = :courseid AND (userid = :userid OR overriddenby = :overriddenby)',
                ['courseid' => $courseid, 'userid' => $userid, 'overriddenby' => $userid]
            );
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }
        $courseid = $context->instanceid;
        $userids  = $userlist->get_userids();

        $instanceids = $DB->get_fieldset_select('pulse_autoinstances', 'id', 'courseid = :courseid', ['courseid' => $courseid]);
        if (!empty($instanceids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED, 'inst');
            [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');

            $scheduleids = $DB->get_fieldset_select(
                'pulseaction_credits_sch',
                'id',
                "instanceid {$insql} AND userid {$usersql}",
                array_merge($inparams, $userparams)
            );
            if (!empty($scheduleids)) {
                [$sinsql, $sinparams] = $DB->get_in_or_equal($scheduleids, SQL_PARAMS_NAMED);
                $DB->delete_records_select('pulseaction_credits_override', "scheduleid {$sinsql}", $sinparams);
            }
            $DB->delete_records_select(
                'pulseaction_credits_sch',
                "instanceid {$insql} AND userid {$usersql}",
                array_merge($inparams, $userparams)
            );
        }

        [$usersql2, $userparams2] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u2');
        $DB->delete_records_select(
            'pulseaction_credits_user_override',
            "courseid = :courseid AND userid {$usersql2}",
            array_merge(['courseid' => $courseid], $userparams2)
        );
    }
}
