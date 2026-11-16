@mod @mod_datalynx @app @javascript
Feature: Browse datalynx entries in the Moodle App
  In order to use a datalynx activity on my phone
  As a student
  I need to browse, search and switch the views of its entries in the Moodle App

  # Runs in the Moodle App (local_moodleappbehat and an app container, see
  # https://moodledev.io/general/app/development/testing/acceptance-testing).
  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
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
    And I add to "Fruits" datalynx the view of "Tabular" type with:
      | name        | Table |
      | description | Table |
    And I add to "Fruits" datalynx the view of "Csv" type with:
      | name        | Export |
      | description | Export |
    And the "Cards" view of "Fruits" datalynx has the entry template "<p>Fruit: [[Datalynx field Title]]</p>"
    And the "Cards" view is the default and edit view of "Fruits" datalynx
    And the "Fruits" datalynx has the following entries:
      | user     | Title  |
      | student1 | Apple  |
      | student1 | Banana |
      | student1 | Cherry |
    And I log out

  Scenario: Browse the grid view, search it and switch to the other views
    Given I entered the course "Course 1" as "student1" in the app
    And I press "Fruits" in the app
    Then I should find "Fruit: Apple" in the app
    And I should find "Fruit: Cherry" in the app
    And I should find "Showing 3 of 3 entries" in the app

    When I set the field "Search entries" to "Banana" in the app
    And I press "Search" in the app
    Then I should find "Fruit: Banana" in the app
    And I should find "Showing 1 of 1 entries" in the app
    But I should not find "Fruit: Apple" in the app

    # A tabular view is listed field by field.
    When I press "View" in the app
    And I press "Table" in the app
    Then I should find "Apple" in the app
    And I should find "Cherry" in the app
    But I should not find "Fruit: Apple" in the app

    # A view type the app cannot show offers the way to the web.
    When I press "View" in the app
    And I press "Export" in the app
    Then I should find "This view cannot be shown in the app." in the app
    And I should find "Open in browser" in the app

  Scenario: Pull to refresh shows entries added in the meantime
    Given I entered the course "Course 1" as "student1" in the app
    And I press "Fruits" in the app
    And I should find "Fruit: Cherry" in the app
    And I should not find "Fruit: Damson" in the app
    When the "Fruits" datalynx has the following entries:
      | user     | Title  |
      | student1 | Damson |
    And I pull to refresh in the app
    Then I should find "Fruit: Damson" in the app
    And I should find "Showing 4 of 4 entries" in the app

  Scenario: Without a connection the app shows its network error and recovers
    Given I entered the course "Course 1" as "student1" in the app
    And I press "Fruits" in the app
    And I should find "Fruit: Apple" in the app
    When I switch network connection to offline
    And I press "View" in the app
    And I press "Table" in the app
    Then I should find "There was a problem connecting to the site" in the app
    # The app empties the page after a failed load; pulling to refresh once online loads it again.
    When I press "OK" in the app
    And I switch network connection to wifi
    And I pull to refresh in the app
    Then I should find "Cherry" in the app
    But I should not find "Fruit: Cherry" in the app
    And I should find "Showing 3 of 3 entries" in the app

  Scenario: Scrolling down loads more entries
    Given the "Fruits" datalynx has the following entries:
      | user     | Title    |
      | student1 | Fruit 04 |
      | student1 | Fruit 05 |
      | student1 | Fruit 06 |
      | student1 | Fruit 07 |
      | student1 | Fruit 08 |
      | student1 | Fruit 09 |
      | student1 | Fruit 10 |
      | student1 | Fruit 11 |
      | student1 | Fruit 12 |
      | student1 | Fruit 13 |
      | student1 | Fruit 14 |
      | student1 | Fruit 15 |
      | student1 | Fruit 16 |
      | student1 | Fruit 17 |
      | student1 | Fruit 18 |
      | student1 | Fruit 19 |
      | student1 | Fruit 20 |
      | student1 | Fruit 21 |
      | student1 | Fruit 22 |
      | student1 | Fruit 23 |
      | student1 | Fruit 24 |
      | student1 | Fruit 25 |
    And I entered the course "Course 1" as "student1" in the app
    And I press "Fruits" in the app
    Then I should find "Showing 20 of 25 entries" in the app
    When I load more items in the app
    Then I should find "Showing 25 of 25 entries" in the app
    And I should not be able to load more items in the app
