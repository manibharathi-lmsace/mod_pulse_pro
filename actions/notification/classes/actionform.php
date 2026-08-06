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
 * Notification pulse action form.
 *
 * @package   pulseaction_notification
 * @copyright 2023, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulseaction_notification;

use html_writer;
use moodle_exception;
use DateTime;
use DatePeriod;
use mod_pulse\automation\helper;
use pulseaction_notification\notification;
use pulseaction_notification\schedule;
use pulseaction_notification\task\notify_users;

/**
 * Notification action form, contains important method and basic plugin details.
 */
class actionform extends \mod_pulse\automation\action_base {
    /**
     * Shortname for the config used in the form field.
     *
     * @return string
     */
    public function config_shortname() {
        return 'pulsenotification';
    }

    /**
     * Get the icon for this component, displayed on the instances list on the course autotemplates sections.
     *
     * @return string
     */
    public function get_action_icon() {
        global $OUTPUT;
        return $OUTPUT->pix_icon("i/notifications", get_string('notifications'));
    }

    /**
     * Get the report source for this action.
     *
     * @return string The report source class name.
     */
    public function get_report_source() {
        return '\pulseaction_notification\reportbuilder\datasource\notification';
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
        global $DB;

        // Check if there are no users for the recipient roles.
        $hasrecipienterror = $this->check_recipient_roles_have_users($row->id);

        if ($hasrecipienterror) {
            // Add warning icon before the report icon.
            $actions[] = [
                'url' => new \moodle_url('#'),
                'icon' => new \pix_icon('i/warning', \get_string('norecipientswarning', 'pulseaction_notification')),
                'attributes' => [
                    'class' => 'action-warning',
                    'id' => 'notification-action-warning',
                    'data-toggle' => 'tooltip',
                    'title' => \get_string('norecipientswarning', 'pulseaction_notification'),
                ],
            ];
        } else {
            $actions[] = [
                'url' => new \moodle_url($listurl, ['report' => 'notification']),
                'icon' => new \pix_icon('i/calendar', \get_string('instancereport', 'pulse')),
                'attributes' => ['class' => 'action-report', 'id' => 'notification-action-report', 'target' => '_blank'],
            ];
        }
    }

    /**
     * Check if recipient roles have users enrolled in the course.
     *
     * @param int $instanceid The automation instance ID.
     * @return bool True if there are no users for recipient roles, false otherwise.
     */
    protected function check_recipient_roles_have_users($instanceid) {
        global $DB;

        // Get the automation instance.
        $instance = $DB->get_record('pulse_autoinstances', ['id' => $instanceid]);
        if (!$instance) {
            return false;
        }

        // Get notification instance data.
        $notificationins = $DB->get_record('pulseaction_notification_ins', ['instanceid' => $instanceid]);
        if (!$notificationins) {
            // If no instance override, get template data.
            $templateid = $instance->templateid;
            $notificationdata = $DB->get_record('pulseaction_notification', ['templateid' => $templateid]);
            if (!$notificationdata) {
                return false;
            }
            $recipients = $notificationdata->recipients;
        } else {
            // Use instance override recipients if available.
            $recipients = $notificationins->recipients ?? null;
            if ($recipients === null) {
                // Fall back to template data.
                $templateid = $instance->templateid;
                $notificationdata = $DB->get_record('pulseaction_notification', ['templateid' => $templateid]);
                if (!$notificationdata) {
                    return false;
                }
                $recipients = $notificationdata->recipients;
            }
        }

        if (empty($recipients)) {
            return false;
        }

        // Decode recipients.
        $recipients = json_decode($recipients);
        if (empty($recipients)) {
            return false;
        }

        // Get course context.
        $context = \context_course::instance($instance->courseid);

        // Clean up custom mails and roles from recipient roles.
        $custommails = array_filter($recipients, fn($v) => validate_email($v));
        $roles = array_filter($recipients, fn($v) => is_number($v));

        // If only custom emails are set, no error.
        if (empty($roles) && !empty($custommails)) {
            return false;
        }

        // Check if there are any users with the recipient roles.
        if (!empty($roles)) {
            [$insql, $inparams] = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED, 'rle');

            $sql = "SELECT COUNT(DISTINCT ra.userid)
                      FROM {role_assignments} ra
                      JOIN {context} c ON ra.contextid = c.id
                     WHERE ra.roleid $insql
                       AND (c.id = :contextid
                            OR (c.contextlevel = :usercontext
                                AND c.instanceid IN (
                                    SELECT u.id
                                      FROM {user_enrolments} ue
                                      JOIN {enrol} e ON ue.enrolid = e.id
                                      JOIN {user} u ON ue.userid = u.id
                                     WHERE e.courseid = :courseid
                                       AND ue.status = 0
                                       AND u.deleted = 0
                                       AND u.suspended = 0
                                )
                            )
                       )";

            $params = array_merge($inparams, [
                'contextid' => $context->id,
                'usercontext' => CONTEXT_USER,
                'courseid' => $instance->courseid,
            ]);

            $usercount = $DB->count_records_sql($sql, $params);

            // If no users found for the roles, return true (there is an error).
            if ($usercount == 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delete notification instances and schedule data for this instance.
     *
     * @param int $instanceid Instance id
     * @return void
     */
    public function delete_instance_action(int $instanceid) {
        global $DB;

        parent::delete_instance_action($instanceid);
        $instancetable = 'pulseaction_notification_sch';
        return $DB->delete_records($instancetable, ['instanceid' => $instanceid]);
    }

    /**
     * Instances disabled, then disable all the schedules of the instances.
     *
     * @param stdclass $instancedata
     * @param bool $status
     * @return void
     */
    public function instance_status_updated($instancedata, $status) {
        global $DB;

        $notificationid = $instancedata->actions['notification']['id'];
        $notification = notification::instance($notificationid);
        $notification->set_notification_data($instancedata->actions['notification'], $instancedata);

        $notification->create_schedule_forinstance();
    }

    /**
     * Prepare editor fileareas.
     *
     * @param stdclass $data Instance/Templates data of the notification.
     * @param \context $context
     * @return void
     */
    public function prepare_editor_fileareas(&$data, \context $context) {

        $data = (object) ($data ?: ['id' => 0]); // Create empty data set if empty.

        $context = \context_system::instance();
        $templateid = $data->templateid ?? $data->id;

        // Support for the instance form. use the course context to prepare and update editor incase it's override in the instance.
        if (isset($data->courseid) && isset($data->instanceid)) {
            $prefix = '_instance';
        }
        // List of editors need to prepare for forms.
        $editor = [
            "pulsenotification_headercontent",
            "pulsenotification_staticcontent",
            "pulsenotification_footercontent",
        ];

        foreach ($editor as $field) {
            // Create empty data set for the new template.
            if (!isset($data->$field)) {
                $data->$field = '';
                $data->{$field . "format"} = editors_get_preferred_format();
            }

            $id = isset($data->instanceid) && isset($data->override[$field . '_editor']) ? $data->instanceid : $templateid;
            $filearea = isset($prefix) && isset($data->override[$field . '_editor']) ? $field . $prefix : $field;

            $data = file_prepare_standard_editor(
                $data,
                $field,
                $this->get_editor_options($context),
                $context,
                'mod_pulse',
                $filearea,
                $id
            );
        }
    }

    /**
     * Prepare editor fileareas.
     *
     * @param stdclass $data Instance/Templates data of the notification.
     * @param \context $context
     * @return void
     */
    public function postupdate_editor_fileareas(&$data, \context $context) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/pulse/lib/vars.php');
        $data = (object) $data;

        $context = \context_system::instance();
        $templateid = $data->templateid ?? $data->id;

        $editor = [
            "pulsenotification_headercontent",
            "pulsenotification_staticcontent",
            "pulsenotification_footercontent",
        ];

        // Use the prefix for the instance.
        if (isset($data->courseid) && isset($data->instanceid)) {
            $prefix = '_instance';
        }

        // Build decode pattern from the URL-type pulse placeholder names.
        $urlnames = array_map(function ($n) {
            return preg_quote($n, '/');
        }, \pulse_email_vars::url_placeholders());
        $urldecodepat = '/(%7B)(' . implode('|', $urlnames) . ')(%7D)/i';

        foreach ($editor as $field) {
            if (!isset($data->$field) && !isset($data->{$field . '_editor'})) {
                continue;
            }

            $id = $data->instanceid ?? $templateid;
            $filearea = isset($prefix) ? $field . $prefix : $field;

            if (!array_key_exists('text', $data->{$field . '_editor'})) {
                continue;
            }

            // Decode URL-encoded pulse placeholders in href attributes.
            $data->{$field . '_editor'}['text'] = preg_replace(
                $urldecodepat,
                '{$2}',
                $data->{$field . '_editor'}['text'] ?? ''
            );

            $data = file_postupdate_standard_editor(
                $data,
                $field,
                $this->get_editor_options($context),
                $context,
                'mod_pulse',
                $filearea,
                $id
            );
        }
    }

    /**
     * Duplicate fileares.
     *
     * @param int $oldinstanceid
     * @param int $newinstanceid
     *
     * @return void
     */
    public function duplicate_fileareas($oldinstanceid, $newinstanceid) {
        global $DB;

        $context = \context_system::instance();
        $fileareas = [
            "pulsenotification_headercontent",
            "pulsenotification_staticcontent",
            "pulsenotification_footercontent",
        ];

        $prefix = '_instance';

        $fs = get_file_storage();

        foreach ($fileareas as $field) {
            $filearea = $field . $prefix;
            $files = $fs->get_area_files(
                $context->id,
                'mod_pulse',
                $filearea,
                $oldinstanceid,
                'itemid, filepath, filename',
                false
            );
            if (empty($files)) {
                continue;
            }

            foreach ($files as $file) {
                $record = new \stdClass();
                $record->contextid = $file->get_contextid();
                $record->component = $file->get_component();
                $record->filearea = $file->get_filearea();
                $record->itemid = $newinstanceid;
                $record->filepath = $file->get_filepath();
                $record->filename = $file->get_filename();

                $fs->create_file_from_storedfile($record, $file);
            }
        }
    }

    /**
     * Get text editor options to manage files.
     *
     * @param \stdclass $context
     * @return array
     */
    protected function get_editor_options($context = null) {
        global $PAGE;

        return [
            'trusttext' => true,
            'subdirs' => true,
            'maxfiles' => 50,
            'context' => $context ?: $PAGE->context,
        ];
    }

    /**
     * Delete the notification instance.
     *
     * @param int $templateid
     * @return void
     */
    public function delete_template_action($templateid) {
        global $DB;

        $instances = $this->get_template_instances($templateid);
        // Remove its instances and schedules when the template is deleted.
        foreach ($instances as $instanceid => $instance) {
            $DB->delete_records('pulseaction_notification_ins', ['instanceid' => $instanceid]);
            $DB->delete_records('pulseaction_notification_sch', ['instanceid' => $instanceid]);
        }

        return $DB->delete_records('pulseaction_notification', ['templateid' => $templateid]);
    }

    /**
     * Action is triggered for the instance. when triggered notification will create a schedule for the triggered users.
     *
     * Create the notification instance and initiate the schedule for this instance.
     *
     * @param stdclass $instancedata
     * @param int $userid
     * @param int $expectedtime
     * @param bool $newuser
     * @param bool $sendscheduled Send the created scheduled notification immediately.
     *
     * @return void
     */
    public function trigger_action($instancedata, $userid, $expectedtime = null, $newuser = false, $sendscheduled = true) {

        if (!isset($instancedata->pulsenotification_id)) {
            return false;
        }

        $notification = notification::instance($instancedata->pulsenotification_id);
        $notificationinstance = (object) helper::filter_record_byprefix($instancedata, $this->config_shortname());

        $notification->set_notification_data($notificationinstance, $instancedata);

        $notification->create_schedule_forinstance($newuser, $userid ?: null, false);

        // Send the scheduled notifications for this user.
        if ($sendscheduled) {
            schedule::instance()->send_scheduled_notification($userid);
        }
    }

    /**
     * Remove the user schedules when the user is deleted.
     *
     * Observe the events, triggered from the main pulse.
     *
     * @param stdclass $instancedata Automation Instance data.
     * @param string $method Name of the triggered event.
     * @param stdclass $eventdata Triggered event data.
     *
     * @return void
     */
    public function trigger_action_event($instancedata, $method, $eventdata) {

        if ($method == 'user_enrolment_deleted') {
            $notificationid = $instancedata->actions['notification']['id'];
            $notification = notification::instance($notificationid);
            $notification->set_notification_data($instancedata->actions['notification'], $instancedata);
            $userid = $eventdata->relateduserid;

            // The instance's conditions have already been re-verified as no longer
            // satisfied (see instances::trigger_action_event()) - the queued
            // schedule is genuinely invalid now, so remove it.
            $notification->remove_user_schedules($userid);
        }
    }

    /**
     * Get the notification record to attach the template create form.
     *
     * @param int $templateid
     * @return stdclass
     */
    public function get_data_fortemplate($templateid) {
        global $DB;
        // Notification data for template.
        $actiondata = $DB->get_record('pulseaction_notification', ['templateid' => $templateid]);
        return $actiondata;
    }

    /**
     * Get the notification instance record.
     *
     * @param int $instanceid
     * @return stdclass Data of the notification instance.
     */
    public function get_data_forinstance($instanceid) {
        global $DB;

        $instancedata = $DB->get_record('pulseaction_notification_ins', ['instanceid' => $instanceid]);
        return $instancedata ?: [];
    }

    /**
     * Decode the json encoded notification data.
     *
     * @param array $actiondata
     * @return void
     */
    public function update_encode_data(&$actiondata) {

        $actiondata = (array) $actiondata;

        $actiondata['recipients'] = json_decode($actiondata['recipients']);
        $actiondata['bcc'] = json_decode($actiondata['bcc']);
        $actiondata['cc'] = json_decode($actiondata['cc']);
        $actiondata['suppress'] = isset($actiondata['suppress']) ? json_decode($actiondata['suppress']) : [];
        $actiondata['notifyinterval'] = $actiondata['notifyinterval'] ? json_decode($actiondata['notifyinterval'], true) : [];
    }

    /**
     * Encode the array fields to json type.
     *
     * @param array $actiondata
     * @return void
     */
    protected function update_data_structure(&$actiondata) {

        // Testing the action data.
        array_walk($actiondata, function (&$value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
        });
    }

    /**
     * Recreate the schedule for the notification instance, It mostly used when the template is updated.
     *
     * @param int $templateid Updated temmplate ID.
     *
     * @return void
     */
    protected function recreate_instance_schedules(int $templateid) {
        global $DB;

        $instances = $this->get_template_instances($templateid);

        foreach ($instances as $instanceid => $instance) {
            $notification = $DB->get_field('pulseaction_notification_ins', 'id', ['instanceid' => $instanceid]);
            if ($notification) {
                notification::instance($notification)->recreate_schedule_forinstance();
            }
        }
    }

    /**
     * Generate the warnings if the instance is not compatibile to send notifications.
     *
     * @param \stdclass $course
     * @return array
     */
    public function display_instance_warnings(\stdclass $course): array {

        // Course visibility warnings.
        if (!$course->visible) {
            $warning[] = get_string('coursehidden', 'pulseaction_notification');
        }

        // Course active users warnings.
        $coursecontext = \context_course::instance($course->id);
        if (!count_enrolled_users($coursecontext, '', null, true)) {
            $warning[] = get_string('noactiveusers', 'pulseaction_notification');
        }

        // Course is not started.
        if ($course->startdate > time()) {
            $warning[] = get_string('coursenotstarted', 'pulseaction_notification');
        }

        // Course is not started.
        if ($course->enddate && $course->enddate < time()) {
            $warning[] = get_string('courseenddatereached', 'pulseaction_notification');
        }

        return $warning ?? [];
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

        $context = \context_system::instance();

        $this->postupdate_editor_fileareas($record, $context);

        // Filter the current action data from the templates data by its shortname.
        $actiondata = $this->filter_action_data((array) $record);

        $actiondata->templateid = $record->templateid;

        if (!empty($actiondata)) {
            // Update the data strucured before save.
            $this->update_data_structure($actiondata);

            try {
                // In moodle, the main table should be the name of the component.
                // Therefore, generate the table name based on the component name.
                $tablename = 'pulseaction_' . $component;
                // Get the record by using the templateid, one instance is allowed for each template.
                // Manage the action component data for this template.
                if ($notification = $DB->get_record($tablename, ['templateid' => $record->templateid])) {
                    $actiondata->id = $notification->id;
                    // Update the latest data into the action component.
                    $DB->update_record($tablename, $actiondata);
                } else {
                    // Create new instance for this tempalte.
                    $DB->insert_record($tablename, $actiondata);
                }

                // Recreate the schedules for the instance.
                $templateid = $actiondata->templateid;
                $this->recreate_instance_schedules($templateid);
            } catch (\Exception $e) {
                // Throw  an error incase of issue with manage the data update.
                throw new \moodle_exception('actiondatanotsave', $component);
            }
        }
        return true;
    }

    /**
     * Save the submitted instance data for the notification action. Update the array values to json.
     * After insert/update the data to DB then trigger the notification schedule for the instance course.
     *
     * @param int $instanceid
     * @param stdclass $record
     * @return bool
     */
    public function process_instance_save($instanceid, $record) {
        global $DB;

        // Filter the current action data from the templates data by its shortname.
        $actiondata = $this->filter_action_data((array) $record);
        // Update the data strucured before save.
        $this->update_data_structure($actiondata);
        $actiondata->instanceid = $instanceid;

        try {
            // In moodle, the main table should be the name of the component.
            // Therefore, generate the table name based on the component name.
            $tablename = 'pulseaction_notification_ins';
            // Get the record by using the templateid, one instance is allowed for each template.
            // Manage the action component data for this template.
            if (isset($instanceid) && $notifyinstance = $DB->get_record($tablename, ['instanceid' => $instanceid])) {
                $actiondata->id = $notifyinstance->id;

                $notificationinstance = $actiondata->id;
                // Update the latest data into the action component.
                $DB->update_record($tablename, $actiondata);
            } else {
                // Create new instance for this tempalte.
                $notificationinstance = $DB->insert_record($tablename, $actiondata);
            }

            // Create schedules for users not yet scheduled; never restart frequency cycles on save.
            notification::instance($notificationinstance)->create_schedule_forinstance(false, null, false, true);
        } catch (\Exception $e) {
            // Throw  an error incase of issue with manage the data update.
            throw new \moodle_exception('actiondatanotsave', 'pulseaction_notification');
        }

        return true;
    }

    /**
     * Default override elements.
     *
     * @return array
     */
    public function default_override_elements() {
        // List of pulse notification elements those are available in only instances.
        return [
            'pulsenotification_suppress',
            'pulsenotification_suppressoperator',
            'pulsenotification_dynamiccontent',
            'pulsenotification_contenttype',
            'pulsenotification_chapterid',
            'pulsenotification_contentlength',
        ];
    }

    /**
     * Load the notification elements for the instance form.
     *
     * @param moodle_form $mform
     * @param actionform $forminstance
     * @return void
     */
    public function load_instance_form(&$mform, $forminstance) {
        global $CFG, $PAGE, $DB;

        require_once($CFG->dirroot . '/lib/modinfolib.php');

        $this->load_global_form($mform, $forminstance);

        // Dynamic Content Group
        // Add 'dynamic_content' element with all activities in the course.
        $courseid = $forminstance->get_customdata('courseid') ?? '';
        $modinfo = \course_modinfo::instance($courseid);

        // Include the suppress activity settings for the instance.
        $completion = new \completion_info(get_course($courseid));
        $activities = $completion->get_activities();
        array_walk($activities, function (&$value) {
            $value = format_string($value->name);
        });

        $suppress = $mform->createElement(
            'autocomplete',
            'pulsenotification_suppress',
            get_string('suppressmodule', 'pulseaction_notification'),
            $activities,
            ['multiple' => 'multiple']
        );

        $mform->insertElementBefore($suppress, 'pulsenotification_notifylimit');
        $mform->addHelpButton('pulsenotification_suppress', 'suppressmodule', 'pulseaction_notification');

        // Operator element.
        $operators = [
            \mod_pulse\automation\action_base::OPERATOR_ALL => get_string('all', 'pulse'),
            \mod_pulse\automation\action_base::OPERATOR_ANY => get_string('any', 'pulse'),
        ];
        $suppressopertor = $mform->createElement(
            'select',
            'pulsenotification_suppressoperator',
            get_string('suppressoperator', 'pulseaction_notification'),
            $operators
        );
        $mform->setDefault('suppressoperator', \mod_pulse\automation\action_base::OPERATOR_ANY);
        $mform->insertElementBefore($suppressopertor, 'pulsenotification_notifylimit');
        $mform->addHelpButton('pulsenotification_suppressoperator', 'suppressoperator', 'pulseaction_notification');

        $modules = [0 => get_string('none')];
        $list = $modinfo->get_instances();
        $contentmods = [];
        foreach ($list as $modname => $mods) {
            foreach ($mods as $mod) {
                $modules[$mod->id] = $mod->get_formatted_name();
                if ($mod->modname == 'page' || $mod->modname == 'book') {
                    $contentmods[] = $mod->id;
                }
            }
        }
        // PAGE modules in this course.
        $pages = $modinfo->get_instances_of('page');

        $dynamic = $mform->createElement(
            'select',
            'pulsenotification_dynamiccontent',
            get_string('dynamiccontent', 'pulseaction_notification'),
            $modules
        );
        $mform->insertElementBefore($dynamic, 'pulsenotification_footercontent_editor');
        $mform->addHelpButton('pulsenotification_dynamiccontent', 'dynamiccontent', 'pulseaction_notification');

        // Add 'content_type' element with the following options.
        $contenttypeoptions = [
            notification::DYNAMIC_PLACEHOLDER => get_string('dynamicplacholder', 'pulseaction_notification'),
            notification::DYNAMIC_DESCRIPTION => get_string('dynamicdescription', 'pulseaction_notification'),
            notification::DYNAMIC_CONTENT => get_string('dynamiccontent', 'pulseaction_notification'),
        ];
        $dynamic2 = $mform->createElement(
            'select',
            'pulsenotification_contenttype',
            get_string('contenttype', 'pulseaction_notification'),
            $contenttypeoptions
        );
        $mform->insertElementBefore($dynamic2, 'pulsenotification_footercontent_editor');
        $mform->hideIf('pulsenotification_contenttype', 'pulsenotification_dynamiccontent', 'eq', 0);
        $mform->addHelpButton('pulsenotification_contenttype', 'contenttype', 'pulseaction_notification');

        // Load Chapters for selected book.
        $instanceid = $forminstance->get_customdata('instanceid');
        $cmid = $instanceid ? $DB->get_field('pulseaction_notification_ins', 'dynamiccontent', ['instanceid' => $instanceid]) : '';
        if (!empty($cmid)) {
            $sql = 'SELECT bc.id, bc.title FROM {course_modules} cm
            JOIN {book_chapters} bc ON bc.bookid = cm.instance
            WHERE cm.id = :cmid';
            $chapters = $DB->get_records_sql_menu($sql, ['cmid' => $cmid]);
        }
        $options['ajax'] = 'pulseaction_notification/chaptersource';
        $chapter = $mform->createElement(
            'autocomplete',
            'pulsenotification_chapterid',
            get_string('chapters', 'pulseaction_notification'),
            $chapters ?? [],
            $options
        );
        $mform->insertElementBefore($chapter, 'pulsenotification_footercontent_editor');
        $mform->addHelpButton('pulsenotification_chapterid', 'chapters', 'pulseaction_notification');
        $mform->hideIf('pulsenotification_chapterid', 'pulsenotification_dynamiccontent', 'eq', 0);
        foreach ($pages as $page) {
            $mform->hideIf('pulsenotification_chapterid', 'pulsenotification_dynamiccontent', 'eq', $page->id);
        }

        // Content Length Group.
        $contentlengthoptions = [
            notification::LENGTH_TEASER => get_string('teaser', 'pulseaction_notification'),
            notification::LENGTH_LINKED => get_string('full_linked', 'pulseaction_notification'),
            notification::LENGTH_NOTLINKED => get_string('full_not_linked', 'pulseaction_notification'),
        ];
        $dynamic3 = $mform->createElement(
            'select',
            'pulsenotification_contentlength',
            get_string('contentlength', 'pulseaction_notification'),
            $contentlengthoptions
        );
        $mform->insertElementBefore($dynamic3, 'pulsenotification_footercontent_editor');
        $mform->addHelpButton('pulsenotification_contentlength', 'contentlength', 'pulseaction_notification');

        $mform->hideIf('pulsenotification_contentlength', 'pulsenotification_dynamiccontent', 'eq', 0);

        asort($mform->_elementIndex);

        $PAGE->requires->js_call_amd(
            'pulseaction_notification/chaptersource',
            'updateChapter',
            ['contextid' => $PAGE->context->id, 'contentmods' => $contentmods]
        );
    }

    /**
     * Global form elements for notification action.
     *
     * @param moodle_form $mform
     * @param \automation_instance_form $forminstance
     * @return void
     */
    public function load_global_form(&$mform, $forminstance) {
        global $CFG, $PAGE;

        // Custom duration form.
        \MoodleQuickForm::registerElementType(
            'pulseactionduration',
            $CFG->dirroot . '/mod/pulse/actions/notification/forms/duration.php',
            'moodlequickform_pulseactionduration'
        );

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/pulse/lib.php');

        // Sender Group.
        $senderoptions = [
            notification::SENDERCOURSETEACHER => get_string('courseteacher', 'pulseaction_notification'),
            notification::SENDERGROUPTEACHER => get_string('groupteacher', 'pulseaction_notification'),
            notification::SENDERCUSTOM => get_string('custom', 'pulseaction_notification'),
        ];
        $mform->addElement('select', 'pulsenotification_sender', get_string('sender', 'pulseaction_notification'), $senderoptions);
        $mform->addHelpButton('pulsenotification_sender', 'sender', 'pulseaction_notification');

        // Add additional settings for the 'custom' option, if selected.
        $mform->addElement('text', 'pulsenotification_senderemail', get_string('senderemail', 'pulseaction_notification'));
        $mform->setType('pulsenotification_senderemail', PARAM_EMAIL);
        $mform->hideIf('pulsenotification_senderemail', 'pulsenotification_sender', 'neq', notification::SENDERCUSTOM);

        $interval = [];
        // Schedule Group.
        $intervaloptions = [
            notification::INTERVALONCE => get_string('once', 'pulseaction_notification'),
            notification::INTERVALDAILY => get_string('daily', 'pulseaction_notification'),
            notification::INTERVALWEEKLY => get_string('weekly', 'pulseaction_notification'),
            notification::INTERVALMONTHLY => get_string('monthly', 'pulseaction_notification'),
        ];
        $interval[] =& $mform->createElement(
            'select',
            'pulsenotification_notifyinterval[interval]',
            get_string('interval', 'pulseaction_notification'),
            $intervaloptions
        );

        // Add additional settings based on the selected interval.
        $dayweeks = [
            'monday' => get_string('monday', 'pulseaction_notification'),
            'tuesday' => get_string('tuesday', 'pulseaction_notification'),
            'wednesday' => get_string('wednesday', 'pulseaction_notification'),
            'thursday' => get_string('thursday', 'pulseaction_notification'),
            'friday' => get_string('friday', 'pulseaction_notification'),
            'saturday' => get_string('saturday', 'pulseaction_notification'),
            'sunday' => get_string('sunday', 'pulseaction_notification'),
        ];

        // Add 'day_of_week' element if 'weekly' is selected in the 'interval' element.
        $interval[] =& $mform->createElement(
            'select',
            'pulsenotification_notifyinterval[weekday]',
            get_string('interval', 'pulseaction_notification'),
            $dayweeks
        );
        $mform->hideIf(
            'pulsenotification_notifyinterval[weekday]',
            'pulsenotification_notifyinterval[interval]',
            'neq',
            notification::INTERVALWEEKLY
        );

        $dates = range(1, 31);
        // Add 'day_of_month' element if 'monthly' is selected in the 'interval' element.
        $interval[] =& $mform->createElement(
            'select',
            'pulsenotification_notifyinterval[monthdate]',
            get_string('interval', 'pulseaction_notification'),
            $dates
        );
        $mform->hideIf(
            'pulsenotification_notifyinterval[monthdate]',
            'pulsenotification_notifyinterval[interval]',
            'neq',
            notification::INTERVALMONTHLY
        );

        // Time to send notification.
        $dates = $this->get_times();
        // Add 'time_of_day' element.
        // Add 'day_of_month' element if 'monthly' is selected in the 'interval' element.
        $interval[] =& $mform->createElement(
            'select',
            'pulsenotification_notifyinterval[time]',
            get_string('interval', 'pulseaction_notification'),
            $dates
        );
        $mform->hideIf(
            'pulsenotification_notifyinterval[time]',
            'pulsenotification_notifyinterval[interval]',
            'eq',
            notification::INTERVALONCE
        );

        // Notification interval button groups.
        $mform->addGroup(
            $interval,
            'pulsenotification_notifyinterval',
            get_string('interval', 'pulseaction_notification'),
            [' '],
            false
        );
        $mform->addHelpButton('pulsenotification_notifyinterval', 'interval', 'pulseaction_notification');

        // Notification delay.
        $delayoptions = [
            notification::DELAYNONE => get_string('none', 'pulseaction_notification'),
            notification::DELAYBEFORE => get_string('before', 'pulseaction_notification'),
            notification::DELAYAFTER => get_string('after', 'pulseaction_notification'),
        ];
        $mform->addElement(
            'select',
            'pulsenotification_notifydelay',
            get_string('delay', 'pulseaction_notification'),
            $delayoptions
        );
        $mform->setDefault('delay', 'none');
        $mform->addHelpButton('pulsenotification_notifydelay', 'delay', 'pulseaction_notification');

        // Delay duration.
        $mform->addElement(
            'pulseactionduration',
            'pulsenotification_delayduration',
            get_string('delayduraion', 'pulseaction_notification')
        );
        $mform->hideIf('pulsenotification_delayduration', 'pulsenotification_notifydelay', 'eq', notification::DELAYNONE);
        $mform->addHelpButton('pulsenotification_delayduration', 'delayduraion', 'pulseaction_notification');

        // Delay base - reference point for delay calculation.
        $delaybaseoptions = [
            notification::DELAYBASE_INSTANCE => get_string('delaybaseinstance', 'pulseaction_notification'),
            notification::DELAYBASE_ENROLMENT => get_string('delaybaseenrolment', 'pulseaction_notification'),
            notification::DELAYBASE_LASTCONDITION => get_string('delaybaselastcondition', 'pulseaction_notification'),
        ];
        $mform->addElement(
            'select',
            'pulsenotification_delaybase',
            get_string('delaybase', 'pulseaction_notification'),
            $delaybaseoptions
        );
        $mform->setDefault('pulsenotification_delaybase', notification::DELAYBASE_INSTANCE);
        $mform->hideIf('pulsenotification_delaybase', 'pulsenotification_notifydelay', 'eq', notification::DELAYNONE);
        $mform->addHelpButton('pulsenotification_delaybase', 'delaybase', 'pulseaction_notification');

        // Limit no of notifications Group.
        $mform->addElement('text', 'pulsenotification_notifylimit', get_string('limit', 'pulseaction_notification'));
        $mform->setType('pulsenotification_notifylimit', PARAM_INT);
        $mform->addHelpButton('pulsenotification_notifylimit', 'limit', 'pulseaction_notification');
        $mform->hideIf(
            'pulsenotification_notifylimit',
            'pulsenotification_notifyinterval[interval]',
            'eq',
            notification::INTERVALONCE
        );

        // Supporess by course completion.
        $options = [
            0 => get_string('no'),
            notification::SUPPRESSCOURSECOMPLETION => get_string('yes'),
        ];
        $mform->addElement(
            'select',
            'pulsenotification_suppresscourse',
            get_string('coursecompletionsuppress', 'pulseaction_notification'),
            $options
        );

        // Custom email.
        $extracustomemail = \pulseaction_notification\local\custom_mail::instance()->get_custom_recipients();

        // Recipients Group.
        // Add 'recipients' element with all roles that can receive notifications.
        $mform->addElement(
            'autocomplete',
            'pulsenotification_recipients',
            get_string('recipients', 'pulseaction_notification'),
            $forminstance->recipients_roles() + $extracustomemail,
            ['multiple' => 'multiple']
        );
        $mform->addHelpButton('pulsenotification_recipients', 'recipients', 'pulseaction_notification');

        // CC Group.
        // Add 'cc' element with all course context and user context roles.
        $courseroles = $forminstance->course_roles();
        // Inlcude custom emails too.
        $recipients = $courseroles + $extracustomemail;
        $mform->addElement(
            'autocomplete',
            'pulsenotification_cc',
            get_string('ccrecipients', 'pulseaction_notification'),
            $recipients,
            ['multiple' => 'multiple']
        );
        $mform->addHelpButton('pulsenotification_cc', 'ccrecipients', 'pulseaction_notification');

        // Set BCC.
        $mform->addElement(
            'autocomplete',
            'pulsenotification_bcc',
            get_string('bccrecipients', 'pulseaction_notification'),
            $recipients,
            ['multiple' => 'multiple']
        );
        $mform->addHelpButton('pulsenotification_bcc', 'bccrecipients', 'pulseaction_notification');

        // Subject.
        $mform->addElement(
            'text',
            'pulsenotification_subject',
            get_string('subject', 'pulseaction_notification'),
            ['size' => 100]
        );
        $mform->setType('pulsenotification_subject', PARAM_TEXT);
        $mform->addHelpButton('pulsenotification_subject', 'subject', 'pulseaction_notification');
        $mform->addRule(
            'pulsenotification_subject',
            get_string('maximumchars', 'pulseaction_notification', 1333),
            'maxlength',
            1333,
            'client'
        );

        $context = \context_system::instance();
        $mform->addElement(
            'editor',
            'pulsenotification_headercontent_editor',
            get_string('headercontent', 'pulseaction_notification'),
            ['class' => 'fitem_id_templatevars_editor'],
            $this->get_editor_options($context)
        );
        $mform->addHelpButton('pulsenotification_headercontent_editor', 'headercontent', 'pulseaction_notification');
        $placeholders = pulse_email_placeholders('header', true);
        $mform->addElement('html', $placeholders);

        // Statecontent editor.
        $mform->addElement(
            'editor',
            'pulsenotification_staticcontent_editor',
            get_string('staticcontent', 'pulseaction_notification'),
            ['class' => 'fitem_id_templatevars_editor'],
            $this->get_editor_options($context)
        );
        $mform->addHelpButton('pulsenotification_staticcontent_editor', 'staticcontent', 'pulseaction_notification');
        $placeholders = pulse_email_placeholders('static', true);
        $mform->addElement('html', $placeholders);

        // Footer Content.
        $mform->addElement(
            'editor',
            'pulsenotification_footercontent_editor',
            get_string('footercontent', 'pulseaction_notification'),
            ['class' => 'fitem_id_templatevars_editor'],
            $this->get_editor_options($context)
        );
        $mform->addHelpButton('pulsenotification_footercontent_editor', 'footercontent', 'pulseaction_notification');
        $placeholders = pulse_email_placeholders('footer', true);
        $mform->addElement('html', $placeholders);

        // Preview Button.
        $test = $mform->addElement('button', 'pulsenotification_preview', get_string('preview', 'pulseaction_notification'));
        $mform->addHelpButton('pulsenotification_preview', 'preview', 'pulseaction_notification');

        // Email tempalte placholders.
        $contextid = $PAGE->context->id;

        $PAGE->requires->js_call_amd('pulseaction_notification/chaptersource', 'previewNotification', ['contextid' => $contextid]);
        $PAGE->requires->js_call_amd('pulseaction_notification/chaptersource', 'toogleEmailVarsVisibility', [
            'actionstatus' => '#id_pulsenotification_actionstatus']);
    }


    /**
     * Get list of options in 30 mins timeinterval for 24 hrs.
     *
     * @return array
     */
    public function get_times() {

        $starttime = new DateTime('00:00'); // Set the start time to midnight.
        $endtime = new DateTime('23:59');   // Set the end time to just before midnight of the next day.

        // Create an interval of 30 minutes.
        $interval = new \DateInterval('PT30M'); // PT30M represents 30 minutes.

        // Create a DatePeriod to iterate through the day with the specified interval.
        $timeperiod = new DatePeriod($starttime, $interval, $endtime);

        // Loop through the DatePeriod and add each timestamp to the array.
        $timelist = [];
        foreach ($timeperiod as $time) {
            $timelist[$time->format('H:i')] = $time->format('H:i'); // Format the timestamp as HH:MM.
        }

        return $timelist;
    }

    /**
     * Get a email placeholders data fields.
     *
     * @return array
     */
    public function get_email_placeholders() {

        $result = [
            'Assignment' => $this->assignment_data_fields(),
        ];

        return $result;
    }

    /**
     * Assignment extension data fields.
     *
     * @return array
     */
    public function assignment_data_fields() {
        return ['Assignment_Extensions', 'Assignment_Feedback', 'Assignment_Grade'];
    }

    /**
     * Get the assignment extenstion placeholders values.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @return array
     */
    public function get_assignment_extension($courseid, $userid) {
        global $DB, $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        // Get only assignments from the course of the instance shall be included.
        $assignments = $DB->get_records('assign', ['course' => $courseid]);
        $extension = '';

        // Get a course completeion.
        $completion = new \completion_info(get_course($courseid));

        foreach ($assignments as $assignment) {
            // Get the assignments only not completed by the user.
            if (!$cm = get_coursemodule_from_instance('assign', $assignment->id)) {
                continue;
            }
            $cminfo = \cm_info::create($cm, $userid);
            $assigncompletion = $completion->get_data($cminfo, true, $userid);

            if ($assigncompletion->completionstate != COMPLETION_COMPLETE) {
                // Get only assignments where the assignment submission deadline was extended.
                if ($DB->record_exists('assign_user_flags', ['assignment' => $assignment->id, 'userid' => $userid])) {
                    $extensionassignments = $DB->get_records('assign_user_flags', [
                        'assignment' => $assignment->id, 'userid' => $userid,
                    ]);
                    foreach ($extensionassignments as $extensionassign) {
                        if ($extensionassign->extensionduedate != 0) {
                            $extensionduedate = !empty($extensionassign->extensionduedate) ?
                                userdate($extensionassign->extensionduedate) : '';
                            $extension .= $assignment->name . ": " . $extensionduedate . '(' .
                                get_string('previously', 'pulse') . ': ' . userdate($assignment->duedate) . ')' . "<br>";
                        }
                    }
                }
            }
        }

        return (object) [
            'extensions' => (!empty($extension)) ? $extension : get_string('noextensiongranted', 'pulse'),
            'feedback' => function ($mod, $user, $course): string|null {
                return \pulseaction_notification\actionform::get_assignment_feedback($mod, $user, $course);
            },
            'grade' => function ($mod, $user, $course): string|null {
                return \pulseaction_notification\actionform::get_assignment_grade($mod, $user, $course);
            },
        ];
    }

    /**
     * Get the assignment feedback for the user.
     *
     * @param course_module $mod
     * @param stdclass $user
     * @param stdclass $course
     * @return string|null
     */
    public static function get_assignment_feedback($mod, $user, $course): string|null {
        global $CFG;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        if (empty($mod->id) || (!empty($mod->type) && $mod->type !== 'assign')) {
            return '';
        }

        // Create a course module.
        $cm = clone($mod);
        $cm->id = $mod->cmid;

        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, $course);
        $grade = $assign->get_user_grade($user->id, false);
        if ($grade === false) {
            return '';
        }
        $feedback = '';
        foreach ($assign->get_feedback_plugins() as $feedbackplugin) {
            if ($feedbackplugin->is_empty($grade)) {
                continue;
            }
            $feedback .= $feedbackplugin->view($grade);
        }
        return $feedback;
    }

    /**
     * Get the assignment grade for the user.
     *
     * @param course_module $mod
     * @param stdclass $user
     * @param stdclass $course
     * @return string|null
     */
    public static function get_assignment_grade($mod, $user, $course): string|null {
        global $CFG;

        require_once($CFG->dirroot . '/lib/grade/grade_grade.php');

        try {
            if (empty($mod->id) || (!empty($mod->type) && $mod->type !== 'assign')) {
                return null;
            }
            // Create a course module.
            $cm = clone($mod);
            $cm->id = $mod->cmid;

            $assign = new \assign(null, $cm, $course);
            $gradeitem = $assign->get_grade_item();
            $grade = \grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $user->id]);
            if ($grade === false) {
                return null;
            }

            return $grade->finalgrade !== null ? format_float($grade->finalgrade, $gradeitem->get_decimals()) : '';
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Recompletion reset for pulse action.
     *
     * @param int $userid User ID.
     * @param stdclass $course Course object.
     * @param stdclass $config Recompletion config.
     * @return void
     *
     */
    public function local_recompletion_reset($userid, $course, $config) {
        global $DB;

        // Delete pulse action notification schedules for the user in the course.
        $selectsql = "instanceid IN (
                        SELECT pan.id
                          FROM {pulse_autoinstances} pan
                         WHERE pan.courseid = :courseid
                    ) AND (userid=:userid OR relateduserid=:relateduserid)";

        $params = ['courseid' => $course->id, 'userid' => $userid, 'relateduserid' => $userid];

        // Delete the records.
        $DB->delete_records_select('pulseaction_notification_sch', $selectsql, $params);
    }

    /**
     * Recompletion reset event to retrigger the notifications.
     *
     * @param int $userid User ID.
     * @param stdclass $course Course object.
     * @return void
     */
    public function local_recompletion_rest_event($userid, $course) {
        global $DB;

        $params = ['courseid' => $course->id, 'userid' => $userid, 'relateduserid' => $userid];
        // Retrigger the notifications for the user in the course.
        $instancesql = 'instanceid IN (
                        SELECT pan.id
                          FROM {pulse_autoinstances} pan
                         WHERE pan.courseid = :courseid
                    )';

        // Fetch the notification instances in the course.
        $notifications = $DB->get_records_select('pulseaction_notification_ins', $instancesql, $params);

        foreach ($notifications as $notification) {
            $pulseinstance = \mod_pulse\automation\instances::create($notification->instanceid);
            $instancedata = (object) $pulseinstance->get_instance_formdata();
            if (empty($instancedata->pulsenotification_actionstatus)) {
                continue;
            }
            $this->trigger_action($instancedata, $userid, null, false, false);
        }
    }
}
