@mod @mod_datalynx @app @javascript
Feature: Delete datalynx entries in the Moodle App
  In order to remove an entry I no longer need
  As a student
  I need to delete my own entries in the Moodle App, and only those

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
      | type | name  | description | param1 |
      | text | Title |             |        |
    And I add to "Fruits" datalynx the view of "Grid" type with:
      | name        | Cards |
      | description | Cards |
    And the "Cards" view of "Fruits" datalynx has the entry template "<p>Fruit: [[Datalynx field Title]]</p> ##delete##"
    And the "Cards" view is the default and edit view of "Fruits" datalynx
    And the "Fruits" datalynx has the following entries:
      | user     | Title  |
      | student1 | Apple  |
      | student1 | Banana |
      | student2 | Cherry |
    And I log out

  Scenario: Delete an own entry; the search stays applied, other users' entries offer no delete
    Given I entered the course "Course 1" as "student1" in the app
    And I press "Fruits" in the app
    # "a" finds Apple and Banana, not Cherry.
    And I set the field "Search entries" to "a" in the app
    And I press "Search" in the app
    And I should find "Showing 2 of 2 entries" in the app

    When I press "Delete" near "Fruit: Banana" in the app
    Then I should find "Delete this entry?" in the app
    When I press "OK" in the app
    Then I should find "Showing 1 of 1 entries" in the app
    And I should find "Fruit: Apple" in the app
    But I should not find "Fruit: Banana" in the app
    And I should not find "Fruit: Cherry" in the app

    # Another user's entry: no delete button.
    When I set the field "Search entries" to "Cherry" in the app
    And I press "Search" in the app
    Then I should find "Fruit: Cherry" in the app
    But I should not find "Delete" in the app

  Scenario: Cancelling the confirmation keeps the entry
    Given I entered the course "Course 1" as "student1" in the app
    And I press "Fruits" in the app
    When I press "Delete" near "Fruit: Apple" in the app
    And I press "Cancel" in the app
    Then I should find "Fruit: Apple" in the app
    And I should find "Showing 3 of 3 entries" in the app
