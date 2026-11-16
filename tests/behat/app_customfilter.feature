@mod @mod_datalynx @app @javascript
Feature: Use a custom filter of a datalynx view in the Moodle App
  In order to find entries on my phone
  As a student
  I need to search the entries with the custom filter of the view

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
      | type   | name   | description | param1    |
      | text   | Title  |             |           |
      | select | Colour |             | Red,Green |
    And I add to "Fruits" datalynx the view of "Grid" type with:
      | name        | Cards |
      | description | Cards |
    And the "Fruits" datalynx has the customfilter "Find" with:
      | searchfields | Title,Colour |
    And I click on "Edit Cards" "link"
    And I click on "View template" "link"
    # A leading "##" would be taken by the editor for a heading.
    And I set the "id_esection_editor" editor to " ##pagingbar## ##customfilter:Find## ##entries## "
    And I press "Save changes"
    And the "Cards" view of "Fruits" datalynx has the entry template "<p>Fruit: [[Datalynx field Title]]</p>"
    And the "Cards" view is the default and edit view of "Fruits" datalynx
    And the "Fruits" datalynx has the following entries:
      | user     | Title  | Colour |
      | student1 | Apple  | Red    |
      | student1 | Banana | Green  |
      | student1 | Cherry | Red    |
    And I log out

  Scenario: Apply the custom filter, search within it and clear it
    Given I entered the course "Course 1" as "student1" in the app
    And I press "Fruits" in the app
    And I should find "Showing 3 of 3 entries" in the app

    When I press "Find" in the app
    And I press "Datalynx field Colour" in the app
    And I press "Red" in the app
    And I press "OK" in the app
    And I press "Apply" in the app
    Then I should find "Showing 2 of 2 entries" in the app
    And I should find "Fruit: Apple" in the app
    And I should find "Fruit: Cherry" in the app
    But I should not find "Fruit: Banana" in the app

    # The search works within the custom filter.
    When I set the field "Search entries" to "Cherry" in the app
    And I press "Search" in the app
    Then I should find "Showing 1 of 1 entries" in the app
    And I should find "Fruit: Cherry" in the app

    # A text field of the filter.
    When I set the field "Datalynx field Title" to "an" in the app
    And I press "Apply" in the app
    Then I should find "Fruit: Banana" in the app
    But I should not find "Fruit: Apple" in the app

    When I press "Clear" in the app
    Then I should find "Showing 3 of 3 entries" in the app
