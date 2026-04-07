@mod @mod_datalynx @javascript
Feature: Test datalynx _rating internal field
  In order to allow rating entries
  As a student
  I need the rating field patterns to render a rating widget and aggregates.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1        | 0        | 1         |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activities" exist:
      | activity | course | idnumber | name                   | assessed | scale |
      | datalynx | C1     | 12345    | Datalynx Test Instance | 1        | 5     |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I add to the "Datalynx Test Instance" datalynx the following fields:
      | type | name | description | param1 | param2 | param3 |
      | text | Text |             |        |        |        |
    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | Gridview   |
      | description | Behat grid |
    And I follow "Set as default view"
    And I follow "Set as edit view"
    And I wait until the page is ready
    And I click on "Edit Gridview" "link"
    And I click on "Entry template" "link"
    And I set the "id_eparam2_editor" editor to "[[Datalynx field Text]] ##ratings:rate## ##ratings:avg## ##edit## ##delete##"
    And I press "Save changes"
    And I log out

  Scenario: Rating widget renders for teachers viewing other users' entries
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type | name | value       |
      | text | Text | Rated entry |
    And I press "Save changes"
    And I press "Continue"
    And I log out
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    Then I should see "Rated entry"
    And "select.postratingmenu" "css_element" should exist

  Scenario: Submitting a rating shows updated average
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type | name | value       |
      | text | Text | Rated entry |
    And I press "Save changes"
    And I press "Continue"
    And I log out
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    Then I should see "Rated entry"
    And "select.postratingmenu" "css_element" should exist
    When I set the field "rating" to "4"
    And I wait until the page is ready
    Then I should see "4"
