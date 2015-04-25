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
      | name        | visible | customsearch                                                                     |
      | RadioFilter | 1       | a:1:{i:1;a:1:{s:3:"AND";a:1:{i:0;a:3:{i:0;s:0:"";i:1;s:4:"USER";i:2;s:1:"3";}}}} |
      | CheckFilter | 1       | a:1:{i:1;a:1:{s:3:"AND";a:1:{i:0;a:3:{i:0;s:0:"";i:1;s:4:"USER";i:2;s:1:"3";}}}} |
    And "Datalynx Test Instance" has following views:
      | type    | name         | status  | redirect     | filter |    
      | grid    | Default view | default | Default view |        |
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
    
    
    
  Scenario: Check if filter works for checkboxes