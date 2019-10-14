@mod @mod_datalynx @file_upload
Feature: In a datalynx instance create a new entry
  In order to create a new entry
  As a teacher
  I need to add a new entry to the datalynx instance.

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
    And I follow "Filters"
    And I follow "Add a filter"
    And I select "10" from the "perpage" singleselect
    And I select "Time created" from the "sortfield0" singleselect
    And I press "Save changes"
    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name | Gridview |
      | description | Behad grid |
      | _filter | New filter |
    And I follow "Set as default view"
    And I follow "Set as edit view"

  @javascript @_file_upload
  Scenario: Add a new entry to datalynx instance
    When I follow "Browse"
    And I follow "Add a new entry"
    Then I should see "Option A"
    And I fill in the entry form fields
      | type             | name               | value                            |
      | checkbox         | Checkbox           | Option 1=0,Option 2=1            |
      | radio            | Radio              | Option A                         |
      | select           | Select             | Option Z                         |
      | text             | Text               | Text for Text                    |
      | textarea         | Text area          | Lorem Ipsum Textarea             |
      | teammemberselect | Team member select | Student 1 (student1@example.com) |
      | duration         | Duration           | 45 days                          |
      | number           | Number             | 9.67                             |
      | time             | Time               | 2.January.2017.20.23             |
    And I upload "lib/tests/fixtures/empty.txt" file to "File" filemanager
    And I press "Save changes"
    Then I should see "updated"
    And I press "Continue"
    Then I should see "Option 2"
    And I should see "Option A"
    And I should see "Option Z"
    And I should see "Text for Text"
    And I should see "Lorem Ipsum Textarea"
    And I should see "Student 1"
    And I should see "45 days"
    And I should see "9.67"
    And I should see "2 January 2017, 8:23 PM"
    And "empty.txt" "link" should exist
    But I should not see "Option Y"
    And I should not see "Option 3"
    And I should not see "Option 1"
    And I should not see "Student 2"
