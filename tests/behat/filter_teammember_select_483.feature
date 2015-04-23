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
      | teacher2  | C1      | editingteacher  |
      | teacher3  | C1      | editingteacher  |
    And the following "activities" exist:
      | activity | course | idnumber | name                   | approval |
      | datalynx | C1     | 12345    | Datalynx Test Instance | 1        |
    And "Datalynx Test Instance" has following views:
      | type    | name                   | status       | redirect           | filter        | param2                                                                                                |
      | grid    | Default view           | default      | Default view       |               | <div ><table><tbody><tr><td>Hi.</td></tr><tr><td>##edit##  ##delete##</td></tr></tbody></table></div> |
    And "Datalynx Test Instance" has following fields:
      | type             | name     |visible | edits |  param1 | param2 | param3    |param4    |
      | teammemberselect | lehrer   |  2     | -1    | 3       | [2]  | 1           |4         |
      | text             | entry    |  2     | -1    |         | [2]  |             |          |
    And "Datalynx Test Instance" has following entries:
      | author   |entry | lehrer            | approved |
      | teacher1 |t1_1  | teacher1          | 1        |
      | teacher2 |t1_2  | teacher1          | 1        |
      | teacher3 |t1_3  | teacher1          | 1        |
      | teacher1 |t2    | teacher2          | 1        |
      | teacher3 |t2_t3 | teacher2,teacher3 | 1        |
    And "Datalynx Test Instance" has following filters:
      | name              | visible  |   customsearch                                                                   |
      | lehrer_teamselect | 1        | a:1:{i:1;a:1:{s:3:"AND";a:1:{i:0;a:3:{i:0;s:0:"";i:1;s:4:"USER";i:2;s:1:"3";}}}} |

Scenario: Login as Teacher1 and see three entries of yourself
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
    And I should not see "t2"
    And I should not see "t2_t3"
    And I should see "t1_1"
    And I should see "t1_2"
    And I should see "t1_3"
    
Scenario: Login as Teacher2 and see one entrie of yourself and one entry with Teacher3
    Given I log in as "teacher2"
    And I follow "Course 1"
    And I follow "Datalynx Test Instance"
    And I follow "Manage"
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
    And I should see "t2"
    And I should see "t2_t3"
    And I should not see "t1_1"
    And I should not see "t1_2"
    And I should not see "t1_3"
    
Scenario: Login as Teacher3 and see one entrie with Teacher2
    Given I log in as "teacher3"
    And I follow "Course 1"
    And I follow "Datalynx Test Instance"
    And I follow "Manage"
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
    And I should not see "t2"
    And I should see "t2_t3"
    And I should not see "t1_1"
    And I should not see "t1_2"
    And I should not see "t1_3"