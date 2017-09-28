@mod @mod_datalynx @dev @_file_upload
Feature: In a datalynx multiedit entries
  In order to update more than one entry at once
  As a teacher
  I need to edit two entries as one.

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
      | file             | File     |                              |        |
      | text             | Text     |                              |        |
      | textarea         | Textarea |                              |        |
      | time             | Time     |                              |        |
      | duration         | Duration |                              |        |
      | radiobutton      | Radio    | Option A, Option B, Option C | 3      |
      | checkbox         | Checkbox | Option 1, Option 2, Option 3 | 3      |
      | select           | Select   | Option X, Option Y, Option Z | 3      |
      | teammemberselect | TMS      | 3                            |        |
    And "Datalynx Test Instance" has following entries:
      | author   | Text         | Textarea    | Time             | Duration | Radio    | Checkbox           | Select   | TMS      | File                                    |
      | student1 | Yo! Whassup? | Whatever    | 16.9.2014 11:00  | 2 days   | Option A | Option 2           | Option Z | teacher1 | mod/datalynx/tests/fixtures/picture.jpg |
      | student3 | Hi there!    | Lorem ipsum | 23.11.1994 21:00 | 11 days  | Option C | Option 1, Option 3 | Option Z | student2 | mod/datalynx/tests/fixtures/picture.jpg |
    And "Datalynx Test Instance" has following views:
      | type    | name    | status        | redirect |
      | tabular | Tabular | default, edit | Tabular  |

  @javascript
  Scenario: Update existing entries
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    When I select "first,second" entry
    And I press "multiedit"
    And I fill entry form with:
      | entry | field | value               |
      | 1     | Text  | This is the first!  |
      | 2     | Text  | This is the second! |
    And I pause scenario execution
    And I press "Save changes"
    And I press "Continue"
    And I pause scenario execution
    Then I should see "This is the first!"
    And I should see "This is the second!"
    And I should see "Option 2"
    And I should see "Lorem ipsum"
    And I should see "Teacher 1"
    And I should see "Student 2"
