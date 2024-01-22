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
 * @copyright 2014 Ivan Šakić
 * @copyright 2016 onwards David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Class datalynx_field_behavior_form
 * This class is responsible for managin the form for the field behaviors
 */
class datalynx_field_behavior_form extends moodleform {

    /**
     *
     * @var mod_datalynx\datalynx
     */
    private $datalynx;

    /**
     * datalynx_field_behavior_form constructor.
     *
     * @param \mod_datalynx\datalynx $datalynx
     */
    public function __construct(mod_datalynx\datalynx $datalynx) {
        $this->datalynx = $datalynx;
        parent::__construct();
    }

    /**
     * @throws coding_exception
     */
    protected function definition() {
        $mform = &$this->_form;

        $new = !required_param('id', PARAM_INT);

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'd', $this->datalynx->id());
        $mform->setType('d', PARAM_INT);

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), array('size' => '32'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', "Behavior name may not contain the pipe symbol \" | \"!", 'regex',
                '/^[^\|]+$/', 'client');

        $mform->addElement('text', 'description', get_string('description'), array('size' => '64'));
        $mform->setType('description', PARAM_TEXT);

        // VISIBILITY OPTIONS.

        $mform->addElement('header', 'visibilityoptions', get_string('visibility', 'datalynx'));
        $mform->setExpanded('visibilityoptions');

        $options = array("multiple" => true);
        $mform->addElement('autocomplete', 'visibletopermission', get_string('visibleto', 'datalynx'),
                $this->datalynx->get_datalynx_permission_names(false, false), $options);
        $mform->addHelpButton('visibletopermission', 'visibleto', 'datalynx');
        $mform->setType('visibletopermission', PARAM_RAW);
        if ($new) {
            $mform->setDefault('visibletopermission',
                    array(mod_datalynx\datalynx::PERMISSION_MANAGER, mod_datalynx\datalynx::PERMISSION_TEACHER,
                            mod_datalynx\datalynx::PERMISSION_STUDENT));
        }

        // Interface for single user, this overrules other visibility options.
        $allusers = $this->get_allusers();
        $options = array("multiple" => true);
        $mform->addElement('autocomplete', 'visibletouser',
                get_string('otheruser', 'datalynx'), $allusers, $options);
        $mform->setType('visibletouser', PARAM_INT);

        // Interface for teammemberselect fields.
        $options = array("multiple" => true);
        $teammemberselect = $this->get_teammemberselect_fields();
        $mform->addElement('autocomplete', 'visibletoteammember', get_string('teammemberselect', 'datalynx'),
                $teammemberselect, $options);
        $mform->setType('visibletoteammember', PARAM_RAW);

        // EDITING OPTIONS.
        $mform->addElement('header', 'editing', get_string('editing', 'datalynx'));
        $mform->setExpanded('editing');

        $mform->addElement('advcheckbox', 'editable', get_string('editable', 'datalynx'));
        if ($new) {
            $mform->setDefault('editable', true);
        }

        $mform->addElement('autocomplete', 'editableby', get_string('editableby', 'datalynx'),
                $this->datalynx->get_datalynx_permission_names(false, false), $options);
        $mform->addHelpButton('editableby', 'editableby', 'datalynx');

        $mform->setType('editableby', PARAM_RAW);
        if ($new) {
            $mform->setDefault('editableby',
                    array(mod_datalynx\datalynx::PERMISSION_MANAGER, mod_datalynx\datalynx::PERMISSION_TEACHER,
                            mod_datalynx\datalynx::PERMISSION_STUDENT));
        }
        $mform->disabledIf('editableby', 'editable', 'notchecked');

        $mform->addElement('advcheckbox', 'required', get_string('required', 'datalynx'));
        if ($new) {
            $mform->setDefault('required', false);
        }
        $mform->disabledIf('required', 'editable', 'notchecked');

        $this->add_action_buttons();
    }

    /**
     * Get all teammemberselect fields in datalynx.
     *
     * @return array fieldid => fieldname
     */
    public function get_teammemberselect_fields(): array {
        $allfields = $this->datalynx->get_fields();
        $fields = [];
        if (!empty($allfields)) {
            foreach ($allfields as $fieldid => $field) {
                if ($field->type === 'teammemberselect') {
                    $fields[$fieldid] = $field->field->name;
                }
            }
        }
        return $fields;
    }

    /**
     * Get all users in moodle instance for autocomplete list.
     * TODO: Really all users or only those with access to this datalynx instance?
     *
     * @return array with userid -> firstname lastname.
     * @throws coding_exception
     */
    public function get_allusers() {
        global $DB;
        $allusers = [];
        $tempusers = $DB->get_records('user', array(), '', $fields = 'id, firstname, lastname');

        foreach ($tempusers as $userdata) {
            // Remove empties to make list more usable.
            if ($userdata->lastname == '') {
                continue;
            }
            $allusers[$userdata->id] = "$userdata->firstname $userdata->lastname";
        }
        return $allusers;
    }

    /**
     * @return object
     */
    public function get_data() {
        $data = parent::get_data();
        if ($data) {
            if (!isset($data->visibleto)) {
                $data->visibleto = [];
            }
            if (!isset($data->editableby)) {
                $data->editableby = [];
                $data->editable = false;
            }
            if (!isset($data->required)) {
                $data->required = false;
            }
        }
        return $data;
    }

    /**
     * @param array|stdClass $data
     */
    public function set_data($data) {
        if (!isset($data->visibleto)) {
            $data->visibleto = [];
        }
        if (!isset($data->editableby)) {
            $data->editableby = [];
        }
        if (empty($data->editableby)) {
            $data->editable = false;
        } else {
            $data->editable = true;
        }
        if (!isset($data->required)) {
            $data->required = false;
        }
        parent::set_data($data);
    }

    /**
     * @param array $data
     * @param array $files
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    public function validation($data, $files) {
        global $DB;
        $errors = array();
        if (!$data['name']) {
            $errors['name'] = "You must supply a value here.";
        }
        if (strpos($data['name'], '|') !== false) {
            $errors['name'] = "Behavior name may not contain the pipe symbol \" | \".";
        }
        if ($data['id'] == 0) {
            // To prevent duplicate renderer names when creating a new renderer.
            if ($DB->record_exists('datalynx_behaviors',
                    array('name' => $data['name'], 'dataid' => $data['d']))) {
                $errors['name'] = get_string('duplicatename', 'datalynx');
            }
        } else {
            // To prevent duplicate renderer names when updating existing renderers.
            $sql = "SELECT 'x'
                    FROM {datalynx_behaviors} r
                    WHERE r.name = ? AND r.dataid = ? AND r.id <> ?";
            $params = array($data['name'], $data['d'], $data['id']);
            if ($DB->record_exists_sql($sql, $params)) {
                $errors['name'] = get_string('duplicatename', 'datalynx');
            }
        }
        return $errors;
    }
}
