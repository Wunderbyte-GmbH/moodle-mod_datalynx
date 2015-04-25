@mod @mod_datalynx @wip4
Feature: If you have a radiobutton and/or checkbox field assigned to a view
         And the filter is "not any of a is check"
         It should show you all entries where A is not chosen

  Background:
    Given the following "courses" exist:
      | fullname  | shortname   | category  | groupmode   |
      | Course 1  | C1          | 0         | 1           |
    And the following "users" exist:
      | username  | firstname   | lastname  | email                   |
      | teacher1  | Teacher     | 1         | teacher1@mailinator.com |
    And the following "course enrolments" exist:
      | user      | course  | role            |
      | teacher1  | C1      | editingteacher  |
    And the following "activities" exist:
      | activity | course | idnumber | name                   |
      | datalynx | C1     | 12345    | Datalynx Test Instance |
    And "Datalynx Test Instance" has following fields:
      | type             | name     | param1            |
      | radiobutton      | Radio    | Option A, Option B|
      | checkbox         | Checkbox | Option A, Option B|
      | text         | Checkbox | Option A, Option B|
    And "Datalynx Test Instance" has following views:
      | type    | name                   | status       | redirect           | filter        | param2                                                                                                |
      | grid    | Default view           | default      | Default view       |               | <div ><table><tbody><tr><td>Hi.</td></tr><tr><td>##edit##  ##delete##</td></tr></tbody></table></div> |
   And "Datalynx Test Instance" has following entries:
      | author   |checkbox | radiobutton            | approved |
      | teacher1 |A  | teacher1          | 1        |
      | teacher2 |A  | teacher1          | 1        |
      | teacher3 |B  | teacher1          | 1        |
      | teacher1 |B  | teacher2          | 1        |
      | teacher3 |   | teacher2,teacher3 | 1        |

  @javascript
  Scenario: Check if filter works for radiobuttons
    Given I log in as "teacher1"
    And I follow "Course 1"
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
      | Checkbox | Option 1, Option 2   |
      | TMS      | Student 1, Student 4 |
    And I press "Save changes"
    And I press "Continue"
    And I follow "Add a new entry"
    And I fill entry form with:
      | field    | value                |
      | Text     | This is the other!   |
      | Textarea | Hello as well!       |
      | Duration | 1 days               |
      | Time     | 27.9.1990 17:45      |
      | Radio    | Option C             |
      | Select   | Option Y             |
      | Checkbox | Option 3, Option 2   |
      | TMS      | Student 2, Student 3, Student 4 |
    And I press "Save changes"
    And I press "Continue"
    When I select "first,second" entry
    And I press "multiedit"
    And I fill entry form with:
      | entry | field    | value                |
      | 1     | Text     | This is the first!   |
      | 2     | Text     | This is the second!  |
    And I press "Save changes"
    And I press "Continue"
    Then I should see "This is the first!"
    And I should see "This is the second!"

  @javascript
  Scenario: Delete one entry
    Given I log in as "teacher1"
    And I follow "Course 1"
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
      | Checkbox | Option 1, Option 2   |
      | TMS      | Student 1, Student 4 |
    And I press "Save changes"
    And I press "Continue"
    And I follow "Add a new entry"
    And I fill entry form with:
      | field    | value                |
      | Text     | This is the other!   |
      | Textarea | Hello as well!       |
      | Duration | 1 days               |
      | Time     | 27.9.1990 17:45      |
      | Radio    | Option C             |
      | Select   | Option Y             |
      | Checkbox | Option 3, Option 2   |
      | TMS      | Student 2, Student 3, Student 4 |
    And I press "Save changes"
    And I press "Continue"
    When I select "first" entry
    And I press "multidelete"
    And I press "Continue"
    Then I should not see "Blah, blah, blah!"
    But I should see "This is the other!"
