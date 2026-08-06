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
 * Credits pulse action - Admin settings.
 *
 * @package   pulseaction_credits
 * @copyright 2025 bdecent GmbH <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    global $DB;

    // Credits display setting.
    $name = 'pulseaction_credits/showcredits';
    $title = get_string('showcredits', 'pulseaction_credits');
    $description = get_string('showcredits_desc', 'pulseaction_credits');
    $setting = new admin_setting_configcheckbox($name, $title, $description, 0);
    $settings->add($setting);

    // User credits override.
    $credits = new admin_externalpage(
        'creditsoverrides',
        get_string('creditsoverride', 'pulseaction_credits'),
        new moodle_url('/mod/pulse/actions/credits/override.php'),
        'pulseaction/credits:override'
    );

    $adminroot->add('modpulse', $credits);
}
