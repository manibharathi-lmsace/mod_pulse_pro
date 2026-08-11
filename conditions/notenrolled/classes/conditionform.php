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
 * Conditions - Pulse condition class for "Not enrolled".
 *
 * @package   pulsecondition_notenrolled
 * @copyright 2026, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulsecondition_notenrolled;

use context_system;
use mod_pulse\automation\condition_base;

/**
 * Automation "Not enrolled" condition form.
 *
 * Triggers for users who have no active course enrolment (optionally only for users whose
 * account is older than a configured window). The recipient is typically the user themselves,
 * so the notification action must be configured with the "triggering user" recipient and the
 * instance anchored to the site course (these users have no course role and no enrolment).
 */
class conditionform extends \mod_pulse\automation\condition_base {
    /**
     * Scope: user must have no active enrolment in any course.
     * @var int
     */
    const SCOPE_ANY = 1;

    /**
     * Scope: user must have no active enrolment in the configured course(s).
     * @var int
     */
    const SCOPE_SPECIFIC = 2;

    /**
     * Whether the current user may configure this condition.
     *
     * Restricted to site-level managers/admins so a single-course teacher cannot use it to
     * notify the whole site.
     *
     * @return bool
     */
    protected function can_manage() {
        return has_capability('pulsecondition/notenrolled:manage', context_system::instance());
    }

    /**
     * Include condition (only for users with the site-level capability).
     *
     * @param array $option
     * @return void
     */
    public function include_condition(&$option) {
        if (!$this->can_manage()) {
            return;
        }
        $option['notenrolled'] = get_string('condition', 'pulsecondition_notenrolled');
    }

    /**
     * This condition is not driven by course enrolment events. It is evaluated for users who
     * have no enrolment, so the enrolment-based FUTURE handling must be skipped.
     *
     * @return bool
     */
    public function is_user_enrolment_based() {
        return false;
    }

    /**
     * This condition supports the delay-base "last condition" feature.
     *
     * @return bool
     */
    public function delay_support_plugins() {
        return true;
    }

    /**
     * The notification recipients for this condition's instances are not course-enrolled, so
     * the enrolment/visibility gate in the send query must be relaxed for these instances.
     *
     * @return bool
     */
    public function schedule_skip_enrolment_gate() {
        return true;
    }

    /**
     * The condition is "satisfied" from the moment the user signed up. Combined with the
     * notification Delay base = "Last condition", this lets the admin send e.g. 1 week after
     * signup. In combos under the ALL operator the more recent condition time dominates.
     *
     * @param int $userid The user ID.
     * @param object $instancedata The automation instance data.
     * @return int The Unix timestamp the condition was satisfied.
     */
    public function get_condition_satisfied_time(int $userid, $instancedata) {
        global $DB;
        $timecreated = $DB->get_field('user', 'timecreated', ['id' => $userid]);
        return $timecreated ? (int) $timecreated : time();
    }

    /**
     * Loads the form elements for the template.
     *
     * @param \MoodleQuickForm $mform The form object.
     * @param object $forminstance The form instance.
     */
    public function load_template_form(&$mform, $forminstance) {
        $this->load_form($mform, $forminstance);
    }

    /**
     * Loads the form elements for the instance.
     *
     * @param \MoodleQuickForm $mform The form object.
     * @param object $forminstance The form instance.
     */
    public function load_instance_form(&$mform, $forminstance) {
        $this->load_form($mform, $forminstance);
    }

    /**
     * Build the shared form elements. Capability gated as defence in depth.
     *
     * @param \MoodleQuickForm $mform The form object.
     * @param object $forminstance The form instance.
     */
    protected function load_form(&$mform, $forminstance) {
        global $DB;

        if (!$this->can_manage()) {
            return;
        }

        $conditionstr = get_string('condition', 'pulsecondition_notenrolled');
        $mform->addElement('select', 'condition[notenrolled][status]', $conditionstr, $this->get_options());
        $mform->addHelpButton('condition[notenrolled][status]', 'condition', 'pulsecondition_notenrolled');

        // Enrolment scope.
        $scopeoptions = [
            self::SCOPE_ANY => get_string('scopeany', 'pulsecondition_notenrolled'),
            self::SCOPE_SPECIFIC => get_string('scopespecific', 'pulsecondition_notenrolled'),
        ];
        $mform->addElement(
            'select',
            'condition[notenrolled][scope]',
            get_string('scope', 'pulsecondition_notenrolled'),
            $scopeoptions
        );
        $mform->addHelpButton('condition[notenrolled][scope]', 'scope', 'pulsecondition_notenrolled');
        $mform->hideIf('condition[notenrolled][scope]', 'condition[notenrolled][status]', 'eq', self::DISABLED);

        // Specific course(s) the user must not be enrolled in.
        $courses = $DB->get_records_select_menu(
            'course',
            'id <> :siteid',
            ['siteid' => SITEID],
            'fullname ASC',
            'id, fullname'
        );
        array_walk($courses, function (&$value) {
            $value = format_string($value);
        });
        $courseselect = $mform->addElement(
            'autocomplete',
            'condition[notenrolled][courses]',
            get_string('courses', 'pulsecondition_notenrolled'),
            $courses
        );
        $courseselect->setMultiple(true);
        $mform->addHelpButton('condition[notenrolled][courses]', 'courses', 'pulsecondition_notenrolled');
        $mform->hideIf('condition[notenrolled][courses]', 'condition[notenrolled][status]', 'eq', self::DISABLED);
        $mform->hideIf('condition[notenrolled][courses]', 'condition[notenrolled][scope]', 'neq', self::SCOPE_SPECIFIC);
        // Autocomplete doesn't work well with the instance form's disabledIf override wrapping,
        // so pre-register the override hidden to bypass auto-wrap (mirrors cohort's pattern).
        // Plain selects/duration get the standard override-by-default treatment.
        $mform->addElement('hidden', 'override[condition_notenrolled_courses]', 1);
        $mform->setType('override[condition_notenrolled_courses]', PARAM_INT);

        // Optional account-age window since signup.
        $mform->addElement(
            'duration',
            'condition[notenrolled][window]',
            get_string('window', 'pulsecondition_notenrolled')
        );
        // Use the nested-array form of setDefaults rather than setDefault with a bracketed key.
        // setDefault('condition[x][y]', ...) writes to the flat key, which HTML_QuickForm's
        // _findValue checks BEFORE walking nested keys — that flat entry then shadows any later
        // set_data($record) that populates the nested condition data, so the form would revert
        // to this default on every reopen.
        $mform->setDefaults(['condition' => ['notenrolled' => ['window' => WEEKSECS]]]);
        $mform->addHelpButton('condition[notenrolled][window]', 'window', 'pulsecondition_notenrolled');
        $mform->hideIf('condition[notenrolled][window]', 'condition[notenrolled][status]', 'eq', self::DISABLED);
    }

    /**
     * Checks whether the user satisfies the "not enrolled" condition.
     *
     * @param object $instancedata The instance data containing condition configuration.
     * @param int $userid The user ID to check.
     * @param \completion_info|null $completion Optional completion info object.
     * @return bool True if the user has no active enrolment in the configured scope.
     */
    public function is_user_completed($instancedata, int $userid, ?\completion_info $completion = null) {
        global $DB;

        $config = $instancedata->condition['notenrolled'] ?? [];
        if (empty($config) || empty($config['status'])) {
            return false;
        }

        // The user must be a real, active account.
        $user = $DB->get_record('user', ['id' => $userid], 'id, timecreated, deleted, suspended');
        if (!$user || $user->deleted || $user->suspended) {
            return false;
        }

        // Optional account-age window: only due once the window since signup has elapsed.
        $window = (int) ($config['window'] ?? 0);
        if ($window > 0 && ($user->timecreated + $window) > time()) {
            return false;
        }

        // Upcoming status: only applies to users who signed up after the condition was set up.
        $upcomingtime = (int) ($config['upcomingtime'] ?? 0);
        if ($upcomingtime > 0 && $user->timecreated < $upcomingtime) {
            return false;
        }

        $now = time();
        $params = ['userid' => $userid, 'now1' => $now, 'now2' => $now];

        $scope = (int) ($config['scope'] ?? self::SCOPE_ANY);
        if ($scope == self::SCOPE_SPECIFIC) {
            $courses = array_filter($config['courses'] ?? []);
            if (empty($courses)) {
                // No target course configured: do not trigger, to avoid accidental site-wide sends.
                return false;
            }
            [$insql, $inparams] = $DB->get_in_or_equal($courses, SQL_PARAMS_NAMED, 'crs');
            $coursewhere = "e.courseid $insql";
            $params += $inparams;
        } else {
            // Any course except the site course (which has no real enrolments).
            $coursewhere = 'e.courseid <> :siteid';
            $params['siteid'] = SITEID;
        }

        $sql = "SELECT COUNT(ue.id)
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid
                   AND ue.status = 0
                   AND $coursewhere
                   AND (ue.timestart = 0 OR ue.timestart <= :now1)
                   AND (ue.timeend = 0 OR ue.timeend > :now2)";

        return $DB->count_records_sql($sql, $params) == 0;
    }

    /**
     * SQL filter for the in-form preview: user past the optional window, past the upcoming time,
     * and with no active enrolment in the configured scope.
     *
     * @param array $config
     * @param object $instancedata
     * @return array
     */
    public function candidate_filter_sql(array $config, $instancedata): array {
        global $DB;
        if (empty($config['status'])) {
            return ['join' => '', 'where' => '', 'params' => []];
        }
        $now = time();
        $params = [];
        $clauses = [];

        $window = (int) ($config['window'] ?? 0);
        if ($window > 0) {
            $clauses[] = 'u.timecreated <= :pchne_thresh';
            $params['pchne_thresh'] = $now - $window;
        }
        $upcomingtime = (int) ($config['upcomingtime'] ?? 0);
        if ($upcomingtime > 0) {
            $clauses[] = 'u.timecreated >= :pchne_upc';
            $params['pchne_upc'] = $upcomingtime;
        }

        $scope = (int) ($config['scope'] ?? self::SCOPE_ANY);
        if ($scope == self::SCOPE_SPECIFIC) {
            $courses = array_filter((array) ($config['courses'] ?? []));
            if (empty($courses)) {
                // No target course configured: engine returns false; mirror that.
                $clauses[] = '1 = 0';
            } else {
                [$insql, $inparams] = $DB->get_in_or_equal($courses, SQL_PARAMS_NAMED, 'pchne_crs_');
                $clauses[] = "NOT EXISTS (SELECT 1 FROM {user_enrolments} ue
                                            JOIN {enrol} e ON e.id = ue.enrolid
                                           WHERE ue.userid = u.id AND ue.status = 0
                                             AND e.courseid $insql
                                             AND (ue.timestart = 0 OR ue.timestart <= :pchne_now1)
                                             AND (ue.timeend = 0 OR ue.timeend > :pchne_now2))";
                $params = $params + $inparams;
                $params['pchne_now1'] = $now;
                $params['pchne_now2'] = $now;
            }
        } else {
            $clauses[] = "NOT EXISTS (SELECT 1 FROM {user_enrolments} ue
                                        JOIN {enrol} e ON e.id = ue.enrolid
                                       WHERE ue.userid = u.id AND ue.status = 0
                                         AND e.courseid <> :pchne_site
                                         AND (ue.timestart = 0 OR ue.timestart <= :pchne_now1)
                                         AND (ue.timeend = 0 OR ue.timeend > :pchne_now2))";
            $params['pchne_site'] = SITEID;
            $params['pchne_now1'] = $now;
            $params['pchne_now2'] = $now;
        }

        return ['join' => '', 'where' => '(' . implode(' AND ', $clauses) . ')', 'params' => $params];
    }

    /**
     * Guard persistence of the condition behind the site-level capability.
     *
     * @param int $templateid The template ID.
     * @param array $data The data to be saved.
     * @return bool
     */
    public function process_save($templateid, $data) {
        if (!empty($data['status']) && !$this->can_manage()) {
            return true;
        }
        return parent::process_save($templateid, $data);
    }

    /**
     * Guard persistence of the instance override behind the site-level capability.
     *
     * @param int $instanceid The instance ID.
     * @param array $data The data to be saved.
     * @param object|null $templatedata Instance template record.
     * @return bool
     */
    public function process_instance_save($instanceid, $data, $templatedata = null) {
        if (!empty($data['status']) && !$this->can_manage()) {
            return true;
        }
        return parent::process_instance_save($instanceid, $data, $templatedata);
    }
}
