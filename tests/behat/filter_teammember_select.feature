@mod @mod_datalynx @datalynxfield_teammemberselect @datalynxrule_teammemberbyprofile
Feature: Filter datalynx entries where I am a team member of a teammemberselect field
  In order to let team members only see the entries they are assigned to
  As a user
  I need a view filter that matches a teammemberselect field against my own user id

  # Reproduces the real-world bug: the "Add team members by profile match" rule populates a
  # teammemberselect field with a JSON array of INTEGER user ids ([5,7]), while the
  # "I am a teammember" view filter used to match the quoted string form (%"5"%) and so found
  # nothing. The whole setup uses only Moodle core Behat steps: the rule fills the team field,
  # so no autocomplete interaction is needed when creating entries.

  Background:
    Given the following "custom profile fields" exist:
      | datatype | shortname   | name         |
      | text     | managerrole | Manager role |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                | profile_field_managerrole |
      | teacher1 | Teacher   | 1        | teacher1@example.com | Faculty1                  |
      | teacher2 | Teacher   | 2        | teacher2@example.com | Faculty2                  |
      | teacher3 | Teacher   | 3        | teacher3@example.com | Faculty1                  |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | editingteacher |
      | teacher3 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | course | idnumber | name                   |
      | datalynx | C1     | dl1      | Datalynx Test Instance |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage

  @javascript
  Scenario: Each team member only sees the entries whose profile-matched team includes them
    # --- Add a select field (the profile match key) and a teammemberselect field via the UI ---
    Given I follow "Datalynx Test Instance"
    And I click on ".nav-item [title='Manage']" "css_element"
    And I follow "Fields"
    And I select "select" from the "type" singleselect
    And I set the field "Name" to "dept"
    And I set the field "addoptions" to multiline:
      """
      Faculty1
      Faculty2
      """
    And I press "Save changes"
    And I select "teammemberselect" from the "type" singleselect
    And I set the field "Name" to "team"
    And I set the field "Maximum team size" to "5"
    And I set the field "Teacher" to "1"
    And I press "Save changes"
    # --- Add the "Add team members by profile match" rule via the UI ---
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I click on ".nav-item [title='Manage']" "css_element"
    And I follow "Rules"
    And I select "Add team members by profile match" from the "type" singleselect
    And I set the field "name" to "teamrule"
    And I set the field "Enabled" to "1"
    And I set the field "Entry created" to "1"
    And I set the field "Entry updated" to "1"
    And I set the field "Select field" to "dept"
    And I set the field "User profile field" to "Manager role"
    And I set the field "Team member field" to "team"
    And I set the field "Required privilege" to "Teacher"
    And I set the field "Existing team members" to "Overwrite (replace with matched users)"
    And I press "Save changes"
    # --- Create the "I am a teammember" view filter on the team field via the UI ---
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I click on ".nav-item [title='Manage']" "css_element"
    And I click on ".nav-item [title='View Filters']" "css_element"
    And I follow "Add a filter"
    And I set the field "name" to "myteamfilter"
    And I set the field "searchandor0" to "AND"
    And I set the field "searchfield0" to "team"
    And I set the field "searchoperator0" to "I am a teammember"
    And I press "Save changes"
    # --- Add a Tabular view that uses the filter and make it the default and edit view ---
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I click on ".nav-item [title='Manage']" "css_element"
    And I select "Tabular" from the "type" singleselect
    And I set the field "Name" to "teamview"
    And I set the field "Filter" to "myteamfilter"
    And I press "Save changes"
    And I follow "Set as default view"
    And I follow "Set as edit view"
    # --- Create entries; the rule fills the team field with the matching teachers ---
    # alpha: dept=Faculty1 -> team = teacher1 + teacher3 ; beta: dept=Faculty2 -> team = teacher2
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    And I set the field with xpath "//div[@data-field-name='dept']//select" to "Faculty1"
    And I press "Save changes"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    And I follow "Add a new entry"
    And I set the field with xpath "//div[@data-field-name='dept']//select" to "Faculty2"
    And I press "Save changes"
    And I log out

    # Teacher 1 (Faculty1) is a team member of alpha only.
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    Then I should see "Faculty1"
    And I should not see "Faculty2"
    And I log out

    # Teacher 2 (Faculty2) is a team member of beta only.
    When I log in as "teacher2"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    Then I should see "Faculty2"
    And I should not see "Faculty1"
    And I log out

    # Teacher 3 (Faculty1) is a team member of alpha only.
    When I log in as "teacher3"
    And I am on "Course 1" course homepage
    And I follow "Datalynx Test Instance"
    Then I should see "Faculty1"
    And I should not see "Faculty2"
