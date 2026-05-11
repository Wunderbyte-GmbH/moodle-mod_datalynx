@mod @mod_datalynx @javascript
Feature: Datalynx shortcodes render AJAX-loaded entries on the site frontpage
  In order to reuse datalynx views outside the activity page
  As a teacher
  I need the displayview shortcode on the site frontpage to load entries via AJAX.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | idnumber | name                   |
      | datalynx | C1     | 12345    | Datalynx Test Instance |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I add to the "Datalynx Test Instance" datalynx the following fields:
      | type | name  | description | param1 | param2 | param3 |
      | text | Title |             |        |        |        |
    And I add to "Datalynx Test Instance" datalynx the view of "Grid" type with:
      | name        | Gridview   |
      | description | Entry view |
    And I follow "Set as default view"
    And the "Datalynx Test Instance" datalynx has the following entries:
      | user     | Title           |
      | teacher1 | Shortcode entry |
    And I log out

  Scenario: Frontpage shortcode loads grid entries via the AJAX browser
    Given the site frontpage contains the displayview shortcode for "Datalynx Test Instance" datalynx view "Gridview"
    When I log in as "teacher1"
    And I am on site homepage
    And I wait until the page is ready
    Then ".mod-datalynx-grid-view-browser [data-entryid]" "css_element" should exist
    And "[data-region='view-browser-loading']" "css_element" should not exist
    And I should see "Shortcode entry"
