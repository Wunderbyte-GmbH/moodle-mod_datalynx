<?php
// This file is part of mod_datalynx for Moodle - http://moodle.org/
//
// It is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// It is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Steps definitions related with the datalynx activity.
 *
 * @package mod_datalynx
 * @category test
 * @copyright  2018 David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including
// /config.php.
require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode as TableNode;

/**
 * Steps definition for mod_datalynx
 *
 * @package mod_datalynx
 * @category test
 * @copyright 2018 David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_datalynx extends behat_base {

    /**
     * Sets up fields for the given datalynx instance.
     * Optional, but must be used after instance declaration.
     *
     * @Given /^I add to the "(?P<activityname_string>(?:[^"]|\\")*)" datalynx the following fields:$/
     *
     * @param string $activityname
     * @param TableNode $table
     */
    public function i_add_to_the_datalynx_the_following_fields($activityname, TableNode $table) {

        $this->execute("behat_general::click_link", $this->escape($activityname));
        $this->execute("behat_general::click_link", "Manage");
        $this->execute("behat_general::click_link", "Fields");

        $fields = $table->getHash();
        foreach ($fields as $field) {
            $this->execute("behat_forms::i_select_from_the_singleselect", array($field['name'], 'type'));
            $field['name'] = "Datalynx field {$field['name']}";
            $this->execute("behat_forms::set_field_value", array('name', $field['name']));
            $this->execute("behat_forms::set_field_value", array('description', $field['description']));
            switch ($field['type']) {
                case "checkbox":
                case "radiobutton":
                    $field['addoptions'] = str_replace(',', "\n", $field['param1']);
                    $this->execute("behat_forms::set_field_value", array('addoptions', $field['addoptions']));
                    $field['param2'] ? "New line" : $field['param2'];
                    $field['param3'] ? "No" : $field['param3'];
                    $this->execute("behat_forms::set_field_value", array('param2', $field['param2']));
                    $this->execute("behat_forms::set_field_value", array('param3', $field['param3']));
                    break;
                case "gradeitem":
                    break;
                case "coursegroup":
                    break;
                case "datalynxview":
                    break;
                case "duration":
                    break;
                case "fieldgroup":
                    break;
                case "file":
                    break;
                case "identifier":
                    break;
                case "number":
                    if (!empty($field['param1'])) {
                        $this->execute("behat_forms::set_field_value", array('param1', $field['param1']));
                    }
                    break;
                case "picture":
                    break;
                case "select":
                    $field['addoptions'] = str_replace(',', "\n", $field['param1']);
                    $this->execute("behat_forms::set_field_value", array('addoptions', $field['addoptions']));
                    if (!empty($field['param2'])) {
                        $this->execute("behat_forms::set_field_value", array('param2', $field['param2']));
                    }
                    if (!empty($field['param6'])) {
                        $this->execute("behat_forms::i_select_from_the_singleselect", array('param6', $field['param6']));
                    }
                    if (!empty($field['param4'])) {
                        $this->execute("behat_forms::i_select_from_the_singleselect", array('param4', $field['param4']));
                    }
                    if (!empty($field['param5'])) {
                        $this->execute("behat_forms::i_select_from_the_singleselect", array('param5', $field['param5']));
                    }
                    break;
                case "multiselect":
                    break;
                case "tag":
                    break;
                case "teammemberselect":
                    if (!empty($field['param1'])) {
                        $this->execute("behat_forms::set_field_value", array('param1', $field['param1']));
                    }
                    if (!empty($field['param2'])) {
                        $roles = explode(',', $field['param2']);
                        foreach ($roles as $roleid) {
                            $this->execute("behat_forms::set_field_value", array('param2' . "[{$roleid}]", 1));
                        }
                    }

                    break;
                case "text":
                    if (!empty($field['param1'])) {
                        $this->execute("behat_forms::set_field_value", array('param1', $field['param1']));
                    }
                    if (!empty($field['param8'])) {
                        $this->execute("behat_forms::i_select_from_the_singleselect", array('param8', $field['param8']));
                    }
                    if (!empty($field['param9'])) {
                        $this->execute("behat_forms::i_select_from_the_singleselect", array('param9', $field['param9']));
                    }
                    if (!empty($field['param4'])) {
                        $this->execute("behat_forms::i_select_from_the_singleselect", array('param4', $field['param4']));
                    }
                    if (!empty($field['param5'])) {
                        $this->execute("behat_forms::i_select_from_the_singleselect", array('param5', $field['param5']));
                    }
                    break;
                case "textarea":
                    if (!empty($field['param2'])) {
                        $this->execute("behat_forms::set_field_value", array('param2', $field['param2']));
                    }
                    if (!empty($field['param3'])) {
                        $this->execute("behat_forms::set_field_value", array('param3', $field['param3']));
                    }
                    break;
                case "editor":
                    break;
                case "time":
                    break;
                case "url":
                    break;
                case "userinfo":
                    break;
                default:
                    break;

            }
            $this->execute('behat_forms::press_button', get_string('savechanges'));
        }
    }

    /**
     * Sets up a view for the specified datalynx instance using the specified viewtype.
     *
     * @Given /^I add to "(?P<activityname_string>(?:[^"]|\\")*)" datalynx the view of "(?P<viewtype_string>(?:[^"]|\\")*)" type with:$/
     *
     * @param string $activityname
     * @param string $viewtype
     * @param TableNode $viewformdata
     * @throws coding_exception
     */
    public function i_add_to_datalynx_the_view_of_type_with($activityname, $viewtype, TableNode $viewformdata) {
        $this->execute("behat_general::click_link", $this->escape($activityname));
        $this->execute("behat_general::click_link", $this->escape("Manage"));
        $this->execute("behat_general::click_link", $this->escape("Views"));
        $this->execute("behat_forms::i_select_from_the_singleselect", array($viewtype, 'type'));
        $this->execute("behat_forms::i_set_the_following_fields_to_these_values", $viewformdata);
        $this->execute('behat_forms::press_button', get_string('savechanges'));
    }

    /**
     * Sets the entry form field to the given value.
     *
     * @Given /^I fill in the entry form fields$/
     *
     * @param TableNode $fielddata
     * @throws coding_exception
     * @throws Exception
     */
    public function i_fill_in_the_entry_form_fields(TableNode $fielddata) {
        $fields = $fielddata->getHash();
        foreach ($fields as $field) {
            switch ($field['type']) {
                case "checkbox":
                    $values = explode(',', $field['value']);
                    foreach ($values as $value) {
                        $fieldvalue = substr($value, -1);
                        $field = substr($value, 0, -2);
                        $this->execute("behat_forms::i_set_the_field_to", array($field, $fieldvalue));
                    }
                    break;
                case "select":
                    $this->execute("behat_forms::i_set_the_field_with_xpath_to",
                            array("//div[@data-field-type='select']//select", $field['value']));
                    break;
                case "radio":
                    $this->execute("behat_forms::i_set_the_field_with_xpath_to",
                            array("//div[@data-field-type='radiobutton']//em[.='{$field['value']}']/preceding-sibling::label//input",
                                    "selected"));
                    break;
                case "text":
                case "number":
                    $this->execute("behat_forms::i_set_the_field_with_xpath_to",
                            array("//div[@data-field-name='Datalynx field {$field['name']}']//input", $field['value']));
                    break;
                case "textarea":
                    $this->execute("behat_forms::i_set_the_field_with_xpath_to",
                            array("//div[@data-field-name='Datalynx field {$field['name']}']//textarea", $field['value']));
                    break;
                case "file":
                break;
                case "teammemberselect":
                    $this->execute("behat_forms::i_open_the_autocomplete_suggestions_list");
                    $this->execute("behat_forms::i_click_on_item_in_the_autocomplete_list", $field['value']);
                    break;
                case "duration":
                    $values = explode(" ", $field['value']);
                    $this->execute("behat_forms::i_set_the_field_with_xpath_to",
                            array("//div[@data-field-name='Datalynx field {$field['name']}']//select", $values[1]));
                    $this->execute("behat_forms::i_set_the_field_with_xpath_to",
                            array("//div[@data-field-name='Datalynx field {$field['name']}']//input", $values[0]));
                    break;
                case "time":
                    $values = explode(".", $field['value']);
                    $this->execute("behat_forms::i_set_the_field_with_xpath_to",
                            array("//div[@data-field-name='Datalynx field {$field['name']}']//input", 1));
                    foreach ($values as $key => $value) {
                        $number = $key + 1;
                        $this->execute("behat_forms::i_set_the_field_with_xpath_to",
                                array("(//div[@data-field-name='Datalynx field {$field['name']}']//select)[{$number}]", $value));
                    }
                    break;
            }
        }
    }

    /**
     * Sets the editor to the given value.
     *
     * @Then I add to :fieldlocator editor the text :newvalue
     *
     * @param string $fieldlocator
     * @param string $newvalue
     * @throws coding_exception
     */
    public function i_add_to_editor_the_text($fieldlocator, $newvalue) {
        if (!$this->running_javascript()) {
            throw new coding_exception('Updating text requires javascript.');
        }
        // We delegate to behat_form_field class, it will
        // guess the type properly.
        $field = behat_field_manager::get_form_field_from_label($fieldlocator, $this);

        if (!method_exists($field, 'set_value')) {
            throw new coding_exception('Field does not support the select_text function.');
        }
        $field->set_value($newvalue);
    }
}