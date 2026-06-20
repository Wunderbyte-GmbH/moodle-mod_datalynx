@mod @mod_datalynx @javascript @mod_datalynx_field_renaming
Feature: Test renaming datalynx fields and their template propagation
  In order to preserve view layouts when fields are renamed
  As a teacher
  I need field occurrences in views to be correctly renamed even with complex patterns.

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
      | activity | course | idnumber | name           |
      | datalynx | C1     | 12345    | Datalynx Test  |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I add to the "Datalynx Test" datalynx the following fields:
      | type | name    | description |
      | text | MyField |             |
    And I add to "Datalynx Test" datalynx the view of "Grid" type with:
      | name        | Gridview   |
      | description | Behat grid |
    And the "Gridview" view of "Datalynx Test" datalynx has the entry template "[[Datalynx field MyField:twodp|b_visible|l_layout]] and [[Datalynx field MyField_extended]]"
    And I wait until the page is ready

  Scenario: Rename field and verify template is updated with complex patterns
    When I follow "Fields"
    And I click on "Datalynx field MyField" "link"
    And I set the field "Name" to "Datalynx field NewName"
    And I press "Save changes"
    And I follow "Views"
    And I click on "Edit Gridview" "link"
    Then the "id_eparam2_editor" editor should contain "[[Datalynx field NewName:twodp|b_visible|l_layout]]"
    And the "id_eparam2_editor" editor should contain "[[Datalynx field MyField_extended]]"
