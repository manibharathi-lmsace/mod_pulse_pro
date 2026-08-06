@mod @mod_pulse @pulse_triggercondition @pulsecondition_events @javascript @_file_upload
Feature: Event trigger event.
  In To Verify Pulse Automation Template Conditions for Event as a Teacher.

  Background:
    Given the following "course" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1        | 0        | 1                |
    And the following "activities" exist:
      | activity | name        | course | idnumber | completion | gradepass | assignsubmission_file_enabled | assignsubmission_file_maxfiles | assignsubmission_file_maxsizebytes | completionusegrade | completionpassgrade |
      | assign   | Assign1     | C1     | assign1  | 1          | 20.00     | 0                             | 0                              | 0                                  | 1                  | 1                   |
      | assign   | Assign2     | C1     | assign2  | 1          | 20.00     | 0                             | 0                              | 0                                  | 1                  | 1                   |
      | assign   | Assign3     | C1     | assign3  | 2          | 20.00     | 1                             | 1                              | 0                                  | 1                  | 1                   |
      | assign   | Assign4     | C1     | assign4  | 2          | 20.00     | 1                             | 1                              | 0                                  | 1                  | 1                   |
      | assign   | Assign5     | C1     | assign5  | 2          | 20.00     | 1                             | 1                              | 0                                  | 1                  | 1                   |
      | forum    | Forum1      | C1     | forum1   | 1          | 5.00      | 0                             | 0                              | 0                                  | 1                  | 1                   |
      | forum    | Forum2      | C1     | forum2   | 1          | 5.00      | 0                             | 0                              | 0                                  | 1                  | 1                   |
      | page     | TestPage 01 | C1     | page1    | 1          | 0.00      | 0                             | 0                              | 0                                  | 1                  | 1                   |
      | page     | TestPage 02 | C1     | page2    | 1          | 0.00      | 0                             | 0                              | 0                                  | 1                  | 1                   |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | student   | User 1   | student1@test.com |
      | teacher1 | Teacher   | User 1   | teacher1@test.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following config values are set as admin:
      | debug        | 0 |
      | debugdisplay | 0 |
    And I log in as "admin"
    And I am on "Course 1" course homepage
    And I navigate to "Settings" in current page administration
    And I expand all fieldsets
    And I set the field "Enable completion tracking" to "Yes"
    And I press "Save and display"
    And I click on "More" "link" in the ".secondary-navigation" "css_element"
    And I click on "Course completion" "link" in the ".secondary-navigation" "css_element"
    And I expand all fieldsets
    And I set the following fields to these values:
      | Assign1       | 1 |
      | Assign2       | 1 |
      | Assign3       | 1 |
      | Assign4       | 1 |
      | Assign5       | 1 |
      | Forum1        | 1 |
      | Forum2        | 1 |
      | TestPage 01   | 1 |
      | TestPage 02   | 1 |
    And I press "Save changes"

    And I navigate to "Plugins > Activity modules > Pulse > Automation templates" in site administration
    Then I should see "Automation templates" in the "#region-main h2" "css_element"
    And I should see "Create new template"
    Then I click on "Create new template" "button"
    Then I set the following fields to these values:
      | Title     | Template1 |
      | Reference | temp1     |
    Then I press "Save changes"

    Then I create automation template with the following fields to these values:
      | Title     | Notification1 |
      | Reference | notification1 |

  @javascript
  Scenario: Event condition with event context - everywhere
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    And I wait until the page is ready
    And I open the autocomplete suggestions list
    And I click on "Template1" item in the autocomplete list
    And I click on "Add automation instance" "button" in the ".template-add-form" "css_element"
    And I set the field "insreference" to "temp1"
    And I click on "#id_override_frequencylimit" "css_element" in the "#fitem_id_frequencylimit" "css_element"
    And I set the field "frequencylimit" to "1"
    And I press "Save changes"

    # Instance Conditions - Activity completion
    And I click on ".action-edit" "css_element" in the "Template1" "table_row"
    Then I follow "Condition"
    And I click on "#id_override_triggeroperator" "css_element" in the "#pulse-condition-tab" "css_element"
    Then the field "Trigger operator" matches value "All"
    And I click on "#id_override_condition_events_status" "css_element" in the "#fitem_id_condition_events_status" "css_element"
    And I set the field "condition[events][status]" to "All"
    And I click on "#id_override_condition_events_event" "css_element" in the "#fitem_id_condition_events_event" "css_element"
    And "Course module created" "text" should exist in the "#fitem_id_condition_events_event" "css_element"
    And "Submission created" "text" should not exist in the "#fitem_id_condition_events_event" "css_element"

    And I click on "#id_override_condition_events_notifyuser" "css_element" in the "#fitem_id_condition_events_notifyuser" "css_element"
    And I set the field "condition[events][notifyuser]" to "Related user"
    And I click on "#id_override_condition_events_eventscontext" "css_element" in the "#fitem_id_condition_events_eventscontext" "css_element"
    And I set the field "condition[events][eventscontext]" to "Course (including activities)"

    # Instance Notifications
    And I click on "Notification" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_pulsenotification_actionstatus" "css_element" in the "#fitem_id_pulsenotification_actionstatus" "css_element"
    And I set the field "id_pulsenotification_actionstatus" to "Enabled"
    And I click on "#id_override_pulsenotification_sender" "css_element" in the "#fitem_id_pulsenotification_sender" "css_element"
    And I set the field "pulsenotification_sender" to "Course teacher"
    And I click on "#id_override_pulsenotification_recipients" "css_element" in the "#fitem_id_pulsenotification_recipients" "css_element"
    And I set the field "Recipients" in the "#pulse-action-notification" "css_element" to "Student"

    And I click on "#id_override_pulsenotification_subject" "css_element" in the "#fitem_id_pulsenotification_subject" "css_element"
    And I set the field "pulsenotification_subject" to "Event completion notification"
    And I click on "#id_override_pulsenotification_headercontent_editor" "css_element" in the "#fitem_id_pulsenotification_headercontent_editor" "css_element"
    And I click on "#header-email-vars-button" "css_element" in the ".mod-pulse-emailvars-toggle" "css_element"
    And I click on pulse "id_pulsenotification_headercontent_editor" editor
    And I click on "#Firstname" "css_element" in the ".Sender_field-placeholders" "css_element"
    And I press "Save changes"

    # Event condition - General settings
    And I navigate to "Plugins > Activity modules > Pulse > Events completion" in site administration
    And I click on "#admin-availableevents .form-autocomplete-selection .badge:nth-child(3)" "css_element"
    And I set the field "Available events" in the "#admin-availableevents" "css_element" to "Submission created"
    And I press "Save changes"

    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    And I click on ".action-edit" "css_element" in the "Template1" "table_row"
    Then I follow "Condition"
    And "Submission created" "text" should exist in the "#fitem_id_condition_events_event" "css_element"
    And "Course module created" "text" should not exist in the "#fitem_id_condition_events_event" "css_element"
    And I press "Save changes"
    And I log out

    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I am on the "Assign4" "assign activity" page
    And I click on "Add submission" "button"
    And I upload "/mod/pulse/tests/fixtures/image.jpg" file to "File submissions" filemanager
    And I press "Save changes"
    And I click on "Submit assignment" "button"
    And I click on "Continue" "button"
    And I log out

    # Completion Status
    # Check the schedule for the instance
    And I log in as "admin"
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Course full name | Message type | Subject                       | Full name      | Time created          | Scheduled time        | Status |
      | Course 1         | Template1    | Event completion notification | student User 1 | ##now##%A, %d %B %Y## | ##now##%A, %d %B %Y## | sent   |
    And I close all opened windows

  Scenario: Event context of event condition - selected activities
    # Event condition - General settings
    And I navigate to "Plugins > Activity modules > Pulse > Events completion" in site administration
    And I click on "#admin-availableevents .form-autocomplete-selection .badge:nth-child(3)" "css_element"
    And I set the field "Available events" in the "#admin-availableevents" "css_element" to "Submission created. \assignsubmission_file\event\submission_created"
    And I press "Save changes"

    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    And I wait until the page is ready
    And I open the autocomplete suggestions list
    And I click on "Template1" item in the autocomplete list
    And I click on "Add automation instance" "button" in the ".template-add-form" "css_element"
    And I set the field "insreference" to "temp1"
    Then I follow "Condition"
    And I click on "#id_override_triggeroperator" "css_element" in the "#pulse-condition-tab" "css_element"
    Then the field "Trigger operator" matches value "All"
    And I click on "#id_override_condition_events_status" "css_element" in the "#fitem_id_condition_events_status" "css_element"
    And I set the field "condition[events][status]" to "All"
    And I click on "#id_override_condition_events_event" "css_element" in the "#fitem_id_condition_events_event" "css_element"
    And I click on "#id_override_condition_events_notifyuser" "css_element" in the "#fitem_id_condition_events_notifyuser" "css_element"
    And I set the field "condition[events][notifyuser]" to "Affected user"
    And I click on "#id_override_condition_events_eventscontext" "css_element" in the "#fitem_id_condition_events_eventscontext" "css_element"
    And I set the field "condition[events][eventscontext]" to "Selected activities"
    And I set the field "Event module" in the "#condition-events" "css_element" to "Assign4"

    # Instance Notifications
    And I click on "Notification" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_pulsenotification_actionstatus" "css_element" in the "#fitem_id_pulsenotification_actionstatus" "css_element"
    And I set the field "id_pulsenotification_actionstatus" to "Enabled"
    And I click on "#id_override_pulsenotification_sender" "css_element" in the "#fitem_id_pulsenotification_sender" "css_element"
    And I set the field "pulsenotification_sender" to "Course teacher"
    And I click on "#id_override_pulsenotification_recipients" "css_element" in the "#fitem_id_pulsenotification_recipients" "css_element"
    And I set the field "Recipients" in the "#pulse-action-notification" "css_element" to "Non-editing teacher, Teacher"

    And I click on "#id_override_pulsenotification_subject" "css_element" in the "#fitem_id_pulsenotification_subject" "css_element"
    And I set the field "pulsenotification_subject" to "Event completion notification"
    And I click on "#id_override_pulsenotification_headercontent_editor" "css_element" in the "#fitem_id_pulsenotification_headercontent_editor" "css_element"
    And I click on "#header-email-vars-button" "css_element" in the ".mod-pulse-emailvars-toggle" "css_element"
    And I click on pulse "id_pulsenotification_headercontent_editor" editor
    And I click on "#Firstname" "css_element" in the ".Sender_field-placeholders" "css_element"
    And I press "Save changes"

    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration

    # Check the schedule for the instance
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And I should see "Nothing to display" in the ".reportbuilder-wrapper" "css_element"
    And I close all opened windows
    And I log out

    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I am on the "Assign4" "assign activity" page
    And I click on "Add submission" "button"
    And I upload "/mod/pulse/tests/fixtures/image.jpg" file to "File submissions" filemanager
    And I press "Save changes"
    And I click on "Submit assignment" "button"
    And I click on "Continue" "button"
    And I log out

    # Completion Status
    # Check the schedule for the instance
    And I log in as "admin"
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Course full name | Message type | Subject                       | Full name      |
      | Course 1         | Template1    | Event completion notification | Teacher User 1 |
    And I close all opened windows

  Scenario: Frequency unlimited notification cycles
    And I navigate to "Plugins > Activity modules > Pulse > Events completion" in site administration
    And I set the field "Available events" in the "#admin-availableevents" "css_element" to "User has logged in"
    And I press "Save changes"
    # Create an automation instance from Notification1 with frequency limit 0 (unlimited).
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    And I open the autocomplete suggestions list
    And I click on "Notification1" item in the autocomplete list
    And I click on "Add automation instance" "button"
    And I set the field "insreference" to "frequnlimited"
    And I click on "#id_override_frequencylimit" "css_element" in the "#fitem_id_frequencylimit" "css_element"
    And I set the field "frequencylimit" to "0"
    # Configure events condition
    Then I follow "Condition"
    And I click on "#id_override_triggeroperator" "css_element" in the "#pulse-condition-tab" "css_element"
    And I click on "#id_override_condition_events_status" "css_element" in the "#fitem_id_condition_events_status" "css_element"
    And I set the field "condition[events][status]" to "All"
    And I click on "#id_override_condition_events_event" "css_element" in the "#fitem_id_condition_events_event" "css_element"
    And I open the autocomplete suggestions list in the "#condition-events" "css_element"
    And I press the escape key
    And I click on "#id_override_condition_events_notifyuser" "css_element" in the "#fitem_id_condition_events_notifyuser" "css_element"
    And I set the field "condition[events][notifyuser]" to "Related user"
    And I click on "#id_override_condition_events_eventscontext" "css_element" in the "#fitem_id_condition_events_eventscontext" "css_element"
    And I set the field "condition[events][eventscontext]" to "System"
    And I click on "Notification" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_pulsenotification_actionstatus" "css_element" in the "#fitem_id_pulsenotification_actionstatus" "css_element"
    And I set the field "id_pulsenotification_actionstatus" to "Enabled"
    And I click on "#id_override_pulsenotification_recipients" "css_element" in the "#fitem_id_pulsenotification_recipients" "css_element"
    And I set the field "Recipients" in the "#pulse-action-notification" "css_element" to "Student"
    And I click on "#id_override_pulsenotification_subject" "css_element" in the "#fitem_id_pulsenotification_subject" "css_element"
    And I set the field "pulsenotification_subject" to "User login frequency test"
    And I press "Save changes"
    And I log out
    And I log in as "student1"
    And I log out
    And I log in as "student1"
    And I log out
    And I log in as "student1"
    And I log out
    And I log in as "admin"
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    And I click on "#notification-action-report" "css_element" in the "Notification1" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Full name      | Subject                   | Status |
      | student User 1 | User login frequency test | sent   |
    And ".reportbuilder-table tbody tr:not(.emptyrow):nth-child(2)" "css_element" should exist
    And ".reportbuilder-table tbody tr:not(.emptyrow):nth-child(3)" "css_element" should exist
    And I close all opened windows
    And I log out

  Scenario: Frequency limit notification cycles
    And I navigate to "Plugins > Activity modules > Pulse > Events completion" in site administration
    And I set the field "Available events" in the "#admin-availableevents" "css_element" to "User has logged in"
    And I press "Save changes"
    # Create an automation instance from Notification1 with frequency limit 2.
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    And I open the autocomplete suggestions list
    And I click on "Notification1" item in the autocomplete list
    And I click on "Add automation instance" "button"
    And I set the field "insreference" to "freqlimit2"
    And I click on "#id_override_frequencylimit" "css_element" in the "#fitem_id_frequencylimit" "css_element"
    And I set the field "frequencylimit" to "2"
    # Configure events condition
    Then I follow "Condition"
    And I click on "#id_override_triggeroperator" "css_element" in the "#pulse-condition-tab" "css_element"
    And I click on "#id_override_condition_events_status" "css_element" in the "#fitem_id_condition_events_status" "css_element"
    And I set the field "condition[events][status]" to "All"
    And I click on "#id_override_condition_events_event" "css_element" in the "#fitem_id_condition_events_event" "css_element"
    And I open the autocomplete suggestions list in the "#condition-events" "css_element"
    And I press the escape key
    And I click on "#id_override_condition_events_notifyuser" "css_element" in the "#fitem_id_condition_events_notifyuser" "css_element"
    And I set the field "condition[events][notifyuser]" to "Related user"
    And I click on "#id_override_condition_events_eventscontext" "css_element" in the "#fitem_id_condition_events_eventscontext" "css_element"
    And I set the field "condition[events][eventscontext]" to "System"
    And I click on "Notification" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_pulsenotification_actionstatus" "css_element" in the "#fitem_id_pulsenotification_actionstatus" "css_element"
    And I set the field "id_pulsenotification_actionstatus" to "Enabled"
    And I click on "#id_override_pulsenotification_recipients" "css_element" in the "#fitem_id_pulsenotification_recipients" "css_element"
    And I set the field "Recipients" in the "#pulse-action-notification" "css_element" to "Student"
    And I click on "#id_override_pulsenotification_subject" "css_element" in the "#fitem_id_pulsenotification_subject" "css_element"
    And I set the field "pulsenotification_subject" to "User login frequency limit 2 test"
    And I press "Save changes"
    And I log out
    And I log in as "student1"
    And I log out
    And I log in as "student1"
    And I log out
    And I log in as "student1"
    And I log out
    And I log in as "admin"
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    And I click on "#notification-action-report" "css_element" in the "Notification1" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Full name      | Subject                           | Status |
      | student User 1 | User login frequency limit 2 test | sent   |
    And ".reportbuilder-table tbody tr:not(.emptyrow):nth-child(2)" "css_element" should exist
    And ".reportbuilder-table tbody tr:not(.emptyrow):nth-child(3)" "css_element" should not exist
    And I close all opened windows
    And I log out
