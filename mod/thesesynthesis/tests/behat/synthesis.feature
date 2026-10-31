@mod @mod_thesesynthesis
Feature: Theses of several cohorts are followed from a single thesis follow-up activity
  In order to follow the theses of every cohort in one place
  As a thesis director, as the registrar and as a teacher
  I need to list, filter, open and search the theses of the followed cohorts

  Background:
    Given the following "users" exist:
      | username  | firstname | lastname  | email                 |
      | director1 | Diane     | Director  | director1@example.com |
      | deve1     | Dana      | Registrar | deve1@example.com     |
      | teacher1  | Tom       | Teacher   | teacher1@example.com  |
      | student1  | Sam       | Student   | student1@example.com  |
      | student2  | Sacha     | Second    | student2@example.com  |
      | student3  | Carla     | Moreau    | student3@example.com  |
      | student4  | Hugo      | Fourth    | student4@example.com  |
    And the following "courses" exist:
      | fullname    | shortname |
      | Cohort 2026 | C2026     |
      | Cohort 2027 | C2027     |
      | Follow-up   | FOLLOW    |
    And the following "course enrolments" exist:
      | user      | course | role           |
      | director1 | C2026  | teacher        |
      | director1 | C2027  | teacher        |
      | director1 | FOLLOW | teacher        |
      | deve1     | C2026  | editingteacher |
      | deve1     | FOLLOW | editingteacher |
      | teacher1  | C2026  | teacher        |
      | teacher1  | C2027  | teacher        |
      | teacher1  | FOLLOW | teacher        |
      | student1  | C2026  | student        |
      | student2  | C2027  | student        |
      | student3  | C2026  | student        |
      | student4  | C2026  | student        |
    And the following "activities" exist:
      | activity       | course | name            | idnumber |
      | these          | C2026  | Theses 2026     | these1   |
      | these          | C2027  | Theses 2027     | these2   |
      | thesesynthesis | FOLLOW | Thesis tracking | synth1   |
    And the following "mod_these > dossiers" exist:
      | activity | user     | status           | title                               | director  | confidential |
      | these1   | student1 | active           | Canine leptospirosis in urban areas | director1 | 0            |
      | these2   | student2 | scheduled        | Feline asthma treatment             | director1 | 0            |
      | these1   | student3 | active           | Leptospirosis vaccine trial         |           | 1            |
      | these1   | student4 | defense_approved | Equine colic surgery                |           | 0            |
    And the thesis follow-up "synth1" follows the thesis activities "these1, these2"

  Scenario: A director sees the theses they direct in both cohorts, filters them by status and opens one
    Given I am on the "synth1" "thesesynthesis activity" page logged in as "director1"
    Then I should see "Followed files"
    And I should see "Canine leptospirosis in urban areas"
    And I should see "Feline asthma treatment"
    And I should not see "Equine colic surgery"
    When I set the field "status" to "Defence scheduled"
    And I press "Filter"
    Then I should see "Feline asthma treatment"
    And I should not see "Canine leptospirosis in urban areas"
    When I click on "Open" "link" in the "Feline asthma treatment" "table_row"
    Then I should see "Feline asthma treatment"
    And I should see "Sacha Second"
    And I should not see "Followed files"

  Scenario: The registrar sees the files awaiting their validation under "To do"
    Given I am on the "synth1" "thesesynthesis activity" page logged in as "deve1"
    Then I should see "To do"
    And I should see "Equine colic surgery" in the "(//table[contains(concat(' ', normalize-space(@class), ' '), ' these-dossiers ')])[1]" "xpath_element"
    And I should not see "Canine leptospirosis in urban areas" in the "(//table[contains(concat(' ', normalize-space(@class), ' '), ' these-dossiers ')])[1]" "xpath_element"
    And I should not see "Feline asthma treatment"

  Scenario: Searching a word of the title finds a thesis and keeps a confidential subject hidden from a teacher outside the jury
    Given I am on the "synth1" "thesesynthesis activity" page logged in as "teacher1"
    And I follow "Search theses"
    When I set the field "search-q" to "leptospirosis"
    And I click on "Search" "button" in the "#thesesynthesis-search" "css_element"
    Then I should see "1 thesis/theses found"
    And I should see "Canine leptospirosis in urban areas"
    And I should not see "Leptospirosis vaccine trial"
    When I set the field "search-q" to "Moreau"
    And I click on "Search" "button" in the "#thesesynthesis-search" "css_element"
    Then I should see "1 thesis/theses found"
    And I should see "Carla Moreau"
    And I should see "Confidential thesis"
    And I should not see "Leptospirosis vaccine trial"
