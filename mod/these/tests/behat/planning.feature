@mod @mod_these
Feature: The defence schedule shows the defences of each week and month
  In order to organise the defences
  As the registrar (DEVE)
  I need to browse the defences week by week and print the monthly schedule

  Background:
    Given the following "users" exist:
      | username | firstname | lastname  | email                |
      | deve1    | Dana      | Registrar | deve1@example.com    |
      | student1 | Sam       | Student   | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Theses   | T1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | deve1    | T1     | editingteacher |
      | student1 | T1     | student        |
    And the following "activities" exist:
      | activity | course | name        | idnumber |
      | these    | T1     | Theses 2026 | these1   |
    And the following "mod_these > dossiers" exist:
      | activity | user     | status    | title               | defensedate                | defenselocation |
      | these1   | student1 | scheduled | Leptospirose canine | ##monday next week 14:00## | Amphithéâtre A  |

  Scenario: The registrar finds next week's defence and the monthly schedule
    Given I log in as "deve1"
    And I am on "Theses" course homepage
    And I follow "Theses 2026"
    When I follow "Defence schedule"
    Then I should see "This week"
    And I should see "Upcoming weeks with defences"
    And I follow "Next week »"
    And I should see "Leptospirose canine"
    And I should see "Sam Student"
    And I should see "Amphithéâtre A"
    And I should see "Date confirmed"
    And I follow "By month"
    And I should see "Leptospirose canine"
    And I should see "Print the monthly schedule (PDF)"

  Scenario: A student has no access to the schedule
    Given I log in as "student1"
    And I am on "Theses" course homepage
    When I follow "Theses 2026"
    Then "Defence schedule" "link" should not exist
