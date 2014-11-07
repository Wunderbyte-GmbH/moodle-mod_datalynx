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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * @package mod_datalynx
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once $CFG->libdir.'/formslib.php';
HTML_QuickForm::registerElementType('checkboxgroup', "$CFG->dirroot/mod/datalynx/checkboxgroup/checkboxgroup.php", 'HTML_QuickForm_checkboxgroup');

/**
 *
 */
class datalynx_field_behavior_form extends moodleform {

    /**
     * @var datalynx
     */
    private $datalynx;

    public function datalynx_field_behavior_form(datalynx $datalynx) {
        $this->datalynx = $datalynx;
        parent::moodleform();
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
        $mform->addRule('name', "Behavior name may not contain the pipe symbol \" | \"!", 'regex', '/^[^\|]+$/', 'client');

        $mform->addElement('text', 'description', get_string('description'), array('size' => '64'));
        $mform->setType('description', PARAM_TEXT);

        //----- VISIBILITY OPTIONS -----

        $mform->addElement('header', 'visibilityoptions', get_string('visibility', 'datalynx'));
        $mform->setExpanded('visibilityoptions');

        $mform->addElement('checkboxgroup', 'visibleto', get_string('roles'), $this->datalynx->get_datalynx_permission_names(), $this->get_permissions_menu_separators());
        $mform->setType('visibleto', PARAM_RAW);
        $mform->setDefault('visibleto', array(datalynx::PERMISSION_MANAGER, datalynx::PERMISSION_TEACHER, datalynx::PERMISSION_STUDENT));

        //----- EDITING OPTIONS -----

        $mform->addElement('header', 'editing', get_string('editing', 'datalynx'));
        $mform->setExpanded('editing');

        $mform->addElement('advcheckbox', 'editable', get_string('editable', 'datalynx'));
        $mform->setDefault('editable', true);

        $mform->addElement('checkboxgroup', 'editableby', get_string('editableby', 'datalynx'), $this->datalynx->get_datalynx_permission_names(), $this->get_permissions_menu_separators());
        $mform->setType('editableby', PARAM_RAW);
        $mform->setDefault('editableby', array(datalynx::PERMISSION_MANAGER, datalynx::PERMISSION_TEACHER, datalynx::PERMISSION_STUDENT));
        $mform->disabledIf('editableby', 'editable', 'notchecked');

        $mform->addElement('advcheckbox', 'required', get_string('required', 'datalynx'));
        $mform->disabledIf('required', 'editable', 'notchecked');

        $this->add_action_buttons();
    }

    public function get_data() {
        $data = parent::get_data();
        if ($data && !isset($data->editableby)) {
            $data->editableby = array();
        }
        return $data;
    }

    public function set_data($data) {
        if (!isset($data->editableby) || empty($data->editableby)) {
            $data->editable = false;
        } else {
            $data->editable = true;
        }
        parent::set_data($data);
    }

    private function get_permissions_menu_separators() {
        return array('<br />', '<br />', '<br />', '<br /><br />', '<br />');
    }

    function validation($data, $files) {
        $errors = array();
        if (!$data['name']) {
            $errors['name'] = "You must supply a value here.";
        }
        if (strpos($data['name'], '|') !== false) {
            $errors['name'] = "Behavior name may not contain the pipe symbol \" | \".";
        }
        return $errors;
    }
}
