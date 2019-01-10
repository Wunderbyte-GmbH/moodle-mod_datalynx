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
  Scenario: Add a new fieldgroup to this instance instance
    When I follow "Fields"
    And I select "Fieldgroup" from the "type" singleselect
    Then I should see "Fieldgroupfields"
    When I set the following fields to these values:
      | Name         | Testfieldgroup1       |
      | Description  | This is a first test  |

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
    ## And I click on "Show more buttons" "button" ## This button is differently named in bht_mdl
    ## And I click on "Show/hide advanced buttons" "button"
    ## And I click on "HTML" "button"
    ## And I click on "Datalynx field Number"
    ## And I press "datalynx-tag-button-delete"
    ## When I type in the atto textform "sdfadfdsaf af saf sadf asdf asdf asdf sadf asf asdf asdf asdf saf sdasadfds"