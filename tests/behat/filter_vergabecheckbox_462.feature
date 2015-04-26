@mod @mod_datalynx @wip7 @mod_peter @mink:selenium2
Feature: 

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
      | activity | course | idnumber | name                   |
      | datalynx | C1     | 12345    | Datalynx Test Instance |
    And "Datalynx Test Instance" has following fields:
      | type        | name    |
      | text        | RecordF |
    And "Datalynx Test Instance" has following filters:
      | name                 | visible | customsearch                                                                              |
      | StatusNotFinalFilter | 1       | a:1:{s:6:"status";a:1:{s:3:"AND";a:1:{i:0;a:3:{i:0;s:3:"NOT";i:1;s:1:"=";i:2;s:1:"2";}}}} |
      | StatusIsFinalFilter  | 1       | a:1:{s:6:"status";a:1:{s:3:"AND";a:1:{i:0;a:3:{i:0;s:0:"";i:1;s:1:"=";i:2;s:1:"2";}}}}    |
      | StatusNotSetFilter   | 1       |                                                                                           |
    And "Datalynx Test Instance" has following views:
      | type    | name            | status  | redirect    | filter               |
      | grid    | DefaultView     | default | DefaultView |                      |
      | grid    | StatusNotView   |         | DefaultView | StatusNotFinalFilter |
      | grid    | StatusIsView    |         | DefaultView | StatusIsFinalFilter  |
      | grid    | StatusSetView   |         | DefaultView | StatusNotSetFilter   |
   And "Datalynx Test Instance" has following entries:
      | author   | RecordF | status   | approved |
      | teacher1 | entry1  | 1        | 1        |
      | teacher1 | entry2  | 1        | 1        |
      | teacher1 | entry3  | 2        | 1        |
      | teacher1 | entry4  | 2        | 1        |
      | teacher1 | entry5  | 0        | 1        |
      
      
  Scenario:
    Given I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Datalynx Test Instance"
    And I follow "Manage"
    And I follow "Views"
  