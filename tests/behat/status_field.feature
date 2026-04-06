@mod @mod_datalynx @javascript
Feature: Test datalynx _status internal field
  In order to manage entry submission workflow
  As a student and teacher
  I need the status field to display correctly, prevent editing after final submission,
  and filter entries by their status.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1        | 0        | 1         |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | manager1 | Manager   | 1        | manager1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | manager1 | C1     | manager        |
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
    And I follow the datalynx "View Filters" link
    And I follow "Add a filter"
    And I set the field "name" to "DraftFilter"
    And I set the field "searchandor0" to "AND"
    And I set the field "searchfield0" to "status"
    And I press "Reload"
    And I set the field "f_0_status" to "1"
    And I press "Save changes"
    Then I should see "DraftFilter"
    And I follow "Duplicate"
    And I press "Continue"
    And I follow "Copy of DraftFilter"
    And I set the field "name" to "FinalFilter"
    And I set the field "f_0_status" to "2"
    And I press "Save changes"
    Then I should see "FinalFilter"
    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | Gridview   |
      | description | Behat grid |
    And I follow "Set as default view"
    And I follow "Set as edit view"
    And I wait until the page is ready
    And I click on "Edit Gridview" "link"
    And I click on "Entry template" "link"
    And I set the "id_eparam2_editor" editor to "[[Datalynx field Text]] ##status## ##edit## ##delete##"
    And I press "Save changes"
    And I add to "Datalynx Test Instance" datalynx the view of "Tabular" type with:
      | name        | Status A view |
      | description | Status A view |
      | filter      | DraftFilter   |
    And I add to "Datalynx Test Instance" datalynx the view of "Tabular" type with:
      | name        | Status B view         |
      | description | Status B view         |
      | filter      | FinalFilter           |
    And I log out

  Scenario: Status label displays correctly as student changes status
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type | name | value          |
      | text | Text | Entry by s1    |
    And I press "Save changes"
    And I press "Continue"
    Then I should see "Not set"
    When I click on "a [aria-label='Edit']" "css_element"
    And I set the field with xpath "//select[contains(@name,'field_status_')]" to "1"
    And I press "Save changes"
    And I press "Continue"
    Then I should see "Draft"
    When I click on "a [aria-label='Edit']" "css_element"
    And I set the field with xpath "//select[contains(@name,'field_status_')]" to "2"
    And I press "Save changes"
    And I press "Continue"
    Then I should see "Final submission"

  Scenario: Final submission prevents student from editing but not the teacher
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type | name | value       |
      | text | Text | Final entry |
    And I press "Save changes"
    And I press "Continue"
    Then I should see "Not set"
    When I click on "a [aria-label='Edit']" "css_element"
    And I set the field with xpath "//select[contains(@name,'field_status_')]" to "2"
    And I press "Save changes"
    And I press "Continue"
    Then I should see "Final submission"
    And "a [aria-label='Edit']" "css_element" should not exist
    And I log out
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    Then "a [aria-label='Edit']" "css_element" should exist
    And I click on "a [aria-label='Edit']" "css_element"
    Then I should not see "It's not allowed to edit this entry."

  Scenario: Status filter separates Draft and Final submission entries (manager sees all)
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type | name | value       |
      | text | Text | Draft entry |
    And I press "Save changes"
    And I press "Continue"
    When I click on "a [aria-label='Edit']" "css_element"
    And I set the field with xpath "//select[contains(@name,'field_status_')]" to "1"
    And I press "Save changes"
    And I press "Continue"
    And I log out
    When I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type | name | value        |
      | text | Text | Final entry  |
    And I press "Save changes"
    And I press "Continue"
    When I click on "a [aria-label='Edit']" "css_element"
    And I set the field with xpath "//select[contains(@name,'field_status_')]" to "2"
    And I press "Save changes"
    And I press "Continue"
    And I log out
    When I log in as "manager1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I select "Status B view" from the "view" singleselect
    And I wait until the page is ready
    Then I should see "Final entry"
    And I should not see "Draft entry"
    When I select "Status A view" from the "view" singleselect
    And I wait until the page is ready
    Then I should see "Draft entry"
    And I should not see "Final entry"
