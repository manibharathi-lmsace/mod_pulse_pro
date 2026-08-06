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
 * Notification pulse action - Admin settings.
 *
 * @package   pulseaction_notification
 * @copyright 2023, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'pulseaction_notification',
        get_string('pluginname', 'pulseaction_notification')
    );

    $name = 'pulseaction_notification/recipients_custom';
    $title = get_string('recipientscustom', 'pulseaction_notification');
    $description = get_string('recipientscustom_desc', 'pulseaction_notification');

    // Create a custom setting class for email validation.
    $setting = new class ($name, $title, $description, '') extends admin_setting_configtextarea {
        /**
         * Return the setting with a warning notice if accounts are missing.
         *
         * @param mixed $data
         * @param string $query
         * @return string HTML output
         */
        public function output_html($data, $query = '') {
            global $DB, $OUTPUT;

            $html = parent::output_html($data, $query);

            // Check for invalid email formats and deleted users when displaying the setting.
            if (!empty($data)) {
                $lines = array_filter(array_map('trim', explode("\n", $data)));
                $invalid = [];
                $deleted = [];

                foreach ($lines as $line) {
                    // Skip empty lines.
                    if (empty($line)) {
                        continue;
                    }

                    $parts = array_map('trim', explode(',', $line, 2));

                    // Determine email based on format.
                    if (count($parts) == 2) {
                        // Format: Name, Email.
                        $email = $parts[1];
                    } else if (count($parts) == 1) {
                        // Format: Email only.
                        $email = $parts[0];
                    } else {
                        continue;
                    }

                    // Check email format first.
                    if (!validate_email($email)) {
                        $invalid[] = $email;
                        continue;
                    }

                    // Check if email can create a valid username.
                    $username = \core_text::strtolower($email);
                    $sanitized = preg_replace('/[^a-z0-9._@-]/', '_', $username);
                    $sanitized = trim($sanitized, '_');

                    // Check if the sanitized username is different from the email (contains invalid chars).
                    if ($sanitized !== $username) {
                        $invalid[] = $email;
                        continue;
                    }

                    // Check if user account already exists (active user with deleted = 0).
                    $activeuser = $DB->get_record('user', ['username' => $sanitized, 'deleted' => 0]);

                    // Only check for deleted users if no active user exists.
                    if (!$activeuser) {
                        // Check if a deleted user exists with a username starting with this email.
                        // Moodle appends a timestamp to usernames when deleting (e.g., user@example.com.1768282927).
                        $sql = "SELECT * FROM {user} WHERE " . $DB->sql_like('username', ':username') . " AND deleted = 1";
                        $deleteduser = $DB->get_record_sql(
                            $sql,
                            ['username' => $DB->sql_like_escape($sanitized) . '%'],
                            IGNORE_MULTIPLE
                        );
                        if ($deleteduser) {
                            $deleted[] = $email;
                        }
                    }
                }
                // Display error for invalid email formats.
                if (!empty($invalid)) {
                    $notification = $OUTPUT->notification(
                        get_string('invalidemailswarning', 'pulseaction_notification', implode(', ', $invalid)),
                        \core\output\notification::NOTIFY_ERROR
                    );
                    $html = $notification . $html;
                }

                // Display warning for deleted user accounts.
                if (!empty($deleted)) {
                    $notification = $OUTPUT->notification(
                        get_string('missingaccountswarning', 'pulseaction_notification', implode(', ', $deleted)),
                        \core\output\notification::NOTIFY_WARNING
                    );
                    $html = $notification . $html;
                }
            }

            return $html;
        }

        /**
         * Validate the email addresses and check for missing user accounts.
         *
         * @param string $data The email addresses to validate (format: email or username,email)
         * @return mixed true if valid, error string otherwise
         */
        public function validate($data) {
            $lines = array_filter(array_map('trim', explode("\n", $data)));
            $invalidentries = [];

            foreach ($lines as $line) {
                // Skip empty lines.
                if (empty($line)) {
                    continue;
                }

                // Parse the line - it can be "email" or "Name, email".
                $parts = array_map('trim', explode(',', $line, 2));

                // Determine email based on format.
                if (count($parts) == 2) {
                    // Format: Name, Email.
                    $email = $parts[1];
                    $name = $parts[0];
                } else if (count($parts) == 1) {
                    // Format: Email only.
                    $email = $parts[0];
                    $name = null;
                } else {
                    $invalidentries[] = $line;
                    continue;
                }

                // Validate email format.
                if (!validate_email($email)) {
                    $invalidentries[] = $email;
                    continue;
                }

                // Check if email can create a valid username (test sanitization).
                $username = \core_text::strtolower($email);
                $sanitized = preg_replace('/[^a-z0-9._@-]/', '_', $username);
                $sanitized = trim($sanitized, '_');

                // Check if the sanitized username is different from the email (contains invalid chars).
                // Or if sanitized username is empty or too short.
                if ($sanitized !== $username || empty($sanitized) || strlen($sanitized) < 2) {
                    $invalidentries[] = $email;
                    continue;
                }
            }

            // Prevent saving if there are invalid entries.
            if (!empty($invalidentries)) {
                return false;
            }

            return true;
        }

        /**
         * Write setting to database and process accounts.
         *
         * @param string $data
         * @return string empty string or error message
         */
        public function write_setting($data) {
            // Call parent to save the setting.
            $result = parent::write_setting($data);

            // Always process the global config to recreate missing accounts.
            if ($result === '') {
                \pulseaction_notification\local\custom_mail::instance()->process_save_globalconfig();
            }

            return $result;
        }
    };

    $settings->add($setting);

    $name = 'pulseaction_notification/recipients_default_lastname';
    $title = get_string('recipientsdefaultlastname', 'pulseaction_notification');
    $description = get_string('recipientsdefaultlastname_desc', 'pulseaction_notification');
    $default = 'Service Account';

    $settings->add(new admin_setting_configtext(
        $name,
        $title,
        $description,
        $default,
        PARAM_TEXT
    ));
}
