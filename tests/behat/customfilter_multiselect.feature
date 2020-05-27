@mod @mod_datalynx @customfilter
Feature: Create entry, add multiselect and use customfilter
  In order to create a new entry
  As a teacher
  I need to add a new entry to the datalynx instance.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1        | 0        | 1         |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activities" exist:
      | activity | course | idnumber | name                   |
      | datalynx | C1     | 12345    | Datalynx Test Instance |

    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I add to the "Datalynx Test Instance" datalynx the following fields:
      | type             | name                | description | param1                     | param2   | param3 |
      | text             | Text                |             |                            |          |        |
      | multiselect      | Multiselect         |             | Option 1,Option 2,Option 3 | Option 1 |        |
    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | Gridview |
      | description | Testgrid |
    And I follow "Set as default view"
    And I follow "Set as edit view"
    # TOOD: Add customfilter.

  @javascript
  Scenario: Add three multiselects to this instance
    When I follow "Browse"
    And I follow "Add a new entry"
    Then I should see "Multiselect"
    And I fill in the entry form fields
      | type             | name               | value                |
      | text             | Text               | testtext1            |
      | multiselect      | Multiselect        | Option 1             |
    And I press "Save changes"
    And I press "Continue"

    And I follow "Add a new entry"
    Then I should see "Multiselect"
    And I fill in the entry form fields
      | type             | name               | value                |
      | text             | Text               | testtext2            |
      | multiselect      | Multiselect        | Option 2             |
    And I press "Save changes"
    And I press "Continue"

    And I follow "Add a new entry"
    Then I should see "Multiselect"
    And I fill in the entry form fields
      | type             | name               | value                |
      | text             | Text               | testtext3            |
      | multiselect      | Multiselect        | Option 3             |
    And I press "Save changes"
    And I press "Continue"

    Then I should see "testtext3"
    And I should see "Option 3"
    And I should not see "Option 4"

    # TODO: Use customfilter.
