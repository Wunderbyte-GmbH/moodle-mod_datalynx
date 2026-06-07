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
      | username | firstname | lastname | email                | idnumber | institution | department |
      | teacher1 | Teacher   | 1        | teacher1@example.com | T123     | Uni1        | Dept1      |
      | student1 | Student   | 1        | student1@example.com | S123     | Uni1        | Dept1      |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | idnumber | name                   |
      | datalynx | C1     | 12345    | Datalynx Test Instance |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"

  Scenario: Configure and use entryauthor and teammemberselect field formats
    And I follow the datalynx "Manage" link
    And I follow "Fields"
    And I add to the "Datalynx Test Instance" datalynx the following fields:
      | type             | name | description | param1 | param2 | param3 |
      | teammemberselect | Team |             | 3      | 0      | 0      |
    And I follow "Datalynx field Team"
    And I set the field "Student" to "1"
    And I set the field "Allow manual unsubscription" to "1"
    And I press "Save changes"

    # Create field formats for entryauthor
    And I follow "Field Formats"
    And I set the field "Add field format" to "Entryauthor"
    And I wait until the page is ready
    And I set the field "Name" to "id"
    And I set the field "Format option" to "id"
    And I press "Save changes"
    And I set the field "Add field format" to "Entryauthor"
    And I wait until the page is ready
    And I set the field "Name" to "name"
    And I set the field "Format option" to "name"
    And I press "Save changes"
    And I set the field "Add field format" to "Entryauthor"
    And I wait until the page is ready
    And I set the field "Name" to "firstname"
    And I set the field "Format option" to "firstname"
    And I press "Save changes"
    And I set the field "Add field format" to "Entryauthor"
    And I wait until the page is ready
    And I set the field "Name" to "lastname"
    And I set the field "Format option" to "lastname"
    And I press "Save changes"
    And I set the field "Add field format" to "Entryauthor"
    And I wait until the page is ready
    And I set the field "Name" to "username"
    And I set the field "Format option" to "username"
    And I press "Save changes"
    And I set the field "Add field format" to "Entryauthor"
    And I wait until the page is ready
    And I set the field "Name" to "idnumber"
    And I set the field "Format option" to "idnumber"
    And I press "Save changes"
    And I set the field "Add field format" to "Entryauthor"
    And I wait until the page is ready
    And I set the field "Name" to "email"
    And I set the field "Format option" to "email"
    And I press "Save changes"
    And I set the field "Add field format" to "Entryauthor"
    And I wait until the page is ready
    And I set the field "Name" to "institution"
    And I set the field "Format option" to "institution"
    And I press "Save changes"
    And I set the field "Add field format" to "Entryauthor"
    And I wait until the page is ready
    And I set the field "Name" to "department"
    And I set the field "Format option" to "department"
    And I press "Save changes"
    And I set the field "Add field format" to "Entryauthor"
    And I wait until the page is ready
    And I set the field "Name" to "picture"
    And I set the field "Format option" to "picture"
    And I press "Save changes"
    And I set the field "Add field format" to "Entryauthor"
    And I wait until the page is ready
    And I set the field "Name" to "picturelarge"
    And I set the field "Format option" to "picturelarge"
    And I press "Save changes"
    And I set the field "Add field format" to "Entryauthor"
    And I wait until the page is ready
    And I set the field "Name" to "badges"
    And I set the field "Format option" to "badges"
    And I press "Save changes"
    And I set the field "Add field format" to "Entryauthor"
    And I wait until the page is ready
    And I set the field "Name" to "edit"
    And I set the field "Format option" to "edit"
    And I press "Save changes"

    # Create field format for teammemberselect with subscribe option
    And I set the field "Add field format" to "Team member select"
    And I wait until the page is ready
    And I set the field "Name" to "subscribe"
    And I click on "Subscribe" "checkbox"
    And I press "Save changes"

    # Use format in view template
    And I follow the datalynx "Views" link
    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | Gridview   |
      | description | Behat grid |
    And I follow "Set as default view"
    And I follow "Set as edit view"
    And I click on "Edit Gridview" "link"
    And I click on "Entry template" "link"
    And I set the "id_eparam2_editor" editor to "Author info: ##author:id## ##author:name## ##author:firstname## ##author:lastname## ##author:username## ##author:idnumber## ##author:email## ##author:institution## ##author:department## ##author:picture## ##author:picturelarge## ##author:badges## ##author:edit## Team members: [[Datalynx field Team:subscribe]]"
    And I press "Save changes"

    # Add an entry where teacher1 is the author
    And the "Datalynx Test Instance" datalynx has the following entries:
      | user     |
      | teacher1 |

    # Log in as student1 and view the entry
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    Then I should see "Author info:"
    And I should see "Teacher 1"
    And I should see "Teacher"
    And I should see "1"
    And I should see "teacher1"
    And I should see "T123"
    And I should see "teacher1@example.com"
    And I should see "Uni1"
    And I should see "Dept1"
    And I should see "Subscribe"
    And I click on "Subscribe" "link"
    And I wait until the page is ready
    Then I should see "Unsubscribe"
    And I should see "Student 1"
