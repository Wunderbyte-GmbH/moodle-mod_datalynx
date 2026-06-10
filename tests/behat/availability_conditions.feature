@mod @mod_datalynx
Feature: Field availability conditions across a multi-view edit flow
  In order to build progressive, branching entry forms
  As a teacher
  I need fields to appear or hide on later views based on values entered on earlier views,
  combined with role-based visibility, while continuing to edit the same entry across views.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | idnumber | name          |
      | datalynx | C1     | 12345    | ConditionTest |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I add to the "ConditionTest" datalynx the following fields:
      | type        | name              | description | param1      | param2 | param3 |
      | radiobutton | Gender            |             | Male,Female |        |        |
      | text        | Diet              |             |             |        |        |
      | text        | Hobby             |             |             |        |        |
      | text        | FemaleOnlyField   |             |             |        |        |
      | text        | MaleTeacherField  |             |             |        |        |
      | text        | VegField          |             |             |        |        |
      | text        | IfFemaleEmpty     |             |             |        |        |
      | text        | IfMaleTeacherEmpty|             |             |        |        |
      | text        | IfVegEmpty        |             |             |        |        |
      | text        | Extra1            |             |             |        |        |
      | text        | Extra2            |             |             |        |        |
      | text        | Extra3            |             |             |        |        |
    And I am on "Course 1" course homepage
    And I add to "ConditionTest" datalynx the view of "Grid" type with:
      | name        | View1       |
      | description | First view  |
    And I am on "Course 1" course homepage
    And I add to "ConditionTest" datalynx the view of "Grid" type with:
      | name        | View2       |
      | description | Second view |
    And I am on "Course 1" course homepage
    And I add to "ConditionTest" datalynx the view of "Grid" type with:
      | name        | View3       |
      | description | Third view  |
    And I am on "Course 1" course homepage
    And I add to "ConditionTest" datalynx the view of "Grid" type with:
      | name        | View4       |
      | description | Fourth view |
    And the "View1" view is the default and edit view of "ConditionTest" datalynx
    And the "ConditionTest" datalynx has the following behaviors:
      | name                 | visibleto                            | editableby                     | match | conditions       |
      | b_femaleonly         | manager,teacher,student,author,guest | manager,teacher,student,author | all   | Gender=Female    |
      | b_maleteacher        | manager,teacher                      | manager,teacher                | all   | Gender=Male      |
      | b_veg                | manager,teacher,student,author,guest | manager,teacher,student,author | all   | Diet~veg         |
      | b_iffemaleempty      | manager,teacher,student,author,guest | manager,teacher,student,author | all   | FemaleOnlyField= |
      | b_ifmaleteacherempty | manager,teacher,student,author,guest | manager,teacher,student,author | all   | MaleTeacherField=|
      | b_ifvegempty         | manager,teacher,student,author,guest | manager,teacher,student,author | all   | VegField=        |
    And the "View1" view of "ConditionTest" datalynx has the entry template "[[Datalynx field Gender]] [[Datalynx field Diet]] [[Datalynx field Hobby]] ##edit##"
    And the "View2" view of "ConditionTest" datalynx has the entry template "[[Datalynx field FemaleOnlyField|b_femaleonly]] [[Datalynx field MaleTeacherField|b_maleteacher]] [[Datalynx field VegField|b_veg]] ##edit##"
    And the "View3" view of "ConditionTest" datalynx has the entry template "[[Datalynx field IfFemaleEmpty|b_iffemaleempty]] [[Datalynx field IfMaleTeacherEmpty|b_ifmaleteacherempty]] [[Datalynx field IfVegEmpty|b_ifvegempty]] ##edit##"
    And the "View4" view of "ConditionTest" datalynx has the entry template "G:[[Datalynx field Gender]] D:[[Datalynx field Diet]] V:[[Datalynx field VegField]] IFE:[[Datalynx field IfFemaleEmpty]] IMTE:[[Datalynx field IfMaleTeacherEmpty]] IVE:[[Datalynx field IfVegEmpty]]"
    And the "View1" view of "ConditionTest" datalynx redirects to the "View2" view continuing editing "1"
    And the "View2" view of "ConditionTest" datalynx redirects to the "View3" view continuing editing "1"
    And the "View3" view of "ConditionTest" datalynx redirects to the "View4" view continuing editing "0"
    And I log out

  @javascript
  Scenario: Conditional fields appear and hide across views based on earlier values and role
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "ConditionTest"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type  | name   | value      |
      | radio | Gender | Male       |
      | text  | Diet   | vegetarian |
      | text  | Hobby  | reading    |
    And I press "Save changes"
    # View 2: VegField shows (Diet contains "veg"); FemaleOnlyField hidden (Gender is Male, not Female);
    # MaleTeacherField hidden for the student by role even though its Gender=Male condition is met.
    Then "div[data-field-name='Datalynx field VegField']" "css_element" should exist
    And "div[data-field-name='Datalynx field FemaleOnlyField']" "css_element" should not exist
    And "div[data-field-name='Datalynx field MaleTeacherField']" "css_element" should not exist
    And I fill in the entry form fields
      | type | name     | value |
      | text | VegField | Salad |
    And I press "Save changes"
    # View 3: fields appear based on the EMPTY value of view-2 fields that were never filled.
    Then "div[data-field-name='Datalynx field IfFemaleEmpty']" "css_element" should exist
    And "div[data-field-name='Datalynx field IfMaleTeacherEmpty']" "css_element" should exist
    And "div[data-field-name='Datalynx field IfVegEmpty']" "css_element" should not exist
    And I fill in the entry form fields
      | type | name               | value     |
      | text | IfFemaleEmpty      | noFemale  |
      | text | IfMaleTeacherEmpty | noTeacher |
    And I press "Save changes"
    # View 4: display only (no ##edit##, no continue editing) - shows all saved values, no edit form.
    Then I should see "vegetarian"
    And I should see "Salad"
    And I should see "noFemale"
    And I should see "noTeacher"
    And "Save changes" "button" should not exist
    And "div[data-field-name='Datalynx field VegField']" "css_element" should not exist
