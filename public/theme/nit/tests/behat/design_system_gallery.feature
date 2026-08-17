@theme @theme_nit
Feature: NIT design system gallery
  In order to review the NIT design system
  As an administrator
  I need the component gallery to render every foundation component

  Background:
    Given I log in as "admin"
    And I visit "/theme/nit/gallery.php"

  Scenario: The gallery renders the token and component showcase
    Then I should see "Colour"
    And I should not see "Exception"
    When I click on "Components" "button"
    Then I should see "Buttons"
    And I should see "Statistics"
    And "Primary" "button" should exist

  Scenario: The fonts tab manages a font per site language
    When I click on "Fonts" "button"
    Then I should see "English font"
    And I should see "Arabic font"
    And "Save fonts" "button" should exist
    And "Remove all fonts" "button" should exist
