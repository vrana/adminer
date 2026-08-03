Feature: Editing an enum column
  enum is MySQL's type and Adminer treats it as one: instead of a text field, the edit form offers
  the values the column declares as radio buttons.

  Background:
    Given I am logged in to the demo database

  Scenario: A new row is offered the declared values, starting on the default
    When I open the insert form for the "articles" table
    Then the "status" field offers "draft, published, archived"
    And the "status" field has "draft" picked

  Scenario: An existing row is offered with its own value picked
    When I open the "articles" table
    And I edit the first row
    Then the "status" field has "draft" picked

  Scenario: The picked value is the one saved
    When I open the insert form for the "articles" table
    And I fill "title" with "Written by a scenario"
    And I pick "archived" for "status"
    And I save the form
    And I open the "articles" table
    Then row 3 contains "archived"
    And row 3 contains "Written by a scenario"
