@mod @mod_datalynx
Feature: Multi-edit selected entries in a tabular Datalynx view
  In order to edit only selected entries in a tabular Datalynx view
  As a teacher
  I need to select all entries, deselect one, update the remaining entries, and keep the deselected entry unchanged.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1        | 0        | 1         |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | course | idnumber | name                   |
      | datalynx | C1     | 12345    | Datalynx Test Instance |
    And I change window size to "large"
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I add to the "Datalynx Test Instance" datalynx the following fields:
      | type   | name   | description | param1                     | param2 | param3 |
      | text   | Text   |             |                            |        |        |
      | select | Select |             | Option X,Option Y,Option Z |        |        |
      | number | Number |             |                            |        |        |
    And I add to "Datalynx Test Instance" datalynx the view of "Tabular" type with:
      | name        | Tabular |
      | description | Tabular view |
    And I follow "Set as default view"
    And I follow "Set as edit view"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type   | name   | value              |
      | text   | Text   | Entry one original |
      | select | Select | Option X           |
      | number | Number | 1001               |
    And I press "Save changes"
    And I press "Continue"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type   | name   | value              |
      | text   | Text   | Entry two original |
      | select | Select | Option Z           |
      | number | Number | 2002               |
    And I press "Save changes"
    And I press "Continue"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type   | name   | value                |
      | text   | Text   | Entry three original |
      | select | Select | Option Y             |
      | number | Number | 3003                 |
    And I press "Save changes"
    And I press "Continue"

  @javascript
  Scenario: Multi-edit only the selected entries after deselecting one
    When I set the field with xpath "//th[contains(concat(' ', @class, ' '), ' lastcol')]//input[@type='checkbox']" to "1"
    And I set the field with xpath "(//input[@name='entryselector'])[2]" to "0"
    And I press "multiedit"
    And I set the field with xpath "(//div[@data-field-name='Datalynx field Text'])[1]//input" to "Entry one updated"
    And I set the field with xpath "(//div[@data-field-name='Datalynx field Select']//select)[1]" to "Option Y"
    And I set the field with xpath "(//div[@data-field-name='Datalynx field Number'])[1]//input" to "4004"
    And I set the field with xpath "(//div[@data-field-name='Datalynx field Text'])[2]//input" to "Entry three updated"
    And I set the field with xpath "(//div[@data-field-name='Datalynx field Select']//select)[2]" to "Option X"
    And I set the field with xpath "(//div[@data-field-name='Datalynx field Number'])[2]//input" to "5005"
    And I press "Save changes"
    And I press "Continue"
    Then I should see "Entry one updated"
    And I should see "Entry three updated"
    And I should see "Entry two original"
    And I should see "4004"
    And I should see "5005"
    And I should see "2002"
    And I should see "Option X"
    And I should see "Option Y"
    And I should see "Option Z"
    But I should not see "Entry one original"
    And I should not see "Entry three original"
