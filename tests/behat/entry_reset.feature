@mod @mod_datalynx @javascript
Feature: Resetting an entry returns it to the state of a new entry
  In order to start an entry over, e.g. to change a value that is locked after saving
  As a student
  I need the reset to clear my data, restore what a new entry gets, and let me cancel it

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
      | type        | name     | description | param1                   | param2   | param3 |
      | select      | Format   |             | Event Grant,Travel Grant |          |        |
      | radiobutton | Workflow |             | In progress,Submitted    |          |        |
    # As in DocSchool: a new entry starts in the first workflow status.
    And the "Applications" datalynx has the following update field rules:
      | name   | events        | field    | value |
      | wf_neu | entry_created | Workflow | 1     |
    And I add to "Applications" datalynx the view of "Grid" type with:
      | name        | Step 1 |
      | description | Step 1 |
    And I click on "Edit Step 1" "link"
    And I click on "View template" "link"
    # A leading "##" would be taken by the editor for a heading.
    And I set the "id_esection_editor" editor to " ##addnewentry## ##entries## "
    And I press "Save changes"
    And the "Step 1" view of "Applications" datalynx has the entry template "<div>Format: [[Datalynx field Format]] ##reset## ##edit##</div>"
    And the "Step 1" view is the default and edit view of "Applications" datalynx
    And I add to "Applications" datalynx the view of "Grid" type with:
      | name        | Overview |
      | description | Overview |
    And the "Overview" view of "Applications" datalynx has the entry template "<div>Status: [[Datalynx field Workflow]] Chosen: [[Datalynx field Format]]</div>"
    And I log out

  Scenario: Cancel keeps the entry, continue clears it and the creation rules set it up again
    Given I log in as "student1"
    And I open the "Step 1" view of "Applications" datalynx
    And I follow "Add a new entry"
    And I set the field with xpath "//select[@aria-label='Datalynx field Format']" to "Travel Grant"
    And I press "Save changes"
    And I open the "Overview" view of "Applications" datalynx
    And I should see "Status: In progress"
    And I should see "Chosen: Travel Grant"
    And I open the "Step 1" view of "Applications" datalynx
    And I click on the 1st entry "Edit" link
    # Cancel returns to the edit form the reset was started from, not to the list of entries.
    When I click on the 1st entry "Reset" link
    And I press "Cancel"
    Then the field with xpath "//select[@aria-label='Datalynx field Format']" matches value "Travel Grant"
    # Continue clears the data; the entry then has what a new entry gets.
    When I click on the 1st entry "Reset" link
    And I press "Continue"
    Then I should see "1 entry(s) reset"
    And the field with xpath "//select[@aria-label='Datalynx field Format']" matches value ""
    And I open the "Overview" view of "Applications" datalynx
    And I should see "Status: In progress"
    And I should not see "Travel Grant"
