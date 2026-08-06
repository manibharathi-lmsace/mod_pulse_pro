@mod @mod_pulse @mod_pulse_automation @mod_pulse_automation_template @pulseactions @pulseaction_notification @pulseaction_notification_recompletion
Feature: Support recompletion notification action in pulse automation templates
  In order to use the features
  As admin
  I need to be able to configure the pulse automation template
  to reset the notification for recompletion.

  Background:
    Given the following "categories" exist:
      | name  | category | idnumber |
      | Cat 1 | 0        | CAT1     |
    And the following "course" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1        | 0        | 1                |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | student   | User 1   | student1@test.com |
      | teacher1 | teacher   | User 1   | teacher1@test.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | teacher1 | C1     | teacher |
    And the following "activities" exist:
      | activity | name    | course | idnumber | intro            | section | completion |
      | assign   | Assign1 | C1     | assign1  | Page description | 1       | 1          |
    And I am on the "Course 1" "course" page logged in as "admin"
    And I navigate to "Course completion" in current page administration
    And I set the field "overall_aggregation" to "2"
    And I expand all fieldsets
    And I set the field "Assignment - Assign1" to "1"
    And I press "Save changes"

  @javascript
  Scenario: Verify the recompletion reset the scheduled notification
    Given I log in as "admin"
    And I navigate to automation templates
    And I create pulse notification template "WELCOME MESSAGE" "WELCOMEMESSAGE_" to these values:
      | Sender         | Course teacher                                                                          |
      | Recipients     | Student                                                                                 |
      | Subject        | Welcome to {Site_FullName}                                                              |
      | Header content | Hi {User_firstname} {User_lastname}, <br> Welcome to learning portal of {Site_FullName} |
      | Footer content | Copyright @ 2023 {Site_FullName}                                                        |
    Then I should see "Automation templates"
    And I should see "WELCOME MESSAGE" in the "pulse_automation_template" "table"
    Then I am on "Course 1" course homepage
    And I follow "Automation"
    And I open the autocomplete suggestions list
    And I click on "WELCOME MESSAGE" item in the autocomplete list
    And I click on "Add automation instance" "button"
    And I set the following fields to these values:
      | insreference | instance |
    And I click on "Condition" "link" in the "#automation-tabs" "css_element"
    And I click on "#id_override_triggeroperator" "css_element" in the "#pulse-condition-tab" "css_element"
    And I set the field "Trigger operator" to "Any"
    And I click on "#id_override_condition_activity_status" "css_element" in the "#fitem_id_condition_activity_status" "css_element"
    And I set the field "Activity completion" to "All"
    And I wait "5" seconds
    And I click on "#id_override_condition_activity_modules" "css_element" in the "#fitem_id_condition_activity_modules" "css_element"
    And I set the field "Select activities" in the "#pulse-condition-tab" "css_element" to "Assign1"
    Then I click on "#id_override_condition_activity_activitycount" "css_element" in the "#fitem_id_condition_activity_activitycount" "css_element"
    And I set the field "Number of activities" to "1"
    And I press "Save changes"
    And I log out
    And I am on the "Course 1" course page logged in as student1
    And I should see "Assign1"
    And I press "Mark as done"
    Then I log out
    Then I log in as "admin"
    And I navigate to course "Course 1" automation instances
    And I should see "WELCOMEMESSAGE_instance" in the "pulse_automation_template" "table"
    Then I click on ".action-report#notification-action-report" "css_element" in the "WELCOMEMESSAGE_instance" "table_row"
    And I switch to a second window
    And ".reportbuilder-report" "css_element" should exist
    And the following should exist in the "reportbuilder-table" table:
      | Full name      | Subject                         | Status |
      | student User 1 | Welcome to Acceptance test site | sent |
    And I am on "Course 1" course homepage
    When I select "More" from secondary navigation
    And I follow "Course recompletion"
    And I expand all fieldsets
    And I set the field "Recompletion type" to "On demand"
    And I click on "#id_pulse_1" "css_element"
    And I press "Save changes"
    Then I select "More" from secondary navigation
    And I follow "Modify course completion dates"
    And I should see "student User 1" in the "participants" "table"
    When I click on "#select-all-participants" "css_element"
    And I press "Reset all completion for selected users"
    And I should see "Completion for the selected students in this course has been reset."
    Then I navigate to course "Course 1" automation instances
    And I click on ".action-report#notification-action-report" "css_element" in the "WELCOMEMESSAGE_instance" "table_row"
    And I switch to a pulse open window
    And the following should exist in the "reportbuilder-table" table:
      | Full name      | Subject                         | Status |
      | student User 1 | Welcome to Acceptance test site | Queued |
    And I log out
