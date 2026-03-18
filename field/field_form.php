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
 *
 * @package mod_datalynx
 * @copyright 2014 onwards by edulabs.org and associated programmers
 * @copyright based on the work by 2013 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * Field form base class.
 *
 * @package mod_datalynx
 */
class datalynxfield_form extends moodleform {
    /** @var datalynxfield_base The field object. */
    protected $_field = null; // phpcs:ignore

    /** @var mod_datalynx\datalynx The datalynx object. */
    protected $dl = null; // phpcs:ignore

    /**
     * Constructor.
     *
     * @param datalynxfield_base $field The field object.
     * @param string|null $action Action URL.
     * @param array|null $customdata Custom data.
     * @param string $method Form method.
     * @param string $target Form target.
     * @param array|null $attributes Form attributes.
     * @param bool $editable Whether the form is editable.
     */
    public function __construct(
        $field,
        $action = null,
        $customdata = null,
        $method = 'post',
        $target = '',
        $attributes = null,
        $editable = true
    ) {
        $this->_field = $field;
        $this->dl = $field->df();

        parent::__construct($action, $customdata, $method, $target, $attributes, $editable);
    }

    /**
     * Defines the form elements.
     */
    public function definition() {
        $mform = &$this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => '32']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'description', get_string('description'), ['size' => '64']);
        $mform->setType('description', PARAM_TEXT);

        $this->field_definition();

        $this->add_action_buttons();
    }

    /**
     * Defines the field-specific elements.
     */
    public function field_definition() {
    }

    /**
     * Adds the action buttons to the form.
     *
     * @param bool $cancel Whether to show the cancel button.
     * @param string|null $submit The submit button label.
     */
    public function add_action_buttons($cancel = true, $submit = null) {
        $mform = &$this->_form;

        $buttonarray = [];
        // Save and display.
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        // Save and continue.
        $buttonarray[] = &$mform->createElement(
            'submit',
            'submitbutton',
            get_string('savecontinue', 'datalynx')
        );
        // Cancel.
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Validates the form data.
     *
     * @param array $data Form data.
     * @param array $files Uploaded files.
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($this->dl->name_exists('fields', $data['name'], $this->_field->id())) {
            $errors['name'] = get_string('invalidname', 'datalynx', get_string('field', 'datalynx'));
        }
        return $errors;
    }

    /**
     * Return array of select menu entries for chosing a datalynx instance that has a textfield.
     * It is used to provide choices for other datalynx instances that are interlinked
     * @return array[]
     */
    public function get_datalynx_instances_menu(): array {
        global $DB;
        // Get all Datalynxs where user has managetemplate capability.
        // TODO: MDL-0000 there may be too many.
        $sql = "SELECT DISTINCT d.id
                FROM {datalynx} d
                INNER JOIN {course_modules} cm ON d.id = cm.instance
                INNER JOIN {modules} m ON m.id = cm.module
                LEFT JOIN {datalynx_fields} df ON d.id = df.dataid
                LEFT JOIN {course} c ON c.id = d.course
                WHERE m.name = 'datalynx'
                AND cm.deletioninprogress = 0
                AND df.type = 'text'
                AND c.visible = 1";

        $datalynxs = [];
        if ($dlids = $DB->get_fieldset_sql($sql)) {
            foreach ($dlids as $dlid) {
                if ($dlid != $this->dl->id()) {
                    $dl = new mod_datalynx\datalynx($dlid);
                    // Only add if user can manage dl templates.
                    if (has_capability('mod/datalynx:managetemplates', $dl->context)) {
                        $dlinfo = new stdClass();
                        $dlinfo->courseshortname = $dl->course->shortname;
                        $dlinfo->name = $dl->name();
                        $datalynxs[$dlid] = $dlinfo;
                    }
                }
            }
        }

        // Autocompletion with content of other textfield from the same or other datalynx instance.
        // Select Datalynx instance (to be stored in param9).
        if ($datalynxs || $this->dl->id() > 0) {
            $dfmenu = ['' => [0 => get_string('noautocompletion', 'datalynx')]];
            $dfmenu[''][$this->dl->id()] = get_string('thisdatalynx', 'datalynx') .
                    " (" . strip_tags(format_string($this->dl->name(), true)) . ")";
            foreach ($datalynxs as $dlid => $dl) {
                if (!isset($dfmenu[$dl->courseshortname])) {
                    $dfmenu[$dl->courseshortname] = [];
                }
                $dfmenu[$dl->courseshortname][$dlid] = strip_tags(
                    format_string($dl->name, true)
                );
            }
        } else {
            $dfmenu = ['' => [0 => get_string('nodatalynxs', 'datalynx')]];
        }
        return $dfmenu;
    }
}

/**
 * base form for fields that use multi and single choice options
 *
 * @author david
 *
 */
class datalynxfield_option_form extends datalynxfield_form {
    /**
     * @var datalynxfield_option
     */
    protected $_field = null;

    public function definition_after_data() {
        $this->add_option_dialog();
    }

    /**
     *  Prepare the form to edit the options for a single or multi choice field*
     * @return void
     */
    protected function add_option_dialog() {
        $mform = &$this->_form;
        $options = $this->_field->get_options();
        if (!empty($options)) {
            $group = [];
            $group[] = &$mform->createElement(
                'static',
                null,
                null,
                '<table><thead><th>' . get_string('option', 'datalynx') . '</th><th>' .
                    get_string('renameoption', 'datalynx') . '</th><th>' .
                get_string('deleteoption', 'datalynx') . '</th></thead><tbody>'
            );
            foreach ($options as $id => $option) {
                $option = htmlspecialchars($option);
                $group[] = &$mform->createElement(
                    'static',
                    null,
                    null,
                    "<tr><td>{$option}</td><td>"
                );
                $group[] = &$mform->createElement('text', "renameoption[{$id}]", '', ['size' => 32]);
                $group[] = &$mform->createElement('static', null, null, '</td><td>');
                $group[] = &$mform->createElement('checkbox', "deleteoption[{$id}]", '', null, ['size' => 1]);
                foreach ($options as $newid => $newoption) {
                    $mform->disabledIf("renameoption[{$id}]", "deleteoption[{$newid}]", 'checked');
                }
                $group[] = &$mform->createElement('static', null, null, '</td></tr>');
            }
            $group[] = &$mform->createElement('static', null, null, '</tbody></table>');
            $tablerow = &$mform->createElement(
                'group',
                'existingoptions',
                get_string('existingoptions', 'datalynx'),
                $group,
                null,
                false
            );
            $mform->insertElementBefore($tablerow, 'param2');
        }
        $addnew = &$mform->createElement(
            'textarea',
            'addoptions',
            get_string('addoptions', 'datalynx'),
            'wrap="soft" rows="5" cols="30"'
        );
        $mform->insertElementBefore($addnew, 'param2');
        if (empty($options)) {
            $mform->addRule(
                'addoptions',
                get_string('err_required', 'form'),
                'required',
                null,
                'client'
            );
        }
    }

    /**
     * Validate form data
     * @param $data
     * @param $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $oldoptions = $this->_field->get_options();
        if (count($oldoptions) == 0 && empty($data['addoptions'])) {
            $errors['existingoptions'] = get_string('nooptions', 'datalynx');
        } else {
            if (
                isset($data['deleteoption']) && count($data['deleteoption']) == count(
                    $oldoptions
                ) && empty($data['addoptions'])
            ) {
                $errors['existingoptions'] = get_string('nooptions', 'datalynx');
            } else {
                if (isset($data['deleteoption']) && isset($data['renameoption'])) {
                    $errors['existingoptions'] = get_string('avoidaddanddeletesimultaneously', 'datalynx');
                }
            }
        }

        return $errors;
    }
}
