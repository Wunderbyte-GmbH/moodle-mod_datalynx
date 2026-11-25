@mod @mod_datalynx @app @javascript
Feature: Add and edit datalynx entries in the Moodle App
  In order to keep my entries up to date on my phone
  As a student
  I need to add entries and edit my own entries in the Moodle App

  # Runs in the Moodle App (local_moodleappbehat and an app container, see
  # https://moodledev.io/general/app/development/testing/acceptance-testing).
  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student2 | C1     | student |
    And the following "activities" exist:
      | activity | course | idnumber | name   |
      | datalynx | C1     | dl1      | Fruits |
    And I log in as "admin"
    And I am on "Course 1" course homepage
    And I add to the "Fruits" datalynx the following fields:
      | type        | name  | description | param1    | param2 | param3 |
      | text        | Title |             |           |        |        |
      | multiselect | Tags  |             | Red,Green |        |        |
    And I add to "Fruits" datalynx the view of "Grid" type with:
      | name        | Cards |
      | description | Cards |
    And I click on "Edit Cards" "link"
    And I click on "View template" "link"
    # A leading "##" would be taken by the editor for a heading.
    And I set the "id_esection_editor" editor to " ##pagingbar## ##addnewentry## ##entries## "
    And I press "Save changes"
    And the "Cards" view of "Fruits" datalynx has the entry template "<p>Fruit: [[Datalynx field Title]]</p> [[Datalynx field Tags]] ##edit##"
    And the "Cards" view is the default and edit view of "Fruits" datalynx
    And the "Fruits" datalynx has the following entries:
      | user     | Title  |
      | student1 | Apple  |
      | student2 | Cherry |
    And I log out

  Scenario: Add an entry with a text and a multiple choice
    Given I entered the course "Course 1" as "student1" in the app
    And I press "Fruits" in the app
    When I press "Add a new entry" in the app
    And I set the field "Datalynx field Title" to "Damson" in the app
    And I press "Datalynx field Tags" in the app
    And I press "Red" in the app
    And I press "OK" in the app
    And I press "Save" in the app
    Then I should find "The entry has been saved." in the app
    When I go back in the app
    And I pull to refresh in the app
    Then I should find "Fruit: Damson" in the app
    And I should find "Red" in the app

  Scenario: Edit an own entry; another user's entry offers no edit
    Given I entered the course "Course 1" as "student1" in the app
    And I press "Fruits" in the app
    When I press "Edit" near "Fruit: Apple" in the app
    And I set the field "Datalynx field Title" to "Apricot" in the app
    And I press "Save" in the app
    Then I should find "The entry has been saved." in the app
    When I go back in the app
    And I pull to refresh in the app
    Then I should find "Fruit: Apricot" in the app
    But I should not find "Fruit: Apple" in the app

    When I set the field "Search entries" to "Cherry" in the app
    And I press "Search" in the app
    Then I should find "Fruit: Cherry" in the app
    But I should not find "Edit" in the app

  Scenario: Add an entry with a weekly schedule, its days tapped and its dates picked in a calendar
    Given I log in as "admin"
    And I am on "Course 1" course homepage
    And I add to the "Fruits" datalynx the following fields:
      | type     | name    | description | param1 | param2 | param3 |
      | schedule | Harvest |             |        |        |        |
    And the "Cards" view of "Fruits" datalynx has the entry template "[[Datalynx field Title]] [[Datalynx field Harvest]] ##edit##"
    And I log out
    And I entered the course "Course 1" as "student1" in the app
    And I press "Fruits" in the app
    When I press "Add a new entry" in the app
    And I set the field "Datalynx field Title" to "Plum" in the app
    Then I should find "Choose a date" in the app
    And I should not find "On these days" in the app
    When I press "Every week" in the app
    And I press "Monday" in the app
    And I press "Wednesday" in the app
    And I press "Runs from" in the app
    And I press "Next month" in the app
    And I press "28" in the app
    And I press "OK" in the app
    And I press "Runs until" in the app
    And I press "Next month" in the app
    And I press "28" in the app
    And I press "OK" in the app
    Then I should not find "Choose a date" in the app
    When I press "Save" in the app
    Then I should find "The entry has been saved." in the app
    And I should find "On these days" in the app
    And I should not find "Choose a date" in the app
