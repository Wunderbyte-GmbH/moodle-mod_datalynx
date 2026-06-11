@mod @mod_datalynx @javascript
Feature: Edit field-behavior availability conditions in the AJAX modal
  In order to build conditional field behaviors without page reloads
  As a teacher
  I need to add a condition source field and have its operator widgets appear over AJAX.

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
      | activity | course | idnumber | name          |
      | datalynx | C1     | 12345    | BehaviorTest  |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I add to the "BehaviorTest" datalynx the following fields:
      | type | name       | description | param1 | param2 | param3 |
      | text | SourceText |             |        |        |        |

  Scenario: Add a behavior condition and have its operator widgets load over AJAX
    When I am on "Course 1" course homepage
    And I follow "BehaviorTest"
    And I click on ".nav-item [title='Manage']" "css_element"
    And I follow "Fields"
    And I follow "Behaviors"
    And I follow "Add behavior"
    And I set the field "Name" to "CondBehavior"
    # Selecting a condition source field reloads that row's operator widgets over AJAX (no page reload).
    And I set the field "condfield0" to "SourceText"
    Then I should see "contains"
    And I set the field "searchoperator0" to "empty"
    And I press "Save changes"
    Then I should see "CondBehavior"
    # Re-open the behavior: the saved condition row is pre-populated, so the operator select shows again.
    And I follow "CondBehavior"
    Then I should see "contains"
