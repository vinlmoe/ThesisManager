@mod @mod_these
Feature: The subject of a confidential thesis is only known to the student, the registrar, the library, the director and the jury
  In order to protect a confidential thesis
  As the registrar
  I need its title to be hidden from the other staff

  Background:
    Given the following "users" exist:
      | username | firstname | lastname  | email                |
      | deve1    | Dana      | Registrar | deve1@example.com    |
      | library1 | Lou       | Library   | library1@example.com |
      | viewer1  | Val       | Viewer    | viewer1@example.com  |
      | student1 | Sam       | Student   | student1@example.com |
    And the following "roles" exist:
      | shortname    | name          | archetype |
      | theseviewall | Thesis viewer |           |
    And the following "courses" exist:
      | fullname | shortname |
      | Theses   | T1        |
    And the following "permission overrides" exist:
      | capability        | permission | role         | contextlevel | reference |
      | mod/these:view    | Allow      | theseviewall | Course       | T1        |
      | mod/these:viewall | Allow      | theseviewall | Course       | T1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | deve1    | T1     | editingteacher |
      | library1 | T1     | theselibrary   |
      | viewer1  | T1     | theseviewall   |
      | student1 | T1     | student        |
    And the following "activities" exist:
      | activity | course | name        | idnumber |
      | these    | T1     | Theses 2026 | these1   |
    And the following "mod_these > dossiers" exist:
      | activity | user     | status | title                          | confidential |
      | these1   | student1 | active | Experimental distemper vaccine | 1            |

  Scenario: Staff who may see all files but not the subject see the file without its subject
    Given I log in as "viewer1"
    And I am on "Theses" course homepage
    When I follow "Theses 2026"
    Then I should see "Confidential thesis"
    And I should not see "Experimental distemper vaccine"
    And I follow "Open"
    And I should see "Confidential thesis"
    And I should not see "Experimental distemper vaccine"

  Scenario: The registrar, the library and the student see the subject
    Given I log in as "deve1"
    And I am on "Theses" course homepage
    When I follow "Theses 2026"
    Then I should see "Experimental distemper vaccine"
    And I log out
    And I log in as "library1"
    And I am on "Theses" course homepage
    And I follow "Theses 2026"
    And I should see "Experimental distemper vaccine"
    And I log out
    And I log in as "student1"
    And I am on "Theses" course homepage
    And I follow "Theses 2026"
    And I should see "Experimental distemper vaccine"
