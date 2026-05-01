@mod @mod_datalynx @javascript
Feature: Report view aggregates datalynx entries
  In order to review aggregated datalynx data
  As a teacher
  I need the report view to show counts per author instead of single entries.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
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
    And the time is frozen at "2026-04-15 12:00"
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I add to the "Datalynx Test Instance" datalynx the following fields:
      | type   | name   | description | param1            | param2 | param3 |
      | text   | Title  |             |                   |        |        |
      | select | Choice |             | Option A,Option B |        |        |
    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | Gridview   |
      | description | Entry view |
    And I follow "Set as default view"
    And I follow "Set as edit view"
    And the "Datalynx Test Instance" datalynx has the following report view:
      | name   | Author report              |
      | param1 | Choice                     |
      | param2 | Do not calculate sums      |
      | param4 | Entry author               |
    And I log out

  Scenario: Report view shows aggregated counts per author
    Given the "Datalynx Test Instance" datalynx has the following entries:
      | user     | Title    | Choice   |
      | student1 | Entry A1 | Option A |
      | student1 | Entry B1 | Option B |
      | student2 | Entry A2 | Option A |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I open the "Author report" view of "Datalynx Test Instance" datalynx
    And I wait until the page is ready
    Then I should see "Student 1"
    And "//table[contains(@class,'mod-datalynx-report-view-browser__table')]//tr[td[contains(.,'Student 1')]]/td[2][normalize-space()='2026-04']" "xpath_element" should exist
    And "//table[contains(@class,'mod-datalynx-report-view-browser__table')]//tr[td[contains(.,'Student 1')]]/td[3][normalize-space()='2']" "xpath_element" should exist
    And "//table[contains(@class,'mod-datalynx-report-view-browser__table')]//tr[td[contains(.,'Student 1')]]/td[4][normalize-space()='1']" "xpath_element" should exist
    And "//table[contains(@class,'mod-datalynx-report-view-browser__table')]//tr[td[contains(.,'Student 1')]]/td[5][normalize-space()='1']" "xpath_element" should exist
    And "//table[contains(@class,'mod-datalynx-report-view-browser__table')]//tr[td[contains(.,'Student 1')]]/td[6][normalize-space()='0']" "xpath_element" should exist
    And "//table[contains(@class,'mod-datalynx-report-view-browser__table')]//tr[td[contains(.,'Student 2')]]/td[3][normalize-space()='1']" "xpath_element" should exist
    And "//table[contains(@class,'mod-datalynx-report-view-browser__table')]//tr[td[contains(.,'Student 2')]]/td[4][normalize-space()='1']" "xpath_element" should exist
    And "//table[contains(@class,'mod-datalynx-report-view-browser__table')]//tr[td[contains(.,'Student 2')]]/td[5][normalize-space()='0']" "xpath_element" should exist
    And the time is no longer frozen
