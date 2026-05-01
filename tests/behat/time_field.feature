@mod @mod_datalynx @javascript
Feature: Test datalynx _time internal field
  In order to track when entries were created and modified
  As a student and teacher
  I need the timecreated and timemodified patterns to display dates in browse view.

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
      | activity | course | idnumber | name                   |
      | datalynx | C1     | 12345    | Datalynx Test Instance |
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
    And I set the "id_eparam2_editor" editor to "[[Datalynx field Text]] ##timecreated## ##timemodified## ##timecreated:Y## ##edit## ##delete##"
    And I press "Save changes"
    And I log out

  Scenario: Time created and modified display after entry is added
    When I log in as "student1"
    And the time is frozen at "2026-04-15 12:00"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type | name | value      |
      | text | Text | Time entry |
    And I press "Save changes"
    And I press "Continue"
    Then I should see "Time entry"
    And I should see "2026"
    And I should see "April"
    And the time is no longer frozen
