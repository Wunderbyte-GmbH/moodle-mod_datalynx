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

use Behat\Gherkin\Node\TableNode;

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
        $csstarget = '.nav-item [title="Manage"]';
        $this->execute('behat_general::i_click_on', [$csstarget, 'css_element']);
        $this->execute("behat_general::click_link", "Fields");

        $fields = $table->getHash();
        foreach ($fields as $field) {
            $this->execute("behat_forms::i_select_from_the_singleselect", [$field['type'], 'type']);
            $field['name'] = "Datalynx field {$field['name']}";
            $this->execute("behat_forms::set_field_value", ['name', $field['name']]);
            $this->execute("behat_forms::set_field_value", ['description', $field['description']]);
            switch ($field['type']) {
                case "checkbox":
                case "radiobutton":
                    $field['addoptions'] = str_replace(',', "\n", $field['param1']);
                    $this->execute("behat_forms::set_field_value", ['addoptions', $field['addoptions']]);
                    $field['param2'] ? "New line" : $field['param2'];
                    $field['param3'] ? "No" : $field['param3'];
                    $this->execute("behat_forms::set_field_value", ['param2', $field['param2']]);
                    $this->execute("behat_forms::set_field_value", ['param3', $field['param3']]);
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
                        $this->execute("behat_forms::set_field_value", ['param1', $field['param1']]);
                    }
                    break;
                case "picture":
                    break;
                case "select":
                    $field['addoptions'] = str_replace(',', "\n", $field['param1']);
                    $this->execute("behat_forms::set_field_value", ['addoptions', $field['addoptions']]);
                    if (!empty($field['param2'])) {
                        $this->execute("behat_forms::set_field_value", ['param2', $field['param2']]);
                    }
                    if (!empty($field['param6'])) {
                        $this->execute("behat_forms::i_select_from_the_singleselect", ['param6', $field['param6']]);
                    }
                    if (!empty($field['param4'])) {
                        $this->execute("behat_forms::i_select_from_the_singleselect", ['param4', $field['param4']]);
                    }
                    if (!empty($field['param5'])) {
                        $this->execute("behat_forms::i_select_from_the_singleselect", ['param5', $field['param5']]);
                    }
                    break;
                case "multiselect":
                    $field['addoptions'] = str_replace(',', "\n", $field['param1']);
                    $this->execute("behat_forms::set_field_value", ['addoptions', $field['addoptions']]);
                    $field['param2'] ? "New line" : $field['param2'];
                    $field['param3'] ? "No" : $field['param3'];
                    $this->execute("behat_forms::set_field_value", ['param2', $field['param2']]);
                    $this->execute("behat_forms::set_field_value", ['param3', $field['param3']]);
                    break;
                case "tag":
                    break;
                case "teammemberselect":
                    if (!empty($field['param1'])) {
                        $this->execute("behat_forms::set_field_value", ['param1', $field['param1']]);
                    }
                    if (!empty($field['param2'])) {
                        $roles = explode(',', $field['param2']);
                        foreach ($roles as $roleid) {
                            $this->execute("behat_forms::set_field_value", ['param2' . "[{$roleid}]", 1]);
                        }
                    }

                    break;
                case "text":
                    if (!empty($field['param1'])) {
                        $this->execute("behat_forms::set_field_value", ['param1', $field['param1']]);
                    }
                    if (!empty($field['param8'])) {
                        $this->execute("behat_forms::i_select_from_the_singleselect", ['param8', $field['param8']]);
                    }
                    if (!empty($field['param9'])) {
                        $this->execute("behat_forms::i_select_from_the_singleselect", ['param9', $field['param9']]);
                    }
                    if (!empty($field['param4'])) {
                        $this->execute("behat_forms::i_select_from_the_singleselect", ['param4', $field['param4']]);
                    }
                    if (!empty($field['param5'])) {
                        $this->execute("behat_forms::i_select_from_the_singleselect", ['param5', $field['param5']]);
                    }
                    break;
                case "textarea":
                    if (!empty($field['param2'])) {
                        $this->execute("behat_forms::set_field_value", ['param2', $field['param2']]);
                    }
                    if (!empty($field['param3'])) {
                        $this->execute("behat_forms::set_field_value", ['param3', $field['param3']]);
                    }
                    break;
                case "editor":
                    break;
                case "time":
                    break;
                case "url":
                    break;
                default:
                    break;
            }
            $this->execute('behat_forms::press_button', get_string('savechanges'));
        }
    }

    // phpcs:disable moodle.Files.LineLength
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
        $csstarget = '.nav-item [title="Manage"]';
        $this->execute('behat_general::i_click_on', [$csstarget, 'css_element']);
        $this->execute("behat_forms::i_select_from_the_singleselect", [$viewtype, 'type']);
        $this->execute("behat_forms::i_set_the_following_fields_to_these_values", $viewformdata);
        $this->execute('behat_forms::press_button', get_string('savechanges'));
    }

    /**
     * Creates a report view directly for the specified datalynx instance.
     *
     * @Given /^the "(?P<activityname_string>(?:[^"]|\\")*)" datalynx has the following report view:$/
     *
     * @param string $activityname
     * @param TableNode $viewdata
     */
    public function the_datalynx_has_the_following_report_view($activityname, TableNode $viewdata) {
        global $DB;

        $record = $DB->get_record('datalynx', ['name' => $activityname], '*', MUST_EXIST);
        $dlx = new \mod_datalynx\datalynx($record->id);
        $fields = $dlx->get_fields();
        $normalizefieldname = static function (string $name): string {
            return preg_replace('/^Datalynx field\s+/u', '', trim($name));
        };

        $values = [];
        foreach ($viewdata->getRowsHash() as $key => $value) {
            $values[$key] = $value;
        }

        $countfieldid = 0;
        foreach ($fields as $field) {
            if ($normalizefieldname($field->name()) === $normalizefieldname($values['param1'])) {
                $countfieldid = (int) $field->field->id;
                break;
            }
        }
        if (!$countfieldid) {
            throw new \Exception('Unable to find the report count field "' . $values['param1'] . '".');
        }

        $groupingid = -1;
        if (($values['param4'] ?? '') === get_string('entryauthor', 'datalynxfield_datalynxview')) {
            $groupingid = -1;
        } else {
            foreach ($fields as $field) {
                if ($normalizefieldname($field->name()) === $normalizefieldname($values['param4'])) {
                    $groupingid = (int) $field->field->id;
                    break;
                }
            }
        }

        $period = $values['param2'] ?? '';
        if ($period === get_string('nosums', 'datalynxview_report')) {
            $period = 'nosums';
        } else if ($period === get_string('month')) {
            $period = 'month';
        }

        $DB->insert_record('datalynx_views', (object) [
            'dataid' => $dlx->id(),
            'type' => 'report',
            'name' => $values['name'] ?? get_string('pluginname', 'datalynxview_report'),
            'description' => $values['description'] ?? '',
            'visible' => 7,
            'filter' => 0,
            'perpage' => 0,
            'groupby' => '',
            'section' => '',
            'param1' => $countfieldid,
            'param2' => $period,
            'param3' => 'sumoffield',
            'param4' => $groupingid,
            'param5' => 0,
            'param10' => 0,
        ]);
    }

    /**
     * Opens the specified datalynx view directly.
     *
     * @When /^I open the "(?P<viewname_string>(?:[^"]|\\")*)" view of "(?P<activityname_string>(?:[^"]|\\")*)" datalynx$/
     *
     * @param string $viewname
     * @param string $activityname
     */
    public function i_open_the_view_of_datalynx($viewname, $activityname) {
        global $DB;

        $record = $DB->get_record('datalynx', ['name' => $activityname], '*', MUST_EXIST);
        $view = $DB->get_record('datalynx_views', ['dataid' => $record->id, 'name' => $viewname], '*', MUST_EXIST);
        $this->execute('behat_general::i_visit', ["/mod/datalynx/view.php?d={$record->id}&view={$view->id}"]);
    }

    /**
     * Creates datalynx entries directly for the specified activity.
     *
     * @Given /^the "(?P<activityname_string>(?:[^"]|\\")*)" datalynx has the following entries:$/
     *
     * @param string $activityname
     * @param TableNode $entriesdata
     */
    public function the_datalynx_has_the_following_entries($activityname, TableNode $entriesdata) {
        global $DB;

        $record = $DB->get_record('datalynx', ['name' => $activityname], '*', MUST_EXIST);
        $dlx = new \mod_datalynx\datalynx($record->id);
        $fields = $dlx->get_fields();
        $normalizefieldname = static function (string $name): string {
            return preg_replace('/^Datalynx field\s+/u', '', trim($name));
        };

        $fieldmap = [];
        foreach ($fields as $field) {
            $fieldmap[$normalizefieldname($field->name())] = $field;
        }

        foreach ($entriesdata->getHash() as $entrydata) {
            $user = $DB->get_record('user', ['username' => $entrydata['user']], '*', MUST_EXIST);
            $timestamp = \core\di::get(\core\clock::class)->time();
            $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
                'dataid' => $dlx->id(),
                'userid' => $user->id,
                'groupid' => 0,
                'approved' => 1,
                'status' => 0,
                'timecreated' => $timestamp,
                'timemodified' => $timestamp,
            ]);

            foreach ($entrydata as $fieldname => $value) {
                if ($fieldname === 'user' || $value === '') {
                    continue;
                }
                $normalizedname = $normalizefieldname($fieldname);
                if (empty($fieldmap[$normalizedname])) {
                    continue;
                }

                $field = $fieldmap[$normalizedname];
                $content = $value;
                if ($field->type === 'select' && method_exists($field, 'get_options')) {
                    $optionid = array_search($value, $field->get_options(), true);
                    if ($optionid === false) {
                        throw new \Exception('Unknown select option "' . $value . '" for field "' . $fieldname . '".');
                    }
                    $content = (string) $optionid;
                }

                $DB->insert_record('datalynx_contents', (object) [
                    'fieldid' => $field->field->id,
                    'entryid' => $entryid,
                    'lineid' => 0,
                    'content' => $content,
                ]);
            }
        }
    }

    // phpcs:enable moodle.Files.LineLength

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
                        $this->execute("behat_forms::i_set_the_field_to", [$field, $fieldvalue]);
                    }
                    break;
                case "select":
                    $this->execute(
                        "behat_forms::i_set_the_field_with_xpath_to",
                        ["//div[@data-field-type='select']//select", $field['value']]
                    );
                    break;
                case "multiselect":
                    $this->execute("behat_forms::i_open_the_autocomplete_suggestions_list");
                    $this->execute("behat_forms::i_click_on_item_in_the_autocomplete_list", $field['value']);
                    break;
                case "radio":
                    $this->execute(
                        "behat_forms::i_set_the_field_with_xpath_to",
                        ["//div[@data-field-type='radiobutton']//label[contains(.,'{$field['value']}')]/input",
                        "selected"]
                    );
                    break;
                case "text":
                case "number":
                    $this->execute(
                        "behat_forms::i_set_the_field_with_xpath_to",
                        ["//div[@data-field-name='Datalynx field {$field['name']}']//input", $field['value']]
                    );
                    break;
                case "textarea":
                    $this->execute(
                        "behat_forms::i_set_the_field_with_xpath_to",
                        ["//div[@data-field-name='Datalynx field {$field['name']}']//textarea", $field['value']]
                    );
                    break;
                case "file":
                    break;
                case "teammemberselect":
                    $this->execute("behat_forms::i_open_the_autocomplete_suggestions_list");
                    $this->execute("behat_forms::i_click_on_item_in_the_autocomplete_list", $field['value']);
                    break;
                case "duration":
                    $values = explode(" ", $field['value']);
                    $this->execute(
                        "behat_forms::i_set_the_field_with_xpath_to",
                        ["//div[@data-field-name='Datalynx field {$field['name']}']//select", $values[1]]
                    );
                    $this->execute(
                        "behat_forms::i_set_the_field_with_xpath_to",
                        ["//div[@data-field-name='Datalynx field {$field['name']}']//input", $values[0]]
                    );
                    break;
                case "time":
                    $values = explode(".", $field['value']);
                    $this->execute(
                        "behat_forms::i_set_the_field_with_xpath_to",
                        ["//div[@data-field-name='Datalynx field {$field['name']}']//input", 1]
                    );
                    foreach ($values as $key => $value) {
                        $number = $key + 1;
                        $this->execute(
                            "behat_forms::i_set_the_field_with_xpath_to",
                            ["(//div[@data-field-name='Datalynx field {$field['name']}']//select)[{$number}]", $value]
                        );
                    }
                    break;
            }
        }
    }

    /**
     * Inserts text into a TinyMCE editor by ID without replacing existing content.
     * Unlike set_value (which calls setContent and wipes existing content), this uses
     * insertContent so previously inserted field-tag buttons are preserved.
     *
     * @Then I add to :editorid editor the text :newvalue
     *
     * @param string $editorid  The textarea ID backing the TinyMCE editor, e.g. "id_eparam2_editor"
     * @param string $newvalue  Content to insert at the current cursor position
     * @throws coding_exception
     */
    public function i_add_to_editor_the_text($editorid, $newvalue) {
        if (!$this->running_javascript()) {
            throw new coding_exception('Updating text requires javascript.');
        }
        $safeid      = addslashes($editorid);
        $safecontent = addslashes($newvalue);
        $this->getSession()->executeScript(
            "var ed = window.tinyMCE ? window.tinyMCE.get('{$safeid}') : null;" .
            "if (ed) { ed.insertContent('{$safecontent}'); }"
        );
    }

    /**
     * Replaces the entire content of a TinyMCE editor by ID.
     * Uses setContent so any previously existing editor content is wiped first.
     *
     * @Then I set the :editorid editor to :newvalue
     *
     * @param string $editorid  The textarea ID backing the TinyMCE editor, e.g. "id_eparam2_editor"
     * @param string $newvalue  New content to set in the editor
     * @throws coding_exception
     */
    public function i_set_the_editor_to($editorid, $newvalue) {
        if (!$this->running_javascript()) {
            throw new coding_exception('Updating text requires javascript.');
        }
        $safeid      = addslashes($editorid);
        $safecontent = addslashes($newvalue);
        $this->getSession()->executeScript(
            "var ed = window.tinyMCE ? window.tinyMCE.get('{$safeid}') : null;" .
            "if (ed) { ed.setContent('{$safecontent}'); }"
        );
    }

    /**
     * Directly selects options in a hidden select element by visible text, bypassing the
     * form-autocomplete widget entirely to avoid timing / pending-key issues.
     * Multiple values can be provided as a comma-separated string.
     *
     * @When I select :value in the datalynx fieldgroup fields selector
     *
     * @param string $value  Comma-separated option texts to select, e.g. "Field A, Field B"
     * @throws coding_exception
     */
    public function i_select_in_fieldgroup_fields_selector($value) {
        if (!$this->running_javascript()) {
            throw new coding_exception('This step requires JavaScript.');
        }
        $values = array_map('trim', explode(',', $value));
        $valuesjson = json_encode($values);
        $this->getSession()->executeScript(
            "(function(values) {" .
            "  var sel = document.getElementById('id_param1');" .
            "  if (!sel) { return; }" .
            "  Array.from(sel.options).forEach(function(opt) {" .
            "    if (values.indexOf(opt.text.trim()) !== -1) {" .
            "      opt.selected = true;" .
            "    }" .
            "  });" .
            "})($valuesjson);"
        );
    }

    /**
     * Directly selects a user option in the nth datalynx teammemberselect field, bypassing
     * the form-autocomplete widget to avoid timing / pending-key issues on slow machines.
     *
     * @When I select :value for the :nth datalynx teammemberselect field
     *
     * @param string $value  Full display text of the option, e.g. "Student 1 (student1 at example.com)"
     * @param int    $nth    1-based index of the teammemberselect on the page
     * @throws coding_exception
     */
    public function i_select_for_teammemberselect($value, $nth) {
        if (!$this->running_javascript()) {
            throw new coding_exception('This step requires JavaScript.');
        }
        $nth = (int) $nth;
        $valuejson = json_encode($value);
        $result = $this->getSession()->evaluateScript(
            "(function(nth, value) {" .
            "  var sels = document.querySelectorAll('[data-fieldtype=\"autocomplete\"] select[multiple]');" .
            "  var sel = sels[nth - 1];" .
            "  if (!sel) { return 'select-not-found:' + sels.length; }" .
            "  var opt = Array.from(sel.options).find(function(o) {" .
            "    return o.text.trim() === value || o.text.trim().indexOf(value) !== -1;" .
            "  });" .
            "  if (!opt) { return 'option-not-found in ' + sel.id +" .
            "   ' options:' + Array.from(sel.options).map(function(o){return o.text.trim();}).join('|'); }" .
            "  opt.selected = true;" .
            "  sel.dispatchEvent(new Event('change', {bubbles: true}));" .
            "  return 'ok';" .
            "})($nth, $valuejson);"
        );
        if ($result !== 'ok') {
            throw new \Exception("Could not select '$value' in teammemberselect #$nth: $result");
        }
    }

    /**
     * Adds a comment to the newest entry in a datalynx instance as the active Behat session user.
     *
     * @When /^I add the comment "(?P<content_string>(?:[^"]|\\")*)" to the latest
     * entry in "(?P<activityname_string>(?:[^"]|\\")*)" datalynx$/
     *
     * @param string $content
     * @param string $activityname
     * @return void
     */
    public function i_add_the_comment_to_the_latest_entry_in_datalynx($content, $activityname) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/comment/lib.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $record = $DB->get_record('datalynx', ['name' => $activityname], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $record->course], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('datalynx', $record->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $entries = $DB->get_records('datalynx_entries', ['dataid' => $record->id], 'id DESC', '*', 0, 1);
        if (empty($entries)) {
            throw new \Exception("No entries found for datalynx '$activityname'.");
        }
        $entry = reset($entries);

        self::set_user($this->get_session_user());

        $manager = new \comment((object) [
            'context' => $context,
            'course' => $course,
            'cm' => $cm,
            'area' => 'entry',
            'itemid' => $entry->id,
            'component' => 'mod_datalynx',
        ]);
        $manager->add($content);

        $this->getSession()->reload();
    }

    /**
     * Asserts that the newest entry in a datalynx instance contains the expected stored comment.
     *
     * @Then /^the latest entry in "(?P<activityname_string>(?:[^"]|\\")*)" datalynx
     * should have the comment "(?P<content_string>(?:[^"]|\\")*)"$/
     *
     * @param string $activityname
     * @param string $content
     * @return void
     */
    public function the_latest_entry_in_datalynx_should_have_the_comment($activityname, $content) {
        global $DB;

        $record = $DB->get_record('datalynx', ['name' => $activityname], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('datalynx', $record->id, $record->course, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $entries = $DB->get_records('datalynx_entries', ['dataid' => $record->id], 'id DESC', '*', 0, 1);
        if (empty($entries)) {
            throw new \Exception("No entries found for datalynx '$activityname'.");
        }
        $entry = reset($entries);

        $comments = $DB->get_records('comments', [
            'contextid' => $context->id,
            'commentarea' => 'entry',
            'itemid' => $entry->id,
            'component' => 'mod_datalynx',
        ]);

        foreach ($comments as $comment) {
            if ($comment->content === $content) {
                return;
            }
        }

        if (!$comments) {
            throw new \Exception("Could not find any comments for the latest entry in '$activityname'.");
        }

        throw new \Exception("Could not find the comment '$content' for the latest entry in '$activityname'.");
    }

    // phpcs:disable moodle.Files.LineLength
    /**
     * Adds a displayview shortcode to the site homepage summary section for a datalynx activity.
     *
     * @Given /^the site frontpage contains the displayview shortcode for "(?P<activityname_string>(?:[^"]|\\")*)" datalynx view "(?P<viewname_string>(?:[^"]|\\")*)"$/
     *
     * @param string $activityname
     * @param string $viewname
     * @return void
     */
    public function the_site_frontpage_contains_the_displayview_shortcode_for_datalynx_view($activityname, $viewname) {
        global $CFG, $DB, $SITE;

        require_once($CFG->dirroot . '/course/lib.php');

        course_create_sections_if_missing($SITE, 1);

        $record = $DB->get_record('datalynx', ['name' => $activityname], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('datalynx', $record->id, $record->course, false, MUST_EXIST);
        $section = $DB->get_record('course_sections', ['course' => $SITE->id, 'section' => 1], '*', MUST_EXIST);
        $shortcode = '[displayview cmid=' . $cm->id . ' view="' . $viewname . '"]';

        $DB->update_record('course_sections', (object) [
            'id' => $section->id,
            'summary' => $shortcode,
            'summaryformat' => FORMAT_HTML,
        ]);

        rebuild_course_cache($SITE->id, true);
    }
    // phpcs:enable moodle.Files.LineLength

    /**
     * Close the auto-complete suggestions list (Assuming there is only one on the page.).
     *
     * @Given /^I close the autocomplete suggestions list$/
     */
    public function i_close_the_autocomplete_suggestions_list() {
        $csstarget = ".col-form-label";
        $this->execute('behat_general::i_click_on', [$csstarget, 'css_element']);
    }

    /**
     * Follow the datalynx link of the elements matching the selector.
     *
     * @When /^I follow the datalynx "(?P<link_string>(?:[^"]|\\")*)" link$/
     * @param string $linktext The link text to follow.
     */
    public function i_follow_the_datalynx_link($linktext) {
        $page = $this->getSession()->getPage();
        switch ($linktext) {
            case 'View Filters':
                // Code to handle the "View Filters" case.
                $link = $page->find('css', sprintf('.nav-item [title="View Filters"]', $linktext));
                break;
            case 'Manage':
                $link = $page->find('css', sprintf('.nav-item [title="Manage"]', $linktext));
                break;
            case 'Views':
                $link = $page->find('css', sprintf('.nav-item [title="Views"]', $linktext));
                break;
        }
        if (null === $link) {
            throw new \RuntimeException(sprintf('The link "%s" was not found or is not visible', $linktext));
        }
        $link->click();
    }

    /**
     * Clicks the Nth entry action link (Edit, Delete, or Duplicate) on the page.
     *
     * The ##edit##, ##delete##, and ##duplicate## patterns each render an <a> element whose
     * accessible name is provided by a <span class="sr-only"> child, e.g. "Edit Entryid 42".
     * This step finds all such links whose sr-only text starts with "<action> Entryid" and
     * clicks the Nth one (1-based), so tests do not need to know the DB entry ID.
     *
     * Example usage:
     *   When I click on the 1st entry "Edit" link
     *   And  I click on the 2nd entry "Edit" link
     *
     * @When /^I click on the (?P<n>\d+)(?:st|nd|rd|th) entry "(?P<action>[^"]*)" link$/
     *
     * @param int    $n      1-based position of the link among all matching links on the page.
     * @param string $action Action label prefix, e.g. "Edit", "Delete", "Duplicate".
     */
    public function i_click_on_the_nth_entry_action_link(int $n, string $action): void {
        $page = $this->getSession()->getPage();
        $spans = $page->findAll('css', 'a .sr-only');
        $prefix = $action . ' Entryid';
        $found = [];
        foreach ($spans as $span) {
            if (str_starts_with(trim($span->getText()), $prefix)) {
                $found[] = $span->getParent();
            }
        }
        if (count($found) < $n) {
            throw new \RuntimeException(sprintf(
                'Cannot find %d "%s" entry action link(s) on the page; only %d found.',
                $n,
                $prefix,
                count($found)
            ));
        }
        $found[$n - 1]->click();
    }

    // phpcs:disable moodle.Files.LineLength
    /**
     * Normalises a datalynx field name by stripping the "Datalynx field " label prefix.
     *
     * @param string $name
     * @return string
     */
    protected static function normalize_field_name(string $name): string {
        return preg_replace('/^Datalynx field\s+/u', '', trim($name));
    }

    /**
     * Map a comma-separated list of role keywords to datalynx permission integers.
     *
     * @param string $roles e.g. "manager,teacher,student,author".
     * @return int[]
     */
    protected static function roles_to_permissions(string $roles): array {
        $map = ['manager' => 1, 'teacher' => 2, 'student' => 4, 'guest' => 8, 'author' => 16, 'mentor' => 32];
        $permissions = [];
        foreach (explode(',', $roles) as $role) {
            $role = strtolower(trim($role));
            if ($role !== '' && isset($map[$role])) {
                $permissions[] = $map[$role];
            }
        }
        return $permissions;
    }

    /**
     * Parse a compact condition spec into behavior condition rules.
     *
     * Spec format: semicolon-separated conditions, each "Field=Value" (equal), "Field~Value"
     * (contains), "Field!=Value" (not equal) or "Field=" (empty). Field names may omit the
     * "Datalynx field " prefix. Select/radiobutton values are resolved to their option index.
     *
     * @param \mod_datalynx\datalynx $dlx
     * @param string $spec
     * @return array[] Rules, each ['sourcefieldid' => int, 'not' => string, 'operator' => string, 'value' => mixed].
     */
    protected static function parse_condition_spec(\mod_datalynx\datalynx $dlx, string $spec): array {
        $fields = $dlx->get_fields();
        $byname = [];
        foreach ($fields as $field) {
            $byname[self::normalize_field_name($field->name())] = $field;
        }

        $rules = [];
        foreach (explode(';', $spec) as $cond) {
            $cond = trim($cond);
            if ($cond === '') {
                continue;
            }

            $not = '';
            if (strpos($cond, '!=') !== false) {
                [$fname, $val] = explode('!=', $cond, 2);
                $not = 'NOT';
                $optoken = '=';
            } else if (strpos($cond, '~') !== false) {
                [$fname, $val] = explode('~', $cond, 2);
                $optoken = '~';
            } else {
                [$fname, $val] = explode('=', $cond, 2);
                $optoken = '=';
            }
            $fname = self::normalize_field_name($fname);
            $val = trim($val);

            if (!isset($byname[$fname])) {
                throw new \Exception("Unknown condition source field: {$fname}");
            }
            $field = $byname[$fname];

            if ($val === '') {
                // Empty operator (no argument).
                $operator = '';
                $value = '';
            } else if ($optoken === '~') {
                $operator = 'LIKE';
                $value = $val;
            } else if (in_array($field->type, ['select', 'radiobutton'], true)) {
                $operator = 'ANY_OF';
                $value = [(int) $field->get_search_value($val)];
            } else {
                $operator = '=';
                $value = $val;
            }

            $rules[] = [
                'sourcefieldid' => (int) $field->field->id,
                'not' => $not,
                'operator' => $operator,
                'value' => $value,
            ];
        }
        return $rules;
    }

    /**
     * Creates field behaviors (including value-based availability conditions and role visibility)
     * directly for the specified datalynx instance.
     *
     * Table columns: name, visibleto, editableby (comma-separated role keywords), match (all|any,
     * optional, default all) and conditions (compact spec, optional).
     *
     * @Given /^the "(?P<activityname_string>(?:[^"]|\\")*)" datalynx has the following behaviors:$/
     *
     * @param string $activityname
     * @param TableNode $table
     */
    public function the_datalynx_has_the_following_behaviors($activityname, TableNode $table) {
        global $DB;

        $record = $DB->get_record('datalynx', ['name' => $activityname], '*', MUST_EXIST);
        $dlx = new \mod_datalynx\datalynx($record->id);

        foreach ($table->getHash() as $row) {
            $visibleto = ['permissions' => self::roles_to_permissions($row['visibleto'] ?? '')];
            $editableby = self::roles_to_permissions($row['editableby'] ?? '');
            $rules = isset($row['conditions']) ? self::parse_condition_spec($dlx, $row['conditions']) : [];
            $conditions = $rules
                ? json_encode(['match' => $row['match'] ?? 'all', 'rules' => $rules])
                : null;

            $DB->insert_record('datalynx_behaviors', (object) [
                'dataid' => $dlx->id(),
                'name' => $row['name'],
                'description' => '',
                'visibleto' => serialize($visibleto),
                'editableby' => serialize($editableby),
                'required' => 0,
                'conditions' => $conditions,
            ]);
        }
    }

    /**
     * Sets the entry template (param2) of a named view directly.
     *
     * @Given /^the "(?P<viewname_string>(?:[^"]|\\")*)" view of "(?P<activityname_string>(?:[^"]|\\")*)" datalynx has the entry template "(?P<template_string>(?:[^"]|\\")*)"$/
     *
     * @param string $viewname
     * @param string $activityname
     * @param string $template
     */
    public function the_view_of_datalynx_has_the_entry_template($viewname, $activityname, $template) {
        global $DB;

        $record = $DB->get_record('datalynx', ['name' => $activityname], '*', MUST_EXIST);
        $view = $DB->get_record(
            'datalynx_views',
            ['dataid' => $record->id, 'name' => $viewname],
            '*',
            MUST_EXIST
        );
        $view->param2 = $template;
        $DB->update_record('datalynx_views', $view);
    }

    /**
     * Configures a named view to redirect to another view after entry submission, optionally
     * continuing to edit the same entry on the target view.
     *
     * @Given /^the "(?P<viewname_string>(?:[^"]|\\")*)" view of "(?P<activityname_string>(?:[^"]|\\")*)" datalynx redirects to the "(?P<target_string>(?:[^"]|\\")*)" view continuing editing "(?P<flag>[01])"$/
     *
     * @param string $viewname
     * @param string $activityname
     * @param string $target
     * @param string $flag "1" to continue editing the same entry, "0" otherwise.
     */
    public function the_view_of_datalynx_redirects_to_view($viewname, $activityname, $target, $flag) {
        global $DB;

        $record = $DB->get_record('datalynx', ['name' => $activityname], '*', MUST_EXIST);
        $view = $DB->get_record(
            'datalynx_views',
            ['dataid' => $record->id, 'name' => $viewname],
            '*',
            MUST_EXIST
        );
        $targetview = $DB->get_record(
            'datalynx_views',
            ['dataid' => $record->id, 'name' => $target],
            '*',
            MUST_EXIST
        );
        $view->param10 = (int) $targetview->id;
        $view->param9 = (int) $flag;
        $DB->update_record('datalynx_views', $view);
    }

    /**
     * Makes a named view both the default view and the single edit view of the instance.
     *
     * @Given /^the "(?P<viewname_string>(?:[^"]|\\")*)" view is the default and edit view of "(?P<activityname_string>(?:[^"]|\\")*)" datalynx$/
     *
     * @param string $viewname
     * @param string $activityname
     */
    public function the_view_is_the_default_and_edit_view_of_datalynx($viewname, $activityname) {
        global $DB;

        $record = $DB->get_record('datalynx', ['name' => $activityname], '*', MUST_EXIST);
        $view = $DB->get_record(
            'datalynx_views',
            ['dataid' => $record->id, 'name' => $viewname],
            '*',
            MUST_EXIST
        );
        $record->defaultview = (int) $view->id;
        $record->singleedit = (int) $view->id;
        $DB->update_record('datalynx', $record);
    }
    // phpcs:enable moodle.Files.LineLength
}
