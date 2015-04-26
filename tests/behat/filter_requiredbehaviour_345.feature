@mod @mod_datalynx @mod_peter @wip5 @mink:selenium2
Feature:When a field is set to required
        The client and server have to respond to a null value
        With "Field Required!"

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
    And "Datalynx Test Instance" has following behaviours:
      |name               |description | visibleto                                  | editableto                                 | required |
      |testfieldBehaviour |            | a:3:{i:0;s:1:"1";i:1;s:1:"2";i:2;s:1:"4";} | a:3:{i:0;s:1:"1";i:1;s:1:"2";i:2;s:1:"4";} | 1        |
    And "Datalynx Test Instance" has following fields:
      |type             |name      |visible | edits |
      |text             |testfield |  2     | -1    |
    And "Datalynx Test Instance" has following views:
      |type    |name     |status        |redirect |param2                                                                                                                                                                                                                                                                                                                                     |
      |grid    |testview |default, edit |testview |<div class="entry"><table class="generaltable" align="center"><tbody><tr class="r0"><td class="cell c0" style="text-align:right;">testfield:</td><td class="cell c1 lastcol">[[testfield|testfieldBehaviour]]</td></tr><tr class="r1 lastrow"><td class="cell c0 lastcol" colspan="2">##edit##  ##delete##</td></tr></tbody></table></div> |
@javascript   
Scenario: Check if the required field works
    Given I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Datalynx Test Instance"
    And I folow "Manage"
    And I folow "Views"
    And I update the edit template of "testview"
    And I follow "Browse"
    And I follow "Add a new entry"
    Then I should see "New entry"
    And I should see "testfield"
    Then I press "Save changes"
    And I should see "must supply a value here"
    
    