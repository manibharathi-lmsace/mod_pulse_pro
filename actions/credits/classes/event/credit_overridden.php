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
 * Credit overridden event.
 *
 * @package   pulseaction_credits
 * @copyright 2025 bdecent GmbH <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulseaction_credits\event;

/**
 * Credit overridden event class.
 *
 * @package   pulseaction_credits
 * @copyright 2025 bdecent GmbH <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credit_overridden extends \core\event\base {
    /**
     * Initialise the event data.
     */
    protected function init() {
        $this->data['objecttable'] = 'pulseaction_credits_override';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Creates an instance from override data
     *
     * @param stdClass $override Override record
     * @param stdClass $schedule Schedule record
     * @return credit_overridden
     */
    public static function create_from_override($override, $schedule) {
        global $DB;

        $courseid = $DB->get_field('pulse_autoinstances', 'courseid', ['id' => $schedule->instanceid], MUST_EXIST);

        $eventparams = [
            'objectid' => $override->id,
            'context'  => \context_course::instance($courseid),
            'relateduserid' => $schedule->userid,
            'other' => [
                'scheduleid' => $schedule->id,
                'schedulecredit' => $schedule->credits,
                'overridecredit' => $override->overridecredit,
                'courseid' => $courseid,
            ],
        ];
        $event = self::create($eventparams);
        $event->add_record_snapshot($event->objecttable, $override);
        $event->add_record_snapshot('pulseaction_credits_sch', $schedule);
        return $event;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event:creditoverridden', 'pulseaction_credits');
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $schedulecredit = $this->other['schedulecredit'];
        $overridecredit = $this->other['overridecredit'];
        return "The user with id '$this->userid' overridden credit allocation from '{$schedulecredit}' to '{$overridecredit}' " .
               "for user with id '{$this->relateduserid}' in course with id '{$this->other['courseid']}'.";
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
