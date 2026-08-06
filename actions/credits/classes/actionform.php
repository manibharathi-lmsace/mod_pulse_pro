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
 * Credits pulse action form.
 *
 * @package   pulseaction_credits
 * @copyright 2025 bdecent GmbH <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulseaction_credits;

defined('MOODLE_INTERNAL') || die();

use core\exception\moodle_exception;
use stdClass;
use html_writer;
use mod_pulse\automation\helper;
use pulseaction_credits\local\credits;
use pulseaction_credits\local\credits_schedule;
use mod_pulse\local\automation\schedule;
use pulseaction_credits\local\override_manager;

require_once($CFG->dirroot . '/mod/pulse/actions/credits/lib.php');

/**
 * Credits action form, contains important method and basic plugin details.
 */
class actionform extends \mod_pulse\automation\action_base {
    /** @var credits|null The credits object. */
    public $credits = null;

    /**
     * Get the report source for this action.
     *
     * @return string The report source class name.
     */
    public function get_report_source() {
        return '\pulseaction_credits\reportbuilder\datasource\credits';
    }

    /**
     * Includes report view actions.
     *
     * @param array $actions The actions array.
     * @param object $row The row object.
     * @param \moodle_url $listurl The list URL.
     *
     * @return array An array of actions to include in the report view.
     */
    public function include_reports_view(&$actions, $row, $listurl) {

        $actions[] = [
            'url' => new \moodle_url($listurl, ['report' => 'credits']),
            'icon' => new \pix_icon('i/db', \get_string('creditsallocationschdule', 'pulseaction_credits')),
            'attributes' => ['class' => 'action-report', 'id' => 'credits-action-report', 'target' => '_blank'],
        ];
    }

    /**
     * Credits action constructor.
     *
     * @param int $instanceid The instance ID.
     * @param stdclass|null $instancedata The instance data.
     *
     * @return credits The credits object.
     */
    public function get_credits($instanceid, $instancedata = null) {

        if ($this->credits && $this->credits->instanceid == $instanceid) {
            if (empty($this->credits->instancedata)) {
                $this->credits->set_instancedata($instancedata);
            }

            return $this->credits;
        }

        return new credits($instanceid, $instancedata);
    }

    /**
     * Shortname for the config used in the form field.
     *
     * @return string
     */
    public function config_shortname() {
        return 'pulsecredits';
    }

    /**
     * Get the icon for the credits, displayed on the instances list on the course autotemplates sections.
     *
     * @return string
     */
    public function get_action_icon() {
        global $OUTPUT;
        return $OUTPUT->pix_icon("i/db", get_string('credits', 'pulseaction_credits'));
    }

    /**
     * Delete credit instances and schedule data for this instance.
     *
     * @param int $instanceid
     * @return void
     */
    public function delete_instance_action(int $instanceid) {
        global $DB;
        parent::delete_instance_action($instanceid);

        $scheduleids = $DB->get_fieldset_select(
            'pulseaction_credits_sch',
            'id',
            'instanceid = :instanceid',
            ['instanceid' => $instanceid]
        );
        if (!empty($scheduleids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($scheduleids);
            $DB->delete_records_select('pulseaction_credits_override', "scheduleid $insql", $inparams);
        }

        $DB->delete_records('pulseaction_credits_sch', ['instanceid' => $instanceid]);
        return true;
    }

    /**
     * Course-level cleanup called only when the course itself is deleted.
     *
     * @param int $courseid
     * @return void
     */
    public function delete_course_action(int $courseid): void {
        global $DB;
        if ($courseid) {
            $DB->delete_records('pulseaction_credits_user_override', ['courseid' => $courseid]);
        }
    }

    /**
     * Instances status updated, then update all the schedules of the instances.
     *
     * @param stdclass $instancedata
     * @param bool $status
     * @return void
     */
    public function instance_status_updated($instancedata, $status) {

        $creditsid = $instancedata->actions['credits']['id'];
        $credits = credits_schedule::instance($creditsid);
        $credits->set_action_data($instancedata->actions['credits'], $instancedata);

        $credits->create_schedule_forinstance();
    }

    /**
     * Delete the credit template action.
     *
     * @param int $templateid
     * @return void
     */
    public function delete_template_action($templateid) {
        global $DB;

        $instances = $this->get_template_instances($templateid);
        foreach ($instances as $instanceid => $instance) {
            $this->delete_instance_action($instanceid);
        }

        return $DB->delete_records('pulseaction_credits', ['templateid' => $templateid]);
    }

    /**
     * Action is triggered for the instance. Create credit allocation schedule for the triggered users.
     *
     * @param \stdclass $instancedata
     * @param int $userid
     * @param int $expectedtime
     * @param bool $newuser
     *
     * @return void
     */
    public function trigger_action($instancedata, $userid, $expectedtime = null, $newuser = false) {
        global $DB;

        if (!isset($instancedata->pulsecredits_id)) {
            return false;
        }

        $creditsinstance = $DB->get_record('pulseaction_credits_ins', ['instanceid' => $instancedata->id]);
        if (empty($creditsinstance) || $creditsinstance->actionstatus === credits::ACTION_CREDITS_DISABLED) {
            return false;
        }

        $manager = new credits_schedule($creditsinstance->id, $instancedata);
        $creditsdata = (object) helper::filter_record_byprefix($instancedata, $this->config_shortname());
        $manager->set_action_data($creditsdata, $instancedata);
        $manager->create_schedule_forinstance($newuser, $userid, true);

        // Allocate the scheduled credits for this user.
        (new credits())->allocate_credits($userid);

        return true;
    }

    /**
     * Remove the user schedules when the user is deleted.
     *
     * @param \stdclass $instancedata Automation Instance data.
     * @param string $method Name of the triggered event.
     * @param stdclass $eventdata Triggered event data.
     *
     * @return void
     */
    public function trigger_action_event($instancedata, $method, $eventdata) {

        if ($method == 'user_enrolment_deleted') {
            $userid = $eventdata->relateduserid;

            if (empty($userid) || empty($instancedata->id)) {
                return;
            }

            // Remove the user overrides if any.
            override_manager::remove_user_overrides($userid, $instancedata->id);

            $manager = credits_schedule::create_from_templateinstance($instancedata->id, $instancedata);
            // The instance's conditions have already been re-verified as no longer
            // satisfied (see instances::trigger_action_event()) - the queued
            // schedule is genuinely invalid now, so remove it.
            $manager->remove_user_schedules($userid, $instancedata->id);
        }
    }

    /**
     * Get the credits record for template form.
     *
     * @param int $templateid
     * @return stdclass
     */
    public function get_data_fortemplate($templateid) {
        global $DB;
        $actiondata = $DB->get_record('pulseaction_credits', ['templateid' => $templateid]);
        return $actiondata;
    }

    /**
     * Get the credits instance record.
     *
     * @param int $instanceid
     * @return stdclass Data of the credits instance.
     */
    public function get_data_forinstance($instanceid) {
        global $DB;
        $instancedata = $DB->get_record('pulseaction_credits_ins', ['instanceid' => $instanceid]);
        return $instancedata ?: [];
    }

    /**
     * Decode the json encoded credits data.
     *
     * @param array $actiondata
     * @return void
     */
    public function update_encode_data(&$actiondata) {
        $actiondata = (array) $actiondata;

        if (array_key_exists('notifyinterval', $actiondata)) {
            $actiondata['notifyinterval'] = !empty($actiondata['notifyinterval'])
                ? json_decode($actiondata['notifyinterval'], true) : ['interval' => schedule::INTERVALONCE];

            $actiondata['intervaltype'] = $actiondata['notifyinterval']['interval'] ?? schedule::INTERVALONCE;
        }

        if (isset($actiondata['recipients'])) {
            $actiondata['recipients'] = json_decode($actiondata['recipients']);
        }
    }

    /**
     * Load the credits elements for the instance form.
     *
     * @param moodle_form $mform
     * @param actionform $forminstance
     * @return void
     */
    public function load_instance_form(&$mform, $forminstance) {
        $this->load_global_form($mform, $forminstance);
    }

    /**
     * Global form elements for credits action.
     *
     * @param moodle_form $mform
     * @param \automation_instance_form $forminstance
     * @return void
     */
    public function load_global_form(&$mform, $forminstance) {
        global $CFG;

        require_once($CFG->dirroot . '/course/lib.php');

        // Credits.
        $mform->addElement('text', 'pulsecredits_credits', get_string('credits', 'pulseaction_credits'));
        $mform->setType('pulsecredits_credits', PARAM_FLOAT);
        $mform->addRule('pulsecredits_credits', get_string('invalidcredits', 'pulseaction_credits'), 'numeric', null, 'client');
        $mform->addHelpButton('pulsecredits_credits', 'credits', 'pulseaction_credits');

        // Allocation method.
        $allocationoptions = [
            credits::ALLOCATION_ADD => get_string('addcredits', 'pulseaction_credits'),
            credits::ALLOCATION_REPLACE => get_string('replacecredits', 'pulseaction_credits'),
        ];
        $mform->addElement(
            'select',
            'pulsecredits_allocationmethod',
            get_string('allocationmethod', 'pulseaction_credits'),
            $allocationoptions
        );
        $mform->setDefault('pulsecredits_allocationmethod', credits::ALLOCATION_ADD);
        $mform->addHelpButton('pulsecredits_allocationmethod', 'allocationmethod', 'pulseaction_credits');

        // Interval settings.
        schedule::include_interval_fields($mform, 'pulsecredits_notifyinterval', [
            schedule::INTERVALONCE, schedule::INTERVALDAILY, schedule::INTERVALWEEKLY,
            schedule::INTERVALMONTHLY, schedule::INTERVALYEARLY, schedule::INTERVALCUSTOM]);
        // Change the help content with custom cron tab schedule.
        $mform->addHelpButton('pulsecredits_notifyinterval', 'interval', 'pulseaction_credits');

        // Base date type.
        $basedateoptions = [
            credits::BASEDATERELATIVE => get_string('basedaterelative', 'pulseaction_credits'),
            credits::BASEDATEFIXED => get_string('basedatefixed', 'pulseaction_credits'),
        ];
        $mform->addElement(
            'select',
            'pulsecredits_basedatetype',
            get_string('basedate', 'pulseaction_credits'),
            $basedateoptions
        );
        $mform->setDefault('pulsecredits_basedatetype', credits::BASEDATERELATIVE);
        $mform->addHelpButton('pulsecredits_basedatetype', 'basedate', 'pulseaction_credits');
        $mform->hideIf('pulsecredits_basedatetype', 'pulsecredits_actionstatus', 'eq', 0);

        // Relative date config.
        $mform->addElement(
            'date_selector',
            'pulsecredits_fixeddate',
            get_string('fixeddate', 'pulseaction_credits'),
            ['optional' => false, 'step' => 2]
        );
        $mform->hideIf('pulsecredits_fixeddate', 'pulsecredits_basedatetype', 'neq', credits::BASEDATEFIXED);

        // Recipients roles that can receive credits.
        $roles = get_roles_with_capability('pulseaction/credits:receivecredits');
        $rolenames = role_fix_names($roles);
        $roleoptions = array_combine(array_column($rolenames, 'id'), array_column($rolenames, 'localname'));
        $mform->addElement(
            'autocomplete',
            'pulsecredits_recipients',
            get_string('recipients', 'pulseaction_credits'),
            $roleoptions,
            ['multiple' => 'multiple']
        );
        $mform->addHelpButton('pulsecredits_recipients', 'recipients', 'pulseaction_credits');
    }

    /**
     * Validate the template form.
     *
     * @param array $data Array of submitted data.
     * @param array $files Array of uploaded files.
     * @param \moodleform $mform The form being validated.
     * @param stdclass|null $instance The instance data if available.
     *
     * @return array
     */
    public function validate_template_form($data, $files, $mform, $instance) {

        // Only for the instance form.
        $isinstance = isset($data['insreference']);
        if ($isinstance && isset($data['pulsecredits_credits']) && $data['pulsecredits_credits'] < 0) {
            $errors['pulsecredits_credits'] = get_string('required', 'core');
        }

        if (
            isset($data['pulsecredits_credits']) && ($data['pulsecredits_credits'] < 0
            || credits::verify_is_validcredits($data['pulsecredits_credits']) === false)
        ) {
            $errors['pulsecredits_credits'] = get_string('invalidcredits', 'pulseaction_credits');
        }

        // Validate crontab fields if custom interval is selected.
        if (
            !empty($data['pulsecredits_notifyinterval']['interval']) &&
            $data['pulsecredits_notifyinterval']['interval'] == schedule::INTERVALCUSTOM
        ) {
            // Use a checker class.
            $checker = new \tool_task\scheduled_checker_task();
            $crondata = $data['pulsecredits_notifyinterval'];
            $checker->set_minute($crondata['cron_minute'] ?: '*');
            $checker->set_hour($crondata['cron_hour'] ?: '*');
            $checker->set_month($crondata['cron_month'] ?: '*');
            $checker->set_day_of_week($crondata['cron_dayofweek'] ?: '*');
            $checker->set_day($crondata['cron_day'] ?: '*');

            if (!$checker->is_valid($checker::FIELD_MINUTE)) {
                $errors['pulsecredits_notifyinterval_cron'] = get_string('invaliddata', 'core_error');
            }
            if (!$checker->is_valid($checker::FIELD_HOUR)) {
                $errors['pulsecredits_notifyinterval_cron'] = get_string('invaliddata', 'core_error');
            }
            if (!$checker->is_valid($checker::FIELD_DAY)) {
                $errors['pulsecredits_notifyinterval_cron'] = get_string('invaliddata', 'core_error');
            }
            if (!$checker->is_valid($checker::FIELD_MONTH)) {
                $errors['pulsecredits_notifyinterval_cron'] = get_string('invaliddata', 'core_error');
            }
            if (!$checker->is_valid($checker::FIELD_DAYOFWEEK)) {
                $errors['pulsecredits_notifyinterval_cron'] = get_string('invaliddata', 'core_error');
            }
        }

        return $errors ?? [];
    }

    /**
     * Default override elements.
     *
     * @return array
     */
    public function default_override_elements() {
        // List of pulse notification elements those are available in only instances.
        return [
            'pulsecredits_notifyinterval_cron',
            'pulsecredits_intervaltype',
            'pulsecredits_instanceid',
            'pulsecredits_timecreated',
            'pulsecredits_timemodified',
            'pulsecredits_id',
        ];
    }


    /**
     * Generate the warnings if the instance is not compatibile to allocate credits.
     *
     * @param \stdclass $course
     * @return array
     */
    public function display_instance_warnings(\stdclass $course): array {

        // Get the credit profile field configuration.
        $creditfield = \pulseaction_credits_get_configured_creditfield_id();
        if (empty($creditfield)) {
            $warning[] = get_string('nocreditprofilefield', 'pulseaction_credits');
        }

        return $warning ?? [];
    }

    /**
     * Create an empty template config for credits action.
     *
     * @param int $templateid
     * @return void
     */
    public function create_empty_template(int $templateid) {
        global $DB;

        $record = new \stdClass();
        $record->templateid = $templateid;
        $record->actionstatus = credits::ACTION_CREDITS_DISABLED;
        $record->credits = 0;
        $record->timecreated = time();
        $record->timemodified = time();

        return $DB->insert_record('pulseaction_credits', $record);
    }

    /**
     * Save the template config.
     *
     * @param stdclass $record
     * @param string $component
     *
     * @return bool
     */
    public function process_save($record, $component) {
        global $DB;

        // Filter the current action data from the templates data by its shortname.
        $actiondata = $this->filter_action_data((array) $record);
        $actiondata->templateid = $record->templateid;
        $actiondata->timecreated = time();
        $actiondata->timemodified = time();

        if (!empty($actiondata)) {
            // Update the data structure before save.
            $actiondata = (array) $actiondata;

            // Add interval type to the data structure. helps to filter in schdule report.
            $actiondata['intervaltype'] = $actiondata['notifyinterval']['interval'] ?? schedule::INTERVALONCE;

            $this->update_data_structure($actiondata);

            try {
                $tablename = 'pulseaction_credits';
                if ($credits = $DB->get_record('pulseaction_credits', ['templateid' => $record->templateid])) {
                    $actiondata['id'] = $credits->id;
                    $actiondata['timecreated'] = $credits->timecreated;
                    $DB->update_record('pulseaction_credits', $actiondata);
                } else {
                    $DB->insert_record('pulseaction_credits', $actiondata);
                }

                // Update schedules for all instances of this template.
                $this->recreate_instance_schedules($record->templateid);
            } catch (\Exception $e) {
                throw new moodle_exception('actiondatanotsave', $component, null, $e->getMessage());
            }
        }

        return true;
    }

    /**
     * Save the submitted instance data for the credits action.
     *
     * @param int $instanceid
     * @param stdclass $record
     * @return bool
     */
    public function process_instance_save($instanceid, $record) {
        global $DB;

        // Filter the current action data from the instance data by its shortname.
        $record = (array) $record;
        $actiondata = (array) $this->filter_action_data($record);

        if ($this->get_data_fortemplate($record['templateid']) == null) {
            $this->create_empty_template($record['templateid']);
        }

        // Add interval type to the data structure. helps to filter in schdule report.
        $actiondata['intervaltype'] = $actiondata['notifyinterval']['interval'] ?? schedule::INTERVALONCE;

        // Update the data strucured before save.
        $this->update_data_structure($actiondata);
        $actiondata = (object) $actiondata;
        $actiondata->instanceid = $instanceid;
        $actiondata->timecreated = time();
        $actiondata->timemodified = time();

        try {
            $tablename = 'pulseaction_credits_ins';
            if (isset($instanceid) && $creditsinstance = $DB->get_record($tablename, ['instanceid' => $instanceid])) {
                $actiondata->id = $creditsinstance->id;
                $actiondata->timecreated = $creditsinstance->timecreated;

                $DB->update_record($tablename, $actiondata);
                $creditsinstanceid = $creditsinstance->id;
            } else {
                $creditsinstanceid = $DB->insert_record($tablename, $actiondata);
            }
            // Create schedules based on recipients.
            credits_schedule::instance($creditsinstanceid)->create_schedule_forinstance();
        } catch (\Exception $e) {
            throw new \moodle_exception('actiondatanotsave', 'pulseaction_credits');
        }

        return true;
    }

    /**
     * Recreate the schedules for credits instances when template is updated.
     *
     * @param int $templateid Updated template ID.
     *
     * @return void
     */
    protected function recreate_instance_schedules(int $templateid) {
        $instances = $this->get_template_instances($templateid);

        foreach ($instances as $instanceid => $instance) {
            $actioninstance = credits_schedule::create_from_templateinstance($instanceid);
            $actioninstance?->recreate_schedule_forinstance();
        }
    }
}
