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
      | teacher1  | Teacher     | 1         | teacher1@asd.com        |
    And the following "course enrolments" exist:
      | user      | course  | role            |
      | teacher1  | C1      | editingteacher  |
    And the following "activities" exist:
      | activity | course | idnumber | name                   |
      | datalynx | C1     | 12345    | Datalynx Test Instance |
    And "Datalynx Test Instance" has following fields:
      | type           | name     | param1                       |
      | text           | Text     |                              |
      | textarea       | Textarea |                              |
      | time           | Time     |                              |
      | duration       | Duration |                              |
      | radiobutton    | Radio    | Option A, Option B, Option C |
      | checkbox       | Checkbox | Option 1, Option 2, Option 3 |
      | select         | Select   | Option X, Option Y, Option Z |
    And "Datalynx Test Instance" has following filters:
      | name       | perpage |
      | TestFilter | 3       |
    And "Datalynx Test Instance" has following views:
      | type    | name    | status       | redirect    | filter     |
      | grid    | Grid    | default      | Grid        | TestFilter |
      | tabular | Tabular | edit         | Grid        |            |
      | pdf     | PDF     | more         | Grid        |            |

  @javascript
  Scenario: add entry
    Given I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Datalynx Test Instance"
    When I create an entry in "Datalynx Test Instance" instance and fill the form with:
      | Option A  | x       |
      | Option 1  | x       |
      | Option 3  | x       |
    And I edit "first" entry
    And I set the following fields to these values:
      | Option B  | x       |
      | Option 3  |         |
      | Option 2  | x       |
    And I press "Save changes"
    And I press "Continue"
    Then I should see "Option B"
    And I should see "Option 1"
    And I should see "Option 2"
    But I should not see "Option 3"
