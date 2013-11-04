@javascript
Feature: Add a new option to a choice attribute directly from the product edit form
  In order to easily add a new option to a choice attribute
  As Julia
  I need to be able to create a new attribute option without leaving the product edit page

  Background:
    Given a "car_tire" product
    And the following attributes:
      | code     | label    | type                     |
      | diameter | Diameter | pim_catalog_simpleselect |
      | widths   | Widths   | pim_catalog_multiselect  |
    And the following "Diameter" attribute options: 15 and 17
    And the following "Widths" attribute options: 215 and 225
    And the following product values:
      | product  | attribute | value |
      | car_tire | diameter  | 15    |
      | car_tire | widths    | 215   |
    And I am logged in as "Julia"
    And I am on the "car_tire" product page

  Scenario: Sucessfully add a new option to a simple select attribute
    Given I add a new option to the "Diameter" attribute
    And I fill in the following information in the popin:
      | Code | 19 |
    And I press the "Save" button in the popin
    Then I should see flash message "Option successfully created"
    When I change the Diameter to "[19]"
    And I save the product
    Then the product Diameter should be "[19]"

  Scenario: Sucessfully add a new option to a multi select attribute
    Given I add a new option to the "Widths" attribute
    And I fill in the following information in the popin:
      | Code | 245 |
    And I press the "Save" button in the popin
    Then I should see flash message "Option successfully created"
    When I change the Widths to "[215], [245]"
    And I save the product
    Then the product Widths should be "[215], [245]"
