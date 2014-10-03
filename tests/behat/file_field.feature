@mod @mod_datalynx @_file_upload
Feature: In a datalynx create, update, and delete fields
  In order to create chapters and subchapters
  As a teacher
  I need to add chapters and subchapters to a book.

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
      | activity | course | idnumber | name                   |
      | datalynx | C1     | 12345    | Datalynx Test Instance |
    And "Datalynx Test Instance" has following fields:
      | type             | name     | param1                       |
      | file             | File     |                              |
      | picture          | Picture  |                              |
    And "Datalynx Test Instance" has following views:
      | type    | name    | status        | redirect |
      | tabular | Tabular | default, edit | Tabular  |

  @javascript
  Scenario: test form
    Given I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    And I fill entry form with:
      | entry | field    | value                                   |
      | 1     | File     | mod/datalynx/tests/fixtures/picture.jpg |
    And I press "Save changes"
    And I press "Continue"
    Then I should see "Add a new entry"
