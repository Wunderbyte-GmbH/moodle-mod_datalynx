@mod @mod_datalynx @datalynx_rides @javascript
Feature: Ride sharing board with Fahrtenschein
  In order to share rides and bill them
  As a driver and as a passenger
  I need to find, request, approve and bill rides on the board

  Background:
    Given the following config values are set as admin:
      | router   | behat | mod_datalynx |
      | geocoder | behat | mod_datalynx |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "mod_datalynx > ride boards" exist:
      | course | variant       | name           |
      | C1     | fahrtenschein | Mitfahrbörse   |
    And the following "users" exist:
      | username   | firstname | lastname  | email                  | profile_field_iban       |
      | driver1    | Dora      | Driver    | driver1@example.com    | AT61 1904 3002 3457 3201 |
      | passenger1 | Paul      | Passenger | passenger1@example.com |                          |
    And the following "course enrolments" exist:
      | user       | course | role    |
      | driver1    | C1     | student |
      | passenger1 | C1     | student |
    And the following "mod_datalynx > rides" exist:
      | board        | driver  | from | to   | days | seats | detour | photo |
      | Mitfahrbörse | driver1 | wien | graz | 7    | 2     | 20     | 0     |
      | Mitfahrbörse | driver1 | wien | graz | -1   | 0     | 20     | 1     |

  Scenario: Every tile of the start page opens its view, and the back link returns to the start page
    Given I am on the "Mitfahrbörse" "datalynx activity" page logged in as "driver1"
    Then I should see "Gemeinsam fahren statt allein"
    And I follow "Fahrt suchen"
    And I should see "Tragen Sie ein, wo Sie losfahren und wo Sie hinwollen"
    And I follow "← Zurück zur Übersicht"
    And I should see "Gemeinsam fahren statt allein"
    And I follow "Meine Fahrten"
    And I should see "Neue Fahrt eintragen"
    And I follow "← Zurück zur Übersicht"
    And I follow "Fahrten verwalten"
    And I should see "Ihre einzelnen Fahrten"
    And I follow "← Zurück zur Übersicht"
    And I follow "Fahrt abrechnen"
    And I should see "Ihre vergangenen Fahrten"
    And I follow "← Zurück zur Übersicht"
    And I follow "Meine Fahrtenscheine"
    And I should see "Ihre Fahrtenscheine"
    And I follow "← Zurück zur Übersicht"
    And I follow "Fahrtangebot anlegen"
    And I should see "Fahrtangebot · Schritt 1 von 5"
    And I follow "← Zurück zur Übersicht"
    And I should see "Gemeinsam fahren statt allein"

  Scenario: A passenger asks for a seat with a pickup point, the driver approves, the passenger is confirmed
    Given I am on the "Mitfahrbörse" "datalynx activity" page logged in as "passenger1"
    And I follow "Fahrt suchen"
    And I press "Mitfahrt beantragen"
    And I set the field "Where should you be picked up?" to "Baden"
    And I click on "Mitfahrt beantragen" "button" in the ".modal-dialog" "css_element"
    Then I should see "Requested"
    And I log out
    And I am on the "Mitfahrbörse" "datalynx activity" page logged in as "driver1"
    And I follow "Meine Fahrten"
    And I should see "Paul Passenger"
    And I should see "Hauptplatz, 2500 Baden"
    And I press "Approve"
    And I should see "Hauptplatz, 2500 Baden"
    And I should not see "Approve"
    And I log out
    And I am on the "Mitfahrbörse" "datalynx activity" page logged in as "passenger1"
    And I follow "Fahrt suchen"
    And I should see "Confirmed"

  Scenario: A driver bills a past ride and finds the submitted Fahrtenschein under "Meine Fahrtenscheine"
    Given I am on the "Mitfahrbörse" "datalynx activity" page logged in as "driver1"
    And I follow "Fahrt abrechnen"
    And I click on "//a[contains(., 'Edit Entryid')]" "xpath_element"
    And I set the field with xpath "//div[@data-field-name='Anlass']//input" to "Erste-Hilfe-Schulung"
    And I set the field with xpath "//div[@data-field-name='Eigenerklärung']//input[@type='checkbox']" to "1"
    And I set the field with xpath "//input[@type='checkbox'][starts-with(@name, 'field_status_')]" to "1"
    And I press "Fahrtenschein speichern"
    Then I should see "Meine Fahrtenscheine"
    And I should see "Final submission"
    And I should see "Erste-Hilfe-Schulung"
    And I should see "PDF herunterladen" in the ".card-footer" "css_element"
    And I should not see "Bearbeiten" in the ".card-footer" "css_element"
