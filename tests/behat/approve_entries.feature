@mod @mod_datalynx @dev @_file_upload
Feature: In datalynx filter approved and not approved entries from multiple students
  In order to view approved and not approved entries
  As teacher
  I need to have a filter searching for approved and not approved entries

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1        | 0        | 1         |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
      | student3 | Student   | 3        | student3@example.com |
      | student4 | Student   | 4        | student4@example.com |
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
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I add to the "Datalynx Test Instance" datalynx the following fields:
      | type             | name               | description | param1                     | param2   | param3 |
      | text             | Text               |             |                            |          |        |
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
    And I follow "Duplicate"
    And I press "Continue"
    And I follow "Copy of notapprovedfilter"
    And I set the field "name" to "approvedfilter"
    And I set the field "f_0_approve" to "1"
    And I press "Save changes"
    Then I should see "approvedfilter"
    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name | Gridview |
      | description | Behat grid |
    And I follow "Set as default view"
    And I follow "Set as edit view"
    And I add to "Datalynx Test Instance" datalynx the view of "Tabular" type with:
      | name | Approved view |
      | description | Approved view |
      | _filter | approvedfilter |
    And I add to "Datalynx Test Instance" datalynx the view of "Tabular" type with:
      | name | Notapproved view |
      | description | Tabular view |
      | _filter | notapprovedfilter |
    And I add to "Datalynx Test Instance" datalynx the view of "Tabular" type with:
      | name | Manage view |
      | description | Manage view |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type             | name               | value                            |
      | text             | Text               | Text of student1                 |
    And I press "Save changes"
    And I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type             | name               | value                            |
      | text             | Text               | Text of student2                 |
    And I press "Save changes"
    And I press "Continue"

  @javascript
  Scenario: Test visibility of approved entries and login as teacher to see approved and not approved entries
    When I should not see "Text of student1"
    Then I should see "Text of student2"
    And I log out
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Manage"
    And I follow "Views"
    And I follow "Manage view"
    And I click on "//td[text()='Text of student1']/following-sibling::td/a[@class='datalynxfield__approve']" "xpath_element"
    Then I wait until "approved" "text" exists
    And I follow "Manage"
    And I follow "Notapproved view"
    Then I should not see "Text of student1"
    But I should see "Text of student2"
    When I select "Approved view" from the "view" singleselect
    Then I should see "Text of student1"
    But I should not see "Text of student2"
    When I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    Then I should see "Text of student2"
    And I should see "Text of student1"
