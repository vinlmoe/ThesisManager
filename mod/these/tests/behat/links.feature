@mod @mod_these
Feature: Links sent by email only work while they are valid
  In order to protect the thesis documents
  As an external signatory or jury member
  I need an invalid or expired link to be refused with an explanation

  Scenario: An unknown signature link is refused
    When I visit "/mod/these/sign.php?token=abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcd"
    Then I should see "This link is no longer valid"

  Scenario: An unknown jury link is refused
    When I visit "/mod/these/jury.php?token=abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcd"
    Then I should see "This access link is not (or no longer) valid"
