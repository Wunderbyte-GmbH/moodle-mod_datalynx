@mod @mod_datalynx @javascript @datalynx_multistep
Feature: Datalynx multi-step form with redirect-and-continue editing option
  In order to enter complex entries step-by-step
  As a student or teacher
  I need to fill out multiple views and continue editing the same entry upon redirection.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1        | 0        | 1         |
    And the following "custom profile fields" exist:
      | datatype | shortname | name          |
      | text     | uinfo     | UserInfoField |
    And the following "users" exist:
      | username | firstname | lastname | email                | profile_field_uinfo |
      | teacher1 | Teacher   | 1        | teacher1@example.com |                     |
      | student1 | Student   | 1        | student1@example.com | student1            |
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
      | type     | name      | description | param1 | param6 | param2 |
      | userinfo | UserInfo1 |             | uinfo  | 1      |        |
      | text     | Text1     |             |        |        |        |
      | text     | Text2     |             |        |        |        |
      | text     | Text3     |             |        |        |        |
      | text     | Text4     |             |        |        |        |
      | text     | Text5     |             |        |        |        |
    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | Step1View  |
      | description | First step |
    And I follow "Set as default view"
    And I follow "Set as edit view"
    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | Step2View   |
      | description | Second step |
    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | Step3View  |
      | description | Third step |
    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | Step4View   |
      | description | Fourth step |
    # Configure View 1
    And I follow "Manage"
    And I click on "Edit Step1View" "link"
    And I expand all fieldsets
    And I set the field "Target view" to "Step2View"
    And I set the field "Redirect and continue editing" to "1"
    And I press "Save changes"
    And I click on "Edit Step1View" "link"
    And I click on "Entry template" "link"
    And I set the "id_eparam2_editor" editor to "UserInfo1: ##author:Datalynx field UserInfo1## Text1: [[Datalynx field Text1]] ##submit## ##cancel##"
    And I press "Save changes"
    # Configure View 2
    And I follow "Manage"
    And I click on "Edit Step2View" "link"
    And I expand all fieldsets
    And I set the field "Target view" to "Step3View"
    And I set the field "Redirect and continue editing" to "1"
    And I press "Save changes"
    And I click on "Edit Step2View" "link"
    And I click on "Entry template" "link"
    And I set the "id_eparam2_editor" editor to "Text2: [[Datalynx field Text2]] Text3: [[Datalynx field Text3]] ##submit## ##cancel##"
    And I press "Save changes"
    # Configure View 3
    And I follow "Manage"
    And I click on "Edit Step3View" "link"
    And I expand all fieldsets
    And I set the field "Target view" to "Step4View"
    And I set the field "Redirect and continue editing" to "0"
    And I press "Save changes"
    And I click on "Edit Step3View" "link"
    And I click on "Entry template" "link"
    And I set the "id_eparam2_editor" editor to "Text4: [[Datalynx field Text4]] Text5: [[Datalynx field Text5]] ##submit## ##cancel##"
    And I press "Save changes"
    # Configure View 4
    And I follow "Manage"
    And I click on "Edit Step4View" "link"
    And I click on "Entry template" "link"
    And I set the "id_eparam2_editor" editor to "UserInfo1: ##author:Datalynx field UserInfo1## Text1: [[Datalynx field Text1]] Text2: [[Datalynx field Text2]] Text3: [[Datalynx field Text3]] Text4: [[Datalynx field Text4]] Text5: [[Datalynx field Text5]]"
    And I press "Save changes"
    And I log out

  Scenario: A student fills in the multi-step form and is redirected to display mode on the final view
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    # Fill View 1
    Then I should see "UserInfo1"
    And I should see "Text1"
    And I should not see "default_save"
    And I fill in the entry form fields
      | type     | name      | value          |
      | userinfo | UserInfo1 | student1       |
      | text     | Text1     | Value for view1|
    And I press "Save changes"
    # Redirection to View 2 in Edit Mode
    Then I should see "Text2"
    And I should see "Text3"
    And I fill in the entry form fields
      | type     | name      | value          |
      | text     | Text2     | Value for view2|
      | text     | Text3     | More view2 val |
    And I press "Save changes"
    # Redirection to View 3 in Edit Mode
    Then I should see "Text4"
    And I should see "Text5"
    And I fill in the entry form fields
      | type     | name      | value          |
      | text     | Text4     | Value for view3|
      | text     | Text5     | More view3 val |
    And I press "Save changes"
    # Redirection to View 4 in View/Display Mode
    Then I should see "student1"
    And I should see "Value for view1"
    And I should see "Value for view2"
    And I should see "More view2 val"
    And I should see "Value for view3"
    And I should see "More view3 val"
    And I should not see "Save changes"

  Scenario: A student cancels form entry on the first view
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    Then I should see "UserInfo1"
    And I should see "Text1"
    And I press "Cancel"
    Then I should not see "UserInfo1"
    And I should not see "Text1"
    And I should not see "updated"
