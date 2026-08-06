@mod @mod_pulse @pulseaction_credits @javascript
Feature: Pulse credits action schedule overrides
  In order to customize credit allocations for specific students
  As a teacher
  I need to override scheduled credit amounts for individual students

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | Student   | 1      | student1@test.com |
      | student2 | Student   | 2      | student2@test.com |
      | student3 | Student   | 3    | student3@test.com |
      | teacher1 | Teacher   | 1      | teacher1@test.com |
    And the following "courses" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1        | 0        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
    And a credit profile field exists
    And the following "mod_pulse > automation templates" exist:
      | title              | reference         | visibility | condition |
      | Override Test Base | overridetest  | Show       | enrolment |
    And the following "pulseaction_credits > credits templates" exist:
      | template           | status | credits | allocationmethod | interval | basedate               | recipients |
      | Override Test Base | Enable | 50      | Add credits      | Once     | Relative to enrollment | student    |
    And the following "pulseaction_credits > credits instances" exist:
      | template           | course | reference | status  |
      | Override Test Base | C1     | instance  | Enable  |
    When I log in as "admin"
    And I save the pulse action instance "overridetestinstance" on course "Course 1"
    Then I navigate to "Plugins > Activity modules > Pulse > Credits display" in site administration
    When I click on "Show credits in popover menu" "checkbox"
    And I press "Save changes"

  Scenario: Override credit amount for a single student
    Given I allocate credits "100" to user "student1"
    And I allocate credits "100" to user "student2"
    And I allocate credits "100" to user "student3"
    When I log in as "admin"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    And I open credits instance override report
    Then ".reportbuilder-report" "css_element" should exist
    And the following should exist in the "reportbuilder-table" table:
      | First name | Status  | Scheduled credits | Current Credits |
      | Student 1  | Planned | 50.00             | 100.00          |
      | Student 2  | Planned | 50.00             | 100.00          |
      | Student 3  | Planned | 50.00             | 100.00          |
    When I click on "No override" "link" in the "Student 1" "table_row"
    And I set the field with xpath "//span[@data-component='pulseaction_credits']//input[@type='text']" to "150"
    And I press the enter key
    And I should see "150.00 (Overridden)" in the "Student 1" "table_row"
    When I click on "No override" "link" in the "Student 2" "table_row"
    And I set the field with xpath "//span[@data-component='pulseaction_credits']//input[@type='text']" to "25"
    And I press the enter key
    Then I should see "25.00 (Overridden)" in the "Student 2" "table_row"
    And I run the credits allocation scheduled task
    And I am on "Course 1" course homepage
    And I follow "Automation"
    And I open credits instance schedule report for "overridetestinstance"
    And the following should exist in the "reportbuilder-table" table:
      | Full name with link | Status    | Scheduled credits |
      | Student 1           | Allocated | 150.00            |
      | Student 2           | Allocated | 25.00             |
      | Student 3           | Allocated | 50.00             |

  @javascript
  Scenario Outline: Override credit amounts with different values
    Given I allocate credits "<initial_credits>" to user "student1"
    When I log in as "admin"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    And I open credits instance override report
    Then ".reportbuilder-report" "css_element" should exist
    And the following should exist in the "reportbuilder-table" table:
      | First name | Status  | Scheduled credits | Current Credits   |
      | Student 1  | Planned | 50.00             | <initial_credits> |
    When I click on "No override" "link" in the "Student 1" "table_row"
    And I set the field with xpath "//span[@data-component='pulseaction_credits']//input[@type='text']" to "<override_amount>"
    And I press the enter key
    And I should see "<overridden_value>" in the "Student 1" "table_row"
    And I press the enter key
    And I run the credits allocation scheduled task
    And I wait "10" seconds
    And I trigger cron
    And I wait "5" seconds
    And I trigger cron
    And I am on "Course 1" course homepage
    And I open credits instance override report
    And the following should exist in the "reportbuilder-table" table:
      | First name | Status    | Scheduled credits | Current Credits     |
      | Student 1  | Allocated | 50.00             | <expected_credits>  |

    Examples:
      | initial_credits | override_amount | overridden_value    | expected_credits   |
      | 0               | 100             | 100.00 (Overridden) | 100.00             |
      | 50              | 200.50          | 200.50 (Overridden) | 250.50             |
      | 100             | 0               | 0.00 (Overridden)   | 100.00             |
      | 250             |                 | No override         | 300.00             |

  @javascript
  Scenario: Schedule override persists after instance update
    Given I allocate credits "10" to user "student1"
    When I log in as "admin"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    And I open credits instance override report
    # Add override
    When I click on "No override" "link" in the "Student 1" "table_row"
    And I set the field with xpath "//span[@data-component='pulseaction_credits']//input[@type='text']" to "5.50"
    And I press the enter key
    And I should see "5.50 (Overridden)" in the "Student 1" "table_row"
    # Update instance settings
    When I am on "Course 1" course homepage
    And I follow "Automation"
    And I click on ".action-edit" "css_element" in the "overridetestinstance" "table_row"
    And I click on "Credits" "link" in the "ul#automation-tabs" "css_element"
    And I set the following fields in the "#pulse-action-credits" "css_element" to these values:
      | override[pulsecredits_credits] | 1  |
      | Credits                        | 75 |
    And I press "Save changes"
    # Verify override is still active
    Then I run the credits allocation scheduled task
    And user "student1" should have "15.5" credits

  Scenario: User credits override in general
    Given I allocate credits "200" to user "student2"
    When I log in as "admin"
    And I run the credits allocation scheduled task
    And user "student2" should have "250" credits
    Then I log in as "admin"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    And I open credits instance override report
    And the following should exist in the "reportbuilder-table" table:
      | First name | Status    | Scheduled credits | Current Credits |
      | Student 2  | Allocated | 50.00             | 250.00          |
    And I change window size to "large"
    When I open the action menu in "Student 2" "table_row"
    And I click on "Edit user credits" "link" in the "Student 2" "table_row"
    And I should see "Edit user credits" in the "h5.modal-title" "css_element"
    And I should see "Student 2 (student2@test.com)" in the ".modal-body" "css_element"
    And I should see "250.00" in the ".modal-body div[id^='fitem_id_currentcredits_display']" "css_element"
    And I set the following fields in the ".modal-body" "css_element" to these values:
      | New Credits | 30.30            |
      | Note        | Initial increase |
    And I click on "Save changes" "button" in the ".modal-footer" "css_element"
    And the following should exist in the "reportbuilder-table" table:
      | First name | Current Credits |
      | Student 2  | 30.30           |
    And user "student2" should have "30.30" credits
