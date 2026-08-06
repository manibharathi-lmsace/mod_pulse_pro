@mod @mod_pulse @pulse_automation_template @pulse_automation_template_new_condition @javascript
Feature: Pulse group condition
  In order to check the the pulse group condition works with new condition settings

  Background:
    Given the following "categories" exist:
      | name  | category | idnumber |
      | Cat 1 | 0        | CAT1     |
      | Cat 2 | 0        | CAT2     |
      | Cat 3 | CAT1     | CAT3     |
    And the following "course" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1        | 0        | 1                |
      | Course 2 | C2        | CAT1     | 1                |
      | Course 3 | C3        | CAT2     | 1                |
      | Course 4 | C4        | CAT3     | 1                |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | student   | User 1   | student1@test.com |
      | student2 | student   | User 2   | student1@test.com |
      | student3 | student   | User 3   | student3@test.com |
      | teacher1 | Teacher   | User 1   | teacher1@test.com |
      | teacher2 | Teacher   | User 2   | teacher2@test.com |
      | teacher3 | Teacher   | User 3   | teacher3@test.com |
    And the following "course enrolments" exist:
      | user     | course | role            |
      | teacher1 | C1     | editingteacher  |
      | teacher2 | C1     | teacher         |
      | teacher3 | C1     | teacher         |
      | student1 | C1     | student         |
      | student2 | C1     | student         |
      | student3 | C1     | student         |
      | student3 | C2     | student         |
    And the following "activities" exist:
      | activity | name    | course | idnumber | intro        | section | completion |
      | assign   | Assign1 | C1     | assign1  | Page Assign1 | 1       | 1          |
      | assign   | Assign2 | C1     | assign2  | Page Assign2 | 2       | 1          |
      | forum    | Forum1  | C1     | forum1   | Page Forum1  | 1       | 1          |
      | page     | Page1   | C1     | page1    | Page Page1   | 1       | 1          |
    And the following "groups" exist:
      | name    | course | idnumber |
      | Group 1 | C1     | G1       |
      | Group 2 | C1     | G2       |
    And the following "group members" exist:
      | user     | group |
      | student1 | G1    |
      | student2 | G1    |
      | student2 | G2    |
      | teacher2 | G1    |
    And the following "groupings" exist:
      | name       | course | idnumber |
      | Grouping 1 | C1     | GG1      |
      | Grouping 2 | C1     | GG2      |
    And the following "grouping groups" exist:
      | grouping | group |
      | GG1      | G1    |
      | GG1      | G2    |

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
      | Assign1 | 1 |
      | Assign2 | 1 |
      | Forum1  | 1 |
      | Page1   | 1 |
    And I press "Save changes"

    Then I create automation template with the following fields to these values:
      | Title     | Template1 |
      | Reference | temp1     |
    And I click on "Create new template" "button"
    Then I set the following fields to these values:
      | Title     | Notification1 |
      | Reference | notification1 |
    And I press "Save changes"

  # Scenario: Activity completion condition: Enhance settings
  #   And I am on "Course 1" course homepage
  #   And I navigate to "Automation" in current page administration
  #   And I open the autocomplete suggestions list
  #   And I click on "Template1" item in the autocomplete list
  #   And I click on "Add automation instance" "button" in the ".template-add-form" "css_element"
  #   And I set the field "insreference" to "temp1"
  #   Then I follow "Condition"
  #   And I click on "#id_override_triggeroperator" "css_element" in the "#pulse-condition-tab" "css_element"
  #   Then the field "Trigger operator" matches value "All"
  #   And I click on "#id_override_condition_activity_status" "css_element" in the "#condition-activity" "css_element"
  #   And I set the field "condition[activity][status]" to "All"

  #   And I set the following fields to these values:
  #     | Select activities | Assign1, Assign2, Forum1, Page1 |
  #   And I press "Save changes"

  # Scenario: New condition: Course dates
  #   And I am on "Course 1" course homepage
  #   And I navigate to "Automation" in current page administration
  #   And I open the autocomplete suggestions list
  #   And I click on "Template1" item in the autocomplete list
  #   And I click on "Add automation instance" "button" in the ".template-add-form" "css_element"
  #   And I set the field "insreference" to "temp1"
  #   Then I follow "Condition"
  #   And I click on "#id_override_triggeroperator" "css_element" in the "#pulse-condition-tab" "css_element"
  #   Then the field "Trigger operator" matches value "All"
  #   And I click on "#id_override_condition_coursedates_status" "css_element" in the "#condition-coursedates" "css_element"
  #   And I set the field "condition[coursedates][status]" to "All"
  #   And I click on "#id_override_condition_coursedates_type" "css_element" in the "#condition-coursedates" "css_element"
  #   And I set the field "condition[coursedates][type]" to "All"

  Scenario: New condition: Course group - No group
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
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

    # Instance Conditions - No Group
    And I click on ".action-edit" "css_element" in the "Template1" "table_row"
    Then I follow "Condition"
    And I click on "#id_override_triggeroperator" "css_element" in the "#pulse-condition-tab" "css_element"
    Then the field "Trigger operator" matches value "All"
    And I click on "#id_override_condition_coursegroup_status" "css_element" in the "#condition-coursegroup" "css_element"
    And I set the field "condition[coursegroup][status]" to "All"
    And I click on "#id_override_condition_coursegroup_type" "css_element" in the "#condition-coursegroup" "css_element"
    And I set the field "condition[coursegroup][type]" to "No group"

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
    And I set the field "pulsenotification_subject" to "No Group notification"
    And I click on "#id_override_pulsenotification_headercontent_editor" "css_element" in the "#fitem_id_pulsenotification_headercontent_editor" "css_element"
    And I click on "#header-email-vars-button" "css_element" in the ".mod-pulse-emailvars-toggle" "css_element"
    And I click on pulse "id_pulsenotification_headercontent_editor" editor
    And I click on "#Firstname" "css_element" in the ".Sender_field-placeholders" "css_element"
    And I press "Save changes"

    # Check the schedule for the instance for No group
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Course full name | Message type | Subject                | Full name      | Time created          | Scheduled time        | Status |
      | Course 1         | Template1    | No Group notification  | Teacher User 1 | ##now##%A, %d %B %Y##	| ##now##%A, %d %B %Y## | Queued |
    And "student User 4" "table_row" should not exist
    And I close all opened windows

    # # Upcoming Trigger in Conditions - No group
    # Instance Conditions - No Group
    And I click on ".action-edit" "css_element" in the "Template1" "table_row"
    Then I follow "Condition"

    # Notification for Newly enrolled user
    # And I click on "#id_override_condition_coursegroup_status" "css_element" in the "#condition-coursegroup" "css_element"
    And I set the field "condition[coursegroup][status]" to "Upcoming"
    And I click on "Notification" "link" in the "#automation-tabs" "css_element"
    And I click on ".badge" "css_element" in the "#fitem_id_pulsenotification_recipients .form-autocomplete-selection" "css_element"
    And I set the field "Recipients" in the "#pulse-action-notification" "css_element" to "Student"
    And I set the field "pulsenotification_subject" to "No Group upcoming notification "
    And I press "Save changes"
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | student4 | student   | User 4   | student4@test.com |
    And the following "course enrolments" exist:
      | user     | course    | role     |
      | student4 | C1        | student  |

    # Check the schedule for the instance for No group
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Course full name | Message type | Subject                        | Full name      | Time created          | Scheduled time        | Status |
      | Course 1         | Template1    | No Group upcoming notification | Teacher User 1 | ##now##%A, %d %B %Y## | ##now##%A, %d %B %Y## | Queued |
      | Course 1         | Template1    | No Group upcoming notification | student User 4 | ##now##%A, %d %B %Y## | ##now##%A, %d %B %Y## | sent   |
    And "student User 1" "table_row" should not exist
    And I close all opened windows

  Scenario: New condition: Course group - Any group
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    And I open the autocomplete suggestions list
    And I click on "Template1" item in the autocomplete list
    And I click on "Add automation instance" "button" in the ".template-add-form" "css_element"
    And I set the field "insreference" to "temp1"
    And I click on "#id_override_frequencylimit" "css_element" in the "#fitem_id_frequencylimit" "css_element"
    And I set the field "frequencylimit" to "1"
    And I press "Save changes"

    # Check the schedule for the instance for Any group
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And I should see "Nothing to display" in the ".reportbuilder-wrapper" "css_element"
    And I close all opened windows

    # Instance Conditions - No Group
    And I click on ".action-edit" "css_element" in the "Template1" "table_row"
    Then I follow "Condition"
    And I click on "#id_override_triggeroperator" "css_element" in the "#pulse-condition-tab" "css_element"
    Then the field "Trigger operator" matches value "All"
    And I click on "#id_override_condition_coursegroup_status" "css_element" in the "#condition-coursegroup" "css_element"
    And I set the field "condition[coursegroup][status]" to "All"
    And I click on "#id_override_condition_coursegroup_type" "css_element" in the "#condition-coursegroup" "css_element"
    And I set the field "condition[coursegroup][type]" to "Any group"

    # Instance Notifications
    And I click on "Notification" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_pulsenotification_actionstatus" "css_element" in the "#fitem_id_pulsenotification_actionstatus" "css_element"
    And I set the field "id_pulsenotification_actionstatus" to "Enabled"
    And I click on "#id_override_pulsenotification_sender" "css_element" in the "#fitem_id_pulsenotification_sender" "css_element"
    And I set the field "pulsenotification_sender" to "Group teacher"
    And I click on "#id_override_pulsenotification_recipients" "css_element" in the "#fitem_id_pulsenotification_recipients" "css_element"
    And I set the field "Recipients" in the "#pulse-action-notification" "css_element" to "Student, Teacher"

    And I click on "#id_override_pulsenotification_subject" "css_element" in the "#fitem_id_pulsenotification_subject" "css_element"
    And I set the field "pulsenotification_subject" to "Any Group notification"
    And I click on "#id_override_pulsenotification_headercontent_editor" "css_element" in the "#fitem_id_pulsenotification_headercontent_editor" "css_element"
    And I click on "#header-email-vars-button" "css_element" in the ".mod-pulse-emailvars-toggle" "css_element"
    And I click on pulse "id_pulsenotification_headercontent_editor" editor
    And I click on "#completionstatus" "css_element" in the ".others_field-placeholders" "css_element"
    And I press "Save changes"

    # Check the schedule for the instance for Any group
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Course full name | Message type | Subject                 | Full name      | Time created          | Scheduled time        | Status |
      | Course 1         | Template1    | Any Group notification  | Teacher User 1 | ##now##%A, %d %B %Y## | ##now##%A, %d %B %Y## | Queued |
      | Course 1         | Template1    | Any Group notification  | Teacher User 1 | ##now##%A, %d %B %Y## | ##now##%A, %d %B %Y## | Queued |
      | Course 1         | Template1    | Any Group notification  | student User 1 | ##now##%A, %d %B %Y## | ##now##%A, %d %B %Y## | Queued |
      | Course 1         | Template1    | Any Group notification  | student User 2 | ##now##%A, %d %B %Y## | ##now##%A, %d %B %Y## | Queued |
    And I click on ".pulse-automation-info-block" "css_element" in the "student User 2" "table_row"
    And I should see "enrolled" in the "Preview" "dialogue"
    And I close all opened windows

  Scenario: New condition: Course group - Selected group
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    And I open the autocomplete suggestions list
    And I click on "Template1" item in the autocomplete list
    And I click on "Add automation instance" "button" in the ".template-add-form" "css_element"
    And I set the field "insreference" to "temp1"
    And I click on "#id_override_frequencylimit" "css_element" in the "#fitem_id_frequencylimit" "css_element"
    And I set the field "frequencylimit" to "1"
    And I press "Save changes"

    # Check the schedule for the instance for Selected group
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And I should see "Nothing to display" in the ".reportbuilder-wrapper" "css_element"
    And I close all opened windows

    # Instance Conditions - Selected Group
    And I click on ".action-edit" "css_element" in the "Template1" "table_row"
    Then I follow "Condition"
    And I click on "#id_override_triggeroperator" "css_element" in the "#pulse-condition-tab" "css_element"
    Then the field "Trigger operator" matches value "All"
    And I click on "#id_override_condition_coursegroup_status" "css_element" in the "#condition-coursegroup" "css_element"
    And I set the field "condition[coursegroup][status]" to "All"
    And I click on "#id_override_condition_coursegroup_type" "css_element" in the "#condition-coursegroup" "css_element"
    And I set the field "condition[coursegroup][type]" to "Selected group"
    And I open the autocomplete suggestions list in the "#fitem_id_condition_coursegroup_groups" "css_element"
    And I click on "Group 2" item in the autocomplete list

    # Instance Notifications
    And I click on "Notification" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_pulsenotification_actionstatus" "css_element" in the "#fitem_id_pulsenotification_actionstatus" "css_element"
    And I set the field "id_pulsenotification_actionstatus" to "Enabled"
    And I click on "#id_override_pulsenotification_sender" "css_element" in the "#fitem_id_pulsenotification_sender" "css_element"
    And I set the field "pulsenotification_sender" to "Group teacher"
    And I click on "#id_override_pulsenotification_recipients" "css_element" in the "#fitem_id_pulsenotification_recipients" "css_element"
    And I set the field "Recipients" in the "#pulse-action-notification" "css_element" to "Student, Teacher"

    And I click on "#id_override_pulsenotification_subject" "css_element" in the "#fitem_id_pulsenotification_subject" "css_element"
    And I set the field "pulsenotification_subject" to "Selected Group notification"
    And I click on "#id_override_pulsenotification_headercontent_editor" "css_element" in the "#fitem_id_pulsenotification_headercontent_editor" "css_element"
    And I click on "#header-email-vars-button" "css_element" in the ".mod-pulse-emailvars-toggle" "css_element"
    And I click on pulse "id_pulsenotification_headercontent_editor" editor
    And I click on "#Summary" "css_element" in the ".Course_field-placeholders" "css_element"
    And I press "Save changes"

    # Check the schedule for the instance for No group
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Course full name | Message type | Subject                      | Full name      | Time created          | Scheduled time   | Status |
      | Course 1         | Template1    | Selected Group notification  | Teacher User 1 | ##now##%A, %d %B %Y## | ##now##%A, %d %B %Y## | Queued |
      | Course 1         | Template1    | Selected Group notification  | student User 2 | ##now##%A, %d %B %Y## | ##now##%A, %d %B %Y## | Queued |
    And I click on ".pulse-automation-info-block" "css_element" in the "student User 2" "table_row"
    And I should see "Test course 1" in the "Preview" "dialogue"
    And I close all opened windows

  Scenario: New condition: Selected grouping - course group
    And I navigate to "Plugins > Activity modules > Pulse > Pulse notifications" in site administration
    And I set the field "s_pulseaction_notification_recipients_custom" to "Aaron, aaron123_sl@gmail.com"
    And I press "Save changes"
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    And I open the autocomplete suggestions list
    And I click on "Template1" item in the autocomplete list
    And I click on "Add automation instance" "button" in the ".template-add-form" "css_element"
    And I set the field "insreference" to "temp1"
    And I click on "#id_override_frequencylimit" "css_element" in the "#fitem_id_frequencylimit" "css_element"
    And I set the field "frequencylimit" to "1"
    And I press "Save changes"

    # Check the schedule for the instance for Selected group
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And I should see "Nothing to display" in the ".reportbuilder-wrapper" "css_element"
    And I close all opened windows

    # Instance Conditions - Selected Group
    And I click on ".action-edit" "css_element" in the "Template1" "table_row"
    Then I follow "Condition"
    And I click on "#id_override_triggeroperator" "css_element" in the "#pulse-condition-tab" "css_element"
    Then the field "Trigger operator" matches value "All"
    And I click on "#id_override_condition_coursegroup_status" "css_element" in the "#condition-coursegroup" "css_element"
    And I set the field "condition[coursegroup][status]" to "All"
    And I click on "#id_override_condition_coursegroup_type" "css_element" in the "#condition-coursegroup" "css_element"
    And I set the field "condition[coursegroup][type]" to "Selected grouping"
    And I open the autocomplete suggestions list in the "#fitem_id_condition_coursegroup_groupings" "css_element"
    And I click on "Grouping 1" item in the autocomplete list

    # Instance Notifications
    And I click on "Notification" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_pulsenotification_actionstatus" "css_element" in the "#fitem_id_pulsenotification_actionstatus" "css_element"
    And I set the field "id_pulsenotification_actionstatus" to "Enabled"
    And I click on "#id_override_pulsenotification_sender" "css_element" in the "#fitem_id_pulsenotification_sender" "css_element"
    And I set the field "pulsenotification_sender" to "Group teacher"
    And I click on "#id_override_pulsenotification_recipients" "css_element" in the "#fitem_id_pulsenotification_recipients" "css_element"
    And I set the field "Recipients" in the "#pulse-action-notification" "css_element" to "Aaron"

    And I click on "#id_override_pulsenotification_subject" "css_element" in the "#fitem_id_pulsenotification_subject" "css_element"
    And I set the field "pulsenotification_subject" to "Selected Grouping notification"
    And I click on "#id_override_pulsenotification_headercontent_editor" "css_element" in the "#fitem_id_pulsenotification_headercontent_editor" "css_element"
    And I click on "#header-email-vars-button" "css_element" in the ".mod-pulse-emailvars-toggle" "css_element"
    And I click on pulse "id_pulsenotification_headercontent_editor" editor
    And I click on "#Summary" "css_element" in the ".Course_field-placeholders" "css_element"
    And I press "Save changes"

    # Check the schedule for the instance for Selected grouping
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Course full name | Message type | Subject                        | Full name      | Time created          | Scheduled time        | Status |
      | Course 1         | Template1    | Selected Grouping notification |                | ##now##%A, %d %B %Y## | ##now##%A, %d %B %Y## | Queued |
    And I click on ".pulse-automation-info-block" "css_element" in the "Course 1" "table_row"
    And I should see "Test course 1" in the "Preview" "dialogue"
    And I close all opened windows

    # # Upcoming Trigger in Conditions - Grouping
    # Instance Conditions - Grouping
    And I click on ".action-edit" "css_element" in the "Template1" "table_row"
    Then I follow "Condition"

    # Notification for Newly enrolled user
    # And I click on "#id_override_condition_coursegroup_status" "css_element" in the "#condition-coursegroup" "css_element"
    And I set the field "condition[coursegroup][status]" to "Upcoming"
    And I click on "Notification" "link" in the "#automation-tabs" "css_element"
    And I click on ".badge" "css_element" in the "#fitem_id_pulsenotification_recipients .form-autocomplete-selection" "css_element"
    And I set the field "Recipients" in the "#pulse-action-notification" "css_element" to "Student"
    And I set the field "pulsenotification_subject" to "Grouping upcoming notification "
    And I press "Save changes"
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | student4 | student   | User 4   | student4@test.com |
    And the following "course enrolments" exist:
      | user     | course    | role     |
      | student4 | C1        | student  |

    # Check the schedule for the instance for Grouping
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Course full name | Message type | Subject                        | Full name      | Time created                    | Scheduled time                  | Status |
      | Course 1         | Template1    | Grouping upcoming notification |  | ##now##%A, %d %B %Y## | ##now##%A, %d %B %Y## | Queued |
      | Course 1         | Template1    | Grouping upcoming notification |  | ##now##%A, %d %B %Y## | ##now##%A, %d %B %Y## | Queued |
    And "student User 1" "table_row" should not exist
    And I close all opened windows

  Scenario: New condition: Activity completion enhancement
    And I navigate to "Plugins > Activity modules > Pulse > Pulse notifications" in site administration
    And I set the field "s_pulseaction_notification_recipients_custom" to "Aaron, aaron123_sl@gmail.com"
    And I press "Save changes"
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    And I open the autocomplete suggestions list
    And I click on "Template1" item in the autocomplete list
    And I click on "Add automation instance" "button" in the ".template-add-form" "css_element"
    And I set the field "insreference" to "temp1"
    And I click on "#id_override_frequencylimit" "css_element" in the "#fitem_id_frequencylimit" "css_element"
    And I set the field "frequencylimit" to "1"
    And I press "Save changes"

    # Check the schedule for the instance for Selected group
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And I should see "Nothing to display" in the ".reportbuilder-wrapper" "css_element"
    And I close all opened windows

    # Instance Conditions - Selected Group
    And I click on ".action-edit" "css_element" in the "Template1" "table_row"
    Then I follow "Condition"
    And I click on "#id_override_triggeroperator" "css_element" in the "#pulse-condition-tab" "css_element"
    Then the field "Trigger operator" matches value "All"
    And I click on "#id_override_condition_coursegroup_status" "css_element" in the "#condition-coursegroup" "css_element"
    And I set the field "condition[coursegroup][status]" to "All"
    And I click on "#id_override_condition_coursegroup_type" "css_element" in the "#condition-coursegroup" "css_element"
    And I set the field "condition[coursegroup][type]" to "Selected grouping"
    And I open the autocomplete suggestions list in the "#fitem_id_condition_coursegroup_groupings" "css_element"
    And I click on "Grouping 1" item in the autocomplete list

    # Instance Notifications
    And I click on "Notification" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_pulsenotification_actionstatus" "css_element" in the "#fitem_id_pulsenotification_actionstatus" "css_element"
    And I set the field "id_pulsenotification_actionstatus" to "Enabled"
    And I click on "#id_override_pulsenotification_sender" "css_element" in the "#fitem_id_pulsenotification_sender" "css_element"
    And I set the field "pulsenotification_sender" to "Group teacher"
    And I click on "#id_override_pulsenotification_recipients" "css_element" in the "#fitem_id_pulsenotification_recipients" "css_element"
    And I set the field "Recipients" in the "#pulse-action-notification" "css_element" to "Aaron"

    And I click on "#id_override_pulsenotification_subject" "css_element" in the "#fitem_id_pulsenotification_subject" "css_element"
    And I set the field "pulsenotification_subject" to "Selected Grouping notification"
    And I click on "#id_override_pulsenotification_headercontent_editor" "css_element" in the "#fitem_id_pulsenotification_headercontent_editor" "css_element"
    And I click on "#header-email-vars-button" "css_element" in the ".mod-pulse-emailvars-toggle" "css_element"
    And I click on pulse "id_pulsenotification_headercontent_editor" editor
    And I click on "#Summary" "css_element" in the ".Course_field-placeholders" "css_element"
    And I press "Save changes"

    # Check the schedule for the instance for Selected grouping
    And I click on "#notification-action-report" "css_element" in the "Template1" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Course full name | Message type | Subject                        | Full name      | Time created          | Scheduled time        | Status |
      | Course 1         | Template1    | Selected Grouping notification |                | ##now##%A, %d %B %Y## | ##now##%A, %d %B %Y## | Queued |
    And I click on ".pulse-automation-info-block" "css_element" in the "Course 1" "table_row"
    And I should see "Test course 1" in the "Preview" "dialogue"
    And I close all opened windows
