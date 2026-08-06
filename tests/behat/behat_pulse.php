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
 * Behat pulse-related steps definitions.
 *
 * @package   mod_pulse
 * @copyright 2021, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode,
    Behat\Mink\Exception\ExpectationException,
    Behat\Mink\Exception\DriverException,
    Behat\Mink\Exception\ElementNotFoundException;

/**
 * Course-related steps definitions.
 *
 * @package   mod_pulse
 * @copyright 2021, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_pulse extends behat_base {
    /**
     * Moodle branch number.
     *
     * @return string Moodle branch number.
     */
    public function moodle_branch() {
        global $CFG;
        return $CFG->branch;
    }

    /**
     * Check that the activity has the given automatic completion condition.
     *
     * @Given /^"((?:[^"]|\\")*)" should have the "((?:[^"]|\\")*)" completion condition type "((?:[^"]|\\")*)"$/
     * @param string $activityname The activity name.
     * @param string $conditionname The automatic condition name.
     * @param string $completiontype The completion type text.
     */
    public function activity_should_have_the_completion_condition_type(
        string $activityname,
        string $conditionname,
        string $completiontype
    ): void {
        global $CFG;
        if ($CFG->branch >= "311") {
            $params = [$activityname, $conditionname];
            $this->execute("behat_completion::activity_should_have_the_completion_condition", $params);
        } else {
            $params = [$activityname, 'pulse', $completiontype];
            $this->execute("behat_completion::activity_has_configuration_completion_checkbox", $params);
        }
    }

    /**
     * Checks if the activity with specified name is maked as complete.
     *
     * @Given /^the "([^"]*)" "([^"]*)" completion condition of "([^"]*)" is displayed as "([^"]*)"$/
     * @param string $conditionname The completion condition text.
     * @param string $completiontype The completion type text.
     * @param string $activityname The activity name.
     * @param string $completionstatus The completion status. Must be either of the following: 'todo', 'done', 'failed'.
     */
    public function activity_completion_condition_displayed_as(
        string $conditionname,
        string $completiontype,
        string $activityname,
        string $completionstatus
    ): void {
        if ($this->moodle_branch() >= "311") {
            $params = [$conditionname, $activityname, $completionstatus];
            $this->execute("behat_completion::activity_completion_condition_displayed_as", $params);
        } else {
            $params = [$activityname, 'pulse', $completiontype];
            $this->execute("behat_completion::activity_marked_as_complete", $params);
        }
    }

    /**
     * Checks if the activity with specified name is maked as complete.
     *
     * @Given /^I should see "([^"]*)" completion condition of "([^"]*)" is displayed as "([^"]*)"$/
     * @param string $conditionname The completion condition text.
     * @param string $activityname The activity name.
     * @param string $completionstatus The completion status. Must be either of the following: 'todo', 'done', 'failed'.
     */
    public function i_should_see_completion_condition_displayed_as(
        string $conditionname,
        string $activityname,
        string $completionstatus
    ): void {
        if ($this->moodle_branch() >= "311") {
            $params = [$conditionname, $activityname, $completionstatus];
            $this->execute("behat_completion::activity_completion_condition_displayed_as", $params);
        } else {
            $params = [$activityname];
            $this->execute("behat_general::assert_page_contains_text", [$conditionname]);
        }
    }

    /**
     * Checks if the activity with specified name is maked as complete.
     *
     * @Given /^I create demo presets$/
     * @return void
     */
    public function i_create_demo_presets(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/pulse/lib.php');
        \mod_pulse\preset::pulse_create_presets();
    }

    /**
     * Open the automation templates listing page.
     *
     * @Given /^I navigate to automation templates$/
     */
    public function i_navigate_to_automation_templates() {
        $this->execute(
            'behat_navigation::i_navigate_to_in_site_administration',
            ['Plugins > Activity modules > Pulse > Automation templates']
        );
    }

    /**
     * Open the automation instance listing page for the course.
     *
     * @Given /^I navigate to course "(?P<coursename>(?:[^"]|\\")*)" automation instances$/
     * @param string $coursename Coursename.
     */
    public function i_navigate_to_course_automation_instances($coursename) {
        $this->execute('behat_navigation::i_am_on_course_homepage', [$coursename]);
        $this->execute('behat_navigation::i_select_from_secondary_navigation', get_string('automation', 'pulse'));
    }

    /**
     * Fills a automation template create form with field/value data.
     *
     * @Given /^I create automation template with the following fields to these values:$/
     * @throws ElementNotFoundException Thrown by behat_base::find
     * @param TableNode $data
     */
    public function i_create_automation_template_with_the_following_fields_to_these_values(TableNode $data) {

        $this->execute(
            'behat_navigation::i_navigate_to_in_site_administration',
            ["Plugins > Activity modules > Pulse > Automation templates"]
        );
        $this->execute("behat_general::i_click_on", ["Create new template", "button"]);
        $this->execute('behat_forms::i_set_the_following_fields_to_these_values', [$data]);
        $this->execute("behat_general::i_click_on", ["Save changes", "button"]);
    }

    /**
     * Fills a automation template condition form with field/value data.
     *
     * @Given /^I create "([^"]*)" template with the set the condition:$/
     * @throws ElementNotFoundException Thrown by behat_base::find
     * @param string $reference
     * @param TableNode $data
     */
    public function i_create_autoation_template_condition_to_these_values($reference, TableNode $data) {

        $this->execute(
            'behat_navigation::i_navigate_to_in_site_administration',
            ["Plugins > Activity modules > Pulse > Automation templates"]
        );
        $this->execute("behat_general::i_click_on_in_the", [".action-edit", "css_element", $reference, "table_row"]);
        $this->execute("behat_general::click_link", ["Condition"]);
        $this->execute('behat_forms::i_set_the_following_fields_to_these_values', [$data]);
        $this->execute("behat_general::i_click_on", ["Save changes", "button"]);
    }

    /**
     * Fills a automation template notification form with field/value data.
     *
     * @Given /^I create "([^"]*)" template with the set the notification:$/
     * @throws ElementNotFoundException Thrown by behat_base::find
     * @param string $reference
     * @param TableNode $data
     */
    public function i_create_automation_template_notification_to_these_values($reference, TableNode $data) {

        $this->execute(
            'behat_navigation::i_navigate_to_in_site_administration',
            ["Plugins > Activity modules > Pulse > Automation templates"]
        );
        $this->execute("behat_general::i_click_on_in_the", [".action-edit", "css_element", $reference, "table_row"]);
        $this->execute(
            'behat_general::i_click_on_in_the',
            ['.nav-item a[href="#pulse-action-notification"]', "css_element", "ul#automation-tabs", "css_element"]
        );
        $this->execute('behat_pulse::i_enable_pulse_action', ['notification']);
        $this->execute('behat_forms::i_set_the_following_fields_to_these_values', [$data]);
        $this->execute("behat_general::i_click_on", ["Save changes", "button"]);
    }

    /**
     * Select the conditions are met option on the activity completion tracking .
     *
     * @Given I set the activity completion tracking
     */
    public function i_set_the_activity_completion_tracking() {
        global $CFG;

        if ($CFG->branch >= "401") {
            $this->execute('behat_forms::i_set_the_field_to', ['Add requirements', '1']);
        } else {
            $this->execute(
                'behat_forms::i_set_the_field_to',
                ['Completion tracking', 'Show activity as complete when conditions are met']
            );
        }
    }

    /**
     * Switches to a pulse new window.
     *
     * @Given /^I switch to a pulse open window$/
     * @throws DriverException If there aren't exactly 2 windows open.
     */
    public function switch_to_open_window() {
        $names = $this->getSession()->getWindowNames();
        $this->getSession()->switchToWindow(end($names));
    }

    /**
     * Switches to a pulse new window.
     *
     * @Given /^I click on pulse "([^"]*)" editor$/
     *
     * @param string $editor
     * @throws DriverException If there aren't exactly 2 windows open.
     */
    public function i_click_on_pulse_editor($editor) {
        global $CFG;

        if ($CFG->branch >= 402) {
            $this->execute(
                'behat_general::i_click_on_in_the',
                ['#' . $editor . '_ifr', 'css_element', '#fitem_' . $editor, 'css_element']
            );
        } else {
            $this->execute(
                'behat_general::i_click_on_in_the',
                ['#' . $editor . 'editable', 'css_element', '#fitem_' . $editor, 'css_element']
            );
        }
    }

    /**
     * View assignment submission button.
     *
     * @Given /^I click on assignment submissions button$/
     *
     * @throws DriverException If there aren't exactly 2 windows open.
     */
    public function i_click_on_assignment_submissions_button() {
        global $CFG;

        if ($CFG->branch >= 405) {
            $this->execute(
                'behat_general::i_click_on_in_the',
                ['Submissions', 'link', '.secondary-navigation', 'css_element']
            );
        } else {
            $this->execute(
                'behat_general::i_click_on_in_the',
                ['View all submissions', 'link', '.tertiary-navigation', 'css_element']
            );
        }
    }

    /**
     * Click on user edit menu button on submissions page.
     *
     * @Given /^I click on "([^"]*)" edit menu on submissions page$/
     *
     * @param string $user
     * @throws DriverException If there aren't exactly 2 windows open.
     */
    public function i_click_on_user_edit_menu_on_submissions_page($user) {
        global $CFG;

        if ($CFG->branch >= 405) {
            $this->execute(
                'behat_general::i_click_on_in_the',
                ['#action-menu-toggle-0', 'css_element', $user, 'table_row']
            );
        } else {
            $this->execute(
                'behat_general::i_click_on_in_the',
                ['Edit', 'link', $user, 'table_row']
            );
        }
    }

    /**
     * Click on user edit menu button on submissions page.
     *
     * @Given /^I add pulse to course "([^"]*)" section "([^"]*)" with:$/
     *
     * @param string $coursename Course name.
     * @param string $section Section name.
     * @param TableNode $data Data to fill in the form.
     * @throws DriverException If there aren't exactly 2 windows open.
     */
    public function i_add_pulse_to_course_section($coursename, $section, TableNode $data) {
        global $CFG;

        if ($CFG->branch >= 404) {
            $this->execute(
                'behat_course::i_add_to_course_section_and_i_fill_the_form_with',
                ['pulse', $coursename, $section, $data]
            );
        } else {
            $this->execute(
                'behat_general::i_add_to_section_and_i_fill_the_form_with',
                ['pulse', $section, $data]
            );
        }
    }

    /**
     * Edit automation template action.
     *
     * @Given /^I edit "([^"]*)" automation template action "([^"]*)"$/
     *
     * @param string $templatename Template name.
     * @param string $action Action name.
     */
    public function i_edit_automation_template_action($templatename, $action) {
        $this->execute("behat_general::i_click_on_in_the", [".action-edit", "css_element", $templatename, "table_row"]);
        $this->execute(
            "behat_general::i_click_on_in_the",
            ['.nav-item a[href="#pulse-action-' . $action . '"]', "css_element", "ul#automation-tabs", "css_element"]
        );
    }

    /**
     * Enable pulse action in the instance.
     *
     * @Given /^I enable pulse action "([^"]*)" in the instance$/
     *
     * @param string $action Action name.
     */
    public function i_enable_pulse_action_in_the_instance($action) {
        $action = strtolower($action);
        $this->execute(
            'behat_general::i_click_on_in_the',
            ['.nav-item a[href="#pulse-action-' . $action . '"]', "css_element", "ul#automation-tabs", "css_element"]
        );
        $this->execute("behat_general::i_click_on", ["#id_override_pulse" . $action . "_actionstatus", "css_element"]);
        $this->execute(
            'behat_forms::i_set_the_field_in_container_to',
            ['Status', '#pulse-action-' . strtolower($action), 'css_element', 'Enabled' ]
        );
    }

    /**
     * Save pulse action instance.
     *
     * @Given /^I save the pulse action instance "([^"]*)" on course "([^"]*)"$/
     *
     * @param string $reference Action name.
     * @param string $coursename Course name.
     */
    public function i_save_the_pulse_action_instance($reference, $coursename) {
        global $DB, $CFG;
        $this->execute('behat_navigation::i_am_on_course_homepage', [$coursename]);
        $this->execute('behat_general::click_link', ['Automation']);
        $this->execute("behat_general::i_click_on_in_the", [".action-edit", "css_element", $reference, "table_row"]);
        $this->execute("behat_general::i_click_on", ["Save changes", "button"]);
    }

    /**
     * Enable pulse action in the instance.
     *
     * @Given /^I enable pulse action "([^"]*)"$/
     *
     * @param string $action Action name.
     */
    public function i_enable_pulse_action($action) {
        $action = strtolower($action);
        $this->execute(
            'behat_general::i_click_on_in_the',
            ['.nav-item a[href="#pulse-action-' . $action . '"]', "css_element", "ul#automation-tabs", "css_element"]
        );
        $this->execute(
            'behat_forms::i_set_the_field_in_container_to',
            ['Status', '#pulse-action-' . strtolower($action), 'css_element', '1' ]
        );
    }

    /**
     * Check that the schedule time for user in table.
     *
     * @Given /^I should see schedule time "([^"]*)" in the "([^"]*)" "([^"]*)"$/
     * @param string $date The date string.
     * @param string $username The username.
     * @param string $type Type of the selector.
     */
    public function i_should_see_schedule_in_the($date, $username, $type) {

        $date = (new behat_transformations())->arg_time_to_string($date);
        $date = userdate($date, get_string('strftimedaydate', 'langconfig'));

        $this->execute(
            'behat_general::assert_element_contains_text',
            [$date, $username, $type]
        );
    }

    /**
     * Change user enrollment time in a course.
     * This is useful for testing time-based conditions like user inactivity.
     *
     * @Given /^I change user enrollment time to "([^"]*)" (minutes|hours|days|weeks) for "([^"]*)" in "([^"]*)"$/
     * @param string $offset The time offset (can be negative or positive number).
     * @param string $unit The time unit (minutes, hours, days, or weeks).
     * @param string $username The username.
     * @param string $coursename The course name.
     */
    public function i_change_user_enrollment_time($offset, $unit, $username, $coursename) {
        global $DB;
        // Get user.
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);

        // Get course.
        $course = $DB->get_record('course', ['shortname' => $coursename], '*', MUST_EXIST);

        // Convert time unit to seconds.

        // Calculate new enrollment time.

        $newenroltime = strtotime("$offset $unit", time());

        // Get user enrolment records for this course.
        $sql = "SELECT ue.*
                FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid
                WHERE ue.userid = :userid AND e.courseid = :courseid";

        $enrolments = $DB->get_records_sql($sql, [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);

        if (empty($enrolments)) {
            throw new ExpectationException(
                "User '{$username}' is not enrolled in course '{$coursename}'",
                $this->getSession()
            );
        }

        // Update all user enrolments for this course.
        foreach ($enrolments as $enrolment) {
            $enrolment->timecreated = $newenroltime;
            $DB->update_record('user_enrolments', $enrolment);
        }
    }

    /**
     * Change the timeadded for a cohort membership.
     * Allows tests to control which condition appears as the most recently satisfied.
     *
     * @Given /^I set cohort membership time to "([^"]*)" (minutes|hours|days|weeks) for "([^"]*)" in cohort "([^"]*)"$/
     * @param string $offset The time offset (can be negative or positive number).
     * @param string $unit The time unit (minutes, hours, days, or weeks).
     * @param string $username The username.
     * @param string $cohortidnumber The cohort idnumber.
     */
    public function i_set_cohort_membership_time($offset, $unit, $username, $cohortidnumber) {
        global $DB;

        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $cohort = $DB->get_record('cohort', ['idnumber' => $cohortidnumber], '*', MUST_EXIST);

        $newtime = strtotime("$offset $unit", time());

        if (!$DB->record_exists('cohort_members', ['userid' => $user->id, 'cohortid' => $cohort->id])) {
            throw new ExpectationException(
                "User '{$username}' is not a member of cohort '{$cohortidnumber}'",
                $this->getSession()
            );
        }

        $DB->set_field('cohort_members', 'timeadded', $newtime, ['userid' => $user->id, 'cohortid' => $cohort->id]);
    }

    /**
     * Change user's last course access time.
     * This is useful for testing access-based inactivity conditions.
     *
     * @Given /^I set last course access to "([^"]*)" (minutes|hours|days|weeks) for "([^"]*)" in "([^"]*)"$/
     * @param string $offset The time offset (positive number for past time).
     * @param string $unit The time unit (minutes, hours, days, or weeks).
     * @param string $username The username.
     * @param string $coursename The course name.
     */
    public function i_set_last_course_access($offset, $unit, $username, $coursename) {
        global $DB;

        // Get user.
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);

        // Get course.
        $course = $DB->get_record('course', ['shortname' => $coursename], '*', MUST_EXIST);

        $lastaccesstime = strtotime("$offset $unit", time());

        // Check if record exists.
        $lastaccess = $DB->get_record('user_lastaccess', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);

        if ($lastaccess) {
            // Update existing record.
            $lastaccess->timeaccess = $lastaccesstime;
            $DB->update_record('user_lastaccess', $lastaccess);
        } else {
            // Create new record.
            $record = new stdClass();
            $record->userid = $user->id;
            $record->courseid = $course->id;
            $record->timeaccess = $lastaccesstime;
            $DB->insert_record('user_lastaccess', $record);
        }
    }

    /**
     * Set activity completion time for a user.
     * This is useful for testing completion-based inactivity conditions.
     *
     * @Given /^I set activity completion time to "([^"]*)" (minutes|hours|days|weeks) for "([^"]*)" on "([^"]*)" in "([^"]*)"$/
     * @param string $offset The time offset (positive number for past time).
     * @param string $unit The time unit (minutes, hours, days, or weeks).
     * @param string $username The username.
     * @param string $activityidnumber The activity ID number.
     * @param string $coursename The course name.
     */
    public function i_set_activity_completion_time($offset, $unit, $username, $activityidnumber, $coursename) {
        global $DB;

        // Get user.
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);

        // Get course.
        $course = $DB->get_record('course', ['shortname' => $coursename], '*', MUST_EXIST);

        $cm = $DB->get_record('course_modules', [
            'course' => $course->id,
            'idnumber' => $activityidnumber,
        ]);

        if (!$cm) {
            throw new ExpectationException(
                "Activity '{$activityidnumber}' not found in course '{$coursename}'",
                $this->getSession()
            );
        }

        // Calculate completion time.
        $completiontime = strtotime("$offset $unit", time());

        // Check if completion record exists.
        $completion = $DB->get_record('course_modules_completion', [
            'coursemoduleid' => $cm->id,
            'userid' => $user->id,
        ]);

        if ($completion) {
            // Update existing record.
            $completion->timemodified = $completiontime;
            $DB->update_record('course_modules_completion', $completion);
        } else {
            // Create new completion record.
            $record = new stdClass();
            $record->coursemoduleid = $cm->id;
            $record->userid = $user->id;
            $record->completionstate = COMPLETION_COMPLETE;
            $record->viewed = 1;
            $record->timemodified = $completiontime;
            $DB->insert_record('course_modules_completion', $record);
        }
    }

    /**
     * Run automation evaluation for all enrolled users in a course.
     *
     * This simulates automation condition evaluation for all enrolled users,
     * useful for testing delay base feature without needing real event triggers.
     *
     * @Given /^I run automation evaluation for "([^"]*)" course$/
     * @param string $courseshortname The course shortname.
     */
    public function i_run_automation_evaluation_for_course($courseshortname) {
        global $DB;

        $course = $DB->get_record('course', ['shortname' => $courseshortname], '*', MUST_EXIST);

        // Get all active automation instances for this course.
        $instances = $DB->get_records('pulse_autoinstances', ['courseid' => $course->id, 'status' => 1]);

        if (empty($instances)) {
            return;
        }

        $context = \context_course::instance($course->id);
        $enrolledusers = get_enrolled_users($context);

        foreach ($instances as $instance) {
            $automationinstance = \mod_pulse\automation\instances::create($instance->id);
            foreach ($enrolledusers as $user) {
                $automationinstance->trigger_action($user->id);
            }
        }
    }

    /**
     * Set a course's start date to an offset from the current time.
     *
     * @Given /^I set course "([^"]*)" start date to "([^"]*)" (minutes|hours|days|weeks)$/
     * @param string $courseshortname  Course shortname.
     * @param string $offset  Signed time offset
     * @param string $unit    Time unit: minutes, hours, days, or weeks.
     */
    public function i_set_course_start_date($courseshortname, $offset, $unit) {
        global $DB;

        $course  = $DB->get_record('course', ['shortname' => $courseshortname], '*', MUST_EXIST);
        $newtime = strtotime("$offset $unit", time());

        $DB->set_field('course', 'startdate', $newtime, ['id' => $course->id]);
        rebuild_course_cache($course->id, true);
    }

    /**
     * Set a course's end date to an offset from the current time.
     *
     * @Given /^I set course "([^"]*)" end date to "([^"]*)" (minutes|hours|days|weeks)$/
     * @param string $courseshortname  Course shortname.
     * @param string $offset  Signed time offset
     * @param string $unit    Time unit: minutes, hours, days, or weeks.
     */
    public function i_set_course_end_date($courseshortname, $offset, $unit) {
        global $DB;

        $course  = $DB->get_record('course', ['shortname' => $courseshortname], '*', MUST_EXIST);
        $newtime = strtotime("$offset $unit", time());

        $DB->set_field('course', 'enddate', $newtime, ['id' => $course->id]);
        rebuild_course_cache($course->id, true);
    }

    /**
     * Backdate the "Upcoming" enabled time (upcomingtime) for a condition override on an
     * automation instance. Useful for testing time-based conditions (e.g. user inactivity)
     * without waiting in real time for the inactivity period to elapse after enabling.
     *
     * @Given /^I set upcoming enabled time to "([^"]*)" (minutes|hours|days|weeks) for condition "([^"]*)" in instance "([^"]*)"$/
     * @param string $offset The time offset (can be negative or positive number).
     * @param string $unit The time unit (minutes, hours, days, or weeks).
     * @param string $triggercondition The condition component name (e.g. "userinactivity").
     * @param string $insreference The automation instance reference.
     */
    public function i_set_upcoming_enabled_time($offset, $unit, $triggercondition, $insreference) {
        global $DB;

        $instanceid = $DB->get_field_sql(
            "SELECT ai.id
               FROM {pulse_autoinstances} ai
               JOIN {pulse_autotemplates_ins} ati ON ati.instanceid = ai.id
              WHERE ati.insreference = :insreference",
            ['insreference' => $insreference],
            MUST_EXIST
        );

        $newtime = strtotime("$offset $unit", time());

        if (!$DB->record_exists('pulse_condition_overrides', ['instanceid' => $instanceid, 'triggercondition' => $triggercondition])) {
            throw new ExpectationException(
                "No condition override for '{$triggercondition}' found on instance '{$insreference}'",
                $this->getSession()
            );
        }

        $DB->set_field('pulse_condition_overrides', 'upcomingtime', $newtime,
            ['instanceid' => $instanceid, 'triggercondition' => $triggercondition]);
    }

    /**
     * Check that the automatic completion condition badge shows the expected text.
     * Moodle 5.02+ replaced .badge with .automatic-completion-is-complete.
     *
     * @Given /^I should see "([^"]*)" in the automatic completion badge$/
     * @param string $text The text expected inside the completion badge.
     */
    public function i_should_see_in_the_automatic_completion_badge(string $text): void {
        if ($this->moodle_branch() >= 502) {
            $selector = '.automatic-completion-conditions .automatic-completion-is-complete';
        } else {
            $selector = '.automatic-completion-conditions .badge';
        }
        $this->execute('behat_general::assert_element_contains_text', [$text, $selector, 'css_element']);
    }

    /**
     * Check the completion status label inside .completion-info.
     * Moodle 5.02+ uses .automatic-completion-is-{complete,failed,incomplete} instead of .badge.
     *
     * @Given /^I should see "([^"]*)" in the completion info badge$/
     * @param string $text The status text to check ("Done:", "Failed:", "To do:").
     */
    public function i_should_see_in_the_completion_info_badge(string $text): void {
        if ($this->moodle_branch() >= 502) {
            $classmap = [
                'Done:'   => 'automatic-completion-is-complete',
                'Failed:' => 'automatic-completion-is-failed',
                'To do:'  => 'automatic-completion-is-incomplete',
            ];
            $class = $classmap[$text] ?? 'automatic-completion-is-complete';
            $selector = ".completion-info .{$class} strong";
        } else {
            $selector = '.completion-info .badge:last-child strong';
        }
        $this->execute('behat_general::assert_element_contains_text', [$text, $selector, 'css_element']);
    }

    /**
     * Selects an activity from the activity chooser and clicks "Add selected activity".
     *
     * @Given /^I add "(?P<activity_name_string>(?:[^"]|\\")*)" activity from the activity chooser$/
     * @param string $activityname
     */
    public function i_add_activity_from_the_activity_chooser($activityname) {
        global $CFG;
        // Open activity chooser.
        $this->execute('behat_course::i_open_the_activity_chooser');

        if ($CFG->branch <= "405") {
            $this->execute('behat_general::i_click_on', [$activityname, "link"]);
        } else {
            $this->execute('behat_general::i_click_on', [
               "Add a new {$activityname}",
                'link',
                "Add an activity or resource",
                'dialogue',
            ]);
            if ($CFG->branch >= "501") {
                $this->execute('behat_general::i_click_on_in_the', [
                    get_string('addselectedactivity', 'course'),
                    'button',
                    get_string('addresourceoractivity', 'moodle'),
                    'dialogue',
                ]);
            }
        }
    }
}
