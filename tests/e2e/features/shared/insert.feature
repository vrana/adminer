Feature: Inserting, editing and deleting a row
  The round trip through the edit form. Only notes is written to, so that the scenarios reading
  users are unaffected, and each of these starts from an empty table - they share a database and
  run in the order they are written, which is not an order to depend on.

  Background:
    Given I am logged in to the demo database
    And the "notes" table is empty

  Scenario: A new row is saved and listed
    When I open the insert form for the "notes" table
    And I fill "title" with "Who watches the watchmen"
    And I fill "body" with "Added by a scenario — em dash and all."
    And I save the form
    And I open the "notes" table
    Then the table lists 1 row
    And row 1 contains "Who watches the watchmen"
    And row 1 contains "Added by a scenario — em dash and all."

  Scenario: The edit form is offered with the saved values, and saves the changed ones
    When I open the insert form for the "notes" table
    And I fill "title" with "Who watches the watchmen"
    And I save the form
    And I open the "notes" table
    And I edit the first row
    Then the field "title" contains "Who watches the watchmen"
    When I fill "title" with "Who tests the tests"
    And I save the form
    And I open the "notes" table
    Then the table lists 1 row
    And row 1 contains "Who tests the tests"

  Scenario: A deleted row is gone from the list
    When I open the insert form for the "notes" table
    And I fill "title" with "Written to be deleted"
    And I save the form
    And I open the "notes" table
    And I edit the first row
    And I delete the row
    Then the table lists 0 rows
