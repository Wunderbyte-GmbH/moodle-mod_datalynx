@mod @mod_datalynx @javascript
Feature: Test datalynx _comment internal field
  In order to allow students to comment on entries
  As a teacher
  I need the comment field patterns to render a comment count and comment widget.

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
      | activity | course | idnumber | name                   |
      | datalynx | C1     | 12345    | Datalynx Test Instance |
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
    And I set the "id_eparam2_editor" editor to "[[Datalynx field Text]] ##comments:count## ##comments## ##edit## ##delete##"
    And I press "Save changes"
    And I log out

  Scenario: Comment count shows zero before any comments
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type | name | value          |
      | text | Text | Commented entry |
    And I press "Save changes"
    And I press "Continue"
    Then I should see "Commented entry"
    And I should see "0"

  Scenario: Comment count increments after a comment is posted
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type | name | value          |
      | text | Text | Commented entry |
    And I press "Save changes"
    And I press "Continue"
    Then I should see "0"
    When I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I click on "Comments" "link"
    And I wait until the page is ready
    And I set the field "content" to "Great entry!"
    And I follow "Save comment"
    And I wait until the page is ready
    Then I should see "Great entry!"
    And I should see "1"
