@theme @theme_nit @local_nit_core
Feature: NIT branding engine
  In order to brand an instance without code
  As an administrator
  I can choose a preset and the whole UI re-themes

  Background:
    Given the following config values are set as admin:
      | theme | nit |

  Scenario: The branding settings page is available under Appearance
    When I log in as "admin"
    And I navigate to "Appearance > NIT Branding" in site administration
    Then I should see "Brand preset"
    And I should see "Primary colour"

  Scenario: Selecting a preset is accepted
    Given the following config values are set as admin:
      | brand_preset | medical | local_nit_core |
    When I log in as "admin"
    And I am on the "NIT Design System — Component Gallery" page
    Then I should not see "Exception"
