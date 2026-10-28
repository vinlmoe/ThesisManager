@mod @mod_these
Feature: The registrar imports existing files and exports the register
  In order to take over files started outside the module and follow them
  As the registrar
  I need to import files at any of their states and export the register with their signatures

  Background:
    Given the following "users" exist:
      | username  | firstname | lastname  | email                 |
      | deve1     | Dana      | Registrar | deve1@example.com     |
      | director1 | Dora      | Director  | director1@example.com |
      | student1  | Sam       | Student   | student1@example.com  |
      | student2  | Sacha     | Other     | student2@example.com  |
      | student3  | Lou       | Third     | student3@example.com  |
    And the following "courses" exist:
      | fullname | shortname |
      | Theses   | T1        |
    And the following "course enrolments" exist:
      | user      | course | role           |
      | deve1     | T1     | editingteacher |
      | director1 | T1     | teacher        |
      | student1  | T1     | student        |
      | student2  | T1     | student        |
      | student3  | T1     | student        |
    And the following "activities" exist:
      | activity | course | name        | idnumber |
      | these    | T1     | Theses 2026 | these1   |
    And the following "mod_these > jurors" exist:
      | title | firstname | lastname | email              | status |
      | Dr    | Claire    | Martin   | claire@example.com | mcf    |

  @javascript @_file_upload
  Scenario: The registrar simulates then imports files at different states
    Given I log in as "deve1"
    And I am on "Theses" course homepage
    And I follow "Theses 2026"
    And I follow "Import files"
    When I upload "mod/these/tests/fixtures/import_dossiers.csv" file to "File (CSV, Excel or OpenDocument)" filemanager
    And I press "Import"
    Then I should see "Dry run: 2 file(s) importable, 2 line(s) with errors."
    And I should see "Ready to import — Corrections requested by the jury — internal co-director: Dr MARTIN Claire"
    And I should see "User not found: nobody"
    And I should see "Status cannot be imported: scheduled"
    And I follow "Theses 2026"
    And I should not see "Canine leptospirosis"
    And I follow "Import files"
    And I upload "mod/these/tests/fixtures/import_dossiers.csv" file to "File (CSV, Excel or OpenDocument)" filemanager
    And I set the field "Dry run (create nothing)" to "0"
    And I press "Import"
    And I should see "2 file(s) imported, 2 line(s) with errors."
    And I follow "Theses 2026"
    And I should see "Canine leptospirosis"
    And I should see "Feline diabetes"

  Scenario: The register export lists the A6, the co-director and the missing signatures
    Given the following "mod_these > dossiers" exist:
      | activity | user     | status          | title                | director  |
      | these1   | student1 | subject_approved | Canine leptospirosis | director1 |
    And I log in as "deve1"
    And I am on "Theses" course homepage
    And I follow "Theses 2026"
    And I follow "Register export"
    When I set the field "Format" to "HTML table"
    And I press "Download"
    Then I should see "A6 validation"
    And I should see "Internal or external co-director"
    And I should see "Signatures missing to move to the next step"
    And I should see "Last refusal by a registrar agent"
    And I should see "Canine leptospirosis"
    And I should see "not validated"
    And I should see "Thesis subject form — Student ; Thesis subject form — Thesis director"
