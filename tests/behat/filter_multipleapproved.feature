@mod @mod_datalynx @dev @_file_upload @wip
Feature: In datalynx filter approved and not approved entries from multiple students
  In order to view approved and not approved entries
  As teacher
  I need to have a filter searching for approved and not approved entries

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1        | 0        | 1         |
    And the following "users" exist:
      | username | firstname | lastname | email                   |
      | teacher1 | Teacher   | 1        | teacher1@mailinator.com |
      | student1 | Student   | 1        | student1@mailinator.com |
      | student2 | Student   | 2        | student2@mailinator.com |
      | student3 | Student   | 3        | student3@mailinator.com |
      | student4 | Student   | 4        | student4@mailinator.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
      | student4 | C1     | student        |
    And the following "activities" exist:
      | activity | course | idnumber | name                   | approval |
      | datalynx | C1     | 12345    | Datalynx Test Instance | 1        |
    And "Datalynx Test Instance" has following fields:
      | type | name | param1 |
      | text | Text |        |
    And "Datalynx Test Instance" has following views:
      | type | name             | status  | redirect      | filter | param2                                                                                                                                     |
      | grid | Approved view    | default | Approved view |        | <div ><table><tbody><tr><td>Text:</td> <td>[[Text]]</td></tr><tr><td>##edit##  ##delete##</td></tr></tbody></table></div>                  |
      | grid | Notapproved view |         | Approved view |        | <div ><table><tbody><tr><td>Text:</td> <td>[[Text]]</td></tr><tr><td>##edit##  ##delete##</td></tr></tbody></table></div>                  |
      | grid | Edit view        | edit    | Approved view |        | <div><table> <tbody><tr> <td>Text:</td><td>[[Text]]</td> </tr> <tr> <td>##edit##  ##delete## ##approve## </td></tr></tbody></table> </div> |
    And "Datalynx Test Instance" has following entries:
      | author   | Text | approved |
      | student1 | yes1 | 1        |
      | student1 | yes2 | 1        |
      | student1 | yes3 | 1        |
      | student1 | not1 | 0        |
      | student3 | not2 | 0        |
      | student2 | yes4 | 1        |
      | student2 | not3 | 0        |

  @javascript
  Scenario: Login as teacher to see approved and not approved entries
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Manage"
    And I follow "Filters"
    And I follow "Add a filter"
    And I set the field "name" to "notapprovedfilter"
    And I set the field "searchandor0" to "AND"
    And I set the field "searchfield0" to "approve"
    And I press "Reload"
    Then I should see "Not approved"
    And I set the field "f_0_approve" to "0"
    And I press "Save changes"
    Then I should see "notapprovedfilter"
    And I should see "&usearch=approve%3AAND%3A%2C%2C0"
    And I follow "Duplicate"
    And I press "Continue"
    And I follow "Copy of notapprovedfilter"
    And I set the field "name" to "approvedfilter"
    And I set the field "f_0_approve" to "1"
    And I press "Save changes"
    Then I should see "approvedfilter"
    And I follow "Views"
    And I follow "Notapproved view"
    And I follow "Edit this view"
    And I set the field "_filter" to "notapprovedfilter"
    And I press "Save changes"
    And I follow "Approved view"
    And I follow "Edit this view"
    And I set the field "_filter" to "approvedfilter"
    And I press "Save changes"
    Then I should see "Add a view"
    When I follow "Notapproved view"
    Then I should see "not1"
    And I should see "not2"
    But I should not see "yes1"
    And I follow "Manage"
    When I follow "Approved view"
    Then I should see "yes1"
    And I should see "yes2"
    But I should not see "not1"