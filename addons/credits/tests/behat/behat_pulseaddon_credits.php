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
 * Behat pulseaddon related steps definitions.
 *
 * @package   pulseaddon_credits
 * @copyright 2024, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use Behat\Gherkin\Node\TableNode;

/**
 * Credits addon related steps definitions.
 */
class behat_pulseaddon_credits extends behat_base {
    /**
     * Creates a user profile field for storing credits.
     *
     * @Given /^a credit profile field exists$/
     * @return void
     */
    public function a_credit_profile_field_exists() {
        global $DB, $CFG;

        $this->execute(
            'behat_data_generators::the_following_entities_exist',
            [
                'custom profile fields',
                new TableNode([
                    0 => ['shortname', 'name', 'datatype'],
                    1 => ['credits', 'Credits', 'text'],
                ]),
            ]
        );

        $creditfieldid = $DB->get_field('user_info_field', 'id', ['shortname' => 'credits'], MUST_EXIST);

        $this->execute(
            'behat_admin::the_following_config_values_are_set_as_admin',
            [
                new TableNode([
                    ['creditsfield', $creditfieldid, 'pulseaddon_credits'],
                ]),
            ]
        );
    }

    /**
     * Runs the scheduled task to allocate credits.
     *
     * @Given /^I run the addon credits allocation task$/
     * @return void
     */
    public function i_run_the_credits_addon_allocation_task() {
        // Get the list of availabletime scheduled tasks and execute them.
        $availabletime = \core\task\manager::get_scheduled_task('\\pulseaddon_availability\\task\\availabletime');
        if ($availabletime) {
            $availabletime->execute();
            $availability = \core\task\manager::get_adhoc_tasks('\\pulseaddon_availability\\task\\availability');
            if ($availability) {
                foreach ($availability as $task) {
                    $task->execute();
                }
            }
        }
        // Run the scheduled task for credit allocations.
        $task = pulseaddon_credits\task\credits::prepare_adhoctask();
        $adhoctask = \core\task\manager::get_adhoc_tasks('\\pulseaddon_credits\\credits');
        if ($adhoctask) {
            foreach ($adhoctask as $task) {
                $task->execute();
            }
        }
    }
}
