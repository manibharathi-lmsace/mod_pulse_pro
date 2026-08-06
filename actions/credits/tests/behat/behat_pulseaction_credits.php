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
 * Behat pulseaction_credits-related steps definitions.
 *
 * @package   pulseaction_credits
 * @copyright 2024, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use Behat\Gherkin\Node\TableNode;
use mod_pulse\local\automation\schedule;
use pulseaction_credits\local\credits;

/**
 * Credits action related steps definitions.
 */
class behat_pulseaction_credits extends behat_base {
    /**
     * Opens the credits instance schedule report for a given automation instanceD.
     *
     * @Given /^I open credits instance schedule report for "([^"]*)"$/
     * @param string $reference
     * @return void
     */
    public function i_open_credits_instance_schedule_report($reference) {
        $this->execute("behat_general::i_click_on_in_the", [
            ".action-report#credits-action-report", "css_element", $reference, "table_row"]);
        $this->execute("behat_pulse::switch_to_open_window");
    }

    /**
     * Opens the credits instance override report.
     *
     * @Given /^I open credits instance override report$/
     * @return void
     */
    public function i_open_credits_instance_override_report() {
        $this->execute('behat_navigation::i_select_from_secondary_navigation', ['More']);
        $this->execute("behat_general::i_click_on", [".pulseaction-credits-override.dropdown-item", "css_element"]);
    }

    /**
     * Verifies the number of credit schedules for a specific status.
     *
     * @Then /^I should see "([^"]*)" credit schedules with status "([^"]*)"$/
     * @param string $count
     * @param string $status
     * @return void
     */
    public function i_should_see_credit_schedules_with_status($count, $status) {
        global $DB;

        $statuslist = [
            'planned' => schedule::STATUS_QUEUED,
            'allocated' => schedule::STATUS_COMPLETED,
            'failed' => schedule::STATUS_FAILED,
            'hold' => schedule::STATUS_DISABLED,
        ];

        $statusvalue = $statuslist[strtolower($status)] ?? null;
        if ($statusvalue === null) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "Invalid status '$status'. Must be one of: queued, allocated, failed, hold",
                $this->getSession()
            );
        }

        $actualcount = $DB->count_records('pulseaction_credits_sch', ['status' => $statusvalue]);

        if ($actualcount != $count) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "Expected $count schedules with status '$status', found $actualcount",
                $this->getSession()
            );
        }
    }

    /**
     * Set custom number of credits to user.
     *
     * @Given /^I allocate credits "([^"]*)" to user "([^"]*)"$/
     *
     * @param string $credits
     * @param string $username
     * @return void
     */
    public function i_allocate_credits_to_user($credits, $username) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $field = $DB->get_record('user_info_field', ['shortname' => 'credits'], '*', MUST_EXIST);

        // Check if data exists.
        if ($datarecord = $DB->get_record('user_info_data', ['userid' => $user->id, 'fieldid' => $field->id])) {
            $datarecord->data = $credits;
            $DB->update_record('user_info_data', $datarecord);
        } else {
            $datarecord = new \stdClass();
            $datarecord->userid = $user->id;
            $datarecord->fieldid = $field->id;
            $datarecord->data = $credits;
            $datarecord->dataformat = 0;
            $DB->insert_record('user_info_data', $datarecord);
        }
    }

    /**
     * Runs the scheduled task to allocate credits.
     *
     * @Given /^I run the credits allocation scheduled task$/
     * @return void
     */
    public function i_run_the_credits_allocation_scheduled_task() {
        // Run the scheduled task for credit allocations.
        $task = \core\task\manager::get_scheduled_task('\\pulseaction_credits\\task\\credits_allocation');
        if ($task) {
            $task->execute();
        }
    }

    /**
     * Verifies a user credit balance.
     *
     * @Then /^user "([^"]*)" should have "([^"]*)" credits$/
     * @param string $username
     * @param string $expectedcredits
     * @return void
     */
    public function user_should_have_credits($username, $expectedcredits) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $field = $DB->get_record('user_info_field', ['shortname' => 'credits'], '*', MUST_EXIST);

        $datarecord = $DB->get_record('user_info_data', ['userid' => $user->id, 'fieldid' => $field->id]);
        $actualcredits = $datarecord ? $datarecord->data : '0';

        if ($actualcredits != $expectedcredits) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "User '$username' has $actualcredits credits, expected $expectedcredits",
                $this->getSession()
            );
        }

        $this->execute('behat_auth::i_log_in_as', [$username]);
        $this->execute('behat_general::assert_element_contains_text', [$expectedcredits, ".credits-count", "css_element"]);
        $this->execute('behat_auth::i_log_out');
    }
}
