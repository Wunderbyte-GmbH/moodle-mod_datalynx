@mod @mod_datalynx @wip4
Feature: If you have a radiobutton and/or checkbox field assigned to a view
         And the filter is "not any of a is check"
         It should show you all entries where A is not chosen

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
      | type        | name    | param1 |
      | radiobutton | RadioF  | A, B   |
      | checkbox    | CheckF  | A, B   |
      | text        | RecordF |        |
    And "Datalynx Test Instance" has following filters:
      | name        | visible | customsearch                                                                                    |
      | RadioFilter | 1       | a:1:{i:1;a:1:{s:3:"AND";a:1:{i:0;a:3:{i:0;s:3:"NOT";i:1;s:6:"ANY_OF";i:2;a:1:{i:0;s:1:"1";}}}}} |
      | CheckFilter | 1       | a:1:{i:2;a:1:{s:3:"AND";a:1:{i:0;a:3:{i:0;s:3:"NOT";i:1;s:6:"ANY_OF";i:2;a:1:{i:0;s:1:"1";}}}}} |
    And "Datalynx Test Instance" has following views:
      | type    | name        | status  | redirect    | filter      |
      | grid    | DefaultView | default | DefaultView | RadioFilter |    
      | grid    | RadioView   |         | RadioView   | DefaultView |
      | grid    | CheckView   |         | CheckView   | DefaultView |
   And "Datalynx Test Instance" has following entries:
      | author   |CheckF | RadioF | RecordF  | approved |
      | teacher1 | A     |        | checkA   | 1        |
      | teacher1 | B     |        | checkB   | 1        |
      | teacher1 |       |        | nonono   | 1        |
      | teacher1 |       | A      | radioA   | 1        |
      | teacher1 |       | B      | radioB   | 1        |
      | teacher1 | B     | B      | bothB    | 1        |
      | teacher1 | A     | A      | bothA    | 1        |
      | teacher1 | A     | B      | bothAB   | 1        |
      | teacher1 | B     | A      | bothBA   | 1        |

  @javascript
  Scenario: Check if filter works for radiobuttons
    Given I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Datalynx Test Instance"
    And I set the field "view" to "RadioView"
    And I should see "checkA"
    And I should see "checkB"
    And I should see "nonono"
    And I should see "radioB"
    And I should see "bothB"
    And I should see "bothAB"
    But I should not see "radioA"
    But I should not see "bothA"
    But I should not see "bothBA"
    
Scenario: Check if filter works for checkbuttons
    Given I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Datalynx Test Instance"
    And I set the field "view" to "CheckView"
    And I should see "radioA"
    And I should see "checkB"
    And I should see "nonono"
    And I should see "radioB"
    And I should see "bothB"
    And I should see "bothBA"
    But I should not see "checkA"
    But I should not see "bothA"
    But I should not see "bothAB"