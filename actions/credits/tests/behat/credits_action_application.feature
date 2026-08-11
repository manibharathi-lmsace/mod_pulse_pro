@mod @mod_pulse @pulseaction_credits @pulseaction_credits_allocation @javascript
Feature: Pulse credits action application
  In order to allocate credits to specific user roles using different methods
  As a teacher
  I need to configure recipient roles for credit allocation

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | Student   | 1        | student1@test.com |
      | student2 | Student   | 2        | student2@test.com |
      # | student3 | Student   | 3        | student3@test.com |
      | teacher1 | Teacher   | 1        | teacher1@test.com |
      | teacher2 | Teacher   | 2        | teacher2@test.com |
      | manager1 | Manager   | 1        | manager1@test.com |
    And the following "courses" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1        | 0        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      # | student3 | C1     | student        |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | teacher        |
    And a credit profile field exists
    And I log in as "admin"
    Then I navigate to "Plugins > Activity modules > Pulse > Credits display" in site administration
    When I click on "Show credits in popover menu" "checkbox"
    And I press "Save changes"

  @javascript
  Scenario Outline: Credits allocation to different recipient roles
    Given the following "mod_pulse > automation templates" exist:
      | title            | reference   | visibility | condition |
      | <template_title> | <reference> | Show       | enrolment |
    And the following "pulseaction_credits > credits template" exist:
      | template         | title            | reference   | status | credits | allocationmethod | interval | basedate   | recipients       |
      | <template_title> | <template_title> | <reference> | Enable | 20      | Add credits      | Once     | enrolment  | <recipient_role> |
    When I log in as "admin"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    And I open the autocomplete suggestions list
    And I click on "<template_title>" item in the autocomplete list
    And I click on "Add automation instance" "button"
    And I set the following fields to these values:
      | insreference | <reference>instance |
    And I press "Save changes"
    Then I should see "Template inserted successfully"
    And I open credits instance schedule report for "<reference>instance"
    Then ".reportbuilder-report" "css_element" should exist
    And the following <studentshouldorshouldnot> exist in the "reportbuilder-table" table:
      | Full name with link | Status  |
      | Student 1           | Planned |
      | Student 2           | Planned |
    And the following <teachershouldorshouldnot> exist in the "reportbuilder-table" table:
      | Full name with link | Status  |
      | Teacher 2           | Planned |
    And I should see "<schedulecount>" credit schedules with status "planned"

    Examples:
      | template_title  | reference      | recipient_role  | schedulecount | studentshouldorshouldnot | teachershouldorshouldnot |
      | Student Credits | studentcredits | student         | 2             | should                   | should not               |
      | Teacher Credits | teachercredits | teacher         | 1             | should not               | should                   |
      | Manager Credits | managercredits | manager         | 0             | should not               | should not               |
      | Editing Teacher | editingteacher | editingteacher  | 1             | should not               | should not               |

  @javascript
  Scenario: Credits allocation to multiple recipient roles
    Given the following "mod_pulse > automation templates" exist:
      | title              | reference        | visibility | condition |
      | Multi Role Credits | multirolecredits | Show       | enrolment |
    And the following "pulseaction_credits > credits template" exist:
      | template           | title              | reference        | status | credits | allocationmethod | interval | basedate  | recipients      |
      | Multi Role Credits | Multi Role Credits | multirolecredits | Enable | 30      | Add credits      | Once     | enrolment | student,teacher |
    And the following "mod_pulse > automation instances" exist:
      | template           | course | reference                |
      | Multi Role Credits | C1     | multirolecreditsinstance |
    When I log in as "admin"
    And I save the pulse action instance "multirolecreditsinstance" on course "Course 1"
    And I open credits instance schedule report for "multirolecreditsinstance"
    Then ".reportbuilder-report" "css_element" should exist
    And the following should exist in the "reportbuilder-table" table:
      | Full name with link | Status  |
      | Student 1           | Planned |
      | Student 2           | Planned |
      | Teacher 2           | Planned |
    Then I should see "3" credit schedules with status "planned"

  @javascript
  Scenario: Credits allocation with upcoming enrolments
    Given the following "mod_pulse > automation templates" exist:
      | title              | reference         | visibility | condition | condition_status |
      | Upcoming enrolment | upcomingenrolment | show       | enrolment | upcoming         |
    And the following "pulseaction_credits > credits template" exist:
      | template           | status | credits | allocationmethod | interval | basedate  | recipients |
      | Upcoming enrolment | Enable | 40      | Add credits      | Once     | enrolment | student    |
    And the following "mod_pulse > automation instances" exist:
      | template           | course | reference               |
      | Upcoming enrolment | C1     | upcomingenrolmentinstance |
    When I log in as "admin"
    # Trigger the schedule by saving the instance in interface.
    And I wait "20" seconds
    And I save the pulse action instance "upcomingenrolmentinstance" on course "Course 1"
    And I open credits instance schedule report for "upcomingenrolmentinstance"
    Then ".reportbuilder-report" "css_element" should exist
    And the following should not exist in the "reportbuilder-table" table:
      | Full name with link | Status  |
      | Student 1           | Planned |
      | Student 2           | Planned |
    And I should see "0" credit schedules with status "planned"
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | student4 | Student   | 4        | student4@test.com |
    And I am on "Course 1" course homepage
    And I enrol "student4" user as "Student"
    When I follow "Automation"
    And I open credits instance schedule report for "upcomingenrolmentinstance"
    Then ".reportbuilder-report" "css_element" should exist
    And the following should exist in the "reportbuilder-table" table:
      | Full name with link | Status    |
      | Student 4           | Allocated |
    And I should see "1" credit schedules with status "allocated"

  @javascript
  Scenario Outline: Credits allocation with different methods and amounts
    Given I allocate credits "<initial_credits>" to user "<username>"
    And the following "mod_pulse > automation templates" exist:
      | title            | reference   | visibility | condition |
      | <template_title> | <reference> | Show       | enrolment |
    And the following "pulseaction_credits > credits template" exist:
      | template         | title            | reference   | status | credits         | allocationmethod    | interval | basedate  | recipients |
      | <template_title> | <template_title> | <reference> | Enable | <credit_amount> | <allocation_method> | Once     | enrolment | student    |
    And the following "pulseaction_credits > credits instances" exist:
      | template         | course | reference           |
      | <template_title> | C1     | <reference>instance |
    When I log in as "admin"
    And I save the pulse action instance "<reference>instance" on course "Course 1"
    And I run the credits allocation scheduled task
    Then user "<username>" should have "<expected_credits>" credits

    Examples:
      | username | initial_credits | template_title    | reference      | credit_amount | allocation_method | expected_credits |
      | student1 | 0               | Add Credits Test  | addcreditstest | 10            | Add credits       | 10               |
      | student2 | 50              | Add More Credits  | addmorecredits | 25            | Add credits       | 75               |
      | student2 | 100             | Replace Credits   | replacecredits | 50            | Replace credits   | 50               |
      | student1 | 25              | Replace With Zero | replacezero    | 0             | Replace credits   | 0                |

  @javascript
  Scenario Outline: Credits instance override credits configurations
    Given the following "mod_pulse > automation templates" exist:
      | title         | reference    | visibility | condition |
      | Override Test | overridetest | Show       | enrolment |
    And the following "pulseaction_credits > credits template" exist:
      | template      | title         | reference    | status | credits | allocationmethod | interval | basedate  | recipients |
      | Override Test | Override Test | overridetest | Enable | 10      | Add credits      | Once     | enrolment | student    |
    And the following "mod_pulse > automation instances" exist:
      | template      | course | reference            |
      | Override Test | C1     | overridetestinstance |
    And I allocate credits "<initialcredits>" to user "student1"
    When I log in as "admin"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    And I click on ".action-edit" "css_element" in the "Override Test" "table_row"
    And I click on "Credits" "link" in the "ul#automation-tabs" "css_element"
    And I click on "#id_override_pulsecredits_allocationmethod" "css_element"
    And I set the following fields to these values:
      | override[pulsecredits_credits]          | 1  |
      | Credits                                 | 25  |
      | Allocation method          | <new_method>        |
    And I press "Save changes"
    Then I should see "Template updated successfully"
    And I run the credits allocation scheduled task
    Then user "student1" should have "<expected_credits>" credits

    Examples:
      |  initialcredits | override_method | new_method      | expected_credits |
      |  25             | 0               | Add credits     | 50               |
      |  50             | 1               | Replace credits | 25               |
