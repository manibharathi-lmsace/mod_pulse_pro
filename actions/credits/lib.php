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
 * Credits pulse action - Library file contains commonly used functions.
 *
 * @package   pulseaction_credits
 * @copyright 2025 bdecent GmbH <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Plugin inplace editable implementation
 *
 * @param string $itemtype
 * @param int $itemid
 * @param string $newvalue
 * @return inplace_editable|null
 */
function pulseaction_credits_inplace_editable(string $itemtype, int $itemid, string $newvalue): ?\core\output\inplace_editable {

    switch ($itemtype) {
        case 'overridecredit':
            return \pulseaction_credits\output\override_credit::update($itemid, $newvalue);
    }

    return null;
}

/**
 * Get the configured credit field ID
 *
 * @return int|null Credit field ID or null if not configured
 */
function pulseaction_credits_get_configured_creditfield_id() {
    // Check if credit field is configured.
    $creditfield = get_config('pulseaddon_credits', 'creditsfield');
    return $creditfield;
}

/**
 * Render credits in navigation bar
 *
 * @return string HTML output for navigation credits display
 */
function pulseaction_credits_render_navbar_output() {
    global $USER, $OUTPUT, $CFG;

    // Check user is logged in.
    if (!isloggedin() || isguestuser()) {
        return '';
    }

    // Check show credits is enabled.
    $shownavigation = get_config('pulseaction_credits', 'showcredits');
    if (empty($shownavigation)) {
        return '';
    }

    // Get user current credits.
    try {
        $creditsobj = new \pulseaction_credits\local\credits();
        $currentcredits = $creditsobj->get_user_credits($USER->id);
    } catch (Exception $e) {
        return '';
    }

    $templatecontext = [
        'credits' => number_format($currentcredits, 2),
        'wwwroot' => $CFG->wwwroot,
        'userid' => $USER->id,
        'datatoggle' => $CFG->branch >= 500 ? 'data-bs-toggle' : 'data-toggle',
        'dataplacement' => $CFG->branch >= 500 ? 'data-bs-placement' : 'data-placement',
        'datacontent' => $CFG->branch >= 500 ? 'data-bs-content' : 'data-content',
    ];

    return $OUTPUT->render_from_template('pulseaction_credits/navbar_credits', $templatecontext);
}


/**
 * Add the link in course secondary navigation menu to open the automation instance list page.
 *
 * @param  navigation_node $navigation
 * @param  stdClass $course
 * @param  context_course $context
 * @return void
 */
function pulseaction_credits_extend_navigation_course(navigation_node $navigation, stdClass $course, $context) {

    // Add pulseaction credits override link if user has capability.
    if (has_capability('pulseaction/credits:override', $context)) {
        $creditsurl = new moodle_url('/mod/pulse/actions/credits/override.php', [
            'courseid' => $course->id,
        ]);
        $creditsnode = $navigation->create(
            get_string('scheduleoverride', 'pulseaction_credits'),
            $creditsurl,
            navigation_node::TYPE_SETTING,
            null,
            'pulseaction-credits-override'
        );
        $creditsnode->add_class('pulseaction-credits-override');
        $creditsnode->set_force_into_more_menu(false);
        $creditsnode->set_show_in_secondary_navigation(true);
        $navigation->add_node($creditsnode, 'gradebooksetup');
    }
}
