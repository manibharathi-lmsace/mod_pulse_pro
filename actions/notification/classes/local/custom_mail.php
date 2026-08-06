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
 * Pulse notification custom mail recipients handling.
 *
 * Create a custom mail as a new moodle user in nologin auth type to prevent the course access.
 * Creates the users on the global setting is updated. Removes users when email is removed from config.
 * Also removes all schedules for the removed email users.
 *
 * @package   pulseaction_notification
 * @copyright 2025, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace pulseaction_notification\local;

use core_user;

/**
 * Custom mail handling for notification pulse action.
 */
class custom_mail {
    /**
     * Custom mail instance.
     *
     * @return self
     */
    public static function instance(): self {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }

    /**
     * Get the custom recipients from config.
     *
     * @return array
     */
    public function get_custom_recipients(): array {
        global $CFG, $DB;

        // Get the custom recipients from config.
        $customemail = get_config('pulseaction_notification', 'recipients_custom');
        $extracustomemail = [];
        if (!empty($customemail)) {
            $lines = preg_split('/\r\n|\r|\n/', trim($customemail));
            foreach ($lines as $line) {
                $parts = array_map('trim', explode(',', $line));
                $email = end($parts);
                // Use get_records with limit to handle multiple users with same email.
                $users = $DB->get_records('user', ['email' => $email, 'deleted' => 0], 'id ASC', '*', 0, 1);
                $user = !empty($users) ? reset($users) : null;
                if ($user) {
                    if (count($parts) == 2) {
                        [$name, $email] = $parts;
                        $extracustomemail[$email] = "{$name} ({$email})";
                    } else if (count($parts) == 1 && !empty($parts[0]) && filter_var($parts[0], FILTER_VALIDATE_EMAIL)) {
                        // Handle email-only entries.
                        $email = $parts[0];
                        $extracustomemail[$email] = "{$user->firstname} {$user->lastname} ({$email})";
                    }
                }
            }
        }
        return $extracustomemail;
    }

    /**
     * Parse the custom recipients from config and return emails.
     *
     * @param string $customrecipients
     * @param bool $emailonly
     * @return array
     */
    public function get_emails_from_config(string $customrecipients, $emailonly = true): array {
        $custommails = [];
        if (!empty($customrecipients)) {
            // Split the custom recipients by new line.
            $emails = preg_split('/\r\n|[\r\n]/', $customrecipients);
            foreach ($emails as $line) {
                $parts = array_map('trim', explode(',', $line));
                if (count($parts) == 2) {
                    [$name, $email] = $parts;
                    $custommails[$email] = $name;
                } else if (count($parts) == 1 && !empty($parts[0]) && filter_var($parts[0], FILTER_VALIDATE_EMAIL)) {
                    // Handle email-only entries (no name provided).
                    $email = $parts[0];
                    $custommails[$email] = null;
                }
            }
        }

        return $emailonly ? array_keys($custommails) : $custommails;
    }

    /**
     * Process the save of global config for custom recipients.
     *
     * @return void
     */
    public function process_save_globalconfig() {
        global $DB;
        // When the custom recipients config is updated, we need to clear all existing schedules.
        $previousrecord = $DB->get_record_sql(
            'SELECT * FROM {config_log} cl WHERE cl.name=:clname AND cl.plugin=:component ORDER BY id DESC',
            ['clname' => 'recipients_custom', 'component' => 'pulseaction_notification'],
            IGNORE_MULTIPLE
        );

        // Get old value from previous record or empty string if first time.
        $oldvalue = (!empty($previousrecord->oldvalue)) ? $previousrecord->oldvalue : '';

        $previousmails = $this->get_emails_from_config($oldvalue, false);
        $currentmails = $this->get_emails_from_config(get_config('pulseaction_notification', 'recipients_custom'), false);

        // Handle removed emails.
        $removedmails = array_diff_key($previousmails, $currentmails);

        if (!empty($removedmails)) {
            foreach ($removedmails as $removedmail => $name) {
                if ($user = core_user::get_user_by_email($removedmail)) {
                    // Remove all schedules for the user.
                    $DB->delete_records('pulseaction_notification_sch', ['userid' => $user->id]);
                    // Delete the user if its nologin auth and was created for custom mail recipient.
                    if ($user->auth === 'nologin' && get_user_preferences('pulseaction_notification_recipient', 0, $user) == 1) {
                        // Delete only nologin users.
                        delete_user($user);
                    }
                }
            }
        }

        // Handle newly added emails only (not all current emails).
        $addedmails = array_diff_key($currentmails, $previousmails);

        // Create accounts for newly added emails.
        foreach ($addedmails as $addedmail => $name) {
            // Create a dummy user for the added custom mail.
            self::create_nologin_user_for_email($addedmail, $name);
        }

        // Also check and recreate accounts for existing emails if they were deleted.
        foreach ($currentmails as $email => $name) {
            // Skip if this was already processed as a new email.
            if (isset($addedmails[$email])) {
                continue;
            }

            // Check if account exists for this email.
            $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
            if (!$user) {
                // Account doesn't exist (was deleted), recreate it.
                self::create_nologin_user_for_email($email, $name);
            }
        }
    }

    /**
     * Create a user in nologin auth for the given email.
     *
     * @param string $email
     * @param string|null $name
     *
     * @return core_user
     */
    public static function create_nologin_user_for_email(string $email, $name = null) {
        global $DB;

        // Sanitize email to create a valid username.
        $username = \core_text::strtolower($email);
        $username = preg_replace('/[^a-z0-9._@-]/', '_', $username);
        $username = trim($username, '_');

        // Check for existing non-deleted user with this username.
        $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
        $usercreated = false;

        if (!$user) {
            // No active user with username, check if email exists with different username.
            $emailuser = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
            if ($emailuser) {
                // User exists with this email but different username, use it.
                $user = $emailuser;
            } else {
                // No active user found, create new user.
                $usercreated = true;
                $user = create_user_record($username, md5($email), 'nologin');
            }
        }

        if ($usercreated) {
            $firstname = '';

            $defaultlastname = get_config('pulseaction_notification', 'recipients_default_lastname');
            $lastname = !empty($defaultlastname) ? $defaultlastname : get_string('serviceaccount', 'pulseaction_notification');

            if (!empty($name)) {
                // Name provided, parse it.
                $nameparts = explode(' ', $name, 2);
                $firstname = $nameparts[0] ?? '';
                $lastname = $nameparts[1] ?? $lastname;
            } else {
                // No name provided, extract firstname from email address.
                // Get the part before @ symbol (e.g., "technicalsupport" from "technicalsupport@bdecent.de").
                $emailparts = explode('@', $email);
                $firstname = $emailparts[0] ?? '';
            }

            $DB->update_record('user', (object)[
                'id' => $user->id,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'email' => $email,
            ]);

            // Set a preference to identify this user as custom mail recipient.
            set_user_preference('pulseaction_notification_recipient', 1, $user);
        }

        return $user;
    }

    /**
     * Get the custom recipients form value.
     *
     * @return string
     */
    public static function get_custom_recipients_formvalue(): string {
        $customrecipients = get_config('pulseaction_notification', 'recipients_custom');
        return $customrecipients ?: '';
    }

    /**
     * Check if the given user is a custom mail recipient user.
     *
     * @param int $userid
     * @return bool
     */
    public static function is_pulse_custom_recipient_user(int $userid): bool {
        static $ispulsecustomrecipientusers = [];

        if (array_key_exists($userid, $ispulsecustomrecipientusers)) {
            return $ispulsecustomrecipientusers[$userid];
        } else {
            $result = get_user_preferences('pulseaction_notification_recipient', null, $userid) == 1;
            $ispulsecustomrecipientusers[$userid] = $result;
            return $result;
        }
    }
}
