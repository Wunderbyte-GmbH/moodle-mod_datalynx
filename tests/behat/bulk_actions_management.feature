@mod @mod_datalynx @javascript
Feature: Bulk actions on the datalynx management lists
  In order to manage many fields at once
  As a teacher
  I need the select-all checkbox and the bulk buttons of the management lists to work.

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
    And I follow "Datalynx Test Instance"
    And I follow the datalynx "Manage" link
    And I follow "Fields"
    And I add to the "Datalynx Test Instance" datalynx the following fields:
      | type | name        | description | param1 | param2 | param3 |
      | text | Alpha field |             |        |        |        |
      | text | Beta field  |             |        |        |        |

  Scenario: Select all fields and duplicate them
    When I click on "input[data-datalynx-selectall='field']" "css_element"
    And I click on "button[name='multiduplicate']" "css_element"
    And I press "Continue"
    Then I should see "Alpha field_1"
    And I should see "Beta field_1"

  Scenario: Nothing happens when no field is selected
    When I click on "button[name='multiduplicate']" "css_element"
    Then I should see "Alpha field"
    And I should not see "Alpha field_1"
