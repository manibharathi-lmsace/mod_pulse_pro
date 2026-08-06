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
 * Scheduled task to check activity partial-completion and trigger automation instances.
 *
 * @package   pulsecondition_activity
 * @copyright 2026, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulsecondition_activity\task;

use pulsecondition_activity\conditionform;

/**
 * Scheduled task to check activity partial-completion and trigger automation instances.
 */
class activity extends \core\task\scheduled_task {
    /**
     * Get the name of this task.
     *
     * @return string The task name.
     */
    public function get_name() {
        return get_string('taskname', 'pulsecondition_activity');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        if (!$DB->get_manager()->table_exists('logstore_standard_log')) {
            return;
        }

        $cmidtoinstances = $this->get_partial_instance_modules();

        if (empty($cmidtoinstances)) {
            return;
        }

        $since = $this->get_last_run_time();
        $changes = $this->get_module_changes(array_keys($cmidtoinstances), $since);

        if (empty($changes)) {
            return;
        }

        $processed = [];
        $triggeredcount = 0;

        foreach ($changes as $change) {
            $userid = $change->relateduserid ?: $change->userid;
            if (empty($userid)) {
                continue;
            }

            foreach ($cmidtoinstances[$change->contextinstanceid] as $instanceid) {
                $key = $instanceid . '_' . $userid;
                if (isset($processed[$key])) {
                    continue;
                }
                $processed[$key] = true; // We already triggered this instance for this user in this run.

                \mod_pulse\automation\instances::create($instanceid)->trigger_action($userid);
                $triggeredcount++;
            }
        }

        mtrace("Activity partial-completion check processed {$triggeredcount} instance/user pairs.");
    }

    /**
     * Get all active pulse_autoinstances that have a condition for activity partial-completion.
     *
     * @return array Array of course module ids
     */
    private function get_partial_instance_modules(): array {
        global $DB;

        $sql = "SELECT ai.id AS instanceid,
                       COALESCE(co.additional, c.additional) AS additional
                  FROM {pulse_autoinstances} ai
                  JOIN {pulse_autotemplates} pat ON pat.id = ai.templateid
             LEFT JOIN {pulse_condition} c
                    ON c.templateid = ai.templateid AND c.triggercondition = 'activity'
             LEFT JOIN {pulse_condition_overrides} co
                    ON co.instanceid = ai.id AND co.triggercondition = 'activity'
                 WHERE ai.status = 1
                   AND (co.status > 0 OR (co.status IS NULL AND c.status > 0))";

        $instances = $DB->get_records_sql($sql);

        $cmidtoinstances = [];

        foreach ($instances as $instance) {
            $additional = $instance->additional ? json_decode($instance->additional, true) : [];
            $completionstatus = (int) ($additional['completionstatus'] ?? conditionform::COMPLETION_STATUS_COMPLETED);

            if ($completionstatus !== conditionform::COMPLETION_STATUS_PARTIAL) {
                continue;
            }

            foreach (($additional['modules'] ?? []) as $cmid) {
                $cmidtoinstances[(int) $cmid][] = $instance->instanceid;
            }
        }

        return $cmidtoinstances;
    }

    /**
     * Get all log entries for the given course module ids since the given time.
     *
     * @param array $cmids Course module ids to watch.
     * @param int $since Only include log entries after this time.
     * @return array Rows with contextinstanceid, userid, relateduserid.
     */
    private function get_module_changes(array $cmids, int $since): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'cmid');
        $params['contextlevel'] = CONTEXT_MODULE;
        $params['since'] = $since;
        $params['levelother'] = \core\event\base::LEVEL_OTHER;

        $sql = "SELECT DISTINCT contextinstanceid, userid, relateduserid
                  FROM {logstore_standard_log}
                 WHERE contextlevel = :contextlevel
                   AND contextinstanceid $insql
                   AND timecreated > :since
                   AND edulevel <> :levelother";

        $changes = [];
        $recordset = $DB->get_recordset_sql($sql, $params);
        foreach ($recordset as $record) {
            $changes[] = $record;
        }
        $recordset->close();

        return $changes;
    }
}
