@mod @mod_datalynx @javascript
Feature: Save the "only trigger when these field values change" setting of an event notification rule
  In order to only be notified when a relevant field actually changes
  As a teacher
  I need the selected on-change fields to be stored when I save the rule in the AJAX modal.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | course | idnumber | name     |
      | datalynx | C1     | 12345    | RuleTest |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I add to the "RuleTest" datalynx the following fields:
      | type | name       | description |
      | text | SourceText |             |
    And I am on "Course 1" course homepage
    And I follow "RuleTest"
    And I click on ".nav-item [title='Manage']" "css_element"
    And I follow "Rules"
    And I click on "Dismiss this notification" "button"

  Scenario: The selected on-change field is saved and reloaded
    When I set the field "Add a rule" to "Event notification"
    Then I should see "General"
    And I set the field "Name" to "TestRule"
    And I set the field "Entry updated" to "1"
    And I set the field "Only trigger when these field values change" to "SourceText"
    And I press "Save changes"
    Then I should see "TestRule"
    And I follow "TestRule"
    Then I should see "General"
    And the field "Entry updated" matches value "1"
    And "SourceText" "autocomplete_selection" should exist
