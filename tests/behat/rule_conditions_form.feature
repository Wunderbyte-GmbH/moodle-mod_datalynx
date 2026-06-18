@mod @mod_datalynx @javascript
Feature: Edit rule availability conditions in the AJAX modal
  In order to build conditional event notification rules
  As a teacher
  I need to add a condition source field and have its comparison value widgets appear dynamically.

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
      | activity | course | idnumber | name         |
      | datalynx | C1     | 12345    | RuleTest     |
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

  Scenario: Add a rule condition and have its operator/value widgets load dynamically
    When I set the field "Add a rule" to "Event notification"
    Then I should see "General"
    And I set the field "Name" to "TestRule"
    And I set the field "param5" to "SourceText"
    And I set the field with xpath "//div[contains(@id, 'fgroup_id_conditiongrp')]//input[@type='text']" to "mycondition"
    And I press "Save changes"
    Then I should see "TestRule"
    And I follow "TestRule"
    Then I should see "General"
    And the field "param5" matches value "Datalynx field SourceText"
    And the field with xpath "//div[contains(@id, 'fgroup_id_conditiongrp')]//input[@type='text']" matches value "mycondition"
