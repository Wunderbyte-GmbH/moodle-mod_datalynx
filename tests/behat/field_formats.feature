@mod @mod_datalynx @javascript @datalynx_field_formats
Feature: Manage and use Datalynx Field Formats
  In order to customize the display of fields in templates
  As a teacher
  I need to configure custom field formats and use them in views.

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
    And I follow "Datalynx Test Instance"

  Scenario: Create, use, edit, and delete a submit button field format
    And I follow the datalynx "Manage" link
    And I follow "Fields"
    And I follow "Field Formats"
    And I set the field "Add field format" to "Submit button"
    And I wait until the page is ready
    And I set the field "Name" to "mysubmit"
    And I set the field "Button text" to "My Custom Submit"
    And I set the field "CSS classes" to "btn-success"
    And I click on "Show arrow" "checkbox"
    And I press "Save changes"
    Then I should see "mysubmit"
    And I should see "Submit button"

    # Use format in view template
    And I follow the datalynx "Views" link
    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | Gridview   |
      | description | Behat grid |
    And I follow "Set as default view"
    And I follow "Set as edit view"
    And I click on "Edit Gridview" "link"
    And I click on "Entry template" "link"
    And I set the "id_eparam2_editor" editor to "<p>##submit:mysubmit##</p>"
    And I press "Save changes"

    # Test display in edit mode (add entry)
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    Then "My Custom Submit →" "button" should exist
    And ".btn-success" "css_element" should exist

    # Try to delete format, verify it is blocked
    And I follow the datalynx "Manage" link
    And I follow "Fields"
    And I follow "Field Formats"
    And I click on "Delete" "link" in the "mysubmit" "table_row"
    Then I should see "The field format cannot be deleted because it is currently in use in the following views: Gridview"

    # Edit the template to remove format reference
    And I follow the datalynx "Views" link
    And I click on "Edit Gridview" "link"
    And I click on "Entry template" "link"
    And I set the "id_eparam2_editor" editor to "<p>##submit##</p>"
    And I press "Save changes"

    # Delete format again, verify it works
    And I follow the datalynx "Manage" link
    And I follow "Fields"
    And I follow "Field Formats"
    And I click on "Delete" "link" in the "mysubmit" "table_row"
    Then I should not see "mysubmit"
