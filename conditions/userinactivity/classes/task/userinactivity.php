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
 * Scheduled task for user inactivity condition monitoring.
 *
 * @package   pulsecondition_userinactivity
 * @copyright 2025, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulsecondition_userinactivity\task;

/**
 * Scheduled task to check for user inactivity and trigger automation conditions.
 */
class userinactivity extends \core\task\scheduled_task {
    /**
     * Duration (seconds) after which an in-progress marker is treated as stale and overridden.
     */
    const STALE_LOCK_SECONDS = 2 * HOURSECS;

    /**
     * Get the name of this task.
     *
     * @return string The task name.
     */
    public function get_name() {
        return get_string('taskuserinactivity', 'pulsecondition_userinactivity');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        mtrace('Starting user inactivity automation check...');

        $sql = "SELECT ai.id AS instanceid, ai.courseid, ai.templateid
                  FROM {pulse_autoinstances} ai
                  JOIN {pulse_autotemplates} pat ON pat.id = ai.templateid
             LEFT JOIN {pulse_condition_overrides} co
                    ON co.instanceid = ai.id AND co.triggercondition = 'userinactivity'
                 WHERE ai.status = 1
                   AND (co.status > 0 OR (co.status IS NULL AND ai.templateid IN (
                       SELECT c.templateid FROM {pulse_condition} c
                        WHERE c.triggercondition = 'userinactivity' AND c.status > 0
                   )))
              ORDER BY ai.courseid, ai.id";

        $instances = $DB->get_records_sql($sql);

        if (empty($instances)) {
            mtrace('No active user inactivity automation instances found.');
            return;
        }

        mtrace('Found ' . count($instances) . ' active user inactivity automation instances.');

        $cache = \cache::make('pulsecondition_userinactivity', 'batchprogress');
        $started = 0;
        $skipped = 0;

        foreach ($instances as $row) {
            $marker = $cache->get($row->instanceid);

            if ($marker && is_object($marker) && (time() - (int) $marker->started) < self::STALE_LOCK_SECONDS) {
                // Already running for this instance - the running chain will pick up any
                // ...new matches on its own, so don't start a second one.
                $skipped++;
                continue;
            }

            if ($marker) {
                mtrace("Instance {$row->instanceid}: previous batch marker is stale, starting a new chain.");
            }

            $cache->set($row->instanceid, (object) ['cursor' => 0, 'started' => time()]);

            $task = new process_batch();
            $task->set_custom_data([
                'instanceid' => (int) $row->instanceid,
                'courseid' => (int) $row->courseid,
                'aftercursor' => 0,
            ]);
            $task->set_component('pulsecondition_userinactivity');
            \core\task\manager::queue_adhoc_task($task, true);
            $started++;
        }

        mtrace("User inactivity automation check completed. Started {$started} batch chain(s), "
            . "{$skipped} instance(s) already in progress.");
    }
}
