Feature: Listing, sorting and searching rows
  The data list is the page Adminer is used for most. The names it lists are seeded out of
  alphabetical order and with accents, so that sorting visibly reorders them and a broken encoding
  fails a scenario instead of ending up in a screenshot nobody looks at.

  Background:
    Given I am logged in to the demo database
    When I open the "users" table

  Scenario: The seeded rows are listed
    Then the table lists 6 rows
    And row 1 contains "Diana Marsh"

  Scenario: Sorting reorders the rows and says so in the URL
    When I sort by the "name" column
    Then the table lists 6 rows
    And row 1 contains "Amélie Fontaine"
    And the URL contains "order"

  Scenario: A reload keeps the sorted order
    When I sort by the "name" column
    And I reload the page
    Then row 1 contains "Amélie Fontaine"

  Scenario: Searching narrows the list down to the matching row
    When I search for "eero@example.com" in "email"
    Then the table lists 1 row
    And row 1 contains "Eero Väisälä"
