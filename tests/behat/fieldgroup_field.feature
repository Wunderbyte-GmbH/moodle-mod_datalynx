@mod @mod_datalynx @fieldgroups
Feature: Create entry and add fieldgroups
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
      | type             | name                | description | param1                     | param2   | param3 |
      | text             | Text                |             |                            |          |        |
      | number           | Number              | 3           | 2                          |          |        |
#      | textarea         | Text area          |             |                            | 90       | 15     |
#      | time             | Time               |             |                            |          |        |
#      | duration         | Duration           |             |                            |          |        |
#      | radiobutton      | Radio              |             | Option A,Option B,Option C |          |        |
#      | checkbox         | Checkbox           |             | Option 1,Option 2,Option 3 | Option 1 |        |
#      | select           | Select             |             | Option X,Option Y,Option Z |          |        |
#      | teammemberselect | Team member select | 3           | 20                         | 1,2,4,8  |        |

    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | Gridview |
      | description | Testgrid |
    And I follow "Set as default view"
    And I follow "Set as edit view"

  @javascript
  Scenario: Add a new fieldgroup to this instance
    When I follow "Fields"
    And I select "Fieldgroup" from the "type" singleselect
    Then I should see "Fieldgroupfields"
    When I set the following fields to these values:
      | Name           | Testfieldgroup1       |
      | Description    | This is a first test  |
      | numshowdefault | 4                     |

    ## Use the autocomplete.
    And I open the autocomplete suggestions list
    Then "Datalynx field Text" "autocomplete_suggestions" should exist

    ## TODO: In the current implementation it is not possible to add two textfields.
    And I click on "Datalynx field Text" item in the autocomplete list
    And I click on "Datalynx field Number" item in the autocomplete list
    And I press "Save changes"
    Then I should see "Testfieldgroup1"
    When I follow "Views"
    ## And I follow "Edit" ## Klicks on editview icon bc. somewhere it says edit.
    And I click on "//table/tbody/tr[1]/td[9]/a" "xpath_element"
    Then I should see "Gridview"
    And I click on "Entry template" "link"
    Then I should see "Field tags"
    ## Add fieldgroup and remove all other fields.
    Then I add to "id_eparam2_editor" editor the text "[[Testfieldgroup1]] ##edit##  ##delete##"
    And I press "Save changes"
    When I follow "Browse"

    ## Add some entries for testing.
    When I follow "Add a new entry"
    Then I should see "Datalynx field Text"
    ## Names do not work bc. iterator.
    Then I set the following fields to these values:
          | field_214000_-1_0       | Text 1 in the first line  |
          | field_214001_-1_0       | 3       |
          | field_214000_-1_1       | Text 1 in the second line  |
          | field_214001_-1_1       | 6       |
          | field_214000_-1_2       | Text 1 in the third line  |
          | field_214001_-1_2       | 9       |
    And I press "Save changes"
    Then I should see "updated"
    And I press "Continue"
    Then I should see "Datalynx field Number: 6"
    ## Add a second entry
    When I follow "Add a new entry"
    Then I set the following fields to these values:
          | field_214000_-1_0       | Text 2 in the first line  |
          | field_214001_-1_0       | 12       |
          | field_214000_-1_1       | Text 2 in the second line  |
          | field_214001_-1_1       | 15       |
          | field_214000_-1_2       | Text 2 in the third line  |
          | field_214001_-1_2       | 18       |
    And I press "Save changes"
    Then I should see "updated"
    And I press "Continue"
    ## Add a third entry
    When I follow "Add a new entry"
    Then I set the following fields to these values:
          | field_214000_-1_0       | Text 3 in the first line  |
          | field_214001_-1_0       | 21       |
          | field_214000_-1_1       | Text 3 in the second line  |
          | field_214001_-1_1       | 24       |
          | field_214000_-1_2       | Text 3 in the third line  |
          | field_214001_-1_2       | 27       |
          | field_214000_-1_3       | Text 3 in the fourth line  |
          | field_214001_-1_3       | 30       |
    And I press "Save changes"
    Then I should see "updated"
    And I press "Continue"
    Then I should see "Datalynx field Number: 30"
    And I should see "Datalynx field Text: Text 3 in the fourth line"

    ## Find the right edit button for the second entry and click it.
    And I click on "//section/div/div/div[2]/div/div[2]/div/a[3]" "xpath_element"

    ## Change some values.
    Then I set the following fields to these values:
          | field_214000_213001_0       | Second Text 2 in the first line  |
          | field_214001_213001_0       | 33       |
          | field_214000_213001_1       | Second Text 2 in the second line |
    ## Save and check.
    And I press "Save changes"
    Then I should see "updated"
    And I press "Continue"
    And I should see "Datalynx field Text: Second Text 2 in the first line"
    And I should not see "Datalynx field Text: Text 2 in the first line"

    ## Check order of content as well.

    ## Edit some more but this time remove a whole line.
    ## Check if empty lines are kept.
