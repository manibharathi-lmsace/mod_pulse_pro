@mod @mod_pulse @pulseaction_notification @pulse_delay_base @javascript
Feature: Delay base for automation notification scheduling
  In order to schedule notifications relative to different reference points
  As an admin or teacher
  I need to configure the delay base (instance, enrolment, or last condition time)
  for automation notifications so that the send-time is calculated correctly.

  Background:
    Given the following "course" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1        | 0        | 1                |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | student   | User 1   | student1@test.com |
      | student2 | student   | User 2   | student2@test.com |
      | teacher1 | Teacher   | User 1   | teacher1@test.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activities" exist:
      | activity | name    | course | idnumber | completion |
      | assign   | Assign1 | C1     | assign1  | 1          |
      | assign   | Assign2 | C1     | assign2  | 1          |
    And the following "cohorts" exist:
      | name     | idnumber |
      | Cohort 1 | CH1      |
    And the following "cohort members" exist:
      | user     | cohort |
      | student1 | CH1    |

  Scenario: Delay base field visible for After and Before modes
    Given I log in as "admin"
    Then I create automation template with the following fields to these values:
      | Title     | Delay Base UI Test |
      | Reference | delaybaseui        |
    And I click on ".action-edit" "css_element" in the "Delay Base UI Test" "table_row"
    And I enable pulse action "notification"
    And "#fitem_id_pulsenotification_delaybase" "css_element" should not be visible
    And I set the field "id_pulsenotification_notifydelay" to "After"
    And "#fitem_id_pulsenotification_delaybase" "css_element" should be visible
    And the "id_pulsenotification_delaybase" select box should contain "Instance base"
    And the "id_pulsenotification_delaybase" select box should contain "Enrolment base"
    And the "id_pulsenotification_delaybase" select box should contain "Last condition base"
    And I set the field "id_pulsenotification_notifydelay" to "None"
    And "#fitem_id_pulsenotification_delaybase" "css_element" should not be visible
    And I set the field "id_pulsenotification_notifydelay" to "Before"
    And "#fitem_id_pulsenotification_delaybase" "css_element" should be visible
    And I set the following fields in the "#pulse-action-notification" "css_element" to these values:
      | id_pulsenotification_delayduration_timeunit | days           |
      | pulsenotification_delayduration[number]     | 5              |
      | id_pulsenotification_delaybase              | Enrolment base |
      | Recipients                                  | Student        |
    And I press "Save changes"
    And I click on ".action-edit" "css_element" in the "Delay Base UI Test" "table_row"
    And I click on "Notification" "link" in the "#automation-tabs" "css_element"
    Then the field "id_pulsenotification_notifydelay" matches value "Before"
    And the field "id_pulsenotification_delaybase" matches value "Enrolment base"
    And I log out

  Scenario: Instance base: enrolment and cohort conditions queued
    Given I log in as "admin"
    Then I create automation template with the following fields to these values:
      | Title     | Instance Base Dual Condition |
      | Reference | instbasedualcond             |
    And I click on ".action-edit" "css_element" in the "Instance Base Dual Condition" "table_row"
    And I click on "Condition" "link" in the "#automation-tabs" "css_element"
    And I set the following fields to these values:
      | Trigger operator  | All |
      | User enrolment    | All |
      | Member in cohorts | All |
    And I enable pulse action "notification"
    And I set the following fields in the "#pulse-action-notification" "css_element" to these values:
      | id_pulsenotification_notifydelay              | After         |
      | id_pulsenotification_delayduration_timeunit   | days          |
      | pulsenotification_delayduration[number]       | 30            |
      | id_pulsenotification_delaybase                | Instance base |
      | Recipients                                    | Student       |
      | Subject                                       | Instance base dual condition test |
    And I press "Save changes"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    When I open the autocomplete suggestions list
    And I click on "Instance Base Dual Condition" item in the autocomplete list
    Then I click on "Add automation instance" "button"
    And I set the following fields to these values:
      | insreference | instbasedualcond |
    And I click on "Condition" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_condition_cohort_status" "css_element" in the "#fitem_id_condition_cohort_status" "css_element"
    And I set the field "Member in cohorts" to "All"
    And I click on "#fitem_id_condition_cohort_cohorts .form-autocomplete-downarrow" "css_element"
    And I click on "Cohort 1" item in the autocomplete list
    And I press "Save changes"
    And I run automation evaluation for "C1" course
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    Then I click on ".action-report#notification-action-report" "css_element" in the "Instance Base Dual Condition" "table_row"
    And I switch to a second window
    # student1 is enrolled AND a member of Cohort 1 – both conditions met, queued at instance base time.
    And the following should exist in the "reportbuilder-table" table:
      | Full name      | Subject                           | Status | Scheduled time               |
      | student User 1 | Instance base dual condition test | Queued | ##today +30 days##%d %B %Y## |
    # student2 is enrolled but NOT a member of any cohort – cohort condition fails, not queued.
    And the following should not exist in the "reportbuilder-table" table:
      | Full name      | Subject                           |
      | student User 2 | Instance base dual condition test |
    And I close all opened windows
    And I log out

  Scenario: Instance base: user inactivity access condition queued
    Given I log in as "admin"
    And I change user enrollment time to "-10" minutes for "student1" in "C1"
    And I change user enrollment time to "-10" minutes for "student2" in "C1"
    And I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I log out
    Then I log in as "admin"
    Then I create automation template with the following fields to these values:
      | Title     | Inactivity Access Delay |
      | Reference | inactivityaccessdelay   |
    And I click on ".action-edit" "css_element" in the "Inactivity Access Delay" "table_row"
    And I enable pulse action "notification"
    And I set the following fields in the "#pulse-action-notification" "css_element" to these values:
      | id_pulsenotification_notifydelay              | After         |
      | id_pulsenotification_delayduration_timeunit   | days          |
      | pulsenotification_delayduration[number]       | 5             |
      | id_pulsenotification_delaybase                | Instance base |
      | Recipients                                    | Student       |
      | Subject                                       | Inactivity access delay test |
    And I press "Save changes"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    When I open the autocomplete suggestions list
    And I click on "Inactivity Access Delay" item in the autocomplete list
    Then I click on "Add automation instance" "button"
    And I set the following fields to these values:
      | insreference | inactivityaccessdelay |
    And I click on "Condition" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_condition_userinactivity_status" "css_element"
    And I set the field "condition[userinactivity][status]" to "All"
    And I click on "#id_override_condition_userinactivity_type" "css_element"
    And I set the field "condition[userinactivity][type]" to "Based on access"
    And I click on "#id_override_condition_userinactivity_inactivityperiod" "css_element"
    And I set the field "condition[userinactivity][inactivityperiod][number]" to "5"
    And I set the field "condition[userinactivity][inactivityperiod][timeunit]" to "minutes"
    And I press "Save changes"
    And I run automation evaluation for "C1" course
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    Then I click on ".action-report#notification-action-report" "css_element" in the "Inactivity Access Delay" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Full name      | Subject                      | Status | Scheduled time              |
      | student User 1 | Inactivity access delay test | Queued | ##today +5 days##%d %B %Y## |
    And the following should not exist in the "reportbuilder-table" table:
      | Full name      | Subject                      |
      | student User 2 | Inactivity access delay test |
    And I close all opened windows
    And I log out

  Scenario: Enrolment base: notification queued within delay period
    Given I log in as "admin"
    Then I create automation template with the following fields to these values:
      | Title     | Enrolment Delay Queued |
      | Reference | enroldelayqueued       |
    And I click on ".action-edit" "css_element" in the "Enrolment Delay Queued" "table_row"
    And I click on "Condition" "link" in the "#automation-tabs" "css_element"
    And I set the following fields to these values:
      | Trigger operator | All |
      | User enrolment   | All |
    And I enable pulse action "notification"
    And I set the following fields in the "#pulse-action-notification" "css_element" to these values:
      | id_pulsenotification_notifydelay              | After          |
      | id_pulsenotification_delayduration_timeunit   | days           |
      | pulsenotification_delayduration[number]       | 30             |
      | id_pulsenotification_delaybase                | Enrolment base |
      | Recipients                                    | Student        |
      | Subject                                       | Enrolment delay queued test |
    And I press "Save changes"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    When I open the autocomplete suggestions list
    And I click on "Enrolment Delay Queued" item in the autocomplete list
    Then I click on "Add automation instance" "button"
    And I set the following fields to these values:
      | insreference | enroldelayqueued |
    And I press "Save changes"
    And I run automation evaluation for "C1" course
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    Then I click on ".action-report#notification-action-report" "css_element" in the "Enrolment Delay Queued" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Full name      | Subject                     | Status | Scheduled time               |
      | student User 1 | Enrolment delay queued test | Queued | ##today +30 days##%d %B %Y## |
      | student User 2 | Enrolment delay queued test | Queued | ##today +30 days##%d %B %Y## |
    And I close all opened windows
    And I log out

  Scenario: Enrolment base: notification sent when delay period elapsed
    Given I log in as "admin"
    Then I create automation template with the following fields to these values:
      | Title     | Enrolment Delay Sent |
      | Reference | enroldelaysent       |
    And I click on ".action-edit" "css_element" in the "Enrolment Delay Sent" "table_row"
    And I click on "Condition" "link" in the "#automation-tabs" "css_element"
    And I set the following fields to these values:
      | Trigger operator | All |
      | User enrolment   | All |
    And I enable pulse action "notification"
    And I set the following fields in the "#pulse-action-notification" "css_element" to these values:
      | id_pulsenotification_notifydelay              | After          |
      | id_pulsenotification_delayduration_timeunit   | days           |
      | pulsenotification_delayduration[number]       | 2              |
      | id_pulsenotification_delaybase                | Enrolment base |
      | Recipients                                    | Student        |
      | Subject                                       | Enrolment delay sent test |
    And I press "Save changes"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    When I open the autocomplete suggestions list
    And I click on "Enrolment Delay Sent" item in the autocomplete list
    Then I click on "Add automation instance" "button"
    And I set the following fields to these values:
      | insreference | enroldelaysent |
    And I press "Save changes"
    And I change user enrollment time to "-5" days for "student1" in "C1"
    And I run automation evaluation for "C1" course
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    Then I click on ".action-report#notification-action-report" "css_element" in the "Enrolment Delay Sent" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Full name      | Subject                   | Status |
      | student User 1 | Enrolment delay sent test | sent   |
    And the following should exist in the "reportbuilder-table" table:
      | Full name      | Subject                   | Status |
      | student User 2 | Enrolment delay sent test | Queued |
    And I close all opened windows
    And I log out

  Scenario: Enrolment base: course start Before delay sends notification
    Given I log in as "admin"
    And I set course "C1" start date to "+7" days
    Then I create automation template with the following fields to these values:
      | Title     | Course Dates Before Delay |
      | Reference | coursedatesbeforedelay    |
    And I click on ".action-edit" "css_element" in the "Course Dates Before Delay" "table_row"
    And I enable pulse action "notification"
    And I set the following fields in the "#pulse-action-notification" "css_element" to these values:
      | id_pulsenotification_notifydelay              | Before           |
      | id_pulsenotification_delayduration_timeunit   | days             |
      | pulsenotification_delayduration[number]       | 2                |
      | id_pulsenotification_delaybase                | Enrolment base   |
      | Recipients                                    | Student          |
      | Subject                                       | Course dates before delay test |
    And I press "Save changes"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    When I open the autocomplete suggestions list
    And I click on "Course Dates Before Delay" item in the autocomplete list
    Then I click on "Add automation instance" "button"
    And I set the following fields to these values:
      | insreference | coursedatesbeforedelay |
    And I click on "Condition" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_condition_coursedates_status" "css_element" in the "#fitem_id_condition_coursedates_status" "css_element"
    And I set the field "condition[coursedates][status]" to "All"
    And I click on "#id_override_condition_coursedates_type" "css_element" in the "#fitem_id_condition_coursedates_type" "css_element"
    And I set the field "condition[coursedates][type]" to "Course start date"
    And I press "Save changes"
    And I run automation evaluation for "C1" course
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    Then I click on ".action-report#notification-action-report" "css_element" in the "Course Dates Before Delay" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Full name      | Subject                        | Status | Scheduled time               |
      | student User 1 | Course dates before delay test | sent   | ##today -2 days##%d %B %Y##  |
      | student User 2 | Course dates before delay test | sent   | ##today -2 days##%d %B %Y##  |
    And I close all opened windows
    And I log out

  Scenario: Last condition base: notification queued when condition just satisfied
    Given I log in as "admin"
    Then I create automation template with the following fields to these values:
      | Title     | Last Condition Queued |
      | Reference | lastcondqueued        |
    And I click on ".action-edit" "css_element" in the "Last Condition Queued" "table_row"
    And I click on "Condition" "link" in the "#automation-tabs" "css_element"
    And I set the following fields to these values:
      | Trigger operator    | Any |
      | Activity completion | All |
    And I enable pulse action "notification"
    And I set the following fields in the "#pulse-action-notification" "css_element" to these values:
      | id_pulsenotification_notifydelay              | After               |
      | id_pulsenotification_delayduration_timeunit   | days                |
      | pulsenotification_delayduration[number]       | 30                  |
      | id_pulsenotification_delaybase                | Last condition base |
      | Recipients                                    | Student             |
      | Subject                                       | Last condition queued test |
    And I press "Save changes"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    When I open the autocomplete suggestions list
    And I click on "Last Condition Queued" item in the autocomplete list
    Then I click on "Add automation instance" "button"
    And I set the following fields to these values:
      | insreference | lastcondqueued |
    And I click on "Condition" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_triggeroperator" "css_element" in the "#pulse-condition-tab" "css_element"
    And I set the field "Trigger operator" to "Any"
    And I click on "#id_override_condition_activity_status" "css_element" in the "#fitem_id_condition_activity_status" "css_element"
    And I set the field "Activity completion" to "All"
    And I click on "#id_override_condition_activity_modules" "css_element" in the "#fitem_id_condition_activity_modules" "css_element"
    And I set the field "Select activities" in the "#pulse-condition-tab" "css_element" to "Assign1"
    Then I click on "#id_override_condition_activity_activitycount" "css_element" in the "#fitem_id_condition_activity_activitycount" "css_element"
    And I set the field "Number of activities" to "1"
    And I press "Save changes"
    And I log out
    # Student marks Assign1 done – completion time is now.
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on "Mark as done" "button" in the ".section .activity:first-child" "css_element"
    And I log out
    And I log in as "admin"
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    Then I click on ".action-report#notification-action-report" "css_element" in the "Last Condition Queued" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Full name      | Subject                    | Status | Scheduled time               |
      | student User 1 | Last condition queued test | Queued | ##today +30 days##%d %B %Y## |
    And I close all opened windows
    And I log out

  Scenario: Last condition base: notification sent when condition satisfied long ago
    Given I log in as "admin"
    Then I create automation template with the following fields to these values:
      | Title     | Last Condition Sent |
      | Reference | lastcondsent        |
    And I click on ".action-edit" "css_element" in the "Last Condition Sent" "table_row"
    And I click on "Condition" "link" in the "#automation-tabs" "css_element"
    And I set the following fields to these values:
      | Trigger operator    | Any |
      | Activity completion | All |
    And I enable pulse action "notification"
    And I set the following fields in the "#pulse-action-notification" "css_element" to these values:
      | id_pulsenotification_notifydelay              | After               |
      | id_pulsenotification_delayduration_timeunit   | days                |
      | pulsenotification_delayduration[number]       | 2                   |
      | id_pulsenotification_delaybase                | Last condition base |
      | Recipients                                    | Student             |
      | Subject                                       | Last condition sent test |
    And I press "Save changes"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    When I open the autocomplete suggestions list
    And I click on "Last Condition Sent" item in the autocomplete list
    Then I click on "Add automation instance" "button"
    And I set the following fields to these values:
      | insreference | lastcondsent |
    And I click on "Condition" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_triggeroperator" "css_element" in the "#pulse-condition-tab" "css_element"
    And I set the field "Trigger operator" to "Any"
    And I click on "#id_override_condition_activity_status" "css_element" in the "#fitem_id_condition_activity_status" "css_element"
    And I set the field "Activity completion" to "All"
    And I click on "#id_override_condition_activity_modules" "css_element" in the "#fitem_id_condition_activity_modules" "css_element"
    And I set the field "Select activities" in the "#pulse-condition-tab" "css_element" to "Assign1"
    Then I click on "#id_override_condition_activity_activitycount" "css_element" in the "#fitem_id_condition_activity_activitycount" "css_element"
    And I set the field "Number of activities" to "1"
    And I press "Save changes"
    And I log out
    # Step 1: Student completes Assign1 now.
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on "Mark as done" "button" in the ".section .activity:first-child" "css_element"
    And I log out
    # Confirm the initial Queued status.
    And I log in as "admin"
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    Then I click on ".action-report#notification-action-report" "css_element" in the "Last Condition Sent" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Full name       | Subject                  | Status | Scheduled time              |
      | student User 1  | Last condition sent test | Queued | ##today +2 days##%d %B %Y## |
    And I close all opened windows
    # Step 2: Backdate activity completion time to 5 days ago.
    And I set activity completion time to "-5" days for "student1" on "assign1" in "C1"
    And I run automation evaluation for "C1" course
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    Then I click on ".action-report#notification-action-report" "css_element" in the "Last Condition Sent" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Full name      | Subject                  | Status | Scheduled time              |
      | student User 1 | Last condition sent test | sent   | ##today -3 days##%d %B %Y## |
    And I close all opened windows
    And I log out

  Scenario: Last condition base: cohort join time used when more recent than enrolment
    Given I log in as "admin"
    And I change user enrollment time to "-3" days for "student1" in "C1"
    And I set cohort membership time to "-1" days for "student1" in cohort "CH1"
    Then I create automation template with the following fields to these values:
      | Title     | Last Condition Dual Pre-existing |
      | Reference | lastconddualpreexist             |
    And I click on ".action-edit" "css_element" in the "Last Condition Dual Pre-existing" "table_row"
    And I click on "Condition" "link" in the "#automation-tabs" "css_element"
    And I set the following fields to these values:
      | Trigger operator  | All |
      | User enrolment    | All |
      | Member in cohorts | All |
    And I enable pulse action "notification"
    And I set the following fields in the "#pulse-action-notification" "css_element" to these values:
      | id_pulsenotification_notifydelay              | After               |
      | id_pulsenotification_delayduration_timeunit   | days                |
      | pulsenotification_delayduration[number]       | 7                   |
      | id_pulsenotification_delaybase                | Last condition base |
      | Recipients                                    | Student             |
      | Subject                                       | Last condition dual pre-existing test |
    And I press "Save changes"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    When I open the autocomplete suggestions list
    And I click on "Last Condition Dual Pre-existing" item in the autocomplete list
    Then I click on "Add automation instance" "button"
    And I set the following fields to these values:
      | insreference | lastconddualpreexist |
    And I click on "Condition" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_condition_cohort_status" "css_element" in the "#fitem_id_condition_cohort_status" "css_element"
    And I set the field "Member in cohorts" to "All"
    And I click on "#fitem_id_condition_cohort_cohorts .form-autocomplete-downarrow" "css_element"
    And I click on "Cohort 1" item in the autocomplete list
    And I press "Save changes"
    And I run automation evaluation for "C1" course
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    Then I click on ".action-report#notification-action-report" "css_element" in the "Last Condition Dual Pre-existing" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Full name      | Subject                               | Status | Scheduled time              |
      | student User 1 | Last condition dual pre-existing test | Queued | ##today +6 days##%d %B %Y## |
    # student2 is enrolled but not in Cohort 1 — cohort condition fails (All operator), not scheduled.
    And the following should not exist in the "reportbuilder-table" table:
      | Full name      | Subject                               |
      | student User 2 | Last condition dual pre-existing test |
    And I close all opened windows
    And I log out

  Scenario: Enrolment base: course group condition scheduling
    Given the following "groups" exist:
      | name    | course | idnumber |
      | Group 1 | C1     | G1       |
    And the following "group members" exist:
      | user     | group |
      | student1 | G1    |
    And I log in as "admin"
    Then I create automation template with the following fields to these values:
      | Title     | Course Group Delay |
      | Reference | coursegroupdelay   |
    And I click on ".action-edit" "css_element" in the "Course Group Delay" "table_row"
    And I enable pulse action "notification"
    And I set the following fields in the "#pulse-action-notification" "css_element" to these values:
      | id_pulsenotification_notifydelay              | After          |
      | id_pulsenotification_delayduration_timeunit   | days           |
      | pulsenotification_delayduration[number]       | 5              |
      | id_pulsenotification_delaybase                | Enrolment base |
      | Recipients                                    | Student        |
      | Subject                                       | Course group delay test |
    And I press "Save changes"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    When I open the autocomplete suggestions list
    And I click on "Course Group Delay" item in the autocomplete list
    Then I click on "Add automation instance" "button"
    And I set the following fields to these values:
      | insreference | coursegroupdelay |
    And I click on "Condition" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_condition_coursegroup_status" "css_element" in the "#condition-coursegroup" "css_element"
    And I set the field "condition[coursegroup][status]" to "All"
    And I click on "#id_override_condition_coursegroup_type" "css_element" in the "#condition-coursegroup" "css_element"
    And I set the field "condition[coursegroup][type]" to "Any group"
    And I press "Save changes"
    And I run automation evaluation for "C1" course
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    Then I click on ".action-report#notification-action-report" "css_element" in the "Course Group Delay" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Full name      | Subject                 | Status | Scheduled time              |
      | student User 1 | Course group delay test | Queued | ##today +5 days##%d %B %Y## |
    And the following should not exist in the "reportbuilder-table" table:
      | Full name      | Subject                 |
      | student User 2 | Course group delay test |
    And I close all opened windows
    And I log out

  Scenario: Enrolment base: course completion condition scheduling
    Given I log in as "admin"
    And I am on "Course 1" course homepage
    And I navigate to "Course completion" in current page administration
    And I expand all fieldsets
    And I set the field "Assignment - Assign1" to "1"
    And I press "Save changes"
    Then I create automation template with the following fields to these values:
      | Title     | Course Completion Delay |
      | Reference | coursecompletiondelay   |
    And I click on ".action-edit" "css_element" in the "Course Completion Delay" "table_row"
    And I click on "Condition" "link" in the "#automation-tabs" "css_element"
    And I set the following fields to these values:
      | Trigger operator  | All |
      | Course completion | All |
    And I enable pulse action "notification"
    And I set the following fields in the "#pulse-action-notification" "css_element" to these values:
      | id_pulsenotification_notifydelay              | After          |
      | id_pulsenotification_delayduration_timeunit   | days           |
      | pulsenotification_delayduration[number]       | 5              |
      | id_pulsenotification_delaybase                | Enrolment base |
      | Recipients                                    | Student        |
      | Subject                                       | Course completion delay test |
    And I press "Save changes"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    When I open the autocomplete suggestions list
    And I click on "Course Completion Delay" item in the autocomplete list
    Then I click on "Add automation instance" "button"
    And I set the following fields to these values:
      | insreference | coursecompletiondelay |
    And I press "Save changes"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on "Mark as done" "button" in the ".section .activity:first-child" "css_element"
    And I log out
    And I log in as "admin"
    And I trigger cron
    And I run automation evaluation for "C1" course
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    Then I click on ".action-report#notification-action-report" "css_element" in the "Course Completion Delay" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Full name      | Subject                      | Status | Scheduled time              |
      | student User 1 | Course completion delay test | Queued | ##today +5 days##%d %B %Y## |
    And the following should not exist in the "reportbuilder-table" table:
      | Full name      | Subject                      |
      | student User 2 | Course completion delay test |
    And I close all opened windows
    And I log out

  Scenario: Last condition base: Any operator locks delay to first condition met
    Given I log in as "admin"
    And I set cohort membership time to "-5" days for "student1" in cohort "CH1"
    Then I create automation template with the following fields to these values:
      | Title     | Last Condition Any Operator |
      | Reference | lastcondanyop               |
    And I click on ".action-edit" "css_element" in the "Last Condition Any Operator" "table_row"
    And I click on "Condition" "link" in the "#automation-tabs" "css_element"
    And I set the following fields to these values:
      | Trigger operator    | Any |
      | Activity completion | All |
      | Member in cohorts   | All |
    And I enable pulse action "notification"
    And I set the following fields in the "#pulse-action-notification" "css_element" to these values:
      | id_pulsenotification_notifydelay              | After               |
      | id_pulsenotification_delayduration_timeunit   | days                |
      | pulsenotification_delayduration[number]       | 7                   |
      | id_pulsenotification_delaybase                | Last condition base |
      | Recipients                                    | Student             |
      | Subject                                       | Last condition any operator test |
    And I press "Save changes"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    When I open the autocomplete suggestions list
    And I click on "Last Condition Any Operator" item in the autocomplete list
    Then I click on "Add automation instance" "button"
    And I set the following fields to these values:
      | insreference | lastcondanyop |
    And I click on "Condition" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_triggeroperator" "css_element" in the "#pulse-condition-tab" "css_element"
    And I set the field "Trigger operator" to "Any"
    And I click on "#id_override_condition_activity_status" "css_element" in the "#fitem_id_condition_activity_status" "css_element"
    And I set the field "Activity completion" to "All"
    And I click on "#id_override_condition_activity_modules" "css_element" in the "#fitem_id_condition_activity_modules" "css_element"
    And I set the field "Select activities" in the "#pulse-condition-tab" "css_element" to "Assign1"
    And I click on "#id_override_condition_activity_activitycount" "css_element" in the "#fitem_id_condition_activity_activitycount" "css_element"
    And I set the field "Number of activities" to "1"
    And I click on "#id_override_condition_cohort_status" "css_element" in the "#fitem_id_condition_cohort_status" "css_element"
    And I set the field "Member in cohorts" to "All"
    And I click on "#fitem_id_condition_cohort_cohorts .form-autocomplete-downarrow" "css_element"
    And I click on "Cohort 1" item in the autocomplete list
    And I press "Save changes"
    # Step 1: Only the cohort condition is satisfied (activity not yet completed).
    And I run automation evaluation for "C1" course
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    Then I click on ".action-report#notification-action-report" "css_element" in the "Last Condition Any Operator" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Full name      | Subject                          | Status | Scheduled time              |
      | student User 1 | Last condition any operator test | Queued | ##today +2 days##%d %B %Y## |
    And I close all opened windows
    # Step 2: student1 completes Assign1 today — the SECOND ANY condition is now met.
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on "Mark as done" "button" in the ".section .activity:first-child" "css_element"
    And I log out
    And I log in as "admin"
    And I run automation evaluation for "C1" course
    And I am on "Course 1" course homepage
    And I navigate to "Automation" in current page administration
    Then I click on ".action-report#notification-action-report" "css_element" in the "Last Condition Any Operator" "table_row"
    And I switch to a second window
    And the following should exist in the "reportbuilder-table" table:
      | Full name      | Subject                          | Status | Scheduled time              |
      | student User 1 | Last condition any operator test | Queued | ##today +2 days##%d %B %Y## |
    And the following should not exist in the "reportbuilder-table" table:
      | Full name      | Subject                          |
      | student User 2 | Last condition any operator test |
    And I close all opened windows
    And I log out
