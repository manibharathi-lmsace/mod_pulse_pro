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
 * Privacy implementation for pulseaddon_reaction
 *
 * @package   pulseaddon_reaction
 * @copyright 2025, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulseaddon_reaction\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\transform;

/**
 * Privacy provider for the pulseaddon_reaction subplugin.
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
        $collection->add_database_table('pulseaddon_reaction_tokens', [
            'userid'  => 'privacy:metadata:reaction_tokens:userid',
            'pulseid' => 'privacy:metadata:reaction_tokens:pulseid',
            'relateduserid' => 'privacy:metadata:reaction_tokens:relateduserid',
            'reactiontype' => 'privacy:metadata:reaction_tokens:reactiontype',
            'status' => 'privacy:metadata:reaction_tokens:status',
            'timecreated' => 'privacy:metadata:reaction_tokens:timecreated',
        ], 'privacy:metadata:reaction_tokens');

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
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                                          AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'pulse'
                  JOIN {pulseaddon_reaction_tokens} prt ON prt.pulseid = cm.instance
                 WHERE prt.userid = :userid OR prt.relateduserid = :relateduserid";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
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
        if (!$context instanceof \context_module) {
            return;
        }

        $params = ['cmid' => $context->instanceid];

        $sql = "SELECT prt.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'pulse'
                  JOIN {pulseaddon_reaction_tokens} prt ON prt.pulseid = cm.instance
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT prt.relateduserid AS userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'pulse'
                  JOIN {pulseaddon_reaction_tokens} prt ON prt.pulseid = cm.instance
                 WHERE cm.id = :cmid AND prt.relateduserid IS NOT NULL";
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
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('pulse', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $sql = "SELECT * FROM {pulseaddon_reaction_tokens}
                     WHERE pulseid = :pulseid AND (userid = :userid OR relateduserid = :relateduserid)";
            $records = $DB->get_records_sql($sql, [
                'pulseid' => $cm->instance,
                'userid' => $userid,
                'relateduserid' => $userid,
            ]);

            if (empty($records)) {
                continue;
            }

            $data = array_map(function ($record) {
                return [
                    'reactiontype' => $record->reactiontype,
                    'status' => $record->status,
                    'timecreated' => $record->timecreated ? transform::datetime($record->timecreated) : '-',
                ];
            }, $records);

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'pulseaddon_reaction')],
                (object) ['reactions' => array_values($data)]
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

        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('pulse', $context->instanceid);
        if ($cm) {
            $DB->delete_records('pulseaddon_reaction_tokens', ['pulseid' => $cm->instance]);
        }
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
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('pulse', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $DB->delete_records_select(
                'pulseaddon_reaction_tokens',
                'pulseid = :pulseid AND (userid = :userid OR relateduserid = :relateduserid)',
                ['pulseid' => $cm->instance, 'userid' => $userid, 'relateduserid' => $userid]
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
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('pulse', $context->instanceid);
        if (!$cm) {
            return;
        }

        [$usersql, $userparams] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $DB->delete_records_select(
            'pulseaddon_reaction_tokens',
            "pulseid = :pulseid AND (userid {$usersql} OR relateduserid {$usersql})",
            array_merge(['pulseid' => $cm->instance], $userparams, $userparams)
        );
    }
}
