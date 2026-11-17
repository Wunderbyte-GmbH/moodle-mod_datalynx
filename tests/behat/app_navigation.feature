@mod @mod_datalynx @app @javascript
Feature: Navigate between the views of a datalynx activity in the Moodle App
  In order to use a datalynx activity built of several views on my phone
  As a student
  I need the links between its views to open in the Moodle App

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
      | name        | Start |
      | description | Start |
    And I add to "Fruits" datalynx the view of "Grid" type with:
      | name        | List |
      | description | List |
    And I add to "Fruits" datalynx the view of "Grid" type with:
      | name        | Detail |
      | description | Detail |
    And I add to "Fruits" datalynx the view of "Grid" type with:
      | name        | Form |
      | description | Form |
    And I add to "Fruits" datalynx the view of "Csv" type with:
      | name        | Export |
      | description | Export |
    # The start page lists no entries: it is a board of buttons, like the Mitfahrbörse portal.
    And I click on "Edit Start" "link"
    And I click on "View template" "link"
    And I set the "id_esection_editor" editor to "<p>Welcome to the fruit board</p><p>##viewlink:List;Browse fruits;;btn btn-primary w-100##</p><p>##viewsesslink:Form;Add a fruit;new=1;btn btn-outline-primary##</p><p>##viewlink:Export;Download the list;;##</p>"
    And I press "Save changes"
    And the "List" view of "Fruits" datalynx has the entry template "<p>Fruit: [[Datalynx field Title]]</p><p>##viewlink:Detail;Details;eids=##entryid##;##</p>"
    And the "Detail" view of "Fruits" datalynx has the entry template "<p>About [[Datalynx field Title]]</p>"
    And the "Form" view of "Fruits" datalynx has the entry template "<p>[[Datalynx field Title]]</p>##submit##"
    And the "Start" view is the default and edit view of "Fruits" datalynx
    And the "Fruits" datalynx has the following entries:
      | user     | Title  |
      | student1 | Apple  |
      | student1 | Banana |
    And I log out

  Scenario: Go from the start page to a list, an entry and back, then to the entry form
    Given I entered the course "Course 1" as "student1" in the app
    When I press "Fruits" in the app
    Then I should find "Welcome to the fruit board" in the app
    And I should find "Browse fruits" in the app
    But I should not find "Fruit: Apple" in the app
    And I should not find "Search entries" in the app

    When I press "Browse fruits" in the app
    Then the header should be "List" in the app
    And I should find "Fruit: Apple" in the app
    And I should find "Fruit: Banana" in the app

    When I press "Details" near "Fruit: Banana" in the app
    Then the header should be "Detail" in the app
    And I should find "About Banana" in the app
    But I should not find "About Apple" in the app

    When I go back 2 times in the app
    Then I should find "Welcome to the fruit board" in the app

    When I press "Add a fruit" in the app
    Then the header should be "Form" in the app
    When I set the field "Datalynx field Title" to "Cherry" in the app
    And I press "Save" in the app
    Then I should find "The entry has been saved." in the app

    When I go back in the app
    And I press "Browse fruits" in the app
    Then I should find "Fruit: Cherry" in the app

  Scenario: A link to a view the app cannot show opens the browser
    Given I entered the course "Course 1" as "student1" in the app
    And I press "Fruits" in the app
    When I press "Download the list" in the app
    And I press "OK" in the app
    Then the app should have opened a browser tab
