@mod @mod_datalynx @datalynxrule_updatefield @javascript
Feature: Update-field rules change an entry's values when their conditions are met
  In order to automate entry data
  As a teacher
  I need updatefield rules to write a field value when a triggering condition holds,
  using the status, select and checkbox fields as conditions and radio/text as targets.

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
      | activity | course | idnumber | name     |
      | datalynx | C1     | 12345    | RuleTest |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I add to the "RuleTest" datalynx the following fields:
      | type        | name     | description | param1        | param2 | param3 |
      | radiobutton | Radio    |             | 1,2,3         |        |        |
      | text        | Text     |             |               |        |        |
      | checkbox    | Checkbox |             | activate      |        |        |
      | select      | Select   |             | one,two,three |        |        |
    And I add to "RuleTest" datalynx the view of "Grid" type with:
      | name        | Gridview   |
      | description | Behat grid |
    And I follow "Set as default view"
    And I follow "Set as edit view"
    And I wait until the page is ready
    And I click on "Edit Gridview" "link"
    And I click on "Entry template" "link"
    And I set the "id_eparam2_editor" editor to "[[Datalynx field Radio]] [[Datalynx field Text]] [[Datalynx field Select]] [[Datalynx field Checkbox]] ##status## ##edit## ##delete##"
    And I press "Save changes"
    And I click on ".nav-item [title='Manage']" "css_element"
    And I follow "Rules"
    And I click on "Dismiss this notification" "button"

  Scenario: Setting an entry to final submission updates the radio field
    When I set the field "Add a rule" to "Update field"
    And I set the field "Name" to "StatusFinalUpdatesRadio"
    And I set the field "Entry created" to "1"
    And I set the field "Entry updated" to "1"
    And I set the field "searchandor0" to "AND"
    And I set the field "searchfield0" to "status"
    And I wait until the page is ready
    And I set the field "f_0_status" to "2"
    And I set the field "Field to update" to "Datalynx field Radio"
    And I set the field "New value (Datalynx field Radio)" to "1"
    And I press "Save changes"
    And I wait until the page is ready
    And I am on "Course 1" course homepage
    And I follow "RuleTest"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type | name | value      |
      | text | Text | final demo |
    And I set the field with xpath "//input[@type='checkbox' and contains(@name,'field_status_')]" to "1"
    And I press "Save changes"
    And I press "Continue"
    Then I should see "Final submission"
    And I should see "1" in the ".mod-datalynx-grid-view-browser" "css_element"

  Scenario: Changing the select field to "two" updates the radio field
    When I set the field "Add a rule" to "Update field"
    And I set the field "Name" to "SelectTwoUpdatesRadio"
    And I set the field "Entry created" to "1"
    And I set the field "Entry updated" to "1"
    And I set the field "searchandor0" to "AND"
    And I set the field "searchfield0" to "Datalynx field Select"
    And I wait until the page is ready
    And I set the field "searchoperator0" to "any of"
    And I open the autocomplete suggestions list in the "[id*=fgroup_id_customsearcharr0]" "css_element"
    And I click on "two" item in the autocomplete list
    And I set the field "Field to update" to "Datalynx field Radio"
    And I set the field "New value (Datalynx field Radio)" to "2"
    And I press "Save changes"
    And I wait until the page is ready
    And I am on "Course 1" course homepage
    And I follow "RuleTest"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type   | name   | value |
      | select | Select | two   |
    And I press "Save changes"
    And I press "Continue"
    Then I should see "2" in the ".mod-datalynx-grid-view-browser" "css_element"

  Scenario: Activating the checkbox updates both the text and the radio field
    When I set the field "Add a rule" to "Update field"
    And I set the field "Name" to "CheckboxUpdatesText"
    And I set the field "Entry created" to "1"
    And I set the field "Entry updated" to "1"
    And I set the field "searchandor0" to "AND"
    And I set the field "searchfield0" to "Datalynx field Checkbox"
    And I wait until the page is ready
    And I set the field "searchoperator0" to "any of"
    And I open the autocomplete suggestions list in the "[id*=fgroup_id_customsearcharr0]" "css_element"
    And I click on "activate" item in the autocomplete list
    And I set the field "Field to update" to "Datalynx field Text"
    And I set the field "New value (Datalynx field Text)" to "checkbox activated"
    And I press "Save changes"
    And I wait until the page is ready
    And I set the field "Add a rule" to "Update field"
    And I set the field "Name" to "CheckboxUpdatesRadio"
    And I set the field "Entry created" to "1"
    And I set the field "Entry updated" to "1"
    And I set the field "searchandor0" to "AND"
    And I set the field "searchfield0" to "Datalynx field Checkbox"
    And I wait until the page is ready
    And I set the field "searchoperator0" to "any of"
    And I open the autocomplete suggestions list in the "[id*=fgroup_id_customsearcharr0]" "css_element"
    And I click on "activate" item in the autocomplete list
    And I set the field "Field to update" to "Datalynx field Radio"
    And I set the field "New value (Datalynx field Radio)" to "3"
    And I press "Save changes"
    And I wait until the page is ready
    And I am on "Course 1" course homepage
    And I follow "RuleTest"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type     | name     | value      |
      | checkbox | Checkbox | activate=1 |
    And I press "Save changes"
    And I press "Continue"
    Then I should see "checkbox activated"
    And I should see "3" in the ".mod-datalynx-grid-view-browser" "css_element"
