@mod @mod_datalynx @javascript @editor_tiny
Feature: Datalynx view links respect view privileges
  In order to avoid leaking protected views
  As a Datalynx user
  I need view links and direct view access to respect each view's visibility settings.

  Background:
    Given the following config values are set as admin:
      | enrol_guest | Yes |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | manager1 | Manager   | 1        | manager1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | manager1 | C1     | manager        |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | idnumber | name                   |
      | datalynx | C1     | 12345    | Datalynx Test Instance |
    And I am on the "Course 1" "enrolment methods" page logged in as teacher1
    And I click on "Enable" "link" in the "Guest access" "table_row"
    And I am on "Course 1" course homepage
    And I add to the "Datalynx Test Instance" datalynx the following fields:
      | type | name | description | param1 | param2 | param3 |
      | text | Text |             |        |        |        |

    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | DefaultView |
      | description | Default view |
    And I click on "Edit DefaultView" "link"
    And I set the field "Manager" to "1"
    And I set the field "Teacher" to "1"
    And I set the field "Student" to "1"
    And I set the field "Guest" to "1"
    And I click on "View template" "link"
    And I add to "id_esection_editor" editor the text "Default view marker ##viewlink:PublicTarget;Public view link;;## ##viewsesslink:PublicTarget;Public session link;mode=public;## ##viewlink:ManagerTarget;Manager view link;;## ##viewsesslink:ManagerTarget;Manager session link;mode=manager;## ##viewlink:TeacherTarget;Teacher view link;;## ##viewsesslink:TeacherTarget;Teacher session link;mode=teacher;## ##viewlink:StudentTarget;Student view link;;## ##viewsesslink:StudentTarget;Student session link;mode=student;## ##viewlink:GuestTarget;Guest view link;;## ##viewsesslink:GuestTarget;Guest session link;mode=guest;##"
    And I press "Save changes"
    And I follow "Set as default view"

    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | PublicTarget |
      | description | Public target |
    And I click on "Edit PublicTarget" "link"
    And I set the field "Manager" to "1"
    And I set the field "Teacher" to "1"
    And I set the field "Student" to "1"
    And I set the field "Guest" to "1"
    And I click on "View template" "link"
    And I add to "id_esection_editor" editor the text "Public target marker"
    And I press "Save changes"

    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | ManagerTarget |
      | description | Manager target |
    And I click on "Edit ManagerTarget" "link"
    And I set the field "Manager" to "1"
    And I set the field "Teacher" to "0"
    And I set the field "Student" to "0"
    And I set the field "Guest" to "0"
    And I click on "View template" "link"
    And I add to "id_esection_editor" editor the text "Manager target marker"
    And I press "Save changes"

    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | TeacherTarget |
      | description | Teacher target |
    And I click on "Edit TeacherTarget" "link"
    And I set the field "Manager" to "0"
    And I set the field "Teacher" to "1"
    And I set the field "Student" to "0"
    And I set the field "Guest" to "0"
    And I click on "View template" "link"
    And I add to "id_esection_editor" editor the text "Teacher target marker"
    And I press "Save changes"

    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | StudentTarget |
      | description | Student target |
    And I click on "Edit StudentTarget" "link"
    And I set the field "Manager" to "0"
    And I set the field "Teacher" to "0"
    And I set the field "Student" to "1"
    And I set the field "Guest" to "0"
    And I click on "View template" "link"
    And I add to "id_esection_editor" editor the text "Student target marker"
    And I press "Save changes"

    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | GuestTarget |
      | description | Guest target |
    And I click on "Edit GuestTarget" "link"
    And I set the field "Manager" to "0"
    And I set the field "Teacher" to "0"
    And I set the field "Student" to "0"
    And I set the field "Guest" to "1"
    And I click on "View template" "link"
    And I add to "id_esection_editor" editor the text "Guest target marker"
    And I press "Save changes"
    And I log out

  Scenario: Roles only see the views they have the viewprivilege to see
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    Then I should see "Default view marker"
    And I should see "Public view link"
    And I should see "Public session link"
    And I should see "Teacher view link"
    And I should see "Teacher session link"
    And I should not see "Manager view link"
    And I should not see "Manager session link"
    And I should not see "Student view link"
    And I should not see "Student session link"
    And I should see "Guest view link"
    And I should see "Guest session link"
    When I open the "TeacherTarget" view of "Datalynx Test Instance" datalynx
    Then I should see "Teacher target marker"
    And I should not see "Default view marker"
    When I open the "ManagerTarget" view of "Datalynx Test Instance" datalynx
    Then I should see "Default view marker"
    And I should not see "Manager target marker"
    # Manager only sees manager and public view links and falls back from protected direct opens
    When I log in as "manager1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    Then I should see "Default view marker"
    And I should see "Public view link"
    And I should see "Public session link"
    And I should see "Manager view link"
    And I should see "Manager session link"
    And I should not see "Teacher view link"
    And I should not see "Teacher session link"
    And I should not see "Student view link"
    And I should not see "Student session link"
    And I should see "Guest view link"
    And I should see "Guest session link"
    When I open the "ManagerTarget" view of "Datalynx Test Instance" datalynx
    Then I should see "Manager target marker"
    And I should not see "Default view marker"
    When I open the "TeacherTarget" view of "Datalynx Test Instance" datalynx
    Then I should see "Default view marker"
    And I should not see "Teacher target marker"
    # Student only sees student and public view links and falls back from protected direct opens
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    Then I should see "Default view marker"
    And I should see "Public view link"
    And I should see "Public session link"
    And I should see "Student view link"
    And I should see "Student session link"
    And I should not see "Manager view link"
    And I should not see "Manager session link"
    And I should not see "Teacher view link"
    And I should not see "Teacher session link"
    And I should see "Guest view link"
    And I should see "Guest session link"
    When I open the "StudentTarget" view of "Datalynx Test Instance" datalynx
    Then I should see "Student target marker"
    And I should not see "Default view marker"
    When I open the "TeacherTarget" view of "Datalynx Test Instance" datalynx
    Then I should see "Default view marker"
    And I should not see "Teacher target marker"
    And I log out
    # Guest only sees guest and public view links and falls back from protected direct opens
    And I am on the "Course 1" course page logged in as guest
    And I follow "Datalynx Test Instance"
    Then I should see "Default view marker"
    And I should see "Public view link"
    And I should see "Public session link"
    And I should see "Guest view link"
    And I should see "Guest session link"
    And I should not see "Manager view link"
    And I should not see "Manager session link"
    And I should not see "Teacher view link"
    And I should not see "Teacher session link"
    And I should not see "Student view link"
    And I should not see "Student session link"
    When I open the "GuestTarget" view of "Datalynx Test Instance" datalynx
    Then I should see "Guest target marker"
    And I should not see "Default view marker"
    When I open the "TeacherTarget" view of "Datalynx Test Instance" datalynx
    Then I should see "Default view marker"
    And I should not see "Teacher target marker"
