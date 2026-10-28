@mod @mod_these
Feature: Subject people come from the teacher directory and the A6 validation has its own page
  In order to avoid typing what the module already knows
  As a student and as the registrar
  I need the director title and an internal co-director taken from the directory, and a page for the A6

  Background:
    Given the following "users" exist:
      | username  | firstname | lastname  | email                 |
      | deve1     | Dana      | Registrar | deve1@example.com     |
      | director1 | Dora      | Director  | director1@example.com |
      | student1  | Sam       | Student   | student1@example.com  |
      | student2  | Sacha     | Other     | student2@example.com  |
    And the following "courses" exist:
      | fullname | shortname |
      | Theses   | T1        |
    And the following "course enrolments" exist:
      | user      | course | role           |
      | deve1     | T1     | editingteacher |
      | director1 | T1     | teacher        |
      | student1  | T1     | student        |
      | student2  | T1     | student        |
    And the following "activities" exist:
      | activity | course | name        | idnumber |
      | these    | T1     | Theses 2026 | these1   |
    And the following "mod_these > jurors" exist:
      | title | firstname | lastname | email                 | status |
      | Pr    | Dora      | Director | director1@example.com | pr     |
      | Dr    | Claire    | Martin   | claire@example.com    | mcf    |

  Scenario: The student chooses an internal co-director and signs under their account name
    Given I log in as "student1"
    And I am on "Theses" course homepage
    And I follow "Theses 2026"
    And I follow "Propose a thesis subject"
    And the field "Signatory name" matches value "Sam Student"
    And "//input[@name='signername'][@disabled]" "xpath_element" should exist
    When I set the following fields to these values:
      | Title              | Canine leptospirosis        |
      | Thesis director    | Dora Director               |
      | Co-director        | Internal to the institution |
      | Internal co-director | Dr MARTIN Claire          |
    And I press "Save draft"
    Then I should see "Draft saved."
    And I should see "Dr MARTIN Claire"

  Scenario: The registrar records the A6 validation of a student without a file
    Given I log in as "deve1"
    And I am on "Theses" course homepage
    And I follow "Theses 2026"
    And I follow "A6 validation"
    And I should see "not validated" in the "Sam Student" "table_row"
    And I should see "No file" in the "Sacha Other" "table_row"
    When I set the field with xpath "//tr[contains(., 'Sacha Other')]//input[@name='userids[]']" to "1"
    And I set the field "Final year (A6) validated on" to "2025-07-01"
    And I press "Record the validation"
    Then I should see "A6 validation recorded for 1 student(s)."
    And I should see "July 2025" in the "Sacha Other" "table_row"
    And I should see "not validated" in the "Sam Student" "table_row"
    And I click on "Edit" "link" in the "Sacha Other" "table_row"
    And the field "aga6date[year]" matches value "2025"
