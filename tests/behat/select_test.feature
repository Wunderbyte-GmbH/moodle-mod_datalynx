@mod @mod_datalynx @dev @_file_upload @select
Feature: Select acts weird blah blah

  Background:
    Given the following "courses" exist:
      | fullname  | shortname   | category  | groupmode   |
      | Course 1  | C1          | 0         | 1           |
    And the following "users" exist:
      | username  | firstname   | lastname  | email                   |
      | teacher1  | Teacher     | 1         | teacher1@mailinator.com |
      | student1  | Student     | 1         | student1@mailinator.com |
      | student2  | Student     | 2         | student2@mailinator.com |
      | student3  | Student     | 3         | student3@mailinator.com |
      | student4  | Student     | 4         | student4@mailinator.com |
    And the following "course enrolments" exist:
      | user      | course  | role            |
      | teacher1  | C1      | editingteacher  |
      | student1  | C1      | student         |
      | student2  | C1      | student         |
      | student3  | C1      | student         |
      | student4  | C1      | student         |
    And the following "activities" exist:
      | activity | course | idnumber | name                   | approval |
      | datalynx | C1     | 12345    | Datalynx Test Instance | 1        |

  @javascript
  Scenario: Login as teacher to see approved and not approved entries
    Given I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Datalynx Test Instance"
    And I follow "Manage"
    And I follow "Views"
    When I set the field "Add a view" to "Grid"
    Then I should see "New Grid view"