@mod @mod_pulse @pulse_triggercondition @pulsecondition_coursedates @javascript @_file_upload
Feature: coursedates trigger event.
  In To Verify Pulse Automation Template Conditions for coursedates as a Teacher.

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

    Then I create automation template with the following fields to these values:
      | Title     | Template1 |
      | Reference | temp1     |
    And I click on "Create new template" "button"
    Then I set the following fields to these values:
      | Title     | Notification1 |
      | Reference | notification1 |
    And I press "Save changes"

  Scenario: Course dates condition with Start date - before
    And I am on "Course 1" course homepage
    And I navigate to "Settings" in current page administration
    And I set the field "Course start date" to "##today +2 days##"
    And I press "Save and display"
    And I navigate to "Automation" in current page administration
    And I wait until the page is ready
    And I open the autocomplete suggestions list
    And I click on "Template1" item in the autocomplete list
    And I click on "Add automation instance" "button" in the ".template-add-form" "css_element"
    And I set the field "insreference" to "temp1"
    And I click on "#id_override_frequencylimit" "css_element" in the "#fitem_id_frequencylimit" "css_element"
    And I set the field "frequencylimit" to "1"
    And I press "Save changes"

    # Check the schedule for the instance
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And I should see "Nothing to display" in the ".reportbuilder-wrapper" "css_element"
    And I close all opened windows

    # Instance Conditions - Activity completion
    And I click on ".action-edit" "css_element" in the "Template1" "table_row"
    Then I follow "Condition"
    And I click on "#id_override_triggeroperator" "css_element" in the "#pulse-condition-tab" "css_element"
    Then the field "Trigger operator" matches value "All"
    And I click on "#id_override_condition_coursedates_status" "css_element" in the "#fitem_id_condition_coursedates_status" "css_element"
    And I set the field "condition[coursedates][status]" to "All"
    And I click on "#id_override_condition_coursedates_type" "css_element" in the "#fitem_id_condition_coursedates_type" "css_element"
    And I set the field "condition[coursedates][type]" to "Course start date"

    # Instance Notifications
    And I click on "Notification" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_pulsenotification_actionstatus" "css_element" in the "#fitem_id_pulsenotification_actionstatus" "css_element"
    And I set the field "id_pulsenotification_actionstatus" to "Enabled"
    And I click on "#id_override_pulsenotification_sender" "css_element" in the "#fitem_id_pulsenotification_sender" "css_element"
    And I set the field "pulsenotification_sender" to "Course teacher"
    And I click on "#id_override_pulsenotification_recipients" "css_element" in the "#fitem_id_pulsenotification_recipients" "css_element"
    And I set the field "Recipients" in the "#pulse-action-notification" "css_element" to "Student"

    # Delay before notification
    And I click on "#id_override_pulsenotification_notifydelay" "css_element" in the "#fitem_id_pulsenotification_notifydelay" "css_element"
    And I set the field "pulsenotification_notifydelay" to "Before"
    And I click on "#id_override_pulsenotification_delayduration" "css_element" in the "#fitem_id_pulsenotification_delayduration" "css_element"
    And I set the field "pulsenotification_delayduration[number]" to "2"
    And I set the field "pulsenotification_delayduration[timeunit]" to "days"

    # Notification mail setup
    And I click on "#id_override_pulsenotification_subject" "css_element" in the "#fitem_id_pulsenotification_subject" "css_element"
    And I set the field "pulsenotification_subject" to "Course date notification"
    And I click on "#id_override_pulsenotification_headercontent_editor" "css_element" in the "#fitem_id_pulsenotification_headercontent_editor" "css_element"
    And I click on "#header-email-vars-button" "css_element" in the ".mod-pulse-emailvars-toggle" "css_element"
    And I click on pulse "id_pulsenotification_headercontent_editor" editor
    And I click on "#Firstname" "css_element" in the ".Sender_field-placeholders" "css_element"
    And I press "Save changes"

    # Check the schedule for the instance
    And I log in as "admin"
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Course full name | Message type | Subject                   | Full name      | Time created          | Scheduled time        | Status |
      | Course 1         | Template1    | Course date notification  | student User 1 | ##now##%A, %d %B %Y## | ##now##%A, %d %B %Y## | Queued |
    And I close all opened windows

  Scenario: Course dates condition with End date - before
    And I am on "Course 1" course homepage
    And I navigate to "Settings" in current page administration
    And I set the field "Course end date" to "##today +2 days##"
    And I press "Save and display"
    And I navigate to "Automation" in current page administration
    And I wait until the page is ready
    And I open the autocomplete suggestions list
    And I click on "Template1" item in the autocomplete list
    And I click on "Add automation instance" "button" in the ".template-add-form" "css_element"
    And I set the field "insreference" to "temp1"
    And I click on "#id_override_frequencylimit" "css_element" in the "#fitem_id_frequencylimit" "css_element"
    And I set the field "frequencylimit" to "1"
    And I press "Save changes"

    # Check the schedule for the instance
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And I should see "Nothing to display" in the ".reportbuilder-wrapper" "css_element"
    And I close all opened windows

    # Instance Conditions - Activity completion
    And I click on ".action-edit" "css_element" in the "Template1" "table_row"
    Then I follow "Condition"
    And I click on "#id_override_triggeroperator" "css_element" in the "#pulse-condition-tab" "css_element"
    Then the field "Trigger operator" matches value "All"
    And I click on "#id_override_condition_coursedates_status" "css_element" in the "#fitem_id_condition_coursedates_status" "css_element"
    And I set the field "condition[coursedates][status]" to "Upcoming"
    And I click on "#id_override_condition_coursedates_type" "css_element" in the "#fitem_id_condition_coursedates_type" "css_element"
    And I set the field "condition[coursedates][type]" to "Course end date"

    # Instance Notifications
    And I click on "Notification" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_pulsenotification_actionstatus" "css_element" in the "#fitem_id_pulsenotification_actionstatus" "css_element"
    And I set the field "id_pulsenotification_actionstatus" to "Enabled"
    And I click on "#id_override_pulsenotification_sender" "css_element" in the "#fitem_id_pulsenotification_sender" "css_element"
    And I set the field "pulsenotification_sender" to "Course teacher"
    And I click on "#id_override_pulsenotification_recipients" "css_element" in the "#fitem_id_pulsenotification_recipients" "css_element"
    And I set the field "Recipients" in the "#pulse-action-notification" "css_element" to "Student"

    # Delay before notification
    And I click on "#id_override_pulsenotification_notifydelay" "css_element" in the "#fitem_id_pulsenotification_notifydelay" "css_element"
    And I set the field "pulsenotification_notifydelay" to "Before"
    And I click on "#id_override_pulsenotification_delayduration" "css_element" in the "#fitem_id_pulsenotification_delayduration" "css_element"
    And I set the field "pulsenotification_delayduration[number]" to "2"
    And I set the field "pulsenotification_delayduration[timeunit]" to "days"

    # Notification mail setup
    And I click on "#id_override_pulsenotification_subject" "css_element" in the "#fitem_id_pulsenotification_subject" "css_element"
    And I set the field "pulsenotification_subject" to "Course date notification"
    And I click on "#id_override_pulsenotification_headercontent_editor" "css_element" in the "#fitem_id_pulsenotification_headercontent_editor" "css_element"
    And I click on "#header-email-vars-button" "css_element" in the ".mod-pulse-emailvars-toggle" "css_element"
    And I click on pulse "id_pulsenotification_headercontent_editor" editor
    And I click on "#Firstname" "css_element" in the ".Sender_field-placeholders" "css_element"
    And I press "Save changes"

    And the following "users" exist:
      | username | firstname | lastname | email             |
      | student2 | student   | User 2   | student2@test.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student2 | C1     | student |

    # Check the schedule for the instance
    And I navigate to "Automation" in current page administration
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Course full name | Message type | Subject                   | Full name      | Time created          | Scheduled time        | Status |
      | Course 1         | Template1    | Course date notification  | student User 1 | ##now##%A, %d %B %Y## | ##now##%A, %d %B %Y## | Queued |
    And I close all opened windows

  Scenario: Course dates condition with Start date - after
    And I am on "Course 1" course homepage
    And I navigate to "Settings" in current page administration
    And I set the field "Course start date" to "##today +2 days##"
    And I press "Save and display"
    And I navigate to "Automation" in current page administration
    And I wait until the page is ready
    And I open the autocomplete suggestions list
    And I click on "Template1" item in the autocomplete list
    And I click on "Add automation instance" "button" in the ".template-add-form" "css_element"
    And I set the field "insreference" to "temp1"
    And I click on "#id_override_frequencylimit" "css_element" in the "#fitem_id_frequencylimit" "css_element"
    And I set the field "frequencylimit" to "1"
    And I press "Save changes"

    # Check the schedule for the instance
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And I should see "Nothing to display" in the ".reportbuilder-wrapper" "css_element"
    And I close all opened windows

    # Instance Conditions - Activity completion
    And I click on ".action-edit" "css_element" in the "Template1" "table_row"
    Then I follow "Condition"
    And I click on "#id_override_triggeroperator" "css_element" in the "#pulse-condition-tab" "css_element"
    Then the field "Trigger operator" matches value "All"
    And I click on "#id_override_condition_coursedates_status" "css_element" in the "#fitem_id_condition_coursedates_status" "css_element"
    And I set the field "condition[coursedates][status]" to "All"
    And I click on "#id_override_condition_coursedates_type" "css_element" in the "#fitem_id_condition_coursedates_type" "css_element"
    And I set the field "condition[coursedates][type]" to "Course start date"

    # Instance Notifications
    And I click on "Notification" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_pulsenotification_actionstatus" "css_element" in the "#fitem_id_pulsenotification_actionstatus" "css_element"
    And I set the field "id_pulsenotification_actionstatus" to "Enabled"
    And I click on "#id_override_pulsenotification_sender" "css_element" in the "#fitem_id_pulsenotification_sender" "css_element"
    And I set the field "pulsenotification_sender" to "Course teacher"
    And I click on "#id_override_pulsenotification_recipients" "css_element" in the "#fitem_id_pulsenotification_recipients" "css_element"
    And I set the field "Recipients" in the "#pulse-action-notification" "css_element" to "Student"

    # Delay before notification
    And I click on "#id_override_pulsenotification_notifydelay" "css_element" in the "#fitem_id_pulsenotification_notifydelay" "css_element"
    And I set the field "pulsenotification_notifydelay" to "After"
    And I click on "#id_override_pulsenotification_delayduration" "css_element" in the "#fitem_id_pulsenotification_delayduration" "css_element"
    And I set the field "pulsenotification_delayduration[number]" to "1"
    And I set the field "pulsenotification_delayduration[timeunit]" to "days"

    # Notification mail setup
    And I click on "#id_override_pulsenotification_subject" "css_element" in the "#fitem_id_pulsenotification_subject" "css_element"
    And I set the field "pulsenotification_subject" to "Course date notification"
    And I click on "#id_override_pulsenotification_headercontent_editor" "css_element" in the "#fitem_id_pulsenotification_headercontent_editor" "css_element"
    And I click on "#header-email-vars-button" "css_element" in the ".mod-pulse-emailvars-toggle" "css_element"
    And I click on pulse "id_pulsenotification_headercontent_editor" editor
    And I click on "#Firstname" "css_element" in the ".Sender_field-placeholders" "css_element"
    And I press "Save changes"

    # Check the schedule for the instance

    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Course full name | Message type | Subject                   | Full name      | Time created          | Scheduled time        | Status |
      | Course 1         | Template1    | Course date notification  | student User 1 | ##now##%A, %d %B %Y## | ##today +3days##%A, %d %B %Y## | Queued |
    And I close all opened windows
