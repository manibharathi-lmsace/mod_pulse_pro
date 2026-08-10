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
 * Adhoc task that processes one limited batch of the user inactivity condition's
 * matching-user set, then re-queues itself to continue until the set is exhausted.
 *
 * @package   pulsecondition_userinactivity
 * @copyright 2026, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulsecondition_userinactivity\task;

/**
 * Processes one batch of up to `pulse/schedulecount` matching users, then queues itself again
 * if there are more left. Started by the userinactivity scheduled task, one chain per instance.
 *
 */
class process_batch extends \core\task\adhoc_task {
    /**
     * Execute one batch hop.
     */
    public function execute() {
        $data = (object) $this->get_custom_data();
        $instanceid  = (int) $data->instanceid;
        $courseid    = (int) $data->courseid;
        $aftercursor = (int) ($data->aftercursor ?? 0);

        $cache = \cache::make('pulsecondition_userinactivity', 'batchprogress');

        try {
            $course = get_course($courseid);
        } catch (\Exception $e) {
            mtrace("userinactivity batch: course {$courseid} not found - stopping chain for instance {$instanceid}.");
            $cache->delete($instanceid);
            return;
        }

        try {
            $instanceobj = \mod_pulse\automation\instances::create($instanceid);
            $instancedata = $instanceobj->get_instance_data();
        } catch (\Exception $e) {
            mtrace("userinactivity batch: instance {$instanceid} no longer exists - stopping chain.");
            $cache->delete($instanceid);
            return;
        }

        $triggercondition = $instancedata->condition['userinactivity'] ?? null;
        if (empty($triggercondition) || (int) ($triggercondition['status'] ?? 0) <= 0) {
            mtrace("userinactivity batch: condition disabled/removed for instance {$instanceid} - stopping chain.");
            $cache->delete($instanceid);
            return;
        }

        // Only skip the extra condition check when userinactivity is the ONLY enabled condition -
        // If another condition is also enabled, we still need the full check for that one.
        $skipconditioncheck = $this->is_sole_enabled_condition($instancedata->condition ?? [], 'userinactivity');

        $conditionform = new \pulsecondition_userinactivity\conditionform();
        $batchsize = (int) (get_config('pulse', 'schedulecount') ?: 100);

        $rs = $conditionform->get_matching_users_recordset($course, $triggercondition, $aftercursor, $batchsize);
        if ($rs === null) {
            mtrace("userinactivity batch: nothing to evaluate for instance {$instanceid} - stopping chain.");
            $cache->delete($instanceid);
            return;
        }

        $alreadynotified = $conditionform->get_already_notified_users($instanceid, $course->id);

        $fetched = 0;
        $triggered = 0;
        $lastuserid = $aftercursor;
        try {
            foreach ($rs as $record) {
                $fetched++;
                $lastuserid = (int) $record->userid;
                if (isset($alreadynotified[$lastuserid])) {
                    continue;
                }
                // Queue the notification only - the notify_users task sends it within a minute.
                $instanceobj->trigger_action($lastuserid, null, false, false, $skipconditioncheck, true);
                $triggered++;
            }
        } finally {
            $rs->close();
        }

        mtrace(
            "userinactivity batch: instance {$instanceid} evaluated {$fetched} candidate(s) after userid "
            . "{$aftercursor} (triggered {$triggered})."
        );

        if ($fetched < $batchsize) {
            // Fewer rows than the batch size means we've reached the end of the matching set.
            $cache->delete($instanceid);
            mtrace("userinactivity batch: instance {$instanceid} complete.");
            return;
        }

        // More users remain - refresh the in-progress marker and queue the next set.
        $cache->set($instanceid, (object) ['cursor' => $lastuserid, 'started' => time()]);

        $next = new self();
        $next->set_custom_data(['instanceid' => $instanceid, 'courseid' => $courseid, 'aftercursor' => $lastuserid]);
        $next->set_component('pulsecondition_userinactivity');
        \core\task\manager::queue_adhoc_task($next, true);
    }

    /**
     * Check whether $component is the only enabled condition on the instance.
     *
     * @param array $conditions Instance condition data, keyed by component name.
     * @param string $component The component to check is the sole enabled one.
     * @return bool
     */
    protected function is_sole_enabled_condition(array $conditions, string $component): bool {
        $enabled = 0;
        $onlythisone = true;
        foreach ($conditions as $name => $option) {
            $status = (is_array($option) && isset($option['status'])) ? $option['status'] : $option;
            if ((int) $status <= 0) {
                continue;
            }
            $enabled++;
            if ($name !== $component) {
                $onlythisone = false;
            }
        }
        return $enabled === 1 && $onlythisone;
    }
}
