@mod @mod_datalynx @datalynx_text_behavior_renderer @javascript
Feature: Test text field behavior and renderer in datalynx
  In order to control field visibility and display formatting
  As a teacher
  I need to configure field behaviors and renderers and verify they work correctly for different users.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
      | student3 | Student   | 3        | student3@example.com |
      | student4 | Student   | 4        | student4@example.com |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
      | student4 | C1     | student        |
      | manager1 | C1     | manager        |
    And the following "activities" exist:
      | activity | course | idnumber | name                   |
      | datalynx | C1     | 12345    | Datalynx Test Instance |

    # Add fields as teacher
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I add to the "Datalynx Test Instance" datalynx the following fields:
      | type             | name               | description | param1 | param2 | param3 |
      | text             | Text               |             |        |        |        |
      | teammemberselect | Team member select |             | 20     | 0      | 0      |
    And I follow "Datalynx field Team member select"
    And I set the field "Student" to "1"
    And I press "Save changes"

    # Add behavior "Text Behavior" via Manage > Fields > Behaviors
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I click on ".nav-item [title='Manage']" "css_element"
    And I follow "Fields"
    And I follow "Behaviors"
    And I follow "Add behavior"
    And I set the field "Name" to "Text Behavior"
    # Set visibletopermission to Manager and Teacher only (remove Student from default selection)
    And I set the field "Visible to" to "Manager, Teacher"
    # Add Student 3 to the explicit user visibility list
    And I set the field "Other user" to "Student 3"
    # Add Team member select field to team member visibility
    And I set the field "Team member select field" to "Datalynx field Team member select"
    # Set editableby to Manager only (remove Teacher and Student from defaults)
    And I set the field "Editable by" to "Manager"
    And I press "Save changes"

    # Add renderer "Text Renderer" via Manage > Fields > Renderers
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I click on ".nav-item [title='Manage']" "css_element"
    And I follow "Fields"
    And I follow "Renderers"
    And I follow "Add renderer"
    And I set the field "Name" to "Text Renderer"
    # When not visible: Custom template
    And I click on "input[name='notvisibleoptions'][value='___2___']" "css_element"
    And I set the field "notvisibletemplate" to "<div>I am not visible</div>"
    # Display template: Custom template with #value
    And I click on "input[name='displayoptions'][value='___2___']" "css_element"
    And I set the field "displaytemplate" to "<span>This is the display template:</span>#value"
    # When empty: Display nothing
    And I click on "input[name='novalueoptions'][value='___0___']" "css_element"
    # Edit template: Custom template with #input
    And I click on "input[name='editoptions'][value='___2___']" "css_element"
    And I set the field "edittemplate" to "<span>The edit template</span>#input"
    # When not editable: Display disabled elements
    And I click on "input[name='noteditableoptions'][value='___3___']" "css_element"
    And I press "Save changes"

    # Create Grid view "Behavior Renderer View"
    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | Behavior Renderer View |
      | description | Test behavior renderer |
    And I follow "Set as default view"
    And I follow "Set as edit view"

    # Edit view to add entry template with field tags via JavaScript dialog
    And I change window size to "large"
    And I click on "Edit Behavior Renderer View" "link"
    And I click on "Entry template" "link"
    # Insert Text field tag from the field tags dropdown
    And I select "[[Datalynx field Text]]" from the "eparam2_editor_field_tag_menu" singleselect
    # Open the dialog for the Text field tag button to assign behavior and renderer
    And I switch to the "id_eparam2_editor" TinyMCE editor iframe
    And I click on "Datalynx field Text" "button"
    And I switch to the main frame
    # Select behavior and renderer in the dialog
    And I select "Text Behavior" from the "datalynx-tag-behavior-select" singleselect
    And I select "Text Renderer" from the "datalynx-tag-renderer-select" singleselect
    And I click on "[data-region='save-tag']" "css_element"
    # Also insert Team member select field tag
    And I select "[[Datalynx field Team member select]]" from the "eparam2_editor_field_tag_menu" singleselect
    # Add raw ##edit## action tag so users with manageentries can enter edit mode
    And I add to "id_eparam2_editor" editor the text " ##edit##"
    And I press "Save changes"
    And I log out

    # Log in as manager1 and add an entry with Text="Hello World" and Student 4 as team member
    And I log in as "manager1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type             | name               | value                            |
      | text             | Text               | Hello World                      |
      | teammemberselect | Team member select | Student 4 (student4@example.com) |
    And I press "Save changes"
    And I press "Continue"
    And I log out

  Scenario: Student with no visibility sees the not-visible custom template
    When I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    Then I should see "I am not visible"
    And I should not see "Hello World"

  Scenario: Student 3 explicitly listed sees the display template with value
    When I log in as "student3"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    Then I should see "This is the display template:"
    And I should see "Hello World"
    And I should not see "I am not visible"

  Scenario: Student 4 selected as team member sees the display template with value
    When I log in as "student4"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    Then I should see "This is the display template:"
    And I should see "Hello World"
    And I should not see "I am not visible"

  Scenario: Teacher sees display template in view mode and disabled input in edit mode
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    Then I should see "This is the display template:"
    And I should see "Hello World"
    # Open edit mode: teacher has manageentries capability so ##edit## renders an Edit link
    When I follow "Edit"
    Then I should see "This is the display template:"
    And "input[disabled]" "css_element" should exist

  Scenario: Manager sees the edit template with input in edit mode
    When I log in as "manager1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    Then I should see "This is the display template:"
    And I should see "Hello World"
    When I follow "Edit"
    Then I should see "The edit template"
    And I should not see "I am not visible"
