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
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
      | student3 | Student   | 3        | student3@example.com |
      | student4 | Student   | 4        | student4@example.com |
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
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I add to the "Datalynx Test Instance" datalynx the following fields:
      | type             | name               | description | param1                     | param2   | param3 |
      | text             | Text               |             |                            |          |        |
      | textarea         | Text area          |             |                            | 90       | 15     |
      | time             | Time               |             |                            |          |        |
      | duration         | Duration           |             |                            |          |        |
      | radiobutton      | Radio              |             | Option A,Option B,Option C |          |        |
      | checkbox         | Checkbox           |             | Option 1,Option 2,Option 3 | Option 1 |        |
      | select           | Select             |             | Option X,Option Y,Option Z |          |        |
      | teammemberselect | Team member select | 3           | 20                         | 1,2,4,8  |        |
      | number           | Number             | 3           | 2                          |          |        |
      | file             | File               | My file     |                            | 2        |        |
    And I add to "Datalynx Test Instance" datalynx the view of "Tabular" type with:
      | name | Tabular |
    And I follow "Set as default view"
    And I follow "Set as edit view"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type             | name               | value                            |
      | text             | Text               | Blah, blah, blah!                |
      | textarea         | Text area          | Lorem Ipsum Textarea             |
      | duration         | Duration           | 2 weeks                          |
      | time             | Time               | 11.October.1968.17.45            |
      | radio            | Radio              | Option A                         |
      | select           | Select             | Option X                         |
      | checkbox         | Checkbox           | Option 1=1,Option 2=0            |
      | teammemberselect | Team member select | Student 4 (student4@example.com) |
    And I press "Save changes"
    And I press "Continue"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type             | name               | value                            |
      | text             | Text               | This is 2nd entry!               |
      | textarea         | Text area          | Hello as we!                     |
      | duration         | Duration           | 1 days                           |
      | time             | Time               | 27.September.1986.17.45          |
      | radio            | Radio              | Option C                         |
      | select           | Select             | Option Y                         |
      | checkbox         | Checkbox           | Option 1=0,Option 3=1            |
      | teammemberselect | Team member select | Student 2 (student2@example.com) |
    And I upload "lib/tests/fixtures/empty.txt" file to "File" filemanager
    And I press "Save changes"
    And I press "Continue"

  @javascript
  Scenario: Update multiple entries
    When I set the field with xpath "//th[contains(concat(' ', @class, ' '), ' lastcol')]//input[@type='checkbox']" to "1"
    And I press "multiedit"
    And I set the field with xpath "(//div[@data-field-name='Datalynx field Text'])[1]//input" to "This is 1"
    And I set the field with xpath "(//div[@data-field-name='Datalynx field Text'])[2]//input" to "This is 2"
    And I press "Save changes"
    And I press "Continue"
    Then I should see "This is 1"
    And I should see "This is 2"

  @javascript
  Scenario: Delete one entry
    When  I set the field with xpath "(//input[@name='entryselector'])[1]" to "1"
    And I press "multidelete"
    And I press "Continue"
    Then I should not see "This is 1"
    But I should see "This is 2"
