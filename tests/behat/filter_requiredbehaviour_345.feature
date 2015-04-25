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
      |name               |description | visibleto                      |  editableto                     | required |
      |testfieldBehaviour |            | {}                             |  {}                             | 1        |
    And "Datalynx Test Instance" has following fields:
      |type             |name      |visible | edits |
      |text             |testfield |  2     | -1    |
    And "Datalynx Test Instance" has following views:
      |type    |name     |status        |redirect |param2                                                        |
      |grid    |testview |default, edit |testview |                                                              |
      
      
@javascript   
Scenario: Check if the required field works
    Given I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    Then I should see "New entry"
    And I should see "testfield"
    Then I press "Save changes"
    And I should see "must supply a value here"
    
    