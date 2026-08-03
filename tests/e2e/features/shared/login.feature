Feature: Logging in and out
  The login form is the one page every user sees, and the only one which has to work before
  anything else can be tested. Logging out goes through the CSRF token, so a broken token shows
  up here first.

  Scenario: Logging in opens the database
    Given I am logged in to the demo database
    Then the breadcrumb shows the demo database
    And the table list contains "users"
    And the table list contains "notes"

  Scenario: Logging out ends the session
    Given I am logged in to the demo database
    When I log out
    Then the login form is shown
    When I open the "users" table
    Then the login form is shown
