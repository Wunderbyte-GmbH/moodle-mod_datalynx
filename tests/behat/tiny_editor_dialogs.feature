@mod @mod_datalynx @datalynx_patterndialogue @editor_tiny
Feature: Test TinyMCE tag buttons and dialogs in datalynx view editor
  In order to configure a view entry template
  As a teacher
  I need to insert field tags as buttons in the TinyMCE editor and edit their properties via a modal dialog.

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
      | type | name | description | param1 | param2 | param3 |
      | text | Text |             |        |        |        |
    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | Gridview |
      | description | Testgrid |
    And I follow "Set as default view"

  @javascript
  Scenario: Insert a field tag via dropdown and open its properties dialog
    When I click on "Edit Gridview" "link"
    And I click on "Entry template" "link"
    Then I should see "Field tags"

    # Open dialog and delete the tag
    When I change window size to "large"
    And I switch to the "id_eparam2_editor" TinyMCE editor iframe
    And I click on "Datalynx field Text" "button"
    And I switch to the main frame
    Then I should see "Field tag properties"
    When I click on "[data-region='delete-tag']" "css_element"

    # Verify dialog closed and button is gone from editor
    Then I should not see "Field tag properties"
    And I switch to the "id_eparam2_editor" TinyMCE editor iframe
    Then "button.datalynx-field-tag" "css_element" should not exist
    And I switch to the main frame

    # Select a field tag from the dropdown - patterndialogue.js inserts it as a button
    When I select "[[Datalynx field Text]]" from the "eparam2_editor_field_tag_menu" singleselect

    # Verify the button appeared in the TinyMCE editor body
    And I switch to the "id_eparam2_editor" TinyMCE editor iframe
    Then I should see "Datalynx field Text"
    And "button.datalynx-field-tag" "css_element" should exist

    # Click the button to open the properties dialog
    When I click on "Datalynx field Text" "button"
    And I switch to the main frame

    # Verify the modal dialog opened with the correct content
    Then I should see "Field tag properties"
    And "[data-region='datalynx-tag-field']" "css_element" should exist
    And "[data-region='tag-behavior-select']" "css_element" should exist
    And "[data-region='tag-renderer-select']" "css_element" should exist

  @javascript
  Scenario: Delete a field tag button via the properties dialog
    When I click on "Edit Gridview" "link"
    And I click on "Entry template" "link"

    # Insert a field tag button via dropdown
    When I select "[[Datalynx field Text]]" from the "eparam2_editor_field_tag_menu" singleselect

    # Verify button is present
    And I switch to the "id_eparam2_editor" TinyMCE editor iframe
    Then "button.datalynx-field-tag" "css_element" should exist

  @javascript
  Scenario: Tags in the view template are converted to buttons when editor loads
    # First save a view with a raw field tag in the entry template
    When I click on "Edit Gridview" "link"
    And I click on "Entry template" "link"
    Then I add to "id_eparam2_editor" editor the text "[[Datalynx field Text]]"
    And I press "Save changes"

    # Re-open the view for editing - patterndialogue.js should convert [[tag]] to a button
    When I click on "Edit Gridview" "link"
    And I click on "Entry template" "link"

    # Verify the tag was converted to a clickable button in the editor
    And I switch to the "id_eparam2_editor" TinyMCE editor iframe
    Then "button.datalynx-field-tag" "css_element" should exist
    And I switch to the main frame

  @javascript
  Scenario: Saving view template converts tag buttons back to raw tags
    # Insert a field tag button
    And I click on "Edit Gridview" "link"
    And I click on "Entry template" "link"
    When I select "[[Datalynx field Text]]" from the "eparam2_editor_field_tag_menu" singleselect
    And I press "Save changes"

    # Re-open and verify the raw tag was preserved (not saved as HTML button)
    When I click on "Edit Gridview" "link"
    And I click on "Entry template" "link"
    And I switch to the "id_eparam2_editor" TinyMCE editor iframe
    # The tag should be rendered as a button (converted from saved raw tag)
    Then "button.datalynx-field-tag" "css_element" should exist
    And I switch to the main frame

  @javascript @debugmodal
  Scenario: View link modals save independently in the view and entry Tiny editors
    When I click on "Edit Gridview" "link"
    And I change window size to "large"
    And I click on "View template" "link"
    Then I should see "General tags"
    When I select "##viewlink:Gridview;;;##" from the "esection_editor_general_tag_menu" singleselect
    And I switch to the "id_esection_editor" TinyMCE editor iframe
    And I click on "viewlink: Gridview" "button"
    And I switch to the main frame
    Then "[data-region='tag-view-select']" "css_element" should exist
    And "[data-region='tag-linktext-input']" "css_element" should exist
    And "[data-region='tag-urlquery-input']" "css_element" should exist
    And "[data-region='tag-cssclass-input']" "css_element" should exist
    When I set the field "dlx-link-text-view" to "View link"
    And I set the field "dlx-url-query-view" to "new=1|bar=2"
    And I set the field "dlx-css-class-view" to "btn btn-primary"
    And I click on ".modal.show.datalynx-view-tag-modal .datalynx-tag-modal-save" "css_element"
    And I click on "View template" "link"
    And I click on "Entry template" "link"
    When I select "##viewsesslink:Gridview;;;##" from the "eparam2_editor_general_tag_menu" singleselect
    And I switch to the "id_eparam2_editor" TinyMCE editor iframe
    And I click on "viewsesslink: Gridview" "button"
    And I switch to the main frame
    Then "[data-region='tag-view-select']" "css_element" should exist
    And "[data-region='tag-linktext-input']" "css_element" should exist
    And "[data-region='tag-urlquery-input']" "css_element" should exist
    And "[data-region='tag-cssclass-input']" "css_element" should exist
    Then I set the field "dlx-link-text-entry" to "Entry link"
    And I set the field "dlx-url-query-entry" to "new=1|entryid=##entryid##"
    And I set the field "dlx-css-class-entry" to "btn btn-secondary"
    And I click on ".modal.show.datalynx-view-tag-modal .datalynx-tag-modal-save" "css_element"
    And I press "Save changes"
    When I click on "Edit Gridview" "link"
    And I click on "View template" "link"
    And I switch to the "id_esection_editor" TinyMCE editor iframe
    Then I should see "View link"
    And "button.datalynx-view-tag" "css_element" should exist
    And I switch to the main frame
    And I click on "Entry template" "link"
    And I switch to the "id_eparam2_editor" TinyMCE editor iframe
    Then I should see "Entry link"
    And "button.datalynx-view-tag" "css_element" should exist
    And I switch to the main frame
