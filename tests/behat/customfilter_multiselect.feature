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
      | name | Gridview |
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
    Then I should see "added"
    And I click on "Duplicate" "link" in the "mycustomfilter" "table_row"
    And I press "Continue"
    Then "Copy of mycustomfilter" "table_row" should exist
    And I click on "Delete" "link" in the "Copy of mycustomfilter" "table_row"
    And I press "Continue"
    Then "Copy of mycustomfilter" "table_row" should not exist

    # Make customfilter visible in view.
    And I follow "Views"
    And I click on "Edit Gridview" "link"
    And I click on "View template" "link"
    And I set the "id_esection_editor" editor to " ##pagingbar## ##addnewentry## ##customfilter:mycustomfilter## ##entries## "

    # Add ##duplicate## to entry template
    And I click on "Entry template" "link"
    And I set the "id_eparam2_editor" editor to "[[Datalynx field Text]] [[Datalynx field Select (multiple)]] ##duplicate## ##edit## ##delete##"
    And I press "Save changes"

    # Add some entries.
    When I follow "Browse"
    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type             | name               | value                |
      | text             | Text               | testtext2            |
    And I open the autocomplete suggestions list
    And I click on "Opt1" item in the autocomplete list
    And I press "Save changes"
    And I press "Continue"
    And I should see "Opt1"

    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type             | name               | value                |
      | text             | Text               | testtext3            |
    And I open the autocomplete suggestions list
    And I click on "Opt2" item in the autocomplete list
    And I click on "Opt3" item in the autocomplete list
    And I press "Save changes"
    And I press "Continue"
    And I should see "Opt1"
    And I should see "Opt2"
    And I should see "Opt3"

    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type             | name               | value                |
      | text             | Text               | testtext4            |
    And I open the autocomplete suggestions list
    And I click on "Opt4" item in the autocomplete list
    And I press "Save changes"
    And I press "Continue"
    Then I should see "testtext3"
    And I should see "Opt1"
    And I should see "Opt2"
    And I should see "Opt3"
    And I should see "Opt4"
    And I should not see "Opt5"

    And I follow "Add a new entry"
    And I fill in the entry form fields
      | type             | name               | value                |
      | text             | Text               | testtext5            |
    And I open the autocomplete suggestions list
    And I click on "Opt1" item in the autocomplete list
    And I click on "Opt5" item in the autocomplete list
    And I press "Save changes"
    And I press "Continue"

  @javascript
  Scenario: Use customfilter.
    When I follow "Search"
    And I open the autocomplete suggestions list
    Then "Opt2" "autocomplete_suggestions" should exist
    And I click on "Opt2" item in the autocomplete list
    And I close the autocomplete suggestions list
    And I press the escape key
    And I press "id_customsearch"
    And I should see "Opt2"
    And I should see "Opt3"
    And I should not see "Opt1"
    And I should not see "Opt4"
    And I should not see "Opt5"

    # Use customfilter to select Opt1 OR Opt2.
    And I open the autocomplete suggestions list
    And I click on "Opt1" item in the autocomplete list
    And I close the autocomplete suggestions list
    And I press the escape key
    And I press "id_customsearch"
    And I should see "Opt1"
    And I should see "Opt2"
    And I should see "Opt3"
    And I should see "testtext3"
    And I should see "testtext2"
    And I should not see "Opt4"

    # Use customfilter to select Opt1 AND Opt5 after deselecting Opt2.
    And I click on "//span[@data-value = '2']" "xpath_element"
    And I open the autocomplete suggestions list
    And I click on "Opt5" item in the autocomplete list
    And I close the autocomplete suggestions list
    And I press the escape key
    And I click on "All selected options have to be part of the entry" "checkbox"
    And I press "id_customsearch"
    And I should see "Opt1"
    And I should see "Opt5"
    And I should not see "Opt2"
    And I should not see "testtext2"
    And I should not see "testtext3"
    And I should not see "testtext4"

    # Reopen customfilter and add fulltextsearch.
    When I follow "Reset filters"
    Then I wait until the page is ready
    Then I should see "testtext4"
    And I click on "Manage" "link" in the "region-main" "region"
    And I follow "Custom Filters"
    And I click on "mycustomfilter" "link"
    # NOTE: name|label did not work.
    And I click on "//input[@id = 'id_fulltextsearch']" "xpath_element"
    And I press "Save changes"

    # Look for Opt2 and testtext3.
    When I follow "Browse"
    And I follow "Search"
    Then I add to "Search" editor the text "testtext3"
    And I open the autocomplete suggestions list
    And I click on "Opt2" item in the autocomplete list
    And I close the autocomplete suggestions list
    And I press the escape key
    And I press "id_customsearch"
    And I should see "Opt2"
    And I should see "testtext3"
    And I should see "Opt3"
    And I should not see "testtext4"

    # Perform duplication 3 times
    When I follow "Reset filters"
    And I follow "Search"
    Then I add to "Search" editor the text "testtext3"
    And I press "Search"
    And I click on "Duplicate" "link"
    And I press "Continue"
    Then I should see "1 entry(s) duplicated"
    And I press "Continue"

    And I follow "Search"
    Then I add to "Search" editor the text "testtext2"
    And I press "Search"
    And I click on "Duplicate" "link"
    And I press "Continue"
    Then I should see "1 entry(s) duplicated"
    And I press "Continue"

    And I follow "Search"
    Then I add to "Search" editor the text "testtext4"
    And I press "Search"
    And I click on "Duplicate" "link"
    And I press "Continue"
    Then I should see "1 entry(s) duplicated"
    And I press "Continue"

    # Now test the paging bar with the customfilter.
    And I follow the datalynx "Manage" link
    And I follow "View Filters"
    And I click on "Add a filter" "link"
    When I set the following fields to these values:
      | name | perpagefilter |
      | perpage   | 2 |
    And I press "Save changes"
    And I follow "Views"
    And I select "perpagefilter" from the "fid" singleselect
    And I follow "Browse"
    And I click on "2" "link"
    Then I should see "testtext4"
    And I should see "testtext5"
    And I click on "3" "link"
    Then I should see "testtext2"

  @javascript
  Scenario: Search a date range and sort by time created, select and text using hardcoded timestamps
    # Add a single-select field so we can search/sort by a "select" value.
    And I am on "Course 1" course homepage
    And I add to the "Datalynx Test Instance" datalynx the following fields:
      | type   | name   | description | param1            | param2 | param3 |
      | select | Choice |             | Xenon,Yttrium,Zinc |        |        |

    # Reconfigure the existing customfilter (referenced by the view as ##customfilter:mycustomfilter##)
    # to expose time created / time modified (searchable + sortable) and the Text / Choice fields.
    And the "Datalynx Test Instance" datalynx has the customfilter "mycustomfilter" with:
      | timecreated          | 1           |
      | timecreatedsortable  | 1           |
      | timemodified         | 1           |
      | timemodifiedsortable | 1           |
      | searchfields         | Text,Choice |
      | sortfields           | Text,Choice |

    # Tag the searchable/sortable fields in the entry template. Field search indexes follow tag order:
    # Text = 1, Choice = 2, timecreated = 3, timemodified = 4.
    And the "Gridview" view of "Datalynx Test Instance" datalynx has the entry template "<span class='dltest'>[[Datalynx field Text]]</span> [[Datalynx field Choice]] ##timecreated## ##timemodified## ##edit## ##delete##"

    # Hardcoded timestamps keep the date-range search independent of the current date.
    #   mike : created 2020-01-01, modified 2020-03-01
    #   alpha: created 2021-01-01, modified 2021-03-01
    #   zulu : created 2022-01-01, modified 2022-03-01
    And the "Datalynx Test Instance" datalynx has the following entries:
      | user     | Text  | Choice  | timecreated | timemodified |
      | student1 | mike  | Zinc    | 1577836800  | 1583020800   |
      | student2 | alpha | Xenon   | 1609459200  | 1614556800   |
      | teacher1 | zulu  | Yttrium | 1640995200  | 1646092800   |

    When I follow "Browse"

    # Date range on time created: 2019-01-01 .. 2020-06-30 matches only "mike".
    And I follow "Search"
    And I set the field "f_3_timecreated_from[enabled]" to "1"
    And I set the field "f_3_timecreated_to[enabled]" to "1"
    And I set the following fields to these values:
      | f_3_timecreated_from[day]   | 1       |
      | f_3_timecreated_from[month] | January |
      | f_3_timecreated_from[year]  | 2019    |
      | f_3_timecreated_to[day]     | 30      |
      | f_3_timecreated_to[month]   | June    |
      | f_3_timecreated_to[year]    | 2020    |
    And I press "id_customsearch"
    Then I should see "mike"
    And I should not see "alpha"
    And I should not see "zulu"

    # Date range on time modified: 2022-01-01 .. 2022-06-30 matches only "zulu".
    When I follow "Reset filters"
    And I follow "Search"
    And I set the field "f_4_timemodified_from[enabled]" to "1"
    And I set the field "f_4_timemodified_to[enabled]" to "1"
    And I set the following fields to these values:
      | f_4_timemodified_from[day]   | 1       |
      | f_4_timemodified_from[month] | January |
      | f_4_timemodified_from[year]  | 2022    |
      | f_4_timemodified_to[day]     | 30      |
      | f_4_timemodified_to[month]   | June    |
      | f_4_timemodified_to[year]    | 2022    |
    And I press "id_customsearch"
    Then I should see "zulu"
    And I should not see "mike"
    And I should not see "alpha"

    # Sort by Text ascending: alpha, mike, zulu.
    When I follow "Reset filters"
    And I follow "Search"
    And I set the field "customfiltersortfield" to "Datalynx field Text"
    And I set the field "customfiltersortdirection" to "Ascending"
    And I press "id_customsearch"
    Then "//span[@class='dltest'][contains(., 'alpha')]" "xpath_element" should appear before "//span[@class='dltest'][contains(., 'mike')]" "xpath_element"
    And "//span[@class='dltest'][contains(., 'mike')]" "xpath_element" should appear before "//span[@class='dltest'][contains(., 'zulu')]" "xpath_element"

    # Sort by time created ascending: mike (2020), alpha (2021), zulu (2022).
    When I follow "Reset filters"
    And I follow "Search"
    And I set the field "customfiltersortfield" to "Time created"
    And I set the field "customfiltersortdirection" to "Ascending"
    And I press "id_customsearch"
    Then "//span[@class='dltest'][contains(., 'mike')]" "xpath_element" should appear before "//span[@class='dltest'][contains(., 'alpha')]" "xpath_element"
    And "//span[@class='dltest'][contains(., 'alpha')]" "xpath_element" should appear before "//span[@class='dltest'][contains(., 'zulu')]" "xpath_element"

    # Sort by Choice ascending (Xenon=1, Yttrium=2, Zinc=3): alpha, zulu, mike.
    When I follow "Reset filters"
    And I follow "Search"
    And I set the field "customfiltersortfield" to "Datalynx field Choice"
    And I set the field "customfiltersortdirection" to "Ascending"
    And I press "id_customsearch"
    Then "//span[@class='dltest'][contains(., 'alpha')]" "xpath_element" should appear before "//span[@class='dltest'][contains(., 'zulu')]" "xpath_element"
    And "//span[@class='dltest'][contains(., 'zulu')]" "xpath_element" should appear before "//span[@class='dltest'][contains(., 'mike')]" "xpath_element"
