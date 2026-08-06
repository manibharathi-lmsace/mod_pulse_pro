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
 * Credit allocated event, handles both schedule based and user override based allocations.
 *
 * @package   pulseaction_credits
 * @copyright 2025 bdecent GmbH <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulseaction_credits\event;


/**
 * Credit allocated event class.
 *
 * @package   pulseaction_credits
 * @copyright 2025 bdecent GmbH <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credit_allocated extends \core\event\base {
    /**
     * Initialise the event data.
     */
    protected function init() {
        $this->data['objecttable'] = 'pulseaction_credits_sch';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Creates an instance from user override data.
     *
     * @param stdClass $override User override record
     * @param int $courseid Course ID
     * @return credit_allocated
     */
    public static function create_from_user_override($override, $courseid) {
        $eventparams = [
            'objectid' => $override->userid,
            'context'  => \context_course::instance($courseid),
            'relateduserid' => $override->userid,
            'other' => [
                'type' => 'user_override',
                'oldcredits' => $override->oldcredits,
                'newcredits' => $override->newcredits,
                'courseid' => $courseid,
                'note' => $override->note,
                'overriddenby' => $override->overriddenby,
            ],
        ];
        $event = self::create($eventparams);
        return $event;
    }

    /**
     * Creates an instance from schedule data.
     *
     * @param stdClass $schedule Schedule record
     * @param float $actualcredits Actual Credits allocated (may be overridden)
     * @return credit_allocated
     */
    public static function create_from_schedule($schedule, $actualcredits = null) {
        $actualcredits = $actualcredits ?: $schedule->credits;

        $eventparams = [
            'objectid' => $schedule->id,
            'context'  => \context_course::instance($schedule->courseid),
            'relateduserid' => $schedule->userid,
            'other' => [
                'type' => 'schedule_allocation',
                'scheduledcredits' => $schedule->credits,
                'actualcredits' => $actualcredits,
                'allocationmethod' => $schedule->allocationmethod ?? 1,
                'courseid' => $schedule->courseid,
                'instanceid' => $schedule->instanceid,
            ],
        ];
        $event = self::create($eventparams);
        $event->add_record_snapshot($event->objecttable, $schedule);
        return $event;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event:creditallocated', 'pulseaction_credits');
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {

        if (isset($this->other['type']) && $this->other['type'] === 'user_override') {
            $oldcredits = $this->other['oldcredits'];
            $newcredits = $this->other['newcredits'];
            return "The user with id '{$this->userid}' directly updated credits from '{$oldcredits}' to '{$newcredits}' " .
                   "for user with id '{$this->relateduserid}' in course with id '{$this->other['courseid']}'.";
        } else {
            $actualcredits = $this->other['actualcredits'];
            $method = ($this->other['allocationmethod'] == 1) ? 'added' : 'replaced';
            return "Credit allocation of '{$actualcredits}' is  {$method} for user with id '{$this->relateduserid}' " .
                   "in course with id '{$this->other['courseid']}'.";
        }
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/pulse/actions/credits/override.php', ['courseid' => $this->other['courseid']]);
    }
}
