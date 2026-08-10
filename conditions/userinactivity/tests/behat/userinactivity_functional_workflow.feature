@mod @mod_pulse @pulse_triggercondition @pulsecondition_userinactivity @javascript
Feature: User inactivity trigger condition - Functional workflow tests
  In order to monitor student engagement and send timely notifications
  As a teacher or admin
  I need to verify that user inactivity conditions trigger notifications correctly based on student behavior

  Background:
    Given the following "course" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1        | 0        | 1                |
    And the following "activities" exist:
      | activity | name        | course | idnumber | completion |
      | assign   | Assign1     | C1     | assign1  | 1          |
      | assign   | Assign2     | C1     | assign2  | 1          |
      | forum    | Forum1      | C1     | forum1   | 1          |
      | page     | TestPage 01 | C1     | page1    | 1          |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | Student   | User 1   | student1@test.com |
      | student2 | Student   | User 2   | student2@test.com |
      | student3 | Student   | User 3   | student3@test.com |
      | teacher1 | Teacher   | User 1   | teacher1@test.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
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
      | Assign1     | 1 |
      | Assign2     | 1 |
    And I press "Save changes"
  Scenario: User inactivity based on course access
    # Create automation template with notification
    Given I create automation template with the following fields to these values:
      | Title     | Access Inactivity Alert |
      | Reference | accessalert             |
    # Configure instance with condition and notification
    And I log in as "student2"
    And I am on "Course 1" course homepage
    Then I change user enrollment time to "-10" minutes for "student2" in "C1"
    Then I change user enrollment time to "-10" minutes for "student1" in "C1"
    #Then I set last course access to "-3" minutes for "student2" in "C1"
    And I log out
    Then I log in as "admin"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    When I open the autocomplete suggestions list
    And I click on "Access Inactivity Alert" item in the autocomplete list
    Then I press "Add automation instance"
    And I set the following fields to these values:
      | insreference | accessinactive1 |
    # Configure inactivity condition - 5 minutes of no course access
    Then I follow "Condition"
    And I click on "#id_override_condition_userinactivity_status" "css_element"
    And I set the field "condition[userinactivity][status]" to "All"
    And I click on "#id_override_condition_userinactivity_type" "css_element"
    And I set the field "condition[userinactivity][type]" to "Based on access"
    And I click on "#id_override_condition_userinactivity_inactivityperiod" "css_element"
    And I set the field "condition[userinactivity][inactivityperiod][number]" to "5"
    And I set the field "condition[userinactivity][inactivityperiod][timeunit]" to "minutes"
    # Configure notification action
    And I click on "Notification" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_pulsenotification_actionstatus" "css_element"
    And I set the field "id_pulsenotification_actionstatus" to "Enabled"
    And I click on "#id_override_pulsenotification_sender" "css_element"
    And I set the field "pulsenotification_sender" to "Course teacher"

    And I click on "#id_override_pulsenotification_recipients" "css_element" in the "#fitem_id_pulsenotification_recipients" "css_element"
    And I set the field "Recipients" in the "#pulse-action-notification" "css_element" to "Student"

    And I click on "#id_override_pulsenotification_subject" "css_element"
    And I set the field "pulsenotification_subject" to "Course Access Reminder"
    And I click on "#id_override_pulsenotification_headercontent_editor" "css_element"
    And I set the field "pulsenotification_headercontent_editor[text]" to "Hi {User_firstname}, you haven't accessed the course recently."
    And I press "Save changes"
    # Simulate student2 accessing the course (active user)
    And I log out

    # Run scheduled task to check inactivity and trigger notifications
    And I log in as "admin"
    And I run all adhoc tasks
    # Verify notification report
    And I am on "Course 1" course homepage
    And I follow "Automation"
    And I click on "#notification-action-report" "css_element" in the "Access Inactivity Alert" "table_row"
    And I switch to a second window
    # Student1 should have notification queued (inactive - no access)
    And the following should exist in the "reportbuilder-table" table:
      | Full name      | Subject                 | Status |
      | Student User 1 | Course Access Reminder  | Queued |
    # Student2 should NOT have notification (active - accessed course)
    And the following should not exist in the "reportbuilder-table" table:
      | Full name      | Subject                 |
      | Student User 2 | Course Access Reminder  |
    And I close all opened windows

  Scenario: User inactivity based on activity completion
    # Create automation template
    Given I create automation template with the following fields to these values:
      | Title     | Completion Inactivity Alert |
      | Reference | completionalert             |
    # Setup enrollment times
    And I log in as "admin"
    Then I change user enrollment time to "-10" minutes for "student1" in "C1"
    Then I change user enrollment time to "-10" minutes for "student2" in "C1"
    Then I change user enrollment time to "-10" minutes for "student3" in "C1"
    And I log out

    And I log in as "student2"
    And I am on "Course 1" course homepage
    And the manual completion button of "Assign1" is displayed as "Mark as done"
    And I toggle the manual completion state of "Assign1"
    And the manual completion button of "Assign2" is displayed as "Mark as done"
    And I toggle the manual completion state of "Assign2"
    And I log out

    And I log in as "admin"
    # Configure instance
    And I am on "Course 1" course homepage
    And I follow "Automation"
    When I open the autocomplete suggestions list
    And I click on "Completion Inactivity Alert" item in the autocomplete list
    Then I press "Add automation instance"
    And I set the following fields to these values:
      | insreference | completioninactive1 |
    # Configure condition - 5 minutes without activity completion
    Then I follow "Condition"
    And I click on "#id_override_condition_userinactivity_status" "css_element"
    And I set the field "condition[userinactivity][status]" to "All"
    And I click on "#id_override_condition_userinactivity_type" "css_element"
    And I set the field "condition[userinactivity][type]" to "Based on activity completion"
    And I click on "#id_override_condition_userinactivity_includedactivities" "css_element"
    And I set the field "condition[userinactivity][includedactivities]" to "All activities"
    And I click on "#id_override_condition_userinactivity_inactivityperiod" "css_element"
    And I set the field "condition[userinactivity][inactivityperiod][number]" to "5"
    And I set the field "condition[userinactivity][inactivityperiod][timeunit]" to "minutes"
    # Configure notification
    And I click on "Notification" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_pulsenotification_actionstatus" "css_element"
    And I set the field "id_pulsenotification_actionstatus" to "Enabled"
    And I click on "#id_override_pulsenotification_sender" "css_element"
    And I set the field "pulsenotification_sender" to "Course teacher"
    And I click on "#id_override_pulsenotification_recipients" "css_element" in the "#fitem_id_pulsenotification_recipients" "css_element"
    And I set the field "Recipients" in the "#pulse-action-notification" "css_element" to "Student"
    And I click on "#id_override_pulsenotification_subject" "css_element"
    And I set the field "pulsenotification_subject" to "Complete Activities Reminder"
    And I click on "#id_override_pulsenotification_headercontent_editor" "css_element"
    And I set the field "pulsenotification_headercontent_editor[text]" to "Hi {User_firstname}, please complete course activities."
    And I press "Save changes"
    # Simulate student2 completing activities (active user)
    # Wait for inactivity period
    And I wait "10" seconds
    And I run all adhoc tasks
    # Verify notification report
    And I am on "Course 1" course homepage
    And I follow "Automation"
    And I click on "#notification-action-report" "css_element" in the "Completion Inactivity Alert" "table_row"
    And I switch to a second window
    # Student1 should have notification (inactive - no completions)
    And the following should exist in the "reportbuilder-table" table:
      | Full name      | Subject                        | Status |
      | Student User 1 | Complete Activities Reminder   | Queued |
    # Student2 should NOT have notification (active - completed activities)
    And the following should not exist in the "reportbuilder-table" table:
      | Full name      | Subject                      |
      | Student User 2 | Complete Activities Reminder |
    # Student3 should also have notification (inactive)
    And the following should exist in the "reportbuilder-table" table:
      | Full name      | Subject                        | Status |
      | Student User 3 | Complete Activities Reminder   | Queued |
    And I close all opened windows

  Scenario: User inactivity with completion relevant activities
    # Create automation template
    Given I create automation template with the following fields to these values:
      | Title     | Relevant Activities Alert |
      | Reference | relevantalert             |
    # Setup enrollment times
    And I log in as "admin"
    Then I change user enrollment time to "-10" minutes for "student1" in "C1"
    Then I change user enrollment time to "-10" minutes for "student2" in "C1"
    Then I change user enrollment time to "-10" minutes for "student3" in "C1"

    # Student2 completes course completion relevant activities
    And I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And the manual completion button of "Assign1" is displayed as "Mark as done"
    And I toggle the manual completion state of "Assign1"
    And I log out

    And I log in as "student1"
    And I am on "Course 1" course homepage
    And the manual completion button of "TestPage 01" is displayed as "Mark as done"
    And I toggle the manual completion state of "TestPage 01"
    And I log out

    And I log in as "admin"
    # Configure instance
    And I am on "Course 1" course homepage
    And I follow "Automation"
    When I open the autocomplete suggestions list
    And I click on "Relevant Activities Alert" item in the autocomplete list
    Then I press "Add automation instance"
    And I set the following fields to these values:
      | insreference | relevant1 |
    # Configure condition - Completion relevant activities only
    Then I follow "Condition"
    And I click on "#id_override_condition_userinactivity_status" "css_element"
    And I set the field "condition[userinactivity][status]" to "All"
    And I click on "#id_override_condition_userinactivity_type" "css_element"
    And I set the field "condition[userinactivity][type]" to "Based on activity completion"
    And I click on "#id_override_condition_userinactivity_includedactivities" "css_element"
    And I set the field "condition[userinactivity][includedactivities]" to "Completion relevant activities"
    And I click on "#id_override_condition_userinactivity_inactivityperiod" "css_element"
    And I set the field "condition[userinactivity][inactivityperiod][number]" to "5"
    And I set the field "condition[userinactivity][inactivityperiod][timeunit]" to "minutes"
    # Configure notification
    And I click on "Notification" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_pulsenotification_actionstatus" "css_element"
    And I set the field "id_pulsenotification_actionstatus" to "Enabled"
    And I click on "#id_override_pulsenotification_sender" "css_element"
    And I set the field "pulsenotification_sender" to "Course teacher"
    And I click on "#id_override_pulsenotification_recipients" "css_element" in the "#fitem_id_pulsenotification_recipients" "css_element"
    And I set the field "Recipients" in the "#pulse-action-notification" "css_element" to "Student"
    And I click on "#id_override_pulsenotification_subject" "css_element"
    And I set the field "pulsenotification_subject" to "Completion Criteria Reminder"
    And I click on "#id_override_pulsenotification_headercontent_editor" "css_element"
    And I set the field "pulsenotification_headercontent_editor[text]" to "Complete required course activities."
    And I press "Save changes"

    And I run all adhoc tasks
    # Verify notifications
    And I am on "Course 1" course homepage
    And I follow "Automation"
    And I click on "#notification-action-report" "css_element" in the "Relevant Activities Alert" "table_row"
    And I switch to a second window
    # Student1 and student3 should have notifications (no completions)
    And the following should exist in the "reportbuilder-table" table:
      | Full name      | Subject                      | Status |
      | Student User 1 | Completion Criteria Reminder | Queued |
      | Student User 3 | Completion Criteria Reminder | Queued |
    # Student2 should NOT have notification (completed relevant activities)
    And the following should not exist in the "reportbuilder-table" table:
      | Full name      | Subject                      |
      | Student User 2 | Completion Criteria Reminder |
    And I close all opened windows
  Scenario: User inactivity with require previous activity
    # Create automation template
    Given I create automation template with the following fields to these values:
      | Title     | Previous Activity Required |
      | Reference | prevrequired               |
    # Setup enrollment times
    And I log in as "admin"
    Then I change user enrollment time to "-10" minutes for "student1" in "C1"
    Then I change user enrollment time to "-10" minutes for "student2" in "C1"
    Then I change user enrollment time to "-10" minutes for "student3" in "C1"
    Then I set last course access to "-10" minutes for "student2" in "C1"
    Then I set last course access to "-10" minutes for "student1" in "C1"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I log out
    And I wait "62" seconds
    # Configure instance
    Then I log in as "admin"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    When I open the autocomplete suggestions list
    And I click on "Previous Activity Required" item in the autocomplete list
    Then I press "Add automation instance"
    And I set the following fields to these values:
      | insreference | prevreq1 |
    # Configure condition with previous activity requirement
    Then I follow "Condition"
    And I click on "#id_override_condition_userinactivity_status" "css_element"
    And I set the field "condition[userinactivity][status]" to "All"
    And I click on "#id_override_condition_userinactivity_type" "css_element"
    And I set the field "condition[userinactivity][type]" to "Based on access"
    And I click on "#id_override_condition_userinactivity_inactivityperiod" "css_element"
    And I set the field "condition[userinactivity][inactivityperiod][number]" to "1"
    And I set the field "condition[userinactivity][inactivityperiod][timeunit]" to "minutes"
    # Enable "Require previous activity"
    And I click on "#id_override_condition_userinactivity_requirepreviousactivity" "css_element"
    And I set the field "condition[userinactivity][requirepreviousactivity]" to "Yes"
    And I should see "Activity period"
    And I click on "#id_override_condition_userinactivity_activityperiod" "css_element"
    And I set the field "condition[userinactivity][activityperiod][number]" to "10"
    And I set the field "condition[userinactivity][activityperiod][timeunit]" to "minutes"
    # Configure notification
    And I click on "Notification" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_pulsenotification_actionstatus" "css_element"
    And I set the field "id_pulsenotification_actionstatus" to "Enabled"
    And I click on "#id_override_pulsenotification_sender" "css_element"
    And I set the field "pulsenotification_sender" to "Course teacher"
    And I click on "#id_override_pulsenotification_recipients" "css_element" in the "#fitem_id_pulsenotification_recipients" "css_element"
    And I set the field "Recipients" in the "#pulse-action-notification" "css_element" to "Student"
    And I click on "#id_override_pulsenotification_subject" "css_element"
    And I set the field "pulsenotification_subject" to "We Miss You"
    And I click on "#id_override_pulsenotification_headercontent_editor" "css_element"
    And I set the field "pulsenotification_headercontent_editor[text]" to "You were active before, please come back!"
    And I press "Save changes"
    # Run task
    #And I log in as "admin"
    And I run all adhoc tasks
    # Verify notifications
    And I am on "Course 1" course homepage
    And I follow "Automation"
    And I click on "#notification-action-report" "css_element" in the "Previous Activity Required" "table_row"
    And I switch to a second window
    # Student1 should get notification (was active, now inactive)
    And the following should exist in the "reportbuilder-table" table:
      | Full name      | Subject     | Status |
      | Student User 1 | We Miss You | Queued |
    # Student2 should NOT get notification (still not active)
    # Student3 should NOT get notification (never had previous activity)
    And the following should not exist in the "reportbuilder-table" table:
      | Full name      | Subject     |
      | Student User 3 | We Miss You |
      | Student User 2 | We Miss You |
    And I close all opened windows

  Scenario: Activity completion inactivity with require previous activity
    # Create automation template
    Given I create automation template with the following fields to these values:
      | Title     | Complete Workflow Test |
      | Reference | completeworkflow       |
    # Setup enrollment times
    And I log in as "admin"
    Then I change user enrollment time to "-10" minutes for "student1" in "C1"
    Then I change user enrollment time to "-10" minutes for "student2" in "C1"
    Then I change user enrollment time to "-10" minutes for "student3" in "C1"
    And I log out

    And I log in as "student1"
    And I am on "Course 1" course homepage
    And the manual completion button of "Assign1" is displayed as "Mark as done"
    And I toggle the manual completion state of "Assign1"
    And I log out
    Then I set activity completion time to "-6" minutes for "student1" on "assign1" in "C1"
    And I wait "62" seconds

    And I log in as "student2"
    And I am on "Course 1" course homepage
    And the manual completion button of "Assign1" is displayed as "Mark as done"
    And I toggle the manual completion state of "Assign1"
    And I log out
    And I log in as "admin"
    # Configure instance with all features
    And I am on "Course 1" course homepage
    And I follow "Automation"
    When I open the autocomplete suggestions list
    And I click on "Complete Workflow Test" item in the autocomplete list
    Then I press "Add automation instance"
    And I set the following fields to these values:
      | insreference | workflow1 |
    # Configure comprehensive condition
    Then I follow "Condition"
    And I click on "#id_override_condition_userinactivity_status" "css_element"
    And I set the field "condition[userinactivity][status]" to "All"
    And I click on "#id_override_triggeroperator" "css_element"
    And I set the field "Trigger operator" to "All"
    And I click on "#id_override_condition_userinactivity_type" "css_element"
    And I set the field "condition[userinactivity][type]" to "Based on activity completion"
    And I click on "#id_override_condition_userinactivity_includedactivities" "css_element"
    And I set the field "condition[userinactivity][includedactivities]" to "All activities"
    And I click on "#id_override_condition_userinactivity_inactivityperiod" "css_element"
    And I set the field "condition[userinactivity][inactivityperiod][number]" to "1"
    And I set the field "condition[userinactivity][inactivityperiod][timeunit]" to "minutes"
    # No previous activity requirement for this test
    And I click on "#id_override_condition_userinactivity_requirepreviousactivity" "css_element"
    And I set the field "condition[userinactivity][requirepreviousactivity]" to "Yes"
    And I should see "Activity period"
    And I click on "#id_override_condition_userinactivity_activityperiod" "css_element"
    And I set the field "condition[userinactivity][activityperiod][number]" to "10"
    And I set the field "condition[userinactivity][activityperiod][timeunit]" to "minutes"
    # Configure comprehensive notification
    And I click on "Notification" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_pulsenotification_actionstatus" "css_element"
    And I set the field "id_pulsenotification_actionstatus" to "Enabled"
    And I click on "#id_override_pulsenotification_sender" "css_element"
    And I set the field "pulsenotification_sender" to "Course teacher"
    And I click on "#id_override_pulsenotification_recipients" "css_element" in the "#fitem_id_pulsenotification_recipients" "css_element"
    And I set the field "Recipients" in the "#pulse-action-notification" "css_element" to "Student"
    And I click on "#id_override_pulsenotification_subject" "css_element"
    And I set the field "pulsenotification_subject" to "Activity Completion Alert"
    And I click on "#id_override_pulsenotification_headercontent_editor" "css_element"
    And I set the field "pulsenotification_headercontent_editor[text]" to "Hi {User_firstname} {User_lastname}, you haven't completed activities in {Course_fullname}."
    And I press "Save changes"
    Then I should see "Complete Workflow Test"
    # Test scenario: Student1 completes multiple activities (active)
    And I run all adhoc tasks
    # Verify notification scheduling and delivery
    And I am on "Course 1" course homepage
    And I follow "Automation"
    And I click on "#notification-action-report" "css_element" in the "Complete Workflow Test" "table_row"
    And I switch to a second window
    And ".reportbuilder-report" "css_element" should exist
    # Verify only inactive student (student3) gets notification
    And the following should exist in the "reportbuilder-table" table:
      | Full name      | Subject                    | Status |
      | Student User 1 | Activity Completion Alert  | Queued |
    # Verify active students (student1, student2) do NOT get notifications
    And the following should not exist in the "reportbuilder-table" table:
      | Full name      | Subject                   |
      | Student User 3 | Activity Completion Alert |
      | Student User 2 | Activity Completion Alert |
    And I close all opened windows
