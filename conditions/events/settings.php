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
 * Events pulse automation condition - Admin settings.
 *
 * @package   pulsecondition_events
 * @copyright 2025 bdecent GmbH <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use pulsecondition_events\conditionform;

if ($hassiteconfig) {
    global $DB;

    // Get the list of available events.
    $eventlist = conditionform::eventslist();

    $defaultlist = conditionform::get_default_events();

    // Available events for pulse evnets conditions.
    // List of selected events in this setting will be available in pulse automation condition events selector.
    $setting = new core_admin\local\settings\autocomplete(
        'pulsecondition_events/availableevents',
        get_string('availableevents', 'pulsecondition_events'),
        get_string('availableeventsdesc', 'pulsecondition_events'),
        array_keys($defaultlist),
        $eventlist,
        ['manageurl' => false],
    );
    $page->add($setting);
}
