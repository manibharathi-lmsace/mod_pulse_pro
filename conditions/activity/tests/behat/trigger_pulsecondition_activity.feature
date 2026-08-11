@mod @mod_pulse @pulse_triggercondition @pulsecondition_activity @javascript @_file_upload
Feature: Activity trigger event.
  In To Verify Pulse Automation Template Conditions for Activity as a Teacher.

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
  Scenario: Activity completion condition with activity count
    And I am on "Course 1" course homepage

    And I am on the "Assign3" "assign activity" page
    And I navigate to "Settings" in current page administration
    And I expand all fieldsets
    And I set the field "id_completion_1" to "1"
    And I press "Save and return to course"

    And I am on the "Assign4" "assign activity" page
    And I navigate to "Settings" in current page administration
    And I expand all fieldsets
    And I set the field "id_completion_1" to "1"
    And I press "Save and return to course"

    And I am on the "Assign5" "assign activity" page
    And I navigate to "Settings" in current page administration
    And I expand all fieldsets
    And I set the field "id_completion_1" to "1"
    And I press "Save and return to course"
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
    And I click on "#id_override_condition_activity_status" "css_element" in the "#fitem_id_condition_activity_status" "css_element"
    And I set the field "condition[activity][status]" to "All"
    And I wait "5" seconds
    And I set the field "Select activities" in the "#fitem_id_condition_activity_modules" "css_element" to "Assign1, Assign2, Forum1, Forum2, TestPage 01, TestPage 02"
    And I click on "#id_override_condition_activity_acompletionmethod" "css_element" in the "#fitem_id_condition_activity_acompletionmethod" "css_element"
    And I set the field "condition[activity][acompletionmethod]" to "Activity count"
    And I click on "#id_override_condition_activity_activitycount" "css_element" in the "#fitem_id_condition_activity_activitycount" "css_element"
    And I set the field "condition[activity][activitycount]" to "2"
    And I click on "#id_override_condition_activity_completionstatus" "css_element" in the "#fitem_id_condition_activity_completionstatus" "css_element"
    And I set the field "condition[activity][completionstatus]" to "Completed"

    # Instance Notifications
    And I click on "Notification" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_pulsenotification_actionstatus" "css_element" in the "#fitem_id_pulsenotification_actionstatus" "css_element"
    And I set the field "id_pulsenotification_actionstatus" to "Enabled"
    And I click on "#id_override_pulsenotification_sender" "css_element" in the "#fitem_id_pulsenotification_sender" "css_element"
    And I set the field "pulsenotification_sender" to "Course teacher"
    And I click on "#id_override_pulsenotification_recipients" "css_element" in the "#fitem_id_pulsenotification_recipients" "css_element"
    And I open the autocomplete suggestions list in the "#fitem_id_pulsenotification_recipients" "css_element"
    And I click on "Teacher" item in the autocomplete list

    And I click on "#id_override_pulsenotification_subject" "css_element" in the "#fitem_id_pulsenotification_subject" "css_element"
    And I set the field "pulsenotification_subject" to "Activity  completion notification"
    And I click on "#id_override_pulsenotification_headercontent_editor" "css_element" in the "#fitem_id_pulsenotification_headercontent_editor" "css_element"
    And I click on "#header-email-vars-button" "css_element" in the ".mod-pulse-emailvars-toggle" "css_element"
    And I click on pulse "id_pulsenotification_headercontent_editor" editor
    And I click on "#Firstname" "css_element" in the ".Sender_field-placeholders" "css_element"
    And I press "Save changes"

    # Check the schedule for the instance
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And I should see "Nothing to display" in the ".reportbuilder-wrapper" "css_element"
    And I close all opened windows
    And I log out

    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on "Mark as done" "button" in the ".section .activity:first-child" "css_element"
    And I click on "Mark as done" "button" in the ".section .activity:nth-child(2)" "css_element"
    And I click on "Mark as done" "button" in the ".section .activity:nth-child(3)" "css_element"
    And I click on "Mark as done" "button" in the ".section .activity:nth-child(4)" "css_element"
    And I log out

    # Completion Status
    # Check the schedule for the instance
    And I log in as "admin"
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Course full name | Message type | Subject                           | Full name      | Time created          | Scheduled time        | Status |
      | Course 1         | Template1    | Activity completion notification  | Teacher User 1 | ##now##%A, %d %B %Y## | ##now##%A, %d %B %Y## | sent   |
    And I close all opened windows

  Scenario: Activity failed condition with selected activities - grade pass
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
    And I click on "#id_override_condition_activity_status" "css_element" in the "#fitem_id_condition_activity_status" "css_element"
    And I set the field "condition[activity][status]" to "Upcoming"
    And I set the field "Select activities" in the "#fitem_id_condition_activity_modules" "css_element" to "Assign3, Assign4, Assign5"
    And I click on "#id_override_condition_activity_acompletionmethod" "css_element" in the "#fitem_id_condition_activity_acompletionmethod" "css_element"
    And I set the field "condition[activity][acompletionmethod]" to "Selected activities"
    And I click on "#id_override_condition_activity_activityoperator" "css_element" in the "#fitem_id_condition_activity_activityoperator" "css_element"
    And I set the field "condition[activity][activityoperator]" to "ALL"
    And I click on "#id_override_condition_activity_completionstatus" "css_element" in the "#fitem_id_condition_activity_completionstatus" "css_element"
    And I set the field "condition[activity][completionstatus]" to "Failed"

    # Instance Notifications
    And I click on "Notification" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_pulsenotification_actionstatus" "css_element" in the "#fitem_id_pulsenotification_actionstatus" "css_element"
    And I set the field "id_pulsenotification_actionstatus" to "Enabled"
    And I click on "#id_override_pulsenotification_sender" "css_element" in the "#fitem_id_pulsenotification_sender" "css_element"
    And I set the field "pulsenotification_sender" to "Course teacher"
    And I click on "#id_override_pulsenotification_recipients" "css_element" in the "#fitem_id_pulsenotification_recipients" "css_element"
    And I open the autocomplete suggestions list in the "#fitem_id_pulsenotification_recipients" "css_element"
    And I click on "Teacher" item in the autocomplete list

    And I click on "#id_override_pulsenotification_subject" "css_element" in the "#fitem_id_pulsenotification_subject" "css_element"
    And I set the field "pulsenotification_subject" to "Activity  completion notification"
    And I click on "#id_override_pulsenotification_headercontent_editor" "css_element" in the "#fitem_id_pulsenotification_headercontent_editor" "css_element"
    And I click on "#header-email-vars-button" "css_element" in the ".mod-pulse-emailvars-toggle" "css_element"
    And I click on pulse "id_pulsenotification_headercontent_editor" editor
    And I click on "#Firstname" "css_element" in the ".Sender_field-placeholders" "css_element"
    And I press "Save changes"

    # Check the schedule for the instance
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And I should see "Nothing to display" in the ".reportbuilder-wrapper" "css_element"
    And I close all opened windows
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | student2 | student   | User 2   | student2@test.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student2 | C1     | student |
    And I log out

    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I am on the "Assign3" "assign activity" page
    And I click on "Add submission" "button"
    And I upload "/mod/pulse/tests/fixtures/image.jpg" file to "File submissions" filemanager
    And I press "Save changes"
    And I click on "Submit assignment" "button"
    And I click on "Continue" "button"

    And I am on the "Assign4" "assign activity" page
    And I click on "Add submission" "button"
    And I upload "/mod/pulse/tests/fixtures/image.jpg" file to "File submissions" filemanager
    And I press "Save changes"
    And I click on "Submit assignment" "button"
    And I click on "Continue" "button"

    And I am on the "Assign5" "assign activity" page
    And I click on "Add submission" "button"
    And I upload "/mod/pulse/tests/fixtures/image.jpg" file to "File submissions" filemanager
    And I press "Save changes"
    And I click on "Submit assignment" "button"
    And I click on "Continue" "button"
    And I log out

    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I am on the "Assign3" "assign activity" page
    And I click on "Add submission" "button"
    And I upload "/mod/pulse/tests/fixtures/image.jpg" file to "File submissions" filemanager
    And I press "Save changes"
    And I click on "Submit assignment" "button"
    And I click on "Continue" "button"

    And I am on the "Assign4" "assign activity" page
    And I click on "Add submission" "button"
    And I upload "/mod/pulse/tests/fixtures/image.jpg" file to "File submissions" filemanager
    And I press "Save changes"
    And I click on "Submit assignment" "button"
    And I click on "Continue" "button"

    And I am on the "Assign5" "assign activity" page
    And I click on "Add submission" "button"
    And I upload "/mod/pulse/tests/fixtures/image.jpg" file to "File submissions" filemanager
    And I press "Save changes"
    And I click on "Submit assignment" "button"
    And I click on "Continue" "button"
    And I log out

    And I log in as "admin"
    And the following "grade grades" exist:
      | gradeitem | user     | grade |
      | Assign4   | student2 | 5.00  |
      | Assign5   | student2 | 5.00  |

    And I am on the "Assign3" "assign activity" page
    And I navigate to "Submissions" in current page administration
    And I click on "Grade" "link" in the ".tertiary-navigation" "css_element"
    And I set the field "Change user" to "student User 2"
    And I set the field "grade" to "5.00"
    And I press "Save changes"

    # Completion Status
    # Check the schedule for the instance for No group
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Course full name | Message type | Subject                           | Full name      | Time created          | Scheduled time        | Status |
      | Course 1         | Template1    | Activity completion notification  | Teacher User 1 | ##now##%A, %d %B %Y## | ##now##%A, %d %B %Y## | sent   |
    And I close all opened windows

  Scenario: Activity passed condition with selected activities - grade pass
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
    And I click on "#id_override_condition_activity_status" "css_element" in the "#fitem_id_condition_activity_status" "css_element"
    And I set the field "condition[activity][status]" to "All"
    And I set the field "Select activities" in the "#fitem_id_condition_activity_modules" "css_element" to "Assign3, Assign4, Assign5"
    And I click on "#id_override_condition_activity_acompletionmethod" "css_element" in the "#fitem_id_condition_activity_acompletionmethod" "css_element"
    And I set the field "condition[activity][acompletionmethod]" to "Selected activities"
    And I click on "#id_override_condition_activity_activityoperator" "css_element" in the "#fitem_id_condition_activity_activityoperator" "css_element"
    And I set the field "condition[activity][activityoperator]" to "ALL"
    And I click on "#id_override_condition_activity_completionstatus" "css_element" in the "#fitem_id_condition_activity_completionstatus" "css_element"
    And I set the field "condition[activity][completionstatus]" to "Passed"

    # Instance Notifications
    And I click on "Notification" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_pulsenotification_actionstatus" "css_element" in the "#fitem_id_pulsenotification_actionstatus" "css_element"
    And I set the field "id_pulsenotification_actionstatus" to "Enabled"
    And I click on "#id_override_pulsenotification_sender" "css_element" in the "#fitem_id_pulsenotification_sender" "css_element"
    And I set the field "pulsenotification_sender" to "Course teacher"
    And I click on "#id_override_pulsenotification_recipients" "css_element" in the "#fitem_id_pulsenotification_recipients" "css_element"
    # And I open the autocomplete suggestions list in the "#fitem_id_pulsenotification_recipients" "css_element"
    # And I click on "Teacher" item in the autocomplete list
    And I set the field "Recipients" in the "#pulse-action-notification" "css_element" to "Student"

    And I click on "#id_override_pulsenotification_subject" "css_element" in the "#fitem_id_pulsenotification_subject" "css_element"
    And I set the field "pulsenotification_subject" to "Activity  completion notification"
    And I click on "#id_override_pulsenotification_headercontent_editor" "css_element" in the "#fitem_id_pulsenotification_headercontent_editor" "css_element"
    And I click on "#header-email-vars-button" "css_element" in the ".mod-pulse-emailvars-toggle" "css_element"
    And I click on pulse "id_pulsenotification_headercontent_editor" editor
    And I click on "#Firstname" "css_element" in the ".Sender_field-placeholders" "css_element"
    And I press "Save changes"

    # Check the schedule for the instance
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And I should see "Nothing to display" in the ".reportbuilder-wrapper" "css_element"
    And I close all opened windows
    And I log out

    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I am on the "Assign3" "assign activity" page
    And I click on "Add submission" "button"
    And I upload "/mod/pulse/tests/fixtures/image.jpg" file to "File submissions" filemanager
    And I press "Save changes"
    And I click on "Submit assignment" "button"
    And I click on "Continue" "button"

    And I am on the "Assign4" "assign activity" page
    And I click on "Add submission" "button"
    And I upload "/mod/pulse/tests/fixtures/image.jpg" file to "File submissions" filemanager
    And I press "Save changes"
    And I click on "Submit assignment" "button"
    And I click on "Continue" "button"

    And I am on the "Assign5" "assign activity" page
    And I click on "Add submission" "button"
    And I upload "/mod/pulse/tests/fixtures/image.jpg" file to "File submissions" filemanager
    And I press "Save changes"
    And I click on "Submit assignment" "button"
    And I click on "Continue" "button"
    And I log out

    And I log in as "admin"
    And the following "grade grades" exist:
      | gradeitem | user     | grade  |
      | Assign3   | student1 | 20.00  |
      | Assign4   | student1 | 20.00  |
      | Assign5   | student1 | 20.00  |

    # Completion Status
    # Check the schedule for the instance for No group
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Course full name | Message type | Subject                           | Full name      | Time created          | Scheduled time        | Status |
      | Course 1         | Template1    | Activity completion notification  | student User 1 | ##now##%A, %d %B %Y## | ##now##%A, %d %B %Y## | sent   |
    And I close all opened windows

  Scenario: Activity partial completion condition with selected activities
    And I am on "Course 1" course homepage

    And I am on the "Assign3" "assign activity" page
    And I navigate to "Settings" in current page administration
    And I expand all fieldsets
    And I set the following fields to these values:
      | id_completionview   | 1 |
      | id_completionsubmit | 1 |
    And I set the field "id_completionusegrade" to "0"
    And I press "Save and return to course"

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
    And I click on "#id_override_condition_activity_status" "css_element" in the "#fitem_id_condition_activity_status" "css_element"
    And I set the field "condition[activity][status]" to "All"
    And I set the field "Select activities" in the "#fitem_id_condition_activity_modules" "css_element" to "Assign1, Assign2, Assign3, Assign4, Assign5"
    And I click on "#id_override_condition_activity_acompletionmethod" "css_element" in the "#fitem_id_condition_activity_acompletionmethod" "css_element"
    And I set the field "condition[activity][acompletionmethod]" to "Selected activities"
    And I click on "#id_override_condition_activity_activityoperator" "css_element" in the "#fitem_id_condition_activity_activityoperator" "css_element"
    And I set the field "condition[activity][activityoperator]" to "ANY"
    And I click on "#id_override_condition_activity_completionstatus" "css_element" in the "#fitem_id_condition_activity_completionstatus" "css_element"
    And I set the field "condition[activity][completionstatus]" to "Partially completed"

    # Instance Notifications
    And I click on "Notification" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_pulsenotification_actionstatus" "css_element" in the "#fitem_id_pulsenotification_actionstatus" "css_element"
    And I set the field "id_pulsenotification_actionstatus" to "Enabled"
    And I click on "#id_override_pulsenotification_sender" "css_element" in the "#fitem_id_pulsenotification_sender" "css_element"
    And I set the field "pulsenotification_sender" to "Course teacher"
    And I click on "#id_override_pulsenotification_recipients" "css_element" in the "#fitem_id_pulsenotification_recipients" "css_element"
    And I set the field "Recipients" in the "#pulse-action-notification" "css_element" to "Student"

    And I click on "#id_override_pulsenotification_subject" "css_element" in the "#fitem_id_pulsenotification_subject" "css_element"
    And I set the field "pulsenotification_subject" to "Activity  completion notification"
    And I click on "#id_override_pulsenotification_headercontent_editor" "css_element" in the "#fitem_id_pulsenotification_headercontent_editor" "css_element"
    And I click on "#header-email-vars-button" "css_element" in the ".mod-pulse-emailvars-toggle" "css_element"
    And I press "Save changes"
    And I log out

    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I am on the "Assign3" "assign activity" page
    And I should see "Done" in the automatic completion badge
    And I log out

    # Completion Status
    # Check the schedule for the instance
    And I log in as "admin"
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Course full name | Message type | Subject                           | Full name      | Time created          | Scheduled time        | Status |
      | Course 1         | Template1    | Activity completion notification  | student User 1 | ##now##%A, %d %B %Y## | ##now##%A, %d %B %Y## | sent   |
    And I close all opened windows
