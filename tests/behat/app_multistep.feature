@mod @mod_datalynx @app @javascript
Feature: Fill in a multi-step form in the Moodle App
  In order to post an entry that a chain of views asks for step by step
  As a student
  I need the app to open the next step after each save, as the web does

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
      | activity | course | idnumber | name  |
      | datalynx | C1     | dl1      | Rides |
    And I log in as "admin"
    And I am on "Course 1" course homepage
    And I add to the "Rides" datalynx the following fields:
      | type | name | description | param1 | param2 | param3 |
      | text | From |             |        |        |        |
      | text | Car  |             |        |        |        |
    And I add to "Rides" datalynx the view of "Grid" type with:
      | name        | Step1 |
      | description | Step1 |
    And I add to "Rides" datalynx the view of "Grid" type with:
      | name        | Step2 |
      | description | Step2 |
    And I add to "Rides" datalynx the view of "Grid" type with:
      | name        | Mine |
      | description | Mine |
    And I click on "Edit Step1" "link"
    And I expand all fieldsets
    And I set the field "Target view" to "Step2"
    And I set the field "Redirect and continue editing" to "1"
    And I press "Save changes"
    And I click on "Edit Step2" "link"
    And I expand all fieldsets
    And I set the field "Target view" to "Mine"
    And I set the field "Redirect and continue editing" to "0"
    And I press "Save changes"
    And the "Step1" view of "Rides" datalynx has the entry template "<p>Step one</p> [[Datalynx field From]] ##submit:text=Next_step##"
    And the "Step2" view of "Rides" datalynx has the entry template "<p>Step two</p> [[Datalynx field Car]] ##submit:text=Save_ride##"
    And the "Mine" view of "Rides" datalynx has the entry template "<p>Ride from [[Datalynx field From]] by [[Datalynx field Car]]</p>"
    And the "Step1" view is the default and edit view of "Rides" datalynx
    And I log out

  Scenario: Each save opens the next step, the last one the list
    Given I entered the course "Course 1" as "student1" in the app
    And I press "Rides" in the app
    When I press "Add a new entry" in the app
    Then I should find "Step one" in the app
    When I set the field "Datalynx field From" to "Wien" in the app
    And I press "Next step" in the app
    Then I should find "Step two" in the app
    And I should find "The entry has been saved." in the app
    When I set the field "Datalynx field Car" to "VW Polo" in the app
    And I press "Save ride" in the app
    Then I should find "Ride from Wien by VW Polo" in the app
    When I pull to refresh in the app
    Then I should find "Ride from Wien by VW Polo" in the app
    And I should not find "Step two" in the app
