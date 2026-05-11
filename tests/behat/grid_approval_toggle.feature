@mod @mod_datalynx @javascript
Feature: Toggle approval repeatedly in Grid view
  In order to moderate entries from the Grid browse view
  As a teacher
  I need the approval switch to stay usable for approve, unapprove, and approve again

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1        | 0        | 1         |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | idnumber | name                   | approval |
      | datalynx | C1     | 12345    | Datalynx Test Instance | 1        |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I add to the "Datalynx Test Instance" datalynx the following fields:
      | type | name | description | param1 | param2 | param3 |
      | text | Text |             |        |        |        |
    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | Gridview   |
      | description | Behat grid |
    And I follow "Set as default view"
    And I follow "Set as edit view"
    And I wait until the page is ready
    And I click on "Edit Gridview" "link"
    And I click on "Entry template" "link"
    And I set the "id_eparam2_editor" editor to "[[Datalynx field Text]] ##approve## ##edit## ##delete##"
    And I press "Save changes"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type | name | value            |
      | text | Text | Text of student1 |
    And I press "Save changes"
    And I press "Continue"
    And I log out

  Scenario: Teacher can approve and unapprove the same Grid entry repeatedly
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I wait until the page is ready
    Then I should see "Text of student1"
    And I should see "Not approved" in the "//div[contains(@class,'mod-datalynx-grid-view-browser')]//div[contains(@class,'entry')][contains(.,'Text of student1')]" "xpath_element"
    When I click on ".//button[contains(@class,'datalynxfield_approve') and @aria-label='Not approved']" "xpath_element" in the "//div[contains(@class,'mod-datalynx-grid-view-browser')]//div[contains(@class,'entry')][contains(.,'Text of student1')]" "xpath_element"
    Then I should see "Approved" in the "//div[contains(@class,'mod-datalynx-grid-view-browser')]//div[contains(@class,'entry')][contains(.,'Text of student1')]" "xpath_element"
    When I click on ".//button[contains(@class,'datalynxfield_approve') and @aria-label='Approved']" "xpath_element" in the "//div[contains(@class,'mod-datalynx-grid-view-browser')]//div[contains(@class,'entry')][contains(.,'Text of student1')]" "xpath_element"
    Then I should see "Not approved" in the "//div[contains(@class,'mod-datalynx-grid-view-browser')]//div[contains(@class,'entry')][contains(.,'Text of student1')]" "xpath_element"
    When I click on ".//button[contains(@class,'datalynxfield_approve') and @aria-label='Not approved']" "xpath_element" in the "//div[contains(@class,'mod-datalynx-grid-view-browser')]//div[contains(@class,'entry')][contains(.,'Text of student1')]" "xpath_element"
    Then I should see "Approved" in the "//div[contains(@class,'mod-datalynx-grid-view-browser')]//div[contains(@class,'entry')][contains(.,'Text of student1')]" "xpath_element"
