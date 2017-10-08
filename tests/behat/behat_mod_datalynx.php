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
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including
// /config.php.
require_once(__DIR__ . '/../../../../lib/behat/behat_files.php');
require_once(__DIR__ . '/../../mod_class.php');

use Behat\Gherkin\Node\TableNode as TableNode;

/**
 * Datalynx-related steps definitions.
 *
 * @package mod_datalynx
 * @category test
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_datalynx extends behat_files {


    /**
     * Sets up fields for the given datalynx instance.
     * Optional, but must be used after instance declaration.
     *
     * @Given /^"(?P<activityname_string>(?:[^"]|\\")*)" has following fields:$/
     *
     * @param string $activityname
     * @param TableNode $table
     */
    public function has_following_fields($activityname, TableNode $table) {
        $fields = $table->getHash();

        $instance = $this->get_instance_by_name($activityname);
        foreach ($fields as $field) {
            $field['dataid'] = $instance->id;
            $this->create_field($field);
        }
    }

    /**
     * Sets up filters for the given datalynx instance.
     * Optional, but must be used after field setup.
     *
     * @Given /^"(?P<activityname_string>(?:[^"]|\\")*)" has following filters:$/
     *
     * @param string $activityname
     * @param TableNode $table
     */
    public function has_following_filters($activityname, TableNode $table) {
        $filters = $table->getHash();

        $instance = $this->get_instance_by_name($activityname);
        foreach ($filters as $filter) {
            $filter['dataid'] = $instance->id;
            $this->create_filter($filter);
        }
    }

    /**
     * Sets up filters for the given datalynx instance.
     * Optional, but must be called after field and filter setup.
     *
     * @Given /^"(?P<activityname_string>(?:[^"]|\\")*)" has following views:$/
     *
     * @param string $activityname
     * @param TableNode $table
     */
    public function has_following_views($activityname, TableNode $table) {
        $views = $table->getHash();

        $instance = $this->get_instance_by_name($activityname);
        $names = array();
        $newviews = array();
        foreach ($views as $view) {
            $view['dataid'] = $instance->id;
            $statuses = explode(',', isset($view['status']) ? $view['status'] : '');
            $options = array();
            foreach ($statuses as $status) {
                $options[trim($status)] = true;
            }

            $view['id'] = $this->create_view($view, $options);
            $names[$view['name']] = $view['id'];
            $newviews[] = $view;
        }

        $this->map_view_names_for_redirect($newviews, $names);
    }

    private function get_instance_by_name($name) {
        global $DB;
        return $DB->get_record('datalynx', array('name' => $name));
    }

    private function create_field($record = null) {
        global $DB;

        $record = (object) (array) $record;

        $defaults = array('description' => '', 'visible' => 2, 'edits' => -1, 'label' => null,
                'param1' => null,
                'param2' => ($record->type == "teammemberselect") ? "[1,2,3,4,5,6,7,8]" : null,
                'param3' => null, 'param4' => ($record->type == "teammemberselect") ? "4" : null,
                'param5' => null, 'param6' => null, 'param7' => null, 'param8' => null, 'param9' => null,
                'param10' => null);

        foreach ($defaults as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }

        if (isset($record->param1) && $record->type == "select") {
            $record->param1 = preg_replace('/,[ ]?/', "\n", $record->param1);
        }

        if (!isset($record->param2) && ($record->type == "file" || $record->type == "picture")) {
            $record->param2 = -1;
        }

        if (!isset($record->param3) && ($record->type == "file" || $record->type == "picture")) {
            $record->param3 = '*';
        }

        $DB->insert_record('datalynx_fields', $record);
    }

    private function create_view($record = null, array $options = null) {
        global $DB;

        $record = (object) (array) $record;
        $options = (array) $options;

        $defaults = array('description' => '', 'visible' => 7, 'perpage' => '', 'groupby' => null,
                'filter' => 0, 'patterns' => null, 'section' => null, 'sectionpos' => 0,
                'param1' => null, 'param2' => null, 'param3' => ($record->type == "tabular") ? 1 : null,
                'param4' => null, 'param5' => null, 'param6' => null, 'param7' => null, 'param8' => null,
                'param9' => null, 'param10' => null);

        foreach ($defaults as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }

        if ($record->filter) {
            $record->filter = $DB->get_field('datalynx_filters', 'id', array('name' => $record->filter));
        }

        $id = $DB->insert_record('datalynx_views', $record);

        $datalynx = new datalynx($record->dataid);
        $datalynx->process_views('reset', $id, true);

        if ($record->param2) {
            $DB->set_field('datalynx_views', 'param2', $record->param2, array('id' => $id));
        }

        if ($record->section) {
            $DB->set_field('datalynx_views', 'section', $record->section, array('id' => $id));
        }

        if (isset($options['default'])) {
            $DB->set_field('datalynx', 'defaultview', $id, array('id' => $record->dataid));
        }
        if (isset($options['edit'])) {
            $DB->set_field('datalynx', 'singleedit', $id, array('id' => $record->dataid));
        }
        if (isset($options['more'])) {
            $DB->set_field('datalynx', 'singleview', $id, array('id' => $record->dataid));
        }

        return $id;
    }

    private function create_filter($record = null) {
        global $DB;

        $record = (object) (array) $record;

        $defaults = array('description' => '', 'visible' => 1, 'perpage' => 0, 'selection' => 0,
                'groupby' => null, 'search' => null, 'customsort' => null, 'customsearch' => null);

        foreach ($defaults as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }

        $DB->insert_record('datalynx_filters', $record);
    }

    private function map_view_names_for_redirect($views, $names) {
        global $DB;
        foreach ($views as $view) {
            $DB->set_field('datalynx_views', 'param10', $names[$view['redirect']],
                    array('id' => $view['id']));
        }
    }

    /**
     * Selects entry for editing
     *
     * @Given /^I edit "(?P<entrynumber_string>(?:[^"]|\\")*)" entry$/
     *
     * @param string $entrynumber
     */
    public function i_edit_entry($entrynumber) {
        switch ($this->escape($entrynumber)) {
            case "first":
            case "1st":
                $number = 1;
                break;
            case "second":
            case "2nd":
                $number = 2;
                break;
            case "third":
            case "3rd":
                $number = 3;
                break;
            case "fourth":
            case "4th":
                $number = 4;
                break;
            case "fifth":
            case "5th":
                $number = 5;
                break;
            default:
                $number = $this->escape($entrynumber);
                break;
        }

        $session = $this->getSession(); // Get the mink session.
        $element = $session->getPage()->find('xpath', '//div[@class="entry"][' . $number . ']//i[@title="Edit"]//ancestor::a');
        $element->click();
    }

    /**
     * Select a select option.
     *
     * @Given /^I select option "(?P<option_string>(?:[^"]|\\")*)" from the "(?P<select_string>(?:[^"]|\\")*)" select$/

     * @param string $option
     * @param string $select
     */
    public function i_select_option_from_the_select($option, $select) {

        $session = $this->getSession(); // Get the mink session.
        $element = $session->getPage()->find('xpath', './/div[@data-field-name="' . $select . '"]//select[1]');
        $element->selectOption($option);
    }

    /**
     * Select a radio button option.
     *
     * @Given /^I click option "(?P<option_string>(?:[^"]|\\")*)" from a radio$/

     * @param string $option
     */
    public function i_click_option_from_a_radio($option) {

        $session = $this->getSession(); // Get the mink session.
        $element = $session->getPage()->find('xpath',
                '//input[@type="radio"][../following-sibling::*[position()=1][contains(., "' . $option . '")]]');
        $element->click();
    }

    /**
     * Select a checkbox option.
     *
     * @Given /^I click option "(?P<option_string>(?:[^"]|\\")*)" from a checkbox$/

     * @param string $option
     */
    public function i_click_option_from_a_checkbox($option) {

        $session = $this->getSession(); // Get the mink session.
        $element = $session->getPage()->find('xpath',
                '//input[@type="checkbox"]/following::*[contains(text()[normalize-space()], "' . $option . '")]');
        $element->click();
    }

    /**
     * @Given /^I fill entry form with:$/
     *
     * @param TableNode $table
     */
    public function i_fill_entry_form_with(TableNode $table) {
        $session = $this->getSession();
        $entries = array();
        if (in_array('entry', $table->getRow(0))) {
            $data = $table->getHash();
            foreach ($data as $row) {
                if (!isset($entries[$row['entry']])) {
                    $entries[$row['entry']] = array();
                }
                $entries[$row['entry']][$row['field']] = $row['value'];
            }
        } else {
            $entry = $table->getRowsHash();
            unset($entry['field']);
            $entries[1] = $entry;
        }

        foreach ($entries as $number => $entry) {
            $entryelement = $session->getPage()->find('xpath',
                    '//div[@class="entriesview"]/table/tbody/tr[' . $number . ']');

            foreach ($entry as $name => $value) {
                $fieldelement = $entryelement->find('xpath', '//div[@data-field-name="' . $name . '"]');
                $type = $fieldelement->getAttribute('data-field-type');
                $this->fill_data($fieldelement, $type, $value);
            }
        }
    }

    /**
     * @Given /^I select "(?P<entrynumbers_string>(?:[^"]|\\")*)" entry$/
     *
     * @param string $entrynumbers
     */
    public function i_select_entry($entrynumbers) {
        $entrynumbers = explode(',', $entrynumbers);
        foreach ($entrynumbers as $entrynumber) {
            switch ($this->escape($entrynumber)) {
                case "first":
                case "1st":
                    $number = 1;
                    break;
                case "second":
                case "2nd":
                    $number = 2;
                    break;
                case "third":
                case "3rd":
                    $number = 3;
                    break;
                case "fourth":
                case "4th":
                    $number = 4;
                    break;
                case "fifth":
                case "5th":
                    $number = 5;
                    break;
                default:
                    $number = $this->escape($entrynumber);
                    break;
            }
            $session = $this->getSession(); // Get the mink session.
            $element = $session->getPage()->find('xpath',
                    '//div[@class="entriesview"]/table/tbody/tr[' . $number . ']//input[@type="checkbox"]');
            $element->click();
        }
    }

    private function fill_data(Behat\Mink\Element\NodeElement $element, $type, $value) {
        switch ($type) {
            case 'text':
            case 'number':
                $element->find('xpath', '//input[@type="text"]')->setValue($value);
                break;
            case 'url':
                list($url, $alt) = explode(' ', $value, 2);
                list($urlfield, $altfield) = $element->findAll('xpath', '//input[@type="text"]');
                $urlfield->setValue($url);
                $altfield->setValue($alt);
                break;
            case 'textarea': // TODO: account for the editor capability.
                $element->find('xpath', '//textarea')->setValue($value);
                break;
            case 'select':
                $element->find('xpath', '//select')->selectOption($value);
                break;
            case '_approve':
                $checkbox = $element->find('xpath', '//input[@type="checkbox"]');
                if (strtolower($value) == "yes") {
                    $checkbox->check();
                } else {
                    $checkbox->uncheck();
                }
                break;
            case 'checkbox':
                $checkboxes = $element->findAll('xpath', '//input[@type="checkbox"]');
                foreach ($checkboxes as $checkbox) {
                    $checkbox->uncheck();
                }
                foreach (explode(',', $value) as $option) {
                    if ($checkbox = $element->find('xpath',
                        '//input[@type="checkbox"]/following::*[contains(text()[normalize-space()], "' . $option . '")]')) {
                        $checkbox->check();
                    }
                }
                break;
            case 'radiobutton':
                $element->find('xpath',
                        '//input[@type="radio"][../following-sibling::*[position()=1][contains(., "' . $value . '")]]'
                        )->click();
                break;
            case 'duration':
                list($amount, $unit) = explode(' ', $value);
                $element->find('xpath', '//input[@type="text"]')->setValue($amount);
                $element->find('xpath', '//select')->selectOption($unit);
                break;
            case 'time':
                $element->find('xpath', '//input[@type="checkbox"]')->check();
                list($day, $month, $year, $hour, $minute) = preg_split('/[ \.\/:-]+/', $value);
                $dateobj = DateTime::createFromFormat('!m', $month);
                $month = $dateobj->format('F');
                $element->find('xpath', '//select[contains(@name, "day")]')->selectOption($day);
                $element->find('xpath', '//select[contains(@name, "month")]')->selectOption($month);
                $element->find('xpath', '//select[contains(@name, "year")]')->selectOption($year);

                $buffelement = $element->find('xpath', '//select[contains(@name, "hour")]');
                if (is_object($buffelement)) {
                    $buffelement->selectOption($hour);
                }

                $buffelement = $element->find('xpath', '//select[contains(@name, "minute")]');
                if (is_object($buffelement)) {
                    $buffelement->selectOption($minute);
                }
                break;
            case 'teammemberselect':
                $input = $element->find('xpath', '//input[contains(@id,"form_autocomplete_input")]');
                $inputid = $input->getAttribute('id');
                $this->execute("behat_forms::i_set_the_field_to", array($inputid, $value));
                break;
            case 'file':
            case 'picture':
                global $CFG;
                $filemanagernode = $element->find('xpath', '//div[contains(@class, "filemanager")]');
                $this->open_add_file_window($filemanagernode, get_string('pluginname', 'repository_upload'));
                // Ensure all the form is ready.
                // Opening the select repository window and selecting the upload repository.
                $this->open_add_file_window($filemanagernode, get_string('pluginname', 'repository_upload'));
                $this->getSession()->wait(self::TIMEOUT, self::PAGE_READY_JS);

                // Form elements to interact with.
                $file = $this->find_file('repo_upload_file');

                // Attaching specified file to the node.
                // Replace 'admin/' if it is in start of path with $CFG->admin .
                $pos = strpos($value, 'admin/');
                if ($pos === 0) {
                    $value = $CFG->admin . DIRECTORY_SEPARATOR . substr($value, 6);
                }
                $filepath = str_replace('/', DIRECTORY_SEPARATOR, $value);
                $fileabsolutepath = $CFG->dirroot . DIRECTORY_SEPARATOR . $filepath;
                $file->attachFile($fileabsolutepath);

                // Submit the file.
                $submit = $this->find_button(get_string('upload', 'repository'));
                $submit->press();

                // We wait for all the JS to finish as it is performing an action.
                $this->getSession()->wait(self::TIMEOUT, self::PAGE_READY_JS);
                break;
            default:
                break;
        }
    }

    /**
     * Sets up entries for the given datalynx instance.
     * Optional, but must be called after field setup.
     *
     * @Given /^"(?P<activityname_string>(?:[^"]|\\")*)" has following entries:$/
     *
     * @param string $activityname
     * @param TableNode $table
     */
    public function has_following_entries($activityname, TableNode $table) {
        global $DB;
        $entries = $table->getHash();

        $instance = $this->get_instance_by_name($activityname);

        foreach ($entries as $entry) {
            $authorid = $DB->get_field('user', 'id', array('username' => trim($entry['author'])));
            $approved = 0;
            $status = 0;
            if (!empty($entry['approved'])) {
                $approved = trim($entry['approved']);
            }
            if (!empty($entry['status'])) {
                $status = trim($entry['status']);
            }
            $record = array('dataid' => $instance->id, 'userid' => $authorid, 'groupid' => 0,
                'description' => '', 'visible' => 1, 'timecreated' => time(),
                'timemodified' => time(), 'approved' => $approved, 'status' => $status
            );

            $entryid = $DB->insert_record('datalynx_entries', $record);

            foreach ($entry as $fieldname => $value) {
                $field = $DB->get_record('datalynx_fields', array('name' => $fieldname));
                if ($field) {
                    $this->create_content($instance->id, $entryid, $field->id, $field->type, $value);
                }
            }
        }
    }

    private function create_content($dataid, $entryid, $fieldid, $type, $value) {
        global $DB, $CFG;

        $content = array('fieldid' => $fieldid, 'entryid' => $entryid, 'content' => null,
            'content1' => null, 'content2' => null, 'content3' => null, 'content4' => null);

        switch ($type) {
            case 'text':
            case 'number':
            case 'textarea':
                $content['content'] = $value;
                break;
            case 'url':
                list($content['content'], $content['content1']) = explode(' ', $value, 2);
                break;
            case 'select':
            case 'radiobutton':
                $result = $DB->get_record('datalynx_fields', array('id' => $fieldid), 'param1, param3');
                if ($result->param3 == 3) {
                    $pattern = '/, /';
                } else {
                    $pattern = '/[\n\r]+/m';
                }
                $options = preg_split($pattern, $result->param1);
                $id = array_search(trim($value), $options);
                if ($id !== false) {
                    $content['content'] = $id + 1;
                } else {
                    $content['content'] = '';
                }
                break;
            case 'checkbox':
                $result = $DB->get_record('datalynx_fields', array('id' => $fieldid), 'param1, param3');
                if ($result->param3 == 3) {
                    $pattern = '/, /';
                } else {
                    $pattern = '/[\n\r]+/m';
                }
                $options = preg_split($pattern, $result->param1);
                $selectedoptions = preg_split($pattern, $value);
                $ids = array();
                foreach ($selectedoptions as $selectedoption) {
                    $id = array_search($selectedoption, $options);
                    if ($id !== false) {
                        $ids[] = $id + 1;
                    }
                }
                if (!empty($ids)) {
                    $content['content'] = '#' . implode('#', $ids) . '#';
                } else {
                    $content['content'] = '';
                }
                break;
            case 'duration':
                $content['content'] = strtotime($value, 0);
                break;
            case 'time':
                list($day, $month, $year, $hour, $minute) = preg_split('/[ \.\/:-]+/', $value);
                $content['content'] = mktime($hour, $minute, 0, $month, $day, $year);
                break;
            case 'teammemberselect':
                $usernames = preg_split('/,[ ]?/', $value);
                $ids = array();
                foreach ($usernames as $username) {
                    $ids[] = '"' . $DB->get_field('user', 'id', array('username' => $username)) . '"';
                }
                $content['content'] = '[' . implode(',', $ids) . ']';
                break;
            case 'file':
            case 'picture':
                $content['content'] = 1;
                $itemid = $DB->insert_record('datalynx_contents', $content);

                $datalynx = new datalynx($dataid);
                $path = explode(DIRECTORY_SEPARATOR, $value);
                $filename = end($path);
                $fileinfo = array('component' => 'mod_datalynx', 'filearea' => 'content',
                    'itemid' => $itemid, 'contextid' => $datalynx->context->id, 'filepath' => '/',
                    'filename' => $filename
                );
                $fs = get_file_storage();
                $fs->create_file_from_pathname($fileinfo, $CFG->libdir . '/../' . $value);
                $content['content'] = 0;
                break;
            default:
                break;
        }

        if ($content['content']) {
            $DB->insert_record('datalynx_contents', $content);
        }
    }

    /**
     * @Given /^I refresh the Entry template of "([^"]*)"$/
     */
    public function irefreshtheentrytemplateof($arg1) {
        $steps = array(new Given('I click "Edit" button of "' . $arg1 . '" item'),
                new Given('I follow "Entry template"'),
                new Given('I click inside "id_eparam2_editoreditable"'),
                new Given('I set the field "eparam2_editor_field_tag_menu" to ""'),
                new Given('I press "Save changes"'));
        return $steps;
    }

    /**
     * Clicks a control button of a field in the list
     *
     * @Given /^I click "(?P<button_string>(?:[^"]|\\")*)" button of "(?P<fieldname_string>(?:[^"]|\\")*)" item$/
     *
     * @param string $button
     * @param string $fieldname
     */
    public function i_click_button_of_item($button, $fieldname) {
        $session = $this->getSession(); // Get the mink session.
        $element = $session->getPage()->find('xpath',
                '//a[text()="' . $this->escape($fieldname) . '"]/ancestor::tr//a/i[@title="' .
                $this->escape($button) . '"]/ancestor::a');
        $element->click();
    }

    /**
     * @Given /^I click inside "([^"]*)"$/
     */
    public function iclickon($arg1) {
        $session = $this->getSession();
        $element = $session->getPage()->findById($arg1);
        $element->click();
    }
    /**
     * @Given /^"([^"]*)" has following behaviors:$/
     */
    public function hasfollowingbehaviors($arg1, TableNode $table) {
        $behaviors = $table->getHash();

        $instance = $this->get_instance_by_name($arg1);

        foreach ($behaviors as $behavior) {
            $behavior['dataid'] = $instance->id;
            $behavior['id'] = $this->create_behavior($behavior);
        }
    }

    private function create_behavior($record = null) {
        global $DB;

        $record = (object) (array) $record;

        $defaults = array('name' => 'Behavior', 'description' => '', 'visibleto' => '',
                'editableby' => '', 'required' => 0);

        foreach ($defaults as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }

        $id = $DB->insert_record('datalynx_behaviors', $record);

        return $id;
    }
}
