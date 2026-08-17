@theme @theme_nit
Feature: NIT theme foundation smoke
  In order to trust the NIT theme foundation
  As an administrator
  I need the theme to install, be selectable, and render core pages without errors

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | Student   | One      | student1@nit.test |
    And the following config values are set as admin:
      | theme | nit |

  Scenario: The dashboard renders under the NIT theme
    When I log in as "student1"
    Then I should see "Dashboard"
    And I should not see "Exception"

  Scenario: The login page renders under the NIT theme
    When I am on the login page
    Then I should see "Log in"
