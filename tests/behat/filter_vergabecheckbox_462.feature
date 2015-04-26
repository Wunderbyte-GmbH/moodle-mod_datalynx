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
      | manager1  | C1      | manager         |
    And the following "activities" exist:
      | activity | course | idnumber | name                   |
      | datalynx | C1     | 12345    | Datalynx Test Instance |
    And "Datalynx Test Instance" has following fields:
      | type        | name               | param1                |
      | text        | Thema              |                       |
      | textarea    | Themenbeschreibung |                       |
      | checkbox    | Vergabestatus      | Vergabe abgeschlossen |
    And "Datalynx Test Instance" has following filters:
      | name                 | visible | customsearch                                                                                  |
      | FilterCBNotAny       | 1       |                          cb is not any check                                                  |
      | FilterCBNotExactly   | 1       |                          cb is not excatly check                                              |
      | FilterCBNotAll       | 1       |                          cb is not allcheck                                                   |
    And "Datalynx Test Instance" has following views:
      | type    | name                    | status  | redirect                | filter | section | param2  | visible |
      | grid    | Übersicht               | default | Übersicht               |        | no cb   |  no cb  | 7       |
      | grid    | Eintrag anlegen         | edit    | Übersicht               |        |  no cb  |  no cb  | 7       | 
      | tabular | Vergabestatus Übersicht |         | Vergabestatus Übersicht |        | all+tag | all+tag | 1       |
   And "Datalynx Test Instance" has following entries:
      | author   | approved | Thema   | Themenbeschreibung            |
      | teacher1 | 1        | Thema_1 | Die Beschreibung Nummer Eins. |
      | teacher1 | 1        | Thema_2 | Die Beschreibung Nummer Zwei. |
      | teacher1 | 1        | Thema_3 | Die Beschreibung Nummer Drei. |
      
      
  Scenario:
    Given I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Datalynx Test Instance"
    And I follow "Manage"
    And I follow "Views"
  