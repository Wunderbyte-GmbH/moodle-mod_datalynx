@mod @mod_datalynx @_file_upload
Feature: In a datalynx instance create, update, and delete entries
  In order to create, update or delete an datalynx entry
  As a teacher
  I need to use the default edit form.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1        | 0        | 1         |
    And the following "users" exist:
      | username | firstname | lastname | email                   |
      | teacher1 | Teacher   | 1        | teacher1@mailinator.com |
      | student1 | Student   | 1        | student1@mailinator.com |
      | student2 | Student   | 2        | student2@mailinator.com |
      | student3 | Student   | 3        | student3@mailinator.com |
      | student4 | Student   | 4        | student4@mailinator.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
      | student4 | C1     | student        |
    And the following "activities" exist:
      | activity | course | idnumber | name                   |
      | datalynx | C1     | 12345    | Datalynx Test Instance |
    And "Datalynx Test Instance" has following fields:
      | type             | name     | param1                       | param3 |
      | text             | Text     |                              |        |
      | textarea         | Textarea |                              |        |
      | time             | Time     |                              |        |
      | duration         | Duration |                              |        |
      | radiobutton      | Radio    | Option A, Option B, Option C | 3      |
      | checkbox         | Checkbox | Option 1, Option 2, Option 3 | 3      |
      | select           | Select   | Option X, Option Y, Option Z | 3      |
      | teammemberselect | TMS      | 3                            |        |
    And "Datalynx Test Instance" has following views:
      | type    | name    | status        | redirect |
      | tabular | Tabular | default, edit | Tabular  |

  @javascript
  Scenario: Create and update entries
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    And I pause scenario execution
    And I fill entry form with:
      | field    | value                |
      | Text     | Blah, blah, blah!    |
      | Textarea | Hello!               |
      | Duration | 2 weeks              |
      | Time     | 11.10.1986 17:45     |
      | Radio    | Option A             |
      | Select   | Option X             |
      | Checkbox | Option 2             |
      | TMS      | Student 4            |
    And I press "Save changes"
    And I press "Continue"
    And I follow "Add a new entry"
    And I fill entry form with:
      | field    | value                           |
      | Text     | This is the other!              |
      | Textarea | Hello as well!                  |
      | Duration | 1 days                          |
      | Time     | 27.9.1990 17:45                 |
      | Radio    | Option C                        |
      | Select   | Option Y                        |
      | Checkbox | Option 3                        |
      | TMS      | Student 2                       |
    And I pause scenario execution
    And I press "Save changes"
    And I press "Continue"
    When I select "first,second" entry
    And I press "multiedit"
    And I fill entry form with:
      | entry | field | value               |
      | 1     | Text  | This is the first!  |
      | 2     | Text  | This is the second! |
    And I press "Save changes"
    And I press "Continue"
    Then I should see "This is the first!"
    And I should see "This is the second!"

  @javascript
  Scenario: Delete one entry
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    And I fill entry form with:
      | field    | value                |
      | Text     | Blah, blah, blah!    |
      | Textarea | Hello!               |
      | Duration | 2 weeks              |
      | Time     | 11.10.1986 17:45     |
      | Radio    | Option A             |
      | Select   | Option X             |
      | Checkbox | Option 2             |
      | TMS      | Student 4            |
    And I press "Save changes"
    And I press "Continue"
    And I follow "Add a new entry"
    And I fill entry form with:
      | field    | value                           |
      | Text     | This is the other!              |
      | Textarea | Hello as well!                  |
      | Duration | 1 days                          |
      | Time     | 27.9.1990 17:45                 |
      | Radio    | Option C                        |
      | Select   | Option Y                        |
      | Checkbox | Option 3                        |
      | TMS      | Student 2                       |
    And I press "Save changes"
    And I press "Continue"
    When I select "first" entry
    And I press "multidelete"
    And I press "Continue"
    Then I should not see "Blah, blah, blah!"
    But I should see "This is the other!"
