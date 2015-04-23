@mod @mod_datalynx @dev @_file_upload @wip2 @mink:selenium2
Feature:When creating a view and a field and you add a viewsesslink tag
        It should be replaced be the appropriate link

  Background:
    Given the following "courses" exist:
      | fullname  | shortname   | category  | groupmode   |
      | Course 1  | C1          | 0         | 1           |
    And the following "users" exist:
      | username  | firstname   | lastname  | email                   |
      | teacher1  | Teacher     | 1         | teacher1@mailinator.com |
    And the following "course enrolments" exist:
      | user      | course  | role            |
      | teacher1  | C1      | editingteacher  |
    And the following "activities" exist:
      | activity | course | idnumber | name                   | approval |
      | datalynx | C1     | 12345    | Datalynx Test Instance | 1        |
  And "Datalynx Test Instance" has following views:
      | type    | name                   | status       | redirect           | filter        | param2                                                                    |
      | grid    | Default view           | default, edit, detail      | Default view       |               | <div ><table><tbody><tr><td>Hi.</td></tr><tr><td>##edit##  ##delete##</td></tr></tbody></table></div> |
  And "Datalynx Test Instance" has following fields:
      | type             | name     |visible | edits |  param1 | param2 | param3    |param4    |
      | teammemberselect | lehrer   |  2     | -1    | 3       | [2]  | 1           |4         |
      
      
Scenario: Login and create a View and a field with a sesslink as viewtemplate
    Given I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Datalynx Test Instance"
    And I follow "Manage"
    And I follow "Fields"
    And I set the field "type" to "Text"
    Then I should see "New Text field"
    And I set the field "name" to "testfield"
    And I press "Save changes"
    Then I should see "testfield"
    And I follow "Views"
    And I set the field "type" to "Grid"
    Then I should see "New Grid view"
    And I set the field "name" to "testview"
    And I press "Save changes"
    Then I should see "testview"
    And I click "Default view" button of "testview" item
    And I click "Edit view" button of "testview" item
    And I click "Detailed view" button of "testview" item