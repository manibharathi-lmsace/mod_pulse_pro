@mod @mod_pulse @pulse_automation_template
Feature: Pulse automation templates
  In order to check the the pulse automation template works
  As a teacher.

  Background:
    Given the following "categories" exist:
      | name  | category | idnumber |
      | Cat 1 | 0        | CAT1     |
      | Cat 2 | 0        | CAT2     |
    And the following "course" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
      | Course 2 | C2        | CAT1     |
      | Course 3 | C3        | CAT2     |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | student   | User 1   | student1@test.com |
      | teacher1 | Teacher   | User 1   | teacher1@test.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  @javascript
  Scenario: Check the automation template.
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > Pulse > Automation templates" in site administration
    Then I should see "Automation templates" in the "#region-main h2" "css_element"
    And I should see "Create new template"
    Then I click on "Create new template" "button"
    And I set the following fields to these values:
      | Title      | WELCOME MESSAGE |
      | Reference  | Welcomemessage  |
      | Visibility | Show            |
      | Status     | Enabled         |
    Then I press "Save changes"
    Then I should see "Template inserted successfully"
    Then I should see "Automation templates"
    Then "#region-main-box .flexible#pulse_automation_template" "css_element" should exist
    And I should see "WELCOME MESSAGE" in the "#pulse_automation_template" "css_element"
    And I should see "Welcomemessage" in the "#pulse_automation_template .template-reference" "css_element"
    And "#pulse_automation_template .menu-item-actions .action-edit" "css_element" should exist
    Then I create automation template with the following fields to these values:
      | Title     | Triggers          |
      | Reference | Conditiontriggers |
    Then I should see "Template inserted successfully"
    And I should see "WELCOME MESSAGE" in the "#pulse_automation_template tbody tr:nth-child(1)" "css_element"
    And I should see "Welcomemessage" in the "#pulse_automation_template tbody tr:nth-child(1) .template-reference" "css_element"
    And I should see "Triggers" in the "#pulse_automation_template tbody tr:nth-child(2)" "css_element"
    And I should see "Conditiontriggers" in the "#pulse_automation_template tbody tr:nth-child(2) .template-reference" "css_element"

  @javascript
  Scenario: Edit the automation template
    Given I log in as "admin"
    Then I create automation template with the following fields to these values:
      | Title     | WELCOME MESSAGE |
      | Reference | Welcomemessage  |
    Then I should see "Template inserted successfully"
    And I should see "WELCOME MESSAGE" in the "#pulse_automation_template tbody tr:nth-child(1)" "css_element"
    And I should see "Welcomemessage" in the "#pulse_automation_template tbody tr:nth-child(1) .template-reference" "css_element"
    And "#pulse_automation_template .menu-item-actions .action-edit" "css_element" should exist
    Then I click on ".action-edit" "css_element" in the "WELCOME MESSAGE" "table_row"
    Then I should see "Edit template"
    And I set the following fields to these values:
      | Title     | Triggers          |
      | Reference | Conditiontriggers |
    Then I press "Save changes"
    Then I should see "Template updated successfully"
    And I should see "Triggers" in the "#pulse_automation_template tbody tr:nth-child(1)" "css_element"
    And I should see "Conditiontriggers" in the "#pulse_automation_template tbody tr:nth-child(1) .template-reference" "css_element"

  @javascript
  Scenario: Check Visibility of automation template
    Given I log in as "admin"
    Then I create automation template with the following fields to these values:
      | Title      | WELCOME MESSAGE |
      | Reference  | Welcomemessage  |
      | Visibility | Show            |
    And I should see "WELCOME MESSAGE" in the "#pulse_automation_template" "css_element"
    And I am on "Course 1" course homepage
    Then I should see "Automation"
    And I follow "Automation"
    Then I should see "Automation" in the "#region-main h2" "css_element"
    And ".template-add-form select#id_templateid" "css_element" should exist
    When I open the autocomplete suggestions list
    Then I should see "WELCOME MESSAGE" in the ".template-add-form .form-autocomplete-suggestions" "css_element"
    And I navigate to "Plugins > Activity modules > Pulse > Automation templates" in site administration
    Then I click on ".action-edit" "css_element" in the "WELCOME MESSAGE" "table_row"
    And I set the field "Visibility" to "Hidden"
    Then I press "Save changes"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    And ".template-add-form select#id_templateid" "css_element" should not exist
    Then I should not see "WELCOME MESSAGE" in the ".template-add-form" "css_element"
    And I navigate to "Plugins > Activity modules > Pulse > Automation templates" in site administration
    Then I click on ".action-show" "css_element" in the "WELCOME MESSAGE" "table_row"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    And ".template-add-form select#id_templateid" "css_element" should exist
    When I open the autocomplete suggestions list
    Then I should see "WELCOME MESSAGE" in the ".template-add-form" "css_element"

  @javascript
  Scenario: Check Status of automation template
    Given I log in as "admin"
    Then I create automation template with the following fields to these values:
      | Title      | WELCOME MESSAGE |
      | Reference  | Welcomemessage  |
      | Visibility | Show            |
      | Status     | Enable          |
    And I should see "WELCOME MESSAGE" in the "#pulse_automation_template" "css_element"
    And I am on "Course 1" course homepage
    Then I should see "Automation"
    And I follow "Automation"
    Then I should see "Automation" in the "#region-main h2" "css_element"
    And ".template-add-form select#id_templateid" "css_element" should exist
    When I open the autocomplete suggestions list
    Then I should see "WELCOME MESSAGE" in the ".template-add-form .form-autocomplete-suggestions" "css_element"
    And I navigate to "Plugins > Activity modules > Pulse > Automation templates" in site administration
    Then I click on ".action-edit" "css_element" in the "WELCOME MESSAGE" "table_row"
    And I set the field "Visibility" to "Hidden"
    Then I press "Save changes"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    And ".template-add-form select#id_templateid" "css_element" should not exist
    Then I should not see "WELCOME MESSAGE" in the ".template-add-form" "css_element"
    And I navigate to "Plugins > Activity modules > Pulse > Automation templates" in site administration
    Then I click on ".action-show" "css_element" in the "WELCOME MESSAGE" "table_row"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    And ".template-add-form select#id_templateid" "css_element" should exist
    When I open the autocomplete suggestions list
    Then I should see "WELCOME MESSAGE" in the ".template-add-form" "css_element"

  @javascript
  Scenario: Check Available in course categories for automation template
    Given I log in as "admin"
    Then I create automation template with the following fields to these values:
      | Title                          | WELCOME MESSAGE |
      | Reference                      | Welcomemessage  |
      | Available in course categories | Category 1      |
    And I am on "Course 1" course homepage
    Then I should see "Automation"
    And I follow "Automation"
    And ".template-add-form select#id_templateid" "css_element" should exist
    When I open the autocomplete suggestions list
    Then I should see "WELCOME MESSAGE" in the ".template-add-form .form-autocomplete-suggestions" "css_element"
    # Course 2
    And I am on "Course 2" course homepage
    Then I should see "Automation"
    And I follow "Automation"
    And ".template-add-form select#id_templateid" "css_element" should not exist
    Then I should not see "WELCOME MESSAGE" in the ".template-add-form" "css_element"
    # Course 3
    And I am on "Course 2" course homepage
    Then I should see "Automation"
    And I follow "Automation"
    And ".template-add-form select#id_templateid" "css_element" should not exist
    Then I should not see "WELCOME MESSAGE" in the ".template-add-form" "css_element"
    # Update the template
    And I navigate to "Plugins > Activity modules > Pulse > Automation templates" in site administration
    Then I click on ".action-edit" "css_element" in the "WELCOME MESSAGE" "table_row"
    And I set the field "Available in course categories" to "Category 1, Cat 1"
    Then I press "Save changes"
    And I am on "Course 1" course homepage
    Then I should see "Automation"
    And I follow "Automation"
    And ".template-add-form select#id_templateid" "css_element" should exist
    When I open the autocomplete suggestions list
    Then I should see "WELCOME MESSAGE" in the ".template-add-form .form-autocomplete-suggestions" "css_element"
    # Course 2
    And I am on "Course 2" course homepage
    Then I should see "Automation"
    And I follow "Automation"
    And ".template-add-form select#id_templateid" "css_element" should exist
    When I open the autocomplete suggestions list
    Then I should see "WELCOME MESSAGE" in the ".template-add-form .form-autocomplete-suggestions" "css_element"
    # Course 3
    And I am on "Course 3" course homepage
    Then I should see "Automation"
    And I follow "Automation"
    And ".template-add-form select#id_templateid" "css_element" should not exist
    Then I should not see "WELCOME MESSAGE" in the ".template-add-form" "css_element"

  @javascript
  Scenario: Check condition for automation template
    Given I log in as "admin"
    Then I create automation template with the following fields to these values:
      | Title                          | WELCOME MESSAGE |
      | Reference                      | Welcomemessage  |
      | Available in course categories | Category 1      |
    Then I create "Welcomemessage" template with the set the condition:
      | Activity completion | 1   |
      | Member in cohorts   | 1   |
      | Trigger operator    | All |
    And I am on "Course 1" course homepage
    And I follow "Automation"
    When I open the autocomplete suggestions list
    And I click on "WELCOME MESSAGE" item in the autocomplete list
    Then I press "Add automation instance"
    Then I follow "Condition"
    Then I should see "Activity completion"
    Then I should see "Member in cohorts"
    Then the field "Activity completion" matches value "All"
    Then the field "Select activities" matches value ""
    Then the field "Member in cohorts" matches value "All"
    Then the field "Cohorts" matches value ""

  @javascript
  Scenario: Check notification for automation template
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > Pulse > Automation templates" in site administration
    Then I create automation template with the following fields to these values:
      | Title                          | WELCOME MESSAGE |
      | Reference                      | Welcomemessage  |
      | Available in course categories | Category 1      |
    Then I create "Welcomemessage" template with the set the condition:
      | Activity completion | 1   |
      | Member in cohorts   | 1   |
      | Trigger operator    | All |
    Then I create "Welcomemessage" template with the set the notification:
      | Sender         | Group teacher                                                                                                          |
      | Interval       | Once                                                                                                                   |
      | Cc             | Teacher                                                                                                                |
      | Bcc            | Manager                                                                                                                |
      | Subject        | Demo MESSAGE                                                                                                           |
      | Header content | Lorem Ipsum is therefore always free from repetition, injected humour                                                  |
      | Static content | There are many variations of passages of Lorem Ipsum available, but the majority have suffered alteration in some form |
    Then I should see "Template updated successfully"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    When I open the autocomplete suggestions list
    And I click on "WELCOME MESSAGE" item in the autocomplete list
    Then I press "Add automation instance"
    Then I click on ".nav-item a[href=\"#pulse-action-notification\"]" "css_element"
    Then I wait "10" seconds
    Then the field "Sender" matches value "Group teacher"
    Then the field "Interval" matches value "Once"
    Then the field "Subject" matches value "Demo MESSAGE"

  @javascript
  Scenario: Check Notification for automation overrides badge
    Given I log in as "admin"
    Then I create automation template with the following fields to these values:
      | Title     | WELCOME MESSAGE |
      | Reference | Welcomemessage  |
    Then I create automation template with the following fields to these values:
      | Title     | Notification |
      | Reference | notification |
    And I am on "Course 1" course homepage
    And I follow "Automation"
    When I open the autocomplete suggestions list
    And I click on "WELCOME MESSAGE" item in the autocomplete list
    Then I click on "Add automation instance" "button"
    And I set the following fields to these values:
      | insreference | Welcomemessageinstance |
    And I press "Save changes"
    When I open the autocomplete suggestions list
    And I click on "WELCOME MESSAGE" item in the autocomplete list
    Then I click on "Add automation instance" "button"
    And I set the following fields to these values:
      | insreference | Welcomemessageinstance2 |
    And I press "Save changes"
    When I open the autocomplete suggestions list
    And I click on "WELCOME MESSAGE" item in the autocomplete list
    Then I click on "Add automation instance" "button"
    And I set the following fields to these values:
      | insreference | Welcomemessageinstance3 |
    And I press "Save changes"
    When I open the autocomplete suggestions list
    And I click on "Notification" item in the autocomplete list
    Then I click on "Add automation instance" "button"
    And I set the following fields to these values:
      | insreference | notificationinstance |
    And I press "Save changes"
    And I navigate to "Plugins > Activity modules > Pulse > Automation templates" in site administration
    And I should see "3(0)" in the "WELCOME MESSAGE" "table_row"
    And I should see "1(0)" in the "notification" "table_row"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    Then I click on ".pulse-instance-status-switch" "css_element" in the "Welcomemessageinstance" "table_row"
    And I navigate to "Plugins > Activity modules > Pulse > Automation templates" in site administration
    And I should see "3(1)" in the "WELCOME MESSAGE" "table_row"
    And I should see "1(0)" in the "notification" "table_row"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    Then I click on ".action-hide" "css_element" in the "Welcomemessageinstance2" "table_row"
    Then I click on ".action-hide" "css_element" in the "notificationinstance" "table_row"
    And I navigate to "Plugins > Activity modules > Pulse > Automation templates" in site administration
    And I should see "3(2)" in the "WELCOME MESSAGE" "table_row"
    And I should see "1(1)" in the "notification" "table_row"

  @javascript
  Scenario: Course deletion cleans up instances and credit schedules
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | student2 | Student   | User 2   | student2@test.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student2 | C1     | student        |
      | teacher1 | C2     | editingteacher |
      | student1 | C2     | student        |
      | student2 | C2     | student        |
      | teacher1 | C3     | editingteacher |
      | student1 | C3     | student        |
      | student2 | C3     | student        |
    And the following "mod_pulse > automation templates" exist:
      | title              | reference        | condition |
      | Course Delete Test | coursedeletetest | enrolment |
    And the following "pulseaction_credits > credits template" exist:
      | template           | credits | allocationmethod | interval | basedate  | recipients |
      | Course Delete Test | 20      | Add credits      | Once     | enrolment | student    |
    # Set up credit profile field and enable credits display in navigation
    And a credit profile field exists
    And the following config values are set as admin:
      | showcredits | 1 | pulseaction_credits |
    And I log in as "admin"
    Then I create "coursedeletetest" template with the set the notification:
      | Sender         | Course teacher                                |
      | Interval       | Once                                          |
      | Subject        | Course delete test notification               |
      | Header content | Welcome {User_Fullname} to {Course_Fullname}  |
    Then I should see "Template updated successfully"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    And I open the autocomplete suggestions list
    And I click on "Course Delete Test" item in the autocomplete list
    And I click on "Add automation instance" "button"
    And I set the following fields to these values:
      | insreference | cdtinstance_c1 |
    And I enable pulse action "credits"
    And I enable pulse action "notification"
    And I press "Save changes"
    Then I should see "Template inserted successfully"
    And I am on "Course 2" course homepage
    And I follow "Automation"
    And I open the autocomplete suggestions list
    And I click on "Course Delete Test" item in the autocomplete list
    And I click on "Add automation instance" "button"
    And I set the following fields to these values:
      | insreference | cdtinstance_c2 |
    And I enable pulse action "credits"
    And I enable pulse action "notification"
    And I press "Save changes"
    Then I should see "Template inserted successfully"
    And I am on "Course 3" course homepage
    And I follow "Automation"
    And I open the autocomplete suggestions list
    And I click on "Course Delete Test" item in the autocomplete list
    And I click on "Add automation instance" "button"
    And I set the following fields to these values:
      | insreference | cdtinstance_c3 |
    And I enable pulse action "credits"
    And I enable pulse action "notification"
    And I press "Save changes"
    Then I should see "Template inserted successfully"
    And I save the pulse action instance "cdtinstance_c1" on course "Course 1"
    And I save the pulse action instance "cdtinstance_c2" on course "Course 2"
    And I save the pulse action instance "cdtinstance_c3" on course "Course 3"
    And I navigate to "Plugins > Activity modules > Pulse > Automation templates" in site administration
    Then I should see "3(0)" in the "Course Delete Test" "table_row"
    And I click on ".action-edit" "css_element" in the "Course Delete Test" "table_row"
    And I click on "Instance Management" "link"
    Then I should see "Course 1" in the "Category 1" "table_row"
    And I should see "Course 2" in the "Cat 1" "table_row"
    And I should see "Course 3" in the "Cat 2" "table_row"
    And I should see "1" in the "Course 1" "table_row"
    And I should see "1" in the "Course 2" "table_row"
    And I should see "1" in the "Course 3" "table_row"
    And I should see "6" credit schedules with status "planned"
    And I navigate to course "Course 2" automation instances
    And I open credits instance schedule report for "cdtinstance_c2"
    Then ".reportbuilder-report" "css_element" should exist
    And the following should exist in the "reportbuilder-table" table:
      | Full name with link | Status  |
      | student User 1      | Planned |
      | Student User 2      | Planned |
    And I close all opened windows
    And I run the credits allocation scheduled task
    Then user "student1" should have "60.00" credits
    And user "student2" should have "60.00" credits
    And I log in as "admin"
    And I go to the courses management page
    And I click on category "Cat 1" in the management interface
    And I click on "delete" action for "Course 2" in management course listing
    And I press "Delete"
    And I should see "C2 has been completely deleted"
    And I press "Continue"
    And I navigate to "Plugins > Activity modules > Pulse > Automation templates" in site administration
    Then I should see "2(0)" in the "Course Delete Test" "table_row"
    And I click on ".action-edit" "css_element" in the "Course Delete Test" "table_row"
    And I click on "Instance Management" "link"
    Then "Course 2" "table_row" should not exist
    And I should see "Course 1" in the "Category 1" "table_row"
    And I should see "Course 3" in the "Cat 2" "table_row"
    And I should see "1" in the "Course 1" "table_row"
    And I should see "1" in the "Course 3" "table_row"
    And I should see "4" credit schedules with status "allocated"
    Then user "student1" should have "60.00" credits
    And user "student2" should have "60.00" credits
    And I log in as "admin"
    And I navigate to course "Course 1" automation instances
    And I open credits instance schedule report for "cdtinstance_c1"
    Then ".reportbuilder-report" "css_element" should exist
    And the following should exist in the "reportbuilder-table" table:
      | Full name with link | Status    |
      | student User 1      | Allocated |
      | Student User 2      | Allocated |
    And I close all opened windows
