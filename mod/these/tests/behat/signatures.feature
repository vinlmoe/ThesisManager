@mod @mod_these
Feature: A file only moves on once the documents of the previous steps are signed
  In order to keep signed documents in step with the workflow
  As the registrar, a signing officer and the library
  I need a step to be blocked while signatures are missing, and to send the university documents for signature

  Background:
    Given the following "users" exist:
      | username  | firstname | lastname   | email                 |
      | deve1     | Dana      | Registrar  | deve1@example.com     |
      | agent1    | Alex      | Officer    | agent1@example.com    |
      | library1  | Lou       | Library    | library1@example.com  |
      | director1 | Dora      | Director   | director1@example.com |
      | student1  | Sam       | Student    | student1@example.com  |
      | student2  | Sacha     | Scheduled  | student2@example.com  |
    And the following "courses" exist:
      | fullname | shortname |
      | Theses   | T1        |
    And the following "course enrolments" exist:
      | user      | course | role           |
      | deve1     | T1     | editingteacher |
      | agent1    | T1     | editingteacher |
      | library1  | T1     | teacher        |
      | director1 | T1     | teacher        |
      | student1  | T1     | student        |
      | student2  | T1     | student        |
    And the following "activities" exist:
      | activity | course | name        | idnumber |
      | these    | T1     | Theses 2026 | these1   |
    And the following "mod_these > library agents" exist:
      | activity | user     |
      | these1   | library1 |
    And the following "mod_these > deve agents" exist:
      | activity | user   |
      | these1   | agent1 |
    And the following "mod_these > dossiers" exist:
      | activity | user     | status           | title               | director  | agreement | conclusions        |
      | these1   | student1 | defense_validated | Canine leptospirosis | director1 | 1         | Final conclusions. |
    And the following "mod_these > agent signature requests" exist:
      | activity | user     | documents              | validator |
      | these1   | student1 | agreement, conclusions | deve1     |

  Scenario: The library cannot certify compliance while the signing officer has not signed
    Given I log in as "library1"
    And I am on "Theses" course homepage
    And I follow "Theses 2026"
    When I click on "Check manuscript compliance" "link" in the "Sam Student" "table_row"
    Then I should see "Step blocked: documents of the previous steps are still waiting for these signatures."
    And I should see "Administrative and scientific approval form — Registrar"
    And "Certify compliance" "button" should not exist
    And "Return the file" "button" should exist

  Scenario: The signing officer sees who validated the documents and refuses to sign them
    Given I log in as "agent1"
    And I visit "/mod/these/mysignatures.php"
    And I should see "validated by Dana Registrar on"
    When I follow "Refuse to sign…"
    And I set the field "Reason for refusal" to "Wrong DEFV date in the administrative approval."
    And I press "Refuse and return the file"
    Then I should see "Signature refused: the file is now \"Defence file returned to the student\"."
    And I should not see "Canine leptospirosis"
    And I log out
    And I log in as "deve1"
    And I am on "Theses" course homepage
    And I follow "Theses 2026"
    And I follow "Open"
    And I should see "File returned by Alex Officer"
    And I should see "Wrong DEFV date in the administrative approval."

  Scenario: The minutes wait for the documents signed by the university
    Given the following "mod_these > dossiers" exist:
      | activity | user     | status    | title           | director  | agreement |
      | these1   | student2 | scheduled | Feline diabetes | director1 | 1         |
    And the following "mod_these > signatures" exist:
      | activity | user     | document   | slot     |
      | these1   | student2 | agreement  | deve     |
      | these1   | student2 | agreement  | director |
      | these1   | student2 | agreement  | dgrep    |
      | these1   | student2 | conformity | library  |
    And I log in as "deve1"
    And I am on "Theses" course homepage
    And I follow "Theses 2026"
    And "Upload the scans back from the university" "link" should exist in the "Sacha Scheduled" "table_row"
    When I click on "Open" "link" in the "Sacha Scheduled" "table_row"
    And I follow "Upload the minutes"
    Then I should see "Documents for the university"
    And I should see "waiting for: CCEM president"
    And I should see "Step blocked: the documents sent to the university have not come back signed."
    And the following "mod_these > signatures" exist:
      | activity | user     | document  | slot | method |
      | these1   | student2 | agreement | ccem | scan   |
    And I reload the page
    And I should not see "Step blocked"
    And I should see "Result"
