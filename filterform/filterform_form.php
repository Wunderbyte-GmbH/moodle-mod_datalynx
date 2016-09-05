<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @package mod_datalynx
 * @copyright 2015 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once $CFG->libdir . '/formslib.php';
HTML_QuickForm::registerElementType('checkboxgroup', 
        "$CFG->dirroot/mod/datalynx/checkboxgroup/checkboxgroup.php", 'HTML_QuickForm_checkboxgroup');


/**
 */
class datalynx_field_filterform_form extends moodleform {

    /**
     *
     * @var datalynx
     */
    private $datalynx;

    public function __construct(datalynx $datalynx) {
        $this->datalynx = $datalynx;
        parent::__construct();
    }

    protected function definition() {
        $mform = &$this->_form;
        
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'd', $this->datalynx->id());
        $mform->setType('d', PARAM_INT);
        
        $mform->addElement('header', 'general', get_string('general', 'form'));
        
        $mform->addElement('text', 'name', get_string('name'), array('size' => '32'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', "Filter form name may not contain the pipe symbol \" | \"!", 
                'regex', '/^[^\|]+$/', 'client');
        
        $mform->addElement('text', 'description', get_string('description'), array('size' => '64'));
        $mform->setType('description', PARAM_TEXT);
        
        $mform->addElement('checkboxgroup', 'fields', get_string('fields', 'datalynx'), 
                array_map(function ($field) {
                    return $field->field->name;
                }, $this->datalynx->get_fields()), '<br>');
        $mform->setType('fields', PARAM_RAW);
        
        $this->add_action_buttons();
    }

    public function get_data() {
        $data = parent::get_data();
        return $data;
    }

    public function set_data($data) {
        parent::set_data($data);
    }

    function validation($data, $files) {
        $errors = array();
        if (!$data['name']) {
            $errors['name'] = "You must supply a value here.";
        }
        if (strpos($data['name'], '|') !== false) {
            $errors['name'] = "filterform name may not contain the pipe symbol \" | \".";
        }
        return $errors;
    }
}
