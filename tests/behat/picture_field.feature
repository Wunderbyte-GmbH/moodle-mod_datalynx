@mod @mod_datalynx @_file_upload
Feature: In a datalynx instance create entries with fieldtype picture
  In order to work with fields of picture type
  As a teacher
  I need to add fields of type picture to the instance

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
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I add to the "Datalynx Test Instance" datalynx the following fields:
      | type    | name    | description | param1 | param2 | param3 |
      | picture | Picture |             |        |        |        |
    And I add to "Datalynx Test Instance" datalynx the view of "Tabular" type with:
      | name        | Tabular      |
      | description | Tabular view |
    And I follow "Set as default view"
    And I follow "Set as edit view"

  @javascript @_file_upload
  Scenario: Add a new entry with a picture to a datalynx instance
    When I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    And I upload "mod/datalynx/tests/fixtures/picture.jpg" file to "Datalynx field Picture" filemanager
    And I press "Save changes"
    And I press "Continue"
    Then I should see "Add a new entry"
