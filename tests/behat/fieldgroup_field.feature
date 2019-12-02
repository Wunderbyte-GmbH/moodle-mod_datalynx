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
#      | textarea         | Text area           |             |                            | 90       | 15     |
#      | time             | Time                |             |                            |          |        |
#      | duration         | Duration            |             |                            |          |        |
#      | radiobutton      | Radio               |             | Option A,Option B,Option C |          |        |
#      | checkbox         | Checkbox            |             | Option 1,Option 2,Option 3 | Option 1 |        |
#      | select           | Select              |             | Option X,Option Y,Option Z |          |        |
      | teammemberselect | Team member select  | 3           | 20                         | 1,2,4,8  |        |
    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | Gridview |
      | description | Testgrid |
    And I follow "Set as default view"
    And I follow "Set as edit view"

  @javascript
  Scenario: Add a fieldgroup with teammemberselect to this instance
    When I follow "Fields"
    And I select "Fieldgroup" from the "type" singleselect
    Then I should see "Fieldgroupfields"
    When I set the following fields to these values:
      | Name           | Testfieldgroup1       |
      | Description    | This is a first test  |
      | param2         | 4                     |
      | param3         | 4                     |

    And I open the autocomplete suggestions list
    Then "Datalynx field Team member select" "autocomplete_suggestions" should exist
    And I click on "Datalynx field Team member select" item in the autocomplete list
    And I press "Save changes"
    When I follow "Views"
    And I click on "//table/tbody/tr[1]/td[9]/a" "xpath_element"
    Then I should see "Gridview"
    And I click on "Entry template" "link"
    Then I should see "Field tags"
    Then I add to "id_eparam2_editor" editor the text "[[Testfieldgroup1]] ##edit##  ##delete##"
    And I press "Save changes"
    When I follow "Browse"
    When I follow "Add a new entry"
    And I open the autocomplete suggestions list
    Then "Student 1 (student1@example.com)" "autocomplete_suggestions" should exist
    And I click on "Student 1 (student1@example.com)" item in the autocomplete list
    Then "Student 2 (student2@example.com)" "autocomplete_suggestions" should exist
    And I click on "Student 2 (student2@example.com)" item in the autocomplete list

    ## Add teammembers to the second line as well.
    And I click on "(//*[@class and contains(concat(' ', normalize-space(@class), ' '), ' form-autocomplete-downarrow ')])[2]" "xpath_element"
    And I click on "(//ul[@class='form-autocomplete-suggestions']//*[contains(concat('|', string(.), '|'),'|Student 1 (student1@example.com)|')])[1]" "xpath_element"
    And I click on "(//*[@class and contains(concat(' ', normalize-space(@class), ' '), ' form-autocomplete-downarrow ')])[2]" "xpath_element"
    And I click on "(//ul[@class='form-autocomplete-suggestions']//*[contains(concat('|', string(.), '|'),'|Student 2 (student2@example.com)|')])[1]" "xpath_element"
    And I press "Save changes"
    Then I should see "updated"
    And I press "Continue"
    Then I should see "Student 2"
    And "Student 1" "text" should appear before "Student 2" "text"

    ## Add a second entry, this time only to the second line.
    When I follow "Add a new entry"
    And I click on "(//*[@class and contains(concat(' ', normalize-space(@class), ' '), ' form-autocomplete-downarrow ')])[2]" "xpath_element"
    And I click on "(//ul[@class='form-autocomplete-suggestions']//*[contains(concat('|', string(.), '|'),'|Student 2 (student2@example.com)|')])[2]" "xpath_element"
    And I press "Save changes"
    Then I should see "updated"
    And I press "Continue"

  @javascript
  Scenario: Add a new fieldgroup with text and number to this instance
    When I follow "Fields"
    And I select "Fieldgroup" from the "type" singleselect
    Then I should see "Fieldgroupfields"
    When I set the following fields to these values:
      | Name           | Testfieldgroup1       |
      | Description    | This is a first test  |
      | param2         | 4                     |
      | param3         | 4                     |

    ## Use the autocomplete.
    And I open the autocomplete suggestions list
    Then "Datalynx field Text" "autocomplete_suggestions" should exist

    ## TODO: In the current implementation it is not possible to add two textfields.
    And I click on "Datalynx field Text" item in the autocomplete list
    And I click on "Datalynx field Number" item in the autocomplete list
    And I press "Save changes"
    Then I should see "Testfieldgroup1"
    When I follow "Views"
    And I follow "Gridview"
    And I click on "Edit this view" "icon"

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

    When I set the field with xpath "(//input[@type='text'])[2]" to "3"
    When I set the field with xpath "(//input[@type='text'])[3]" to "Text 1 in the first line"
    When I set the field with xpath "(//input[@type='text'])[4]" to "6"
    When I set the field with xpath "(//input[@type='text'])[5]" to "Text 1 in the second line"
    When I set the field with xpath "(//input[@type='text'])[6]" to "9"
    When I set the field with xpath "(//input[@type='text'])[7]" to "Text 1 in the third line"

    And I press "Save changes"
    Then I should see "updated"
    And I press "Continue"
    Then I should see "Datalynx field Number: 6"
    ## Add a second entry
    When I follow "Add a new entry"

    When I set the field with xpath "(//input[@type='text'])[2]" to "12"
    When I set the field with xpath "(//input[@type='text'])[3]" to "Text 2 in the first line"
    When I set the field with xpath "(//input[@type='text'])[4]" to "15"
    When I set the field with xpath "(//input[@type='text'])[5]" to "Text 2 in the second line"
    When I set the field with xpath "(//input[@type='text'])[6]" to "18"
    When I set the field with xpath "(//input[@type='text'])[7]" to "Text 2 in the third line"

    And I press "Save changes"
    Then I should see "updated"
    And I press "Continue"
    ## Add a third entry
    When I follow "Add a new entry"

    When I set the field with xpath "(//input[@type='text'])[2]" to "21"
    When I set the field with xpath "(//input[@type='text'])[3]" to "Text 3 in the first line"
    When I set the field with xpath "(//input[@type='text'])[4]" to "24"
    When I set the field with xpath "(//input[@type='text'])[5]" to "Text 3 in the second line"
    When I set the field with xpath "(//input[@type='text'])[6]" to "27"
    When I set the field with xpath "(//input[@type='text'])[7]" to "Text 3 in the third line"
    When I set the field with xpath "(//input[@type='text'])[8]" to "30"
    When I set the field with xpath "(//input[@type='text'])[9]" to "Text 3 in the fourth line"

    And I press "Save changes"
    Then I should see "updated"
    And I press "Continue"
    Then I should see "Datalynx field Number: 30"
    And I should see "Datalynx field Text: Text 3 in the fourth line"

    ## Find the right edit button for the second entry and click it.
    And I click on "(//a/i[@title='Edit'])[2]" "xpath_element"

    ## Change some values.
    When I set the field with xpath "(//input[@type='text'])[2]" to "33"
    When I set the field with xpath "(//input[@type='text'])[3]" to "Second Text 2 in the first line"
    When I set the field with xpath "(//input[@type='text'])[4]" to ""
    When I set the field with xpath "(//input[@type='text'])[5]" to "Second Text 2 in the second line"

    ## Save and check.
    And I press "Save changes"
    Then I should see "updated"
    And I press "Continue"
    And I should see "Datalynx field Text: Second Text 2 in the first line"
    And I should not see "Datalynx field Text: Text 2 in the first line"
    And I should see "Datalynx field Text: Text 2 in the third line"
    ## Check order of content as well.
    And "Datalynx field Text: Second Text 2 in the first line" "text" should appear before "Datalynx field Text: Second Text 2 in the second line" "text"

    ## Edit the first entry and remove a whole line.
    And I click on "(//a/i[@title='Edit'])[1]" "xpath_element"

    When I set the field with xpath "(//input[@type='text'])[2]" to ""
    When I set the field with xpath "(//input[@type='text'])[3]" to ""
    When I set the field with xpath "(//input[@type='text'])[4]" to "36"
    When I set the field with xpath "(//input[@type='text'])[5]" to "Second Text 1 in the second line"
    When I set the field with xpath "(//input[@type='text'])[6]" to ""
    When I set the field with xpath "(//input[@type='text'])[7]" to ""
    When I set the field with xpath "(//input[@type='text'])[8]" to ""
    When I set the field with xpath "(//input[@type='text'])[9]" to ""

    And I press "Save changes"
    Then I should see "updated"
    And I press "Continue"

    ## Check if empty lines are kept.
    And I should not see "Datalynx field Text: Text 1 in the first line"
    And I should not see "Datalynx field Number: 3 "
    ## TODO: Fix this to test if not needed lines are removed.
