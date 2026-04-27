@mod @mod_datalynx @fieldgroups
Feature: Basic fieldgroup entry flow with repeated checkbox rows
  In order to enter grouped checkbox data
  As a teacher
  I need the Add Fieldgroup button to reveal extra rows and keep working when fieldgroup requirements change.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | course | idnumber | name                   |
      | datalynx | C1     | 12345    | Datalynx Test Instance |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I add to the "Datalynx Test Instance" datalynx the following fields:
      | type     | name              | description | param1 | param2 | param3   |
      | checkbox | Checkbox letters  |             | A,B,C  |        | New line |
      | checkbox | Checkbox numbers  |             | 1,2,3  |        | New line |
    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | Gridview |
      | description | Testgrid |
    And I follow "Set as default view"
    And I follow "Set as edit view"

  @javascript
  Scenario: Add and edit entries with a basic checkbox fieldgroup
    When I follow "Fields"
    And I select "Fieldgroup" from the "type" singleselect
    Then I should see "Fieldgroupfields"
    When I set the following fields to these values:
      | Name        | Fieldgroup |
      | Description | Basic fieldgroup test |
      | param2      | 3 |
      | param3      | 1 |
      | param4      | 1 |
    And I select "Datalynx field Checkbox letters, Datalynx field Checkbox numbers" in the datalynx fieldgroup fields selector
    And I press "Save changes"
    Then I should see "Fieldgroup"
    When I follow "Views"
    And I click on "Edit Gridview" "link"
    And I click on "Entry template" "link"
    And I set the "id_eparam2_editor" editor to "[[Fieldgroup]] ##edit## ##delete##"
    And I press "Save changes"
    And I follow "Browse"

    When I follow "Add a new entry"
    And I click on "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='1']//div[@data-field-name='Datalynx field Checkbox letters']//label[normalize-space()='A']" "xpath_element"
    And I click on "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='1']//div[@data-field-name='Datalynx field Checkbox numbers']//label[normalize-space()='1']" "xpath_element"
    And I press "Add Fieldgroup"
    And I click on "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='2']//div[@data-field-name='Datalynx field Checkbox letters']//label[normalize-space()='B']" "xpath_element"
    And I click on "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='2']//div[@data-field-name='Datalynx field Checkbox numbers']//label[normalize-space()='2']" "xpath_element"
    And I press "Save changes"
    Then I should see "updated"
    And I press "Continue"
    And I should see "A"
    And I should see "B"
    And I should see "2"
    And I click on "(//a/i[@title='Edit'])[1]" "xpath_element"
    And I click on "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='2']//div[@data-field-name='Datalynx field Checkbox letters']//label[normalize-space()='C']" "xpath_element"
    And I press "Save changes"
    Then I should see "updated"
    And I press "Continue"
    And I should see "C"

    When I follow "Add a new entry"
    And I click on "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='1']//div[@data-field-name='Datalynx field Checkbox letters']//label[normalize-space()='C']" "xpath_element"
    And I click on "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='1']//div[@data-field-name='Datalynx field Checkbox numbers']//label[normalize-space()='1']" "xpath_element"
    And I press "Add Fieldgroup"
    And I click on "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='2']//div[@data-field-name='Datalynx field Checkbox letters']//label[normalize-space()='A']" "xpath_element"
    And I click on "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='2']//div[@data-field-name='Datalynx field Checkbox numbers']//label[normalize-space()='2']" "xpath_element"
    And I press "Add Fieldgroup"
    And I click on "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='3']//div[@data-field-name='Datalynx field Checkbox letters']//label[normalize-space()='B']" "xpath_element"
    And I click on "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='3']//div[@data-field-name='Datalynx field Checkbox numbers']//label[normalize-space()='3']" "xpath_element"
    And I should not see "Add Fieldgroup"
    And I click on "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='3']//button[@data-removeline='3']" "xpath_element"
    And I should see "Add Fieldgroup"
    And I press "Add Fieldgroup"
    And I click on "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='3']//div[@data-field-name='Datalynx field Checkbox letters']//label[normalize-space()='B']" "xpath_element"
    And I click on "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='3']//div[@data-field-name='Datalynx field Checkbox numbers']//label[normalize-space()='3']" "xpath_element"
    And I should not see "Add Fieldgroup"
    And I press "Save changes"
    Then I should see "updated"
    And I press "Continue"
    And I should see "3"

    When I follow the datalynx "Manage" link
    And I follow "Fields"
    And I follow "Fieldgroups"
    And I follow "Fieldgroup"
    And I set the field "param4" to "2"
    And I press "Save changes"
    And I follow "Browse"
    And I click on "(//a/i[@title='Edit'])[2]" "xpath_element"
    And I click on "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='1']//button[@data-removeline='1']" "xpath_element"
    And the field with xpath "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='1']//div[@data-field-name='Datalynx field Checkbox letters']//label[normalize-space()='A']/input[@type='checkbox']" matches value "1"
    And the field with xpath "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='1']//div[@data-field-name='Datalynx field Checkbox numbers']//label[normalize-space()='2']/input[@type='checkbox']" matches value "1"
    And the field with xpath "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='2']//div[@data-field-name='Datalynx field Checkbox letters']//label[normalize-space()='B']/input[@type='checkbox']" matches value "1"
    And the field with xpath "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='2']//div[@data-field-name='Datalynx field Checkbox numbers']//label[normalize-space()='3']/input[@type='checkbox']" matches value "1"
    And "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='3']" "xpath_element" should not be visible
    And I should see "Add Fieldgroup"
    And I press "Save changes"
    Then I should see "updated"
    And I press "Continue"
    And I click on "(//a/i[@title='Edit'])[2]" "xpath_element"
    And the field with xpath "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='1']//div[@data-field-name='Datalynx field Checkbox letters']//label[normalize-space()='A']/input[@type='checkbox']" matches value "1"
    And the field with xpath "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='1']//div[@data-field-name='Datalynx field Checkbox numbers']//label[normalize-space()='2']/input[@type='checkbox']" matches value "1"
    And the field with xpath "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='2']//div[@data-field-name='Datalynx field Checkbox letters']//label[normalize-space()='B']/input[@type='checkbox']" matches value "1"
    And the field with xpath "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='2']//div[@data-field-name='Datalynx field Checkbox numbers']//label[normalize-space()='3']/input[@type='checkbox']" matches value "1"
    And "//form[contains(@class,'mform')]//div[@data-field-type='fieldgroup']//div[contains(@class,'lines') and @data-line='3']" "xpath_element" should not be visible
