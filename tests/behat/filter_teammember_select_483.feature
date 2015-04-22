@mod @mod_datalynx @dev @_file_upload @wip1 @mink:selenium2
Feature:Team member should only see their entry
        Where they have been assigned to
        And neither the others nor nothing if there would be anything

  Background:
    Given the following "courses" exist:
      | fullname  | shortname   | category  | groupmode   |
      | Course 1  | C1          | 0         | 1           |
    And the following "users" exist:
      | username  | firstname   | lastname  | email                   |
      | teacher1  | Teacher     | 1         | teacher1@mailinator.com |
      | teacher2  | Teacher     | 2         | teacher2@mailinator.com |
      | teacher3  | Teacher     | 3         | teacher3@mailinator.com |
    And the following "course enrolments" exist:
      | user      | course  | role            |
      | teacher1  | C1      | editingteacher  |
    And the following "activities" exist:
      | activity | course | idnumber | name                   | approval |
      | datalynx | C1     | 12345    | Datalynx Test Instance | 1        |
    And "Datalynx Test Instance" has following views:
      | type    | name             | status       | redirect           | filter       | param2                                                                                                |
      | grid    | Default view     | default      | Default view       |              | <div ><table><tbody><tr><td>Hi.</td></tr><tr><td>##edit##  ##delete##</td></tr></tbody></table></div> |
    And "Datalynx Test Instance" has following fields:
      | type             | name     |visible | edits |  param1 | param2 | param3    |param4    |
      | teammemberselect | lehrer   |  2     | -1    | 3       | [2]  | 1           |4         |
    And "Datalynx Test Instance" has following entries:
      | author   | lehrer             | approved |
      | teacher1 | teacher1           | 1        |
      | teacher2 | teacher1           | 1        |
      | teacher3 | teacher1           | 1        |
      | teacher1 | teacher2           | 1        |
      | teacher3 | teacher2,teacher3  | 1        |
    And "Datalynx Test Instance" has following filters:
      | name              | visible  |   customsearch                                                                   |
      | lehrer_teamselect | 1        | a:1:{i:1;a:1:{s:3:"AND";a:1:{i:0;a:3:{i:0;s:0:"";i:1;s:4:"USER";i:2;s:1:"3";}}}} |

Scenario: Login to Course and add Fields with data
    Given I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Datalynx Test Instance"
    And I follow "Manage"
    And I follow "Fields"
    Then I should see "lehrer"
    And I follow "Filters"
    Then I should see "lehrer_teamselect"
    And I follow "Views"
    And I set the field "type" to "Grid"
    Then I should see "New Grid view"
    And I set the field "name" to "lehrer_teamselector"
    And I set the field "visible[4]" to "0"
    And I set the field "_filter" to "lehrer_teamselect"
    And I press "Save changes"
    Then I should see "lehrer_teamselector"
    And I follow "Browse"
    And I set the field "view" to "lehrer_teamselector"
    Then I should see "Add a new entry"
    Then I should see "There are no entries to display"
    And I follow "Add a new entry"
    Then I should see "lehrer"
    And I set the field "field_1_-1_dropdown[0]" to "3"
    And I press "Save changes"
    Then I should see "1 entry(s) updated"
    And I press "Continue"  
    Then I should not see "There are no entries to display"
    And I should not see "lehrer2"
    And I should not see "lehrer3"
    And I should see "lehrer1"
    
Scenario: Login to Course and add Fields with data
    Given I log in as "teacher2"
    And I follow "Course 1"
    And I follow "Datalynx Test Instance"
    And I follow "Manage"
    And I follow "Fields"
    Then I should see "lehrer"
    And I follow "Filters"
    Then I should see "lehrer_teamselect"
    And I follow "Views"
    And I set the field "type" to "Grid"
    Then I should see "New Grid view"
    And I set the field "name" to "lehrer_teamselector2"
    And I set the field "visible[4]" to "0"
    And I set the field "_filter" to "lehrer_teamselect3"
    And I press "Save changes"
    Then I should see "lehrer_teamselector3"
    And I follow "Browse"
    And I set the field "view" to "lehrer_teamselector3"
    Then I should see "Add a new entry"
    Then I should see "There are no entries to display"
    And I follow "Add a new entry"
    Then I should see "lehrer"
    And I set the field "field_1_-1_dropdown[0]" to "3"
    And I press "Save changes"
    Then I should see "1 entry(s) updated"
    And I press "Continue"  
    Then I should not see "There are no entries to display"
    And I should see "lehrer2"
    And I should see "lehrer2, lehrer3"
    And I should not see "lehrer1"
    
Scenario: Login to Course and add Fields with data
    Given I log in as "teacher3"
    And I follow "Course 1"
    And I follow "Datalynx Test Instance"
    And I follow "Manage"
    And I follow "Fields"
    Then I should see "lehrer"
    And I follow "Filters"
    Then I should see "lehrer_teamselect"
    And I follow "Views"
    And I set the field "type" to "Grid"
    Then I should see "New Grid view"
    And I set the field "name" to "lehrer_teamselector3"
    And I set the field "visible[4]" to "0"
    And I set the field "_filter" to "lehrer_teamselect3"
    And I press "Save changes"
    Then I should see "lehrer_teamselector3"
    And I follow "Browse"
    And I set the field "view" to "lehrer_teamselector3"
    Then I should see "Add a new entry"
    Then I should see "There are no entries to display"
    And I follow "Add a new entry"
    Then I should see "lehrer"
    And I set the field "field_1_-1_dropdown[0]" to "3"
    And I press "Save changes"
    Then I should see "1 entry(s) updated"
    And I press "Continue"  
    Then I should not see "There are no entries to display"
    And I should see "lehrer2"
    And I should see "lehrer2, lehrer3"
    And I should not see "lehrer1"