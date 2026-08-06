@mod @mod_pulse @pulseaction_credits @javascript
Feature: Pulse credits action configuration
  In order to configure credit allocations in pulse automation templates
  As a teacher
  I need to configure credits action in pulse automation template

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | Student   | 1      | student1@test.com |
      | student2 | Student   | 2      | student2@test.com |
      | teacher1 | Teacher   | 1      | teacher1@test.com |
    And the following "courses" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1        | 0        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activities" exist:
      | activity | name    | course | idnumber | completion |
      | assign   | Assign1 | C1     | assign1  | 1          |
    And a credit profile field exists

  Scenario: Configure credits action in automation template and instance
    When I log in as "admin"
    And I navigate to "Plugins > Activity modules > Pulse > Automation templates" in site administration
    Then I click on "Create new template" "button"
    And I set the following fields to these values:
      | Title     | Welcome Credits    |
      | Reference | welcomecredits     |
      | Visibility| Show               |
    And I click on "Credits" "link" in the "ul#automation-tabs" "css_element"
    And I set the following fields in the "#pulse-action-credits" "css_element" to these values:
      | Status            | Enabled                    |
      | Credits           | 10                         |
      | Allocation method | Add credits                |
      | Interval          | Once                       |
      | Base date         | Relative to enrollment     |
      | Recipients        | Student                    |
    And I press "Save changes"
    Then I should see "Template inserted successfully"
    And ".action-icon.pulseaction_credits" "css_element" should exist in the "welcomecredits" "table_row"
    When I am on "Course 1" course homepage
    And I follow "Automation"
    And I open the autocomplete suggestions list
    And I click on "Welcome Credits" item in the autocomplete list
    And I click on "Add automation instance" "button"
    And I set the following fields to these values:
      | insreference | instance |
    And I press "Save changes"
    Then I should see "Template inserted successfully"
    And ".action-icon.pulseaction_credits" "css_element" should exist in the "welcomecreditsinstance" "table_row"

  Scenario: Configure credits action in course automation instance
    Given the following "mod_pulse > automation templates" exist:
      | title              | reference         | visibility | condition |
      | Completion Credits | completioncredits | Show       | enrolment |
    When I log in as "admin"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    And I open the autocomplete suggestions list
    And I click on "Completion Credits" item in the autocomplete list
    Then I click on "Add automation instance" "button"
    And I set the field "insreference" to "instance"
    And I click on "Credits" "link" in the "ul#automation-tabs" "css_element"
    # And I wait "20" seconds
    And I click on "#id_override_pulsecredits_actionstatus" "css_element"
    And I set the following fields in the ".tab-pane#pulse-action-credits" "css_element" to these values:
      | Status      | Enabled |
      | override[pulsecredits_credits] | 1 |
      | Credits     | 25 |
      | Recipients | Student |
    And I click on "#id_override_pulsecredits_recipients" "css_element"
    And I press "Save changes"
    And I open credits instance schedule report for "completioncreditsinstance"
    Then ".reportbuilder-report" "css_element" should exist
    And the following should exist in the "reportbuilder-table" table:
      | Full name with link | Status  | Scheduled credits |
      | Student 1           | Planned | 25.00             |
      | Student 2           | Planned | 25.00             |
    And I should see "2" credit schedules with status "planned"

  Scenario: Disable the credits action in course automation instance
    Given the following "mod_pulse > automation templates" exist:
      | title           | reference      | visibility | condition  |
      | Upcoming Credits | upcomingcredits | Show       | enrolment |
    And the following "pulseaction_credits > credits template" exist:
      | template         | title           | reference        | status | credits | allocationmethod | interval | basedate               | recipients |
      | Upcoming Credits | Upcoming Credits | upcomingcredits | Enable | 15      | Add credits      | Once     | Relative to enrollment | student    |
    And the following "pulseaction_credits > credits instances" exist:
      | template         | course | reference     | status   |
      | Upcoming Credits | C1     | instance      | Disabled |
    When I log in as "admin"
    And I save the pulse action instance "upcomingcreditsinstance" on course "Course 1"
    Then ".action-icon.pulseaction_credits" "css_element" should not exist in the "upcomingcreditsinstance" "table_row"
    And I open credits instance schedule report for "upcomingcreditsinstance"
    Then ".reportbuilder-report" "css_element" should exist
    And the following should not exist in the "reportbuilder-table" table:
      | Full name with link | Status  |
      | Student 1           | Planned |
      | Student 2           | Planned |
    And I should see "0" credit schedules with status "planned"
    When I am on "Course 1" course homepage
    And I follow "Automation"
    And I click on ".action-edit" "css_element" in the "upcomingcreditsinstance" "table_row"
    And I click on "Credits" "link" in the "ul#automation-tabs" "css_element"
    And I set the following fields in the "#pulse-action-credits" "css_element" to these values:
      | Status    | Enabled                |
      | override[pulsecredits_credits] | 1 |
      | Credits   | 15                     |
    And I press "Save changes"
    Then ".action-icon.pulseaction_credits" "css_element" should exist in the "upcomingcreditsinstance" "table_row"
    And I open credits instance schedule report for "upcomingcreditsinstance"
    Then ".reportbuilder-report" "css_element" should exist
    And the following should exist in the "reportbuilder-table" table:
      | Full name with link | Status  | Scheduled credits |
      | Student 1           | Planned | 15.00             |
      | Student 2           | Planned | 15.00             |
    And I should see "2" credit schedules with status "planned"

  Scenario: Configure credits action with replace credits method and fixed date
    Given the following "mod_pulse > automation templates" exist:
      | title       | reference | visibility | condition |
      | Fixed date  | fixeddate | Show       | enrolment |
    When I log in as "admin"
    And I navigate to "Plugins > Activity modules > Pulse > Automation templates" in site administration
    And I click on ".action-edit" "css_element" in the "fixeddate" "table_row"
    And I click on "Credits" "link" in the "ul#automation-tabs" "css_element"
    And I set the following fields in the "#pulse-action-credits" "css_element" to these values:
      | Status            | Enabled         |
      | Credits           | 15              |
      | Allocation method | Replace credits |
      | Interval          | Once            |
      | Base date         | Fixed date      |
      | Fixed date        | ##first day of next month## |
      | Recipients        | Student         |
    And I press "Save changes"
    Then I should see "Template updated successfully"
    When I am on "Course 1" course homepage
    And I follow "Automation"
    And I open the autocomplete suggestions list
    And I click on "Fixed date" item in the autocomplete list
    And I click on "Add automation instance" "button"
    And I set the following fields to these values:
      | insreference | fixeddateinstance |
    And I press "Save changes"
    Then I should see "Template inserted successfully"
    And ".action-icon.pulseaction_credits" "css_element" should exist in the "fixeddateinstance" "table_row"
    And I open credits instance schedule report for "fixeddateinstance"
    Then ".reportbuilder-report" "css_element" should exist
    And the following should exist in the "reportbuilder-table" table:
      | Full name with link | Status  | Scheduled credits | Scheduled time |
      | Student 1           | Planned | 15.00             | ##first day of next month##%A, %d %B %Y## |
      | Student 2           | Planned | 15.00             | ##first day of next month##%A, %d %B %Y## |
    And I should see "2" credit schedules with status "planned"

  Scenario: Modify fixed date in instance and verify schedule updates
    Given the following "mod_pulse > automation templates" exist:
      | title               | reference          | visibility | condition |
      | Modifiable Credits  | modifiablecredits  | Show       | enrolment |
    And the following "pulseaction_credits > credits templates" exist:
      | template            | status | credits | allocationmethod | interval | basedate   | fixedbasedate     | recipients |
      | Modifiable Credits  | Enable | 75      | Add credits      | Once     | Fixed date | ##+5 days##       | student    |
    And the following "pulseaction_credits > credits instances" exist:
      | template            | course | reference              | status  |
      | Modifiable Credits  | C1     | instance     | Enable  |
    When I log in as "admin"
    And I save the pulse action instance "modifiablecreditsinstance" on course "Course 1"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    And I open credits instance schedule report for "modifiablecreditsinstance"
    Then I should see "2" credit schedules with status "planned"
    Then ".reportbuilder-report" "css_element" should exist
    And the following should exist in the "reportbuilder-table" table:
      | Full name with link | Status  | Scheduled credits | Scheduled time |
      | Student 1           | Planned | 75.00             | ##+5 days##%A, %d %B %Y## |
      | Student 2           | Planned | 75.00             | ##+5 days##%A, %d %B %Y## |
    And I am on "Course 1" course homepage
    And I follow "Automation"
    And I click on ".action-edit" "css_element" in the "modifiablecreditsinstance" "table_row"
    And I click on "Credits" "link" in the "ul#automation-tabs" "css_element"
    And I click on "#id_override_pulsecredits_fixeddate" "css_element"
    And I set the following fields in the "#pulse-action-credits" "css_element" to these values:
      | Fixed date        | ##first day of next year## |
    And I press "Save changes"
    And I open credits instance schedule report for "modifiablecreditsinstance"
    Then the following should exist in the "reportbuilder-table" table:
      | Full name with link | Status  | Scheduled credits | Scheduled time |
      | Student 1           | Planned | 75.00             | ##first day of next year##%A, %d %B %Y## |
      | Student 2           | Planned | 75.00             | ##first day of next year##%A, %d %B %Y## |

  Scenario: Fixed date in the past creates immediate schedule
    Given the following "mod_pulse > automation templates" exist:
      | title            | reference       | visibility | condition |
      | Past Date Award  | pastdateaward   | Show       | enrolment |
    And the following "pulseaction_credits > credits templates" exist:
      | template        | status | credits | allocationmethod | interval | basedate   | fixedbasedate | recipients |
      | Past Date Award | Enable | 25      | Add credits      | Once     | Fixed date | ##-1 day##    | student    |
    And the following "pulseaction_credits > credits instances" exist:
      | template        | course | reference        | status  |
      | Past Date Award | C1     | instance     | Enable  |
    When I log in as "admin"
    And I am on "Course 1" course homepage
    And I follow "Automation"
    And I save the pulse action instance "pastdateawardinstance" on course "Course 1"
    And I open credits instance schedule report for "pastdateawardinstance"
    Then ".reportbuilder-report" "css_element" should exist
    And I should see "2" credit schedules with status "planned"
    And the following should exist in the "reportbuilder-table" table:
      | Full name with link | Status  | Scheduled credits |
      | Student 1           | Planned | 25.00             |
      | Student 2           | Planned | 25.00             |
