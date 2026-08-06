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
 * DB Events - Define event observers for the "Course group" condition.
 *
 * @package   pulsecondition_coursegroup
 * @copyright 2025 bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Observe group membership add/remove events for Pulse condition.
$observers = [
    [
        'eventname'   => '\core\event\group_member_added',
        'callback'    => '\pulsecondition_coursegroup\conditionform::member_added',
    ],
    [
        'eventname'   => '\core\event\group_member_removed',
        'callback'    => '\pulsecondition_coursegroup\conditionform::member_removed',
    ],
];
