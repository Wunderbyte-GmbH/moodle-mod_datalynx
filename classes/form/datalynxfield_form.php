<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_datalynx\form;
use mod_datalynx;
use mod_datalynx\local\field\datalynxfield_base;
use moodleform;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Field form base class.
 *
 * @package mod_datalynx
 * @copyright 2025 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datalynxfield_form extends moodleform {
    /** @var datalynxfield_base The field object. */
    protected $field = null;

    /** @var mod_datalynx\datalynx The datalynx object. */
    protected $dl = null;

    /**
     * Constructor.
     *
     * @param datalynxfield_base $field The field object.
     * @param ?string $action Action URL.
     * @param ?array $customdata Custom data.
     * @param string $method Form method.
     * @param string $target Form target.
     * @param ?array $attributes Form attributes.
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
        $this->field = $field;
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
     * @param ?string $submit The submit button label.
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

        if ($this->dl->name_exists('fields', $data['name'], $this->field->id())) {
            $errors['name'] = get_string('invalidname', 'datalynx', get_string('field', 'datalynx'));
        }
        return $errors;
    }

    /**
     * Return array of select menu entries for chosing a datalynx instance that has a textfield.
     * It is used to provide choices for other datalynx instances that are interlinked
     *
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
