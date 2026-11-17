@mod @mod_datalynx @app @javascript
Feature: Approve datalynx entries in the Moodle App
  In order to publish the entries of my students from my phone
  As a teacher
  I need to approve entries and withdraw approval in the Moodle App

  # Runs in the Moodle App (local_moodleappbehat and an app container, see
  # https://moodledev.io/general/app/development/testing/acceptance-testing).
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
      | activity | course | idnumber | name   | approval |
      | datalynx | C1     | dl1      | Fruits | 1        |
    And I log in as "admin"
    And I am on "Course 1" course homepage
    And I add to the "Fruits" datalynx the following fields:
      | type | name  | description | param1 |
      | text | Title |             |        |
    And I add to "Fruits" datalynx the view of "Grid" type with:
      | name        | Cards |
      | description | Cards |
    And the "Cards" view of "Fruits" datalynx has the entry template "<p>Fruit: [[Datalynx field Title]]</p> ##approve##"
    And the "Cards" view is the default and edit view of "Fruits" datalynx
    And the "Fruits" datalynx has the following entries:
      | user     | Title  | approved |
      | student1 | Apple  | 0        |
      | student1 | Banana | 1        |
    And I log out

  Scenario: Approve an entry and withdraw the approval of another
    Given I entered the course "Course 1" as "teacher1" in the app
    And I press "Fruits" in the app
    When I press "Approve" near "Fruit: Apple" in the app
    Then I should find "Withdraw approval" near "Fruit: Apple" in the app
    When I press "Withdraw approval" near "Fruit: Banana" in the app
    Then I should find "Approve" near "Fruit: Banana" in the app

  Scenario: A student is offered no approval
    Given I entered the course "Course 1" as "student1" in the app
    When I press "Fruits" in the app
    Then I should find "Fruit: Apple" in the app
    And I should find "Fruit: Banana" in the app
    But I should not find "Withdraw approval" in the app
