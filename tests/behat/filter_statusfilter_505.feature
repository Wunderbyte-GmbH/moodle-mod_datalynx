@mod @mod_datalynx @wip6 @mod_peter @mink:selenium2
Feature: If you have a filter for status tags which are in Final Submission
         It used to show false results
         And The IS NOT SET Filter could not be saved

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
      | name                 | visible | customsearch |
      | StatusNotFinalFilter | 1       |  |
      | StatusIsFinalFilter  | 1       |  |
      | StatusNotSetFilter   | 1       |              |
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
      
@javascript
Scenario: Check if Final Submission Filter ist working
    Given I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Datalynx Test Instance"
    Then I set the field "view" to "StatusNotView"
    And I should see "entry1"
    And I should see "entry2"
    And I should not see "entry3"
    And I should not see "entry4"
    And I should not see "entry5"
    Then I set the field "view" to "StatusIsView"
    And I should not see "entry1"
    And I should not see "entry2"
    And I should see "entry3"
    And I should see "entry4"
    And I should not see "entry5"
    
Scenario: Check if Is Not Set shows correct output and is saveable
    Given I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Datalynx Test Instance"
    Then I follow "Manage"
    And I should see "StatusNotSetFilter"
    Then I follow "StatusNotSetFilter"
    And I set the field "searchandor0" to "AND"
    And I set the field "searchfield0" to "Status"
    And I press "Reload"
    Then I press "Save changes"
    And I should see "StatusNotSetFilter"
    And I follow "Browse"
    Then I set the field "view" to "StatusSetView"
    And I should not see "entry1"
    And I should not see "entry2"
    And I should not see "entry3"
    And I should not see "entry4"
    And I should not see "entry5"
    
    