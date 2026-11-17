@mod @mod_datalynx @app @javascript
Feature: The entry form in the Moodle App shows the entry template of its view
  In order to understand what a form step asks of me on my phone
  As a student
  I need the texts of the entry template around the inputs, as on the web

  # Runs in the Moodle App (local_moodleappbehat and an app container, see
  # https://moodledev.io/general/app/development/testing/acceptance-testing).
  # Built like the first step of the DocSchool application form: a heading, a progress hint and
  # an info box in the entry template, the inputs in between.
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
      | activity | course | idnumber | name         |
      | datalynx | C1     | dl1      | Applications |
    And I log in as "admin"
    And I am on "Course 1" course homepage
    And I add to the "Applications" datalynx the following fields:
      | type   | name    | description | param1                 |
      | select | Format  |             | Event Grant,Travel Grant |
      | text   | Purpose |             |                        |
    # The question is part of the field layout, before #input, as in DocSchool.
    And the "Applications" datalynx has the following field layouts:
      | name     | edittemplate                                                                                         |
      | r_format | <div class='mb-4'><label class='form-label'>What type of funding are you applying for?</label><div>#input</div></div> |
    And I add to "Applications" datalynx the view of "Grid" type with:
      | name        | Step 1 |
      | description | Step 1 |
    And I click on "Edit Step 1" "link"
    And I click on "View template" "link"
    # A leading "##" would be taken by the editor for a heading.
    And I set the "id_esection_editor" editor to " ##addnewentry## ##entries## "
    And I press "Save changes"
    And the "Step 1" view of "Applications" datalynx has the entry template "<div class='card'><div class='text-uppercase small'>Funding application · Step 1 of 4</div><h3>Choose application type and section</h3><span class='badge'>1. Application type</span><div class='progress'><div class='progress-bar' style='width: 25%;'> </div></div><p class='text-muted'>First select the funding type.</p>[[Datalynx field Format||r_format]]<div class='alert alert-info'>The funding format cannot be changed after saving.</div><p>Purpose: [[Datalynx field Purpose]]</p>##edit##</div>"
    And the "Step 1" view is the default and edit view of "Applications" datalynx
    And I log out

  Scenario: A new entry shows the texts of the entry template around the inputs
    Given I entered the course "Course 1" as "student1" in the app
    And I press "Applications" in the app
    When I press "Add a new entry" in the app
    Then I should find "Funding application · Step 1 of 4" in the app
    And I should find "Choose application type and section" in the app
    And I should find "1. Application type" in the app
    And I should find "First select the funding type." in the app
    And I should find "The funding format cannot be changed after saving." in the app
    And I should find "What type of funding are you applying for?" in the app
    But I should not find "Datalynx field Format" in the app
    And I should find "Datalynx field Purpose" in the app
    When I set the field "Datalynx field Purpose" to "Conference in Vienna" in the app
    And I press "What type of funding are you applying for?" in the app
    And I press "Travel Grant" in the app
    And I press "OK" in the app
    And I press "Save" in the app
    Then I should find "The entry has been saved." in the app
    And I should find "Choose application type and section" in the app
    And I should find "The funding format cannot be changed after saving." in the app

  Scenario: Editing an entry shows the texts of the entry template as well
    Given the "Applications" datalynx has the following entries:
      | user     | Purpose  |
      | student1 | Workshop |
    And I entered the course "Course 1" as "student1" in the app
    And I press "Applications" in the app
    When I press "Edit" near "Purpose: Workshop" in the app
    Then I should find "Choose application type and section" in the app
    And I should find "The funding format cannot be changed after saving." in the app
    And the field "Datalynx field Purpose" matches value "Workshop" in the app
