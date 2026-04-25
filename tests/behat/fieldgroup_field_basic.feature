@mod @mod_datalynx @fieldgroups
Feature: Basic fieldgroup entry flow with repeated checkbox rows
  In order to enter grouped checkbox data
  As a teacher
  I need the Add Fieldgroup button to reveal extra rows and keep working when fieldgroup requirements change.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | course | idnumber | name                   |
      | datalynx | C1     | 12345    | Datalynx Test Instance |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I add to the "Datalynx Test Instance" datalynx the following fields:
      | type     | name              | description | param1 | param2 | param3   |
      | checkbox | Checkbox letters  |             | A,B,C  |        | New line |
      | checkbox | Checkbox numbers  |             | 1,2,3  |        | New line |
    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | Gridview |
      | description | Testgrid |
    And I follow "Set as default view"
    And I follow "Set as edit view"

  @javascript
  Scenario: Add and edit entries with a basic checkbox fieldgroup
    When I follow "Fields"
    And I select "Fieldgroup" from the "type" singleselect
    Then I should see "Fieldgroupfields"
    When I set the following fields to these values:
      | Name        | Fieldgroup |
      | Description | Basic fieldgroup test |
      | param2      | 3 |
      | param3      | 1 |
      | param4      | 1 |
    And I select "Datalynx field Checkbox letters, Datalynx field Checkbox numbers" in the datalynx fieldgroup fields selector
    And I press "Save changes"
    Then I should see "Fieldgroup"
    When I follow "Views"
    And I click on "Edit Gridview" "link"
    And I click on "Entry template" "link"
    And I set the "id_eparam2_editor" editor to "[[Fieldgroup]] ##edit## ##delete##"
    And I press "Save changes"
    And I follow "Browse"

    When I follow "Add a new entry"
    And I check the "A" option for the "Datalynx field Checkbox letters" fieldgroup field in row "1"
    And I check the "1" option for the "Datalynx field Checkbox numbers" fieldgroup field in row "1"
    And I press "Add Fieldgroup"
    And I check the "B" option for the "Datalynx field Checkbox letters" fieldgroup field in row "2"
    And I check the "2" option for the "Datalynx field Checkbox numbers" fieldgroup field in row "2"
    And I press "Save changes"
    Then I should see "updated"
    And I press "Continue"
    And I should see "A"
    And I should see "B"
    And I should see "2"
    And I click on "(//a/i[@title='Edit'])[1]" "xpath_element"
    And I check the "C" option for the "Datalynx field Checkbox letters" fieldgroup field in row "2"
    And I press "Save changes"
    Then I should see "updated"
    And I press "Continue"
    And I should see "C"

    When I follow "Add a new entry"
    And I check the "C" option for the "Datalynx field Checkbox letters" fieldgroup field in row "1"
    And I check the "1" option for the "Datalynx field Checkbox numbers" fieldgroup field in row "1"
    And I press "Add Fieldgroup"
    And I check the "A" option for the "Datalynx field Checkbox letters" fieldgroup field in row "2"
    And I check the "2" option for the "Datalynx field Checkbox numbers" fieldgroup field in row "2"
    And I press "Add Fieldgroup"
    And I check the "B" option for the "Datalynx field Checkbox letters" fieldgroup field in row "3"
    And I check the "3" option for the "Datalynx field Checkbox numbers" fieldgroup field in row "3"
    And I should not see "Add Fieldgroup"
    And I delete the fieldgroup row "3"
    And I should see "Add Fieldgroup"
    And I press "Add Fieldgroup"
    And I check the "B" option for the "Datalynx field Checkbox letters" fieldgroup field in row "3"
    And I check the "3" option for the "Datalynx field Checkbox numbers" fieldgroup field in row "3"
    And I should not see "Add Fieldgroup"
    And I press "Save changes"
    Then I should see "updated"
    And I press "Continue"
    And I should see "3"

    When I follow the datalynx "Manage" link
    And I follow "Fields"
    And I follow "Fieldgroups"
    And I follow "Fieldgroup"
    And I set the field "param4" to "2"
    And I press "Save changes"
    And I follow "Browse"
    And I click on "(//a/i[@title='Edit'])[2]" "xpath_element"
    And I delete the fieldgroup row "1"
    And the "A" option should be checked for the "Datalynx field Checkbox letters" fieldgroup field in row "1"
    And the "2" option should be checked for the "Datalynx field Checkbox numbers" fieldgroup field in row "1"
    And the "B" option should be checked for the "Datalynx field Checkbox letters" fieldgroup field in row "2"
    And the "3" option should be checked for the "Datalynx field Checkbox numbers" fieldgroup field in row "2"
    And fieldgroup row "3" should be hidden
    And I should see "Add Fieldgroup"
    And I press "Save changes"
    Then I should see "updated"
    And I press "Continue"
    And I click on "(//a/i[@title='Edit'])[2]" "xpath_element"
    And the "A" option should be checked for the "Datalynx field Checkbox letters" fieldgroup field in row "1"
    And the "2" option should be checked for the "Datalynx field Checkbox numbers" fieldgroup field in row "1"
    And the "B" option should be checked for the "Datalynx field Checkbox letters" fieldgroup field in row "2"
    And the "3" option should be checked for the "Datalynx field Checkbox numbers" fieldgroup field in row "2"
    And fieldgroup row "3" should be hidden
