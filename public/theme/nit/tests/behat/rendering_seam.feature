@theme @theme_nit @local_nit_core
Feature: NIT rendering seam
  In order to render surfaces consistently across the framework
  As a developer
  The SDK view-model must render through the theme on the dashboard

  Scenario: The gated welcome panel renders on the dashboard when enabled
    Given the following config values are set as admin:
      | theme | nit |
    And the following config values are set as admin:
      | showwelcomepanel | 1 | local_nit_core |
    When I log in as "admin"
    And I visit "/my/"
    Then I should see "Welcome back"

  Scenario: The welcome panel is hidden by default
    Given the following config values are set as admin:
      | theme | nit |
    When I log in as "admin"
    And I visit "/my/"
    Then I should not see "Welcome back, Admin"
