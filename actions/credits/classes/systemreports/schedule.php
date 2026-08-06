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
 * Credits schedule system report
 *
 * @package   pulseaction_credits
 * @copyright 2025 bdecent GmbH <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulseaction_credits\systemreports;

use context_course;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\local\report\{action, column};
use core_reportbuilder\system_report;
use pulseaction_credits\local\entities\creditallocation;
use lang_string;
use mod_pulse\local\entities\automation_instance;
use moodle_url;
use pix_icon;
use stdClass;

/**
 * Credits schedule system report class.
 */
class schedule extends system_report {
    /** @var string Alias for override table */
    public $overridealias;

    /**
     * Report initialisation
     */
    protected function initialise(): void {
        global $PAGE;
        // Use creditallocation entity as main table.
        $entitymain = new creditallocation();
        $entitymainalias = $entitymain->get_table_alias('pulseaction_credits_sch');

        // Set main table using entity alias.
        $this->set_main_table('pulseaction_credits_sch', $entitymainalias);
        $this->add_entity($entitymain);

        // Base fields.
        $this->add_base_fields("{$entitymainalias}.id, {$entitymainalias}.userid, " .
                              "{$entitymainalias}.credits, {$entitymainalias}.scheduletime, " .
                              "{$entitymainalias}.status, {$entitymainalias}.timecreated, {$entitymainalias}.instanceid");

        $autoinstance = new \mod_pulse\local\entities\automation_instance();
        $autoinstancealias = $autoinstance->get_table_alias('pulse_autoinstances');
        $this->add_join(
            "JOIN {pulse_autoinstances} {$autoinstancealias} ON {$autoinstancealias}.id = {$entitymainalias}.instanceid"
        );

        $autotemplate = new \mod_pulse\local\entities\automation_template();
        $autotemplatealias = $autotemplate->get_table_alias('pulse_autotemplates');

        $this->add_join(
            "JOIN {pulse_autotemplates} {$autotemplatealias} ON {$autotemplatealias}.id = {$autoinstancealias}.templateid"
        );

        // Only show records for a specific course if provided.
        $courseid = $this->get_parameter('courseid', 0, PARAM_INT);
        if ($courseid > 0) {
            $this->add_base_condition_simple("{$autoinstancealias}.courseid", $courseid);
        }

        // Add core automation instance entity.
        $instanceentity = new automation_instance();
        $instancealias = $instanceentity->get_table_alias('pulse_autoinstances');
        $instancejoin = "JOIN {pulse_autoinstances} {$instancealias} ON {$instancealias}.id = {$entitymainalias}.instanceid";
        $this->add_entity($instanceentity->add_join($instancejoin));
        $this->add_join($instancejoin);
        $this->add_join($instanceentity->get_instance_join($instancealias));

        // Join user entity.
        $entityuser = new user();
        $entityuseralias = $entityuser->get_table_alias('user');

        $this->add_entity(
            $entityuser->add_join("JOIN {user} {$entityuseralias} ON {$entityuseralias}.id = {$entitymainalias}.userid")
        );

        // Join course entity.
        $entitycourse = new course();
        $entitycoursealias = $entitycourse->get_table_alias('course');
        $entitycourse->add_join("JOIN {course} {$entitycoursealias} ON {$entitycoursealias}.id = {$autoinstancealias}.courseid");
        $this->add_entity($entitycourse);

        // Left join override table.
        $overridealias = $entitymain->get_table_alias('pulseaction_credits_override');
        $this->overridealias = $overridealias;

        $this->add_join(
            "LEFT JOIN {pulseaction_credits_override} {$overridealias} ON {$overridealias}.scheduleid = {$entitymainalias}.id"
        );

        $this->add_columns();
        $this->add_filters();
        $this->add_actions();

        $this->set_downloadable(true);

        // Inline the inplace editable JS.
        // During the pagination, the JS file is not loaded properly.
        $PAGE->requires->js_amd_inline("require(['core/inplace_editable']);");
    }

    /**
     * Report access.
     *
     * @return bool
     */
    protected function can_view(): bool {

        $courseid = $this->get_parameter('courseid', 0, PARAM_INT);
        if ($courseid > 0) {
            $context = context_course::instance($courseid);
            return has_capability('pulseaction/credits:manage', $context);
        }

        return has_capability('pulseaction/credits:manage', \context_system::instance());
    }

    /**
     * Report columns.
     *
     * @return void
     */
    protected function add_columns(): void {
        // User fullname with link.
        $this->add_column_from_entity('user:fullnamewithlink');

        // User email.
        $this->add_column_from_entity('user:email');

        // Course fullname with link.
        $this->add_column_from_entity('course:coursefullnamewithlink');

        // Use columns from creditallocation entity.
        $this->add_column_from_entity('creditallocation:scheduletime');
        $this->add_column_from_entity('creditallocation:allocatedtime');
        $this->add_column_from_entity('creditallocation:status');

        // Add automation reference column.
        $this->add_column_from_entity('automation_instance:reference');

        $entitymainalias = $this->get_main_table_alias();
        $overridealias = $this->overridealias;

        // Original schduled credits.
        $this->add_column((new column(
            'orgcredits',
            new lang_string('scheduledcredits', 'pulseaction_credits'),
            'creditallocation',
        ))
            ->add_fields("{$entitymainalias}.credits, {$overridealias}.scheduledcredit")
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_joins($this->get_joins())
            ->set_callback(static function ($value, stdClass $row): string {
                return $row->scheduledcredit ? $row->scheduledcredit : $row->credits;
            }));

        // Override credits editable, add directly with override table fields.
        $this->add_column((new column(
            'overridecredit',
            new lang_string('overridecredit', 'pulseaction_credits'),
            'creditallocation',
        ))
            ->add_fields("{$entitymainalias}.id, {$entitymainalias}.status, " .
                        "{$entitymainalias}.credits, {$overridealias}.overridecredit, {$overridealias}.id as overrideid")
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_joins($this->get_joins())
            ->set_callback(static function ($value, stdClass $row): string {
                global $PAGE;
                $editable = new \pulseaction_credits\output\override_credit($row);
                return $editable->render($PAGE->get_renderer('core'));
            }));

        // Add override status column.
        $this->add_column((new column(
            'overridestatus',
            new lang_string('overridestatus', 'pulseaction_credits'),
            'creditallocation',
        ))
            ->add_fields("{$overridealias}.status as overridestatus, {$overridealias}.timecreated as overridetimecreated")
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(false)
            ->set_callback(static function ($value, stdClass $row): string {
                if (!empty($row->overridestatus)) {
                    return get_string('overridden', 'pulseaction_credits') . ' (' . userdate($row->overridetimecreated) . ')';
                }
                return '-';
            }));

        // Add current user credits column.
        $this->add_column((new column(
            'currentcredits',
            new lang_string('currentcredits', 'pulseaction_credits'),
            'creditallocation',
        ))
            ->add_fields("{$entitymainalias}.userid")
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(false)
            ->set_callback(static function ($value, stdClass $row): string {
                $creditsobj = new \pulseaction_credits\local\credits();
                $currentcredits = $creditsobj->get_user_credits((int) $row->userid);
                return number_format($currentcredits, 2);
            }));

        $this->set_initial_sort_column('creditallocation:scheduletime', SORT_ASC);
    }

    /**
     * Report filters
     */
    protected function add_filters(): void {
        $this->add_filters_from_entities([
            'user:fullname',
            'user:email',
            'course:fullname',
            'creditallocation:scheduletime',
            'creditallocation:timecreated',
            'creditallocation:status',
            'creditallocation:credits',
            'creditallocation:allocationmethod',
            'automation_instance:reference',
        ]);
    }

    /**
     * Report actions
     */
    protected function add_actions(): void {

        // Edit user credits action - available for all records.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('i/edit', ''),
            [
                'data-action' => 'edit-user-credits',
                'data-userid' => ':userid',
                'data-courseid' => $this->get_parameter('courseid', 0, PARAM_INT),
                'data-scheduleid' => ':id',
            ],
            false,
            new lang_string('editusercredits', 'pulseaction_credits'),
        ))
            ->add_callback(static function (stdClass $schedule): bool {
                // Available for all records.
                return true;
            }));
    }
}
