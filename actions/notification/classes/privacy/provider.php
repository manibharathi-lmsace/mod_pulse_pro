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
 * Privacy implementation for pulseaction_notification
 *
 * @package   pulseaction_notification
 * @copyright 2025, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulseaction_notification\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\transform;

/**
 * Privacy provider for the pulseaction_notification subplugin.
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
        $collection->add_database_table('pulseaction_notification_sch', [
            'userid' => 'privacy:metadata:notification_sch:userid',
            'relateduserid' => 'privacy:metadata:notification_sch:relateduserid',
            'instanceid' => 'privacy:metadata:notification_sch:instanceid',
            'scheduletime' => 'privacy:metadata:notification_sch:scheduletime',
            'notifiedtime' => 'privacy:metadata:notification_sch:notifiedtime',
            'status' => 'privacy:metadata:notification_sch:status',
            'notifycount' => 'privacy:metadata:notification_sch:notifycount',
            'timecreated' => 'privacy:metadata:notification_sch:timecreated',
        ], 'privacy:metadata:notification_sch');

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

        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {pulse_autoinstances} ai ON ai.courseid = ctx.instanceid
                                               AND ctx.contextlevel = :contextlevel
                  JOIN {pulseaction_notification_sch} pns ON pns.instanceid = ai.id
                 WHERE pns.userid = :userid OR pns.relateduserid = :relateduserid";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'userid' => $userid,
            'relateduserid' => $userid,
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

        $sql = "SELECT pns.userid
                  FROM {pulse_autoinstances} ai
                  JOIN {pulseaction_notification_sch} pns ON pns.instanceid = ai.id
                 WHERE ai.courseid = :courseid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT pns.relateduserid AS userid
                  FROM {pulse_autoinstances} ai
                  JOIN {pulseaction_notification_sch} pns ON pns.instanceid = ai.id
                 WHERE ai.courseid = :courseid AND pns.relateduserid IS NOT NULL";
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

            $sql = "SELECT pns.*
                      FROM {pulse_autoinstances} ai
                      JOIN {pulseaction_notification_sch} pns ON pns.instanceid = ai.id
                     WHERE ai.courseid = :courseid
                       AND (pns.userid = :userid OR pns.relateduserid = :relateduserid)
                  ORDER BY pns.id ASC";

            $records = $DB->get_records_sql($sql, [
                'courseid' => $context->instanceid,
                'userid' => $userid,
                'relateduserid' => $userid,
            ]);

            if (empty($records)) {
                continue;
            }

            $data = array_map(function ($record) {
                return [
                    'scheduletime' => $record->scheduletime ? transform::datetime($record->scheduletime) : '-',
                    'notifiedtime' => $record->notifiedtime ? transform::datetime($record->notifiedtime) : '-',
                    'status' => $record->status,
                    'notifycount' => $record->notifycount,
                ];
            }, $records);

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'pulseaction_notification')],
                (object) ['schedules' => array_values($data)]
            );
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

        $instanceids = $DB->get_fieldset_select(
            'pulse_autoinstances',
            'id',
            'courseid = :courseid',
            ['courseid' => $context->instanceid]
        );
        if (empty($instanceids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('pulseaction_notification_sch', "instanceid {$insql}", $inparams);
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

            $instanceids = $DB->get_fieldset_select(
                'pulse_autoinstances',
                'id',
                'courseid = :courseid',
                ['courseid' => $context->instanceid]
            );
            if (empty($instanceids)) {
                continue;
            }

            [$insql, $inparams] = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED);
            $DB->delete_records_select(
                'pulseaction_notification_sch',
                "instanceid {$insql} AND (userid = :userid OR relateduserid = :relateduserid)",
                array_merge($inparams, ['userid' => $userid, 'relateduserid' => $userid])
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

        $instanceids = $DB->get_fieldset_select(
            'pulse_autoinstances',
            'id',
            'courseid = :courseid',
            ['courseid' => $context->instanceid]
        );
        if (empty($instanceids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED, 'inst');
        [$usersql, $userparams] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED, 'usr');

        $DB->delete_records_select(
            'pulseaction_notification_sch',
            "instanceid {$insql} AND (userid {$usersql} OR relateduserid {$usersql})",
            array_merge($inparams, $userparams, $userparams)
        );
    }
}
