@mod @mod_these
Feature: Thesis files are created by the registrar and followed by the student
  In order to follow a veterinary thesis from the subject to the defence
  As the registrar (DEVE) and as a student
  I need to create a thesis file and see it from the activity

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | deve1    | Dana      | Registrar | deve1@example.com   |
      | student1 | Sam       | Student   | student1@example.com |
      | student2 | Sacha     | Other     | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Theses   | T1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | deve1    | T1     | editingteacher |
      | student1 | T1     | student        |
      | student2 | T1     | student        |
    And the following "activities" exist:
      | activity | course | name         | idnumber |
      | these    | T1     | Theses 2026  | these1   |

  Scenario: A student without a file is invited to propose a subject
    Given I log in as "student2"
    And I am on "Theses" course homepage
    When I follow "Theses 2026"
    Then I should see "My thesis file"
    And I should see "Propose a thesis subject"

  Scenario: A student follows the file created for them
    Given the following "mod_these > dossiers" exist:
      | activity | user     | status | title                |
      | these1   | student1 | active | Leptospirose canine  |
    And I log in as "student1"
    And I am on "Theses" course homepage
    When I follow "Theses 2026"
    Then I should see "My thesis file"
    And I should see "Leptospirose canine"
    And I should not see "Propose a thesis subject"

  @javascript
  Scenario: The registrar creates a file for a student
    Given I log in as "deve1"
    And I am on "Theses" course homepage
    And I follow "Theses 2026"
    And I follow "Create a file"
    When I set the field "Student" to "Sam Student"
    And I press "Create a file"
    Then I should see "File created."
