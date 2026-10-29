@mod @mod_these
Feature: An external director is backed by an internal examiner and the studies validation has its own page
  In order to avoid typing what the module already knows
  As a student and as the registrar
  I need an internal examiner to handle the procedure for an external director, and a page for the DEFV and the A6

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

  Scenario: The student declares an external director and an internal examiner, and signs under their account name
    Given I log in as "student1"
    And I am on "Theses" course homepage
    And I follow "Theses 2026"
    And I follow "Propose a thesis subject"
    And the field "Signatory name" matches value "Sam Student"
    And "//input[@name='signername'][@disabled]" "xpath_element" should exist
    And I should not see "Co-director"
    And I should not see "Subject attachments"
    When I set the following fields to these values:
      | Title                                               | Canine leptospirosis       |
      | The thesis director is external to the institution | 1                          |
      | External director: title, name                      | Pr Jean Durand             |
      | Internal examiner                                   | Dora Director              |
    And I press "Save draft"
    Then I should see "Draft saved."
    And I should see "Pr Jean Durand"
    And I should see "Dora Director" in the "Internal examiner" "table_row"

  Scenario: The registrar records the studies validation of a student without a file
    Given I log in as "deve1"
    And I am on "Theses" course homepage
    And I follow "Theses 2026"
    And I follow "Studies validation"
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
    And I set the field "defvdate[enabled]" to "1"
    And I set the field "defvdate[year]" to "2023"
    And I press "Save changes"
    And I should see "Studies validation recorded."
    And I should see "2023" in the "Sacha Other" "table_row"
