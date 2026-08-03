Feature: Switching PostgreSQL schemas
  PostgreSQL is the only driver here whose database holds more than one namespace, so Adminer
  prints a Schema selector next to the database one and carries the choice in ns=. The table list,
  the links and the data all follow from it.

  Background:
    Given I am logged in to the demo database

  Scenario: Both schemas are offered and the tables of the current one are listed
    Then the schema selector offers "analytics"
    And the table list contains "users"
    But the table list does not contain "reports"

  Scenario: Switching the selector lists the tables of the other schema
    When I switch to the "analytics" schema
    Then the URL contains "ns=analytics"
    And the table list contains "reports"
    But the table list does not contain "users"

  Scenario: The rows of the other schema are reachable
    When I open the "reports" table in the "analytics" schema
    Then the table lists 2 rows
    And row 1 contains "Daily summary"
