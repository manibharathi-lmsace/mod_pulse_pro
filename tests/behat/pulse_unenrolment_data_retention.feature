@mod @mod_pulse @core_completion
Feature: Pulse data is retained while a user remains enrolled via any method
  In order to avoid losing pulse completion data unexpectedly
  As a teacher
  I need pulse completion records to survive when a student loses only one of several enrolment methods

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | Student   | User 1   | student1@test.com |
      | teacher1 | Teacher   | User 1   | teacher1@test.com |
    And the following "courses" exist:
      | fullname | shortname | category | enablecompletion | showcompletionconditions |
      | Course 1 | C1        | 0        | 1                | 1                        |
    And the following "cohorts" exist:
      | name    | idnumber | visible |
      | CohortA | CA       | 1       |
      | CohortB | CB       | 1       |
    And the following "cohort members" exist:
      | user     | cohort |
      | student1 | CA     |
      | student1 | CB     |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activity" exists:
      | activity | pulse        |
      | course   | C1           |
      | idnumber | pulse1       |
      | name     | Test pulse 1 |
      | intro    | Test pulse 1 |
      | pulse    | 0            |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I click on "Edit" "link" in the ".modtype_pulse" "css_element"
    And I click on ".menu-action-text" "css_element" in the ".modtype_pulse" "css_element"
    And I expand all fieldsets
    And I click on "[name='pulse'][type='checkbox']" "css_element"
    And I set the activity completion tracking
    And I click on "Mark as complete by student to complete this activity" "checkbox"
    And I press "Save and return to course"
    And I add "Cohort sync" enrolment method in "Course 1" with:
      | Cohort | CohortA |
    And I add "Cohort sync" enrolment method in "Course 1" with:
      | Cohort | CohortB |
    And I trigger cron
    And I log out

  @javascript
  Scenario: Losing one of two cohort-synced enrolments keeps pulse completion data intact
    # Student marks the pulse activity complete while enrolled via both cohorts.
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I should see "Mark as complete" in the ".pulse-completion-btn" "css_element"
    When I click on "Mark as complete" "link"
    And I should see "Marked as completed" in the ".notifications" "css_element"
    And "Test pulse 1" should have the "Self marked complete on" completion condition type "done"
    And I log out

    # Remove the student from CohortA only - they remain enrolled via CohortB's cohort sync.
    And I log in as "admin"
    And I navigate to "Users > Accounts > Cohorts" in site administration
    And I press "Assign" action in the "CohortA" report row
    And I set the field "removeselect[]" to "Student User 1 (student1@test.com)"
    And I click on "Remove" "button"
    And I trigger cron
    And I log out

    # Completion data must survive - the student is still enrolled via CohortB.
    Then I log in as "student1"
    And I am on "Course 1" course homepage
    And "Test pulse 1" should have the "Self marked complete on" completion condition type "done"
    And I log out

    # Remove the student from CohortB too - now they have no enrolment left at all.
    And I log in as "admin"
    And I navigate to "Users > Accounts > Cohorts" in site administration
    And I press "Assign" action in the "CohortB" report row
    And I set the field "removeselect[]" to "Student User 1 (student1@test.com)"
    And I click on "Remove" "button"
    And I trigger cron

    # Re-enrol the student fresh via CohortA to observe whether old completion data survived the full unenrolment.
    And I navigate to "Users > Accounts > Cohorts" in site administration
    And I press "Assign" action in the "CohortA" report row
    And I set the field "addselect_searchtext" to "student1"
    And I set the field "addselect[]" to "Student User 1 (student1@test.com)"
    And I click on "Add" "button"
    And I trigger cron
    And I log out

    # Completion data must now be gone - the student had genuinely left the course entirely.
    Then I log in as "student1"
    And I am on "Course 1" course homepage
    And I should see "Mark as complete" in the ".pulse-completion-btn" "css_element"
