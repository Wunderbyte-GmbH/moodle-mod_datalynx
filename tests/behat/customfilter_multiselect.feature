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
      | type             | name                | description | param1                     | param2 | param3 |
      | text             | Text                |             |                            |        |        |
      | multiselect      | Select (multiple)   |             | Opt1,Opt2,Opt3,Opt4,Opt5   |        |        |

    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | Gridview |
      | description | Testgrid |
    And I follow "Set as default view"
    And I follow "Set as edit view"

    # Add customfilter.
    When I follow "Custom Filters"
    And I click on "Add a custom filter" "link"
    When I set the following fields to these values:
      | Name           | mycustomfilter       |
    And I click on "//input[@value = 'Datalynx field Select (multiple)']" "xpath_element"
    And I press "Save changes"

    # Make customfilter visible in view.
    When I follow "Views"
    And I click on "//table/tbody/tr[1]/td[9]/a" "xpath_element"
    Then I should see "Gridview"
    And I click on "View template" "link"
    Then I add to "id_esection_editor" editor the text ". ##addnewentry## ##customfilter:mycustomfilter## ##entries## ."
    And I press "Save changes"

    # Add me some fields.
    When I follow "Browse"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type             | name               | value                |
      | text             | Text               | testtext1            |
    And I open the autocomplete suggestions list
    And I click on "Opt4" item in the autocomplete list
    And I click on "Opt5" item in the autocomplete list
    And I press "Save changes"
    And I should see "1 entry(s) updated"
    Then I press "Continue"

    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type             | name               | value                |
      | text             | Text               | testtext2            |
    And I click on "Opt2" item in the autocomplete list
    And I press "Save changes"
    And I press "Continue"

    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type             | name               | value                |
      | text             | Text               | testtext3            |
    And I click on "Opt3" item in the autocomplete list
    And I press "Save changes"
    And I press "Continue"

    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type             | name               | value                |
      | text             | Text               | testtext4            |
    And I click on "Opt4" item in the autocomplete list
    And I press "Save changes"
    And I press "Continue"
    Then I should see "testtext3"
    And I should see "Opt3"
    And I should not see "Opt6"

  @javascript
  Scenario: Use customfilter.
    When I follow "Search" 
    And I open the autocomplete suggestions list
    Then "Opt2" "autocomplete_suggestions" should exist
    And I click on "Opt2" item in the autocomplete list
    And I close the autocomplete suggestions list
    And I click on "//input[@value = 'Search']" "xpath_element"
    And I should see "Opt2"
    And I should not see "Opt3"
    And I should not see "Opt5"

    # Use customfilter to select Opt1 OR Opt2.
    And I open the autocomplete suggestions list
    And I click on "Opt1" item in the autocomplete list
    And I close the autocomplete suggestions list
    And I click on "//input[@value = 'Search']" "xpath_element"
    And I should see "Opt1"
    And I should see "Opt2"
    And I should see "Opt4"
    And I should see "Opt5"
    And I should not see "Opt3"

    # Use customfilter to select Opt1 AND Opt5 after deselecting Opt2.
    And I click on "//span[@data-value = '2']" "xpath_element"
    And I open the autocomplete suggestions list
    And I click on "Opt5" item in the autocomplete list
    And I close the autocomplete suggestions list
    And I click on "All selected options have to be part of the entry" "checkbox"
    And I click on "//input[@value = 'Search']" "xpath_element"
    And I should not see "Opt2"
    And I should not see "testtext2"

    # Reopen customfilter and add fulltextsearch.
    When I follow "Manage"
    And I follow "Custom Filters"
    And I click on "mycustomfilter" "link"
    # NOTE: name|label did not work.
    And I click on "//input[@id = 'id_fulltextsearch']" "xpath_element"
    And I press "Save changes"

    # Look for Opt1 and testtext1.
    When I follow "Browse"
    And I follow "Search"
    Then I add to "Search" editor the text "testtext1"
    And I open the autocomplete suggestions list
    And I click on "Opt1" item in the autocomplete list
    And I close the autocomplete suggestions list
    And I click on "//input[@value = 'Search']" "xpath_element"
    And I should see "Opt1"
    And I should see "testtext1"
    And I should see "Opt4"
    And I should see "Opt5"
    And I should not see "testtext2"
