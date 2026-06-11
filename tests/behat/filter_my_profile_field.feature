@mod @mod_datalynx
Feature: Filter datalynx entries by a select field that matches the viewer's profile field
  In order to let department managers only see the entries that concern them
  As a non-editing teacher
  I need a view whose filter matches a select field against my own user profile field

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "custom profile fields" exist:
      | datatype | shortname   | name         |
      | text     | managerrole | Manager role |
    And the following "users" exist:
      | username | firstname | lastname | email                | profile_field_managerrole    |
      | teacher1 | Teacher   | 1        | teacher1@example.com |                              |
      | manager1 | Manager   | One      | manager1@example.com | Department manager faculty 1 |
      | manager2 | Manager   | Two      | manager2@example.com | Department manager faculty 2 |
      | student1 | Student   | 1        | student1@example.com |                              |
      | student2 | Student   | 2        | student2@example.com |                              |
      | student3 | Student   | 3        | student3@example.com |                              |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | manager1 | C1     | teacher        |
      | manager2 | C1     | teacher        |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
    And the following "activities" exist:
      | activity | course | idnumber | name                 |
      | datalynx | C1     | dl1      | Departments datalynx |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I add to the "Departments datalynx" datalynx the following fields:
      | type   | name               | description | param1                                                              |
      | text   | Title              |             |                                                                     |
      | select | Department manager |             | Department manager faculty 1,Department manager faculty 2,Coordinator |
    And the "Departments datalynx" datalynx has the following "matches my profile field" filters:
      | name          | field              | profile     |
      | Manager filter | Department manager | managerrole |
    And I add to "Departments datalynx" datalynx the view of "Tabular" type with:
      | name        | Manager view   |
      | description | Manager view   |
      | filter      | Manager filter |
    And I follow "Set as default view"
    And the "Departments datalynx" datalynx has the following entries:
      | user     | Title              | Department manager           |
      | student1 | Entry by student 1 | Department manager faculty 2 |
      | student2 | Entry by student 2 | Department manager faculty 2 |
      | student3 | Entry by student 3 | Coordinator                  |
    And I log out

  @javascript
  Scenario: A non-editing teacher sees only the entries matching their own profile field value
    When I log in as "manager2"
    And I am on "Course 1" course homepage
    And I follow "Departments datalynx"
    Then I should see "Entry by student 1"
    And I should see "Entry by student 2"
    And I should not see "Entry by student 3"

  @javascript
  Scenario: A non-editing teacher with a different profile field value does not see the entries
    When I log in as "manager1"
    And I am on "Course 1" course homepage
    And I follow "Departments datalynx"
    Then I should not see "Entry by student 1"
    And I should not see "Entry by student 2"
    And I should not see "Entry by student 3"
