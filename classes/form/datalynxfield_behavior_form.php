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

namespace mod_datalynx\form;
use coding_exception;
use dml_exception;
use html_writer;
use mod_datalynx;
use moodleform;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Class datalynx_field_behavior_form
 * This class is responsible for managin the form for the field behaviors
 */
class datalynxfield_behavior_form extends moodleform {
    /**
     *
     * @var mod_datalynx\datalynx
     */
    private $dlx;

    /**
     * datalynx_field_behavior_form constructor.
     *
     * @param \mod_datalynx\datalynx $dlx
     */
    public function __construct(mod_datalynx\datalynx $dlx) {
        $this->dlx = $dlx;
        parent::__construct();
    }

    /**
     * Define the form elements for the behavior form.
     *
     * @throws coding_exception
     */
    protected function definition() {
        $mform = &$this->_form;

        $new = !required_param('id', PARAM_INT);

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'd', $this->dlx->id());
        $mform->setType('d', PARAM_INT);

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => '32']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule(
            'name',
            "Behavior name may not contain the pipe symbol \" | \"!",
            'regex',
            '/^[^\|]+$/',
            'client'
        );

        $mform->addElement('text', 'description', get_string('description'), ['size' => '64']);
        $mform->setType('description', PARAM_TEXT);

        // VISIBILITY OPTIONS.

        $mform->addElement('header', 'visibilityoptions', get_string('visibility', 'datalynx'));
        $mform->setExpanded('visibilityoptions');

        $mform->addElement(
            'static',
            'visibletopermission_header',
            '',
            html_writer::tag('strong', get_string('visibleto', 'datalynx'))
        );
        $mform->addHelpButton('visibletopermission_header', 'visibleto', 'datalynx');

        $this->add_permission_checkbox(
            $mform,
            'visibletopermission_1',
            get_string('visible1', 'datalynx'),
            'mod/datalynx:viewprivilegemanager',
            1
        );
        $this->add_permission_checkbox(
            $mform,
            'visibletopermission_2',
            get_string('visible2', 'datalynx'),
            'mod/datalynx:viewprivilegeteacher',
            2
        );
        $this->add_permission_checkbox(
            $mform,
            'visibletopermission_4',
            get_string('visible4', 'datalynx'),
            'mod/datalynx:viewprivilegestudent',
            4
        );
        $this->add_permission_checkbox(
            $mform,
            'visibletopermission_8',
            get_string('visible8', 'datalynx'),
            'mod/datalynx:viewprivilegeguest',
            8
        );
        $this->add_permission_checkbox(
            $mform,
            'visibletopermission_16',
            get_string('author', 'datalynx'),
            '',
            16,
            false,
            get_string('dynamic_check_author_desc', 'datalynx')
        );
        $this->add_permission_checkbox(
            $mform,
            'visibletopermission_32',
            get_string('mentor', 'datalynx'),
            '',
            32,
            false,
            get_string('dynamic_check_mentor_desc', 'datalynx')
        );

        // Interface for single user, this overrules other visibility options.
        $allusers = $this->get_allusers();
        $options = ["multiple" => true];
        $mform->addElement(
            'autocomplete',
            'visibletouser',
            get_string('otheruser', 'datalynx'),
            $allusers,
            $options
        );
        $mform->setType('visibletouser', PARAM_INT);

        // Interface for teammemberselect fields.
        $options = ["multiple" => true];
        $teammemberselect = $this->get_teammemberselect_fields();
        $mform->addElement(
            'autocomplete',
            'visibletoteammember',
            get_string('teammemberselect', 'datalynx'),
            $teammemberselect,
            $options
        );
        $mform->setType('visibletoteammember', PARAM_RAW);

        // EDITING OPTIONS.
        $mform->addElement('header', 'editing', get_string('editing', 'datalynx'));
        $mform->setExpanded('editing');

        $mform->addElement('advcheckbox', 'editable', get_string('editable', 'datalynx'));
        if ($new) {
            $mform->setDefault('editable', true);
        }

        $mform->addElement(
            'static',
            'editableby_header',
            '',
            html_writer::tag('strong', get_string('editableby', 'datalynx'))
        );
        $mform->addHelpButton('editableby_header', 'editableby', 'datalynx');

        $this->add_permission_checkbox(
            $mform,
            'editableby_1',
            get_string('visible1', 'datalynx'),
            'mod/datalynx:editprivilegemanager',
            1
        );
        $this->add_permission_checkbox(
            $mform,
            'editableby_2',
            get_string('visible2', 'datalynx'),
            'mod/datalynx:editprivilegeteacher',
            2
        );
        $this->add_permission_checkbox(
            $mform,
            'editableby_4',
            get_string('visible4', 'datalynx'),
            'mod/datalynx:editprivilegestudent',
            4
        );
        $this->add_permission_checkbox(
            $mform,
            'editableby_8',
            get_string('visible8', 'datalynx'),
            'mod/datalynx:editprivilegeguest',
            8
        );
        $this->add_permission_checkbox(
            $mform,
            'editableby_16',
            get_string('author', 'datalynx'),
            '',
            16,
            false,
            get_string('dynamic_check_author_desc', 'datalynx')
        );
        $this->add_permission_checkbox(
            $mform,
            'editableby_32',
            get_string('mentor', 'datalynx'),
            '',
            32,
            false,
            get_string('dynamic_check_mentor_desc', 'datalynx')
        );

        $permissions = [1, 2, 4, 8, 16, 32];
        foreach ($permissions as $perm) {
            $mform->disabledIf("editableby_{$perm}", 'editable', 'notchecked');
        }

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
        $allfields = $this->dlx->get_fields();
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
        $tempusers = $DB->get_records('user', [], '', 'id, firstname, lastname');

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
     * Get data from the form, ensuring required fields are set.
     *
     * @return object
     */
    public function get_data() {
        $data = parent::get_data();
        if ($data) {
            $permissions = [1, 2, 4, 8, 16, 32];

            $visibletopermission = [];
            foreach ($permissions as $perm) {
                if (!empty($data->{"visibletopermission_{$perm}"})) {
                    $visibletopermission[] = $perm;
                }
                unset($data->{"visibletopermission_{$perm}"});
            }
            $data->visibletopermission = $visibletopermission;

            $editableby = [];
            foreach ($permissions as $perm) {
                if (!empty($data->{"editableby_{$perm}"})) {
                    $editableby[] = $perm;
                }
                unset($data->{"editableby_{$perm}"});
            }
            $data->editableby = $editableby;

            // When editable is unchecked, disabledIf hides the UI widget but the checkbox inputs
            // still submit previously-selected values. Force empty when unchecked.
            if (empty($data->editable)) {
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
     * Set form data, ensuring required fields have defaults.
     *
     * @param array|stdClass $data
     */
    public function set_data($data) {
        if (!isset($data->visibletopermission)) {
            $data->visibletopermission = [];
        }
        if (!isset($data->editableby)) {
            $data->editableby = [];
        }

        $permissions = [1, 2, 4, 8, 16, 32];
        foreach ($permissions as $perm) {
            $data->{"visibletopermission_{$perm}"} = in_array($perm, $data->visibletopermission) ? 1 : 0;
            $data->{"editableby_{$perm}"} = in_array($perm, $data->editableby) ? 1 : 0;
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
     * Validate form data.
     *
     * @param array $data
     * @param array $files
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    public function validation($data, $files) {
        global $DB;
        $errors = [];
        if (!$data['name']) {
            $errors['name'] = "You must supply a value here.";
        }
        if (strpos($data['name'], '|') !== false) {
            $errors['name'] = "Behavior name may not contain the pipe symbol \" | \".";
        }
        if ($data['id'] == 0) {
            // To prevent duplicate renderer names when creating a new renderer.
            if (
                    $DB->record_exists(
                        'datalynx_behaviors',
                        ['name' => $data['name'], 'dataid' => $data['d']]
                    )
            ) {
                $errors['name'] = get_string('duplicatename', 'datalynx');
            }
        } else {
            // To prevent duplicate renderer names when updating existing renderers.
            $sql = "SELECT 'x'
                    FROM {datalynx_behaviors} r
                    WHERE r.name = ? AND r.dataid = ? AND r.id <> ?";
            $params = [$data['name'], $data['d'], $data['id']];
            if ($DB->record_exists_sql($sql, $params)) {
                $errors['name'] = get_string('duplicatename', 'datalynx');
            }
        }
        return $errors;
    }

    /**
     * Get the names of the roles that have a capability allowed in the current context.
     *
     * @param string $capability
     * @return array List of localized role names.
     */
    protected function get_allowed_role_names($capability) {
        $context = $this->dlx->context;
        $allroles = role_get_names($context, ROLENAME_ALIAS, true);
        $roleswithcap = get_roles_with_capability($capability, CAP_ALLOW, $context);
        $matchingrolenames = [];
        foreach ($roleswithcap as $role) {
            if (isset($allroles[$role->id])) {
                $matchingrolenames[] = $allroles[$role->id];
            }
        }
        return $matchingrolenames;
    }

    /**
     * Add a permission checkbox to the form with dynamic feedback (roles or description).
     *
     * @param \MoodleQuickForm $mform
     * @param string $elementname
     * @param string $label
     * @param string $capability
     * @param int $value
     * @param bool $is_capability
     * @param string $desc
     */
    protected function add_permission_checkbox($mform, $elementname, $label, $capability, $value, $is_capability = true, $desc = '') {
        if ($is_capability) {
            $allowedroles = $this->get_allowed_role_names($capability);

            $html = '<div class="d-inline-block align-middle ml-2">';
            $html .= '<div><small class="text-muted">' .
                    get_string('visible_capability', 'datalynx', $capability) . '</small></div>';

            if (empty($allowedroles)) {
                $warningtext = get_string('visible_no_roles_warning', 'datalynx');
                $warningicon = '<i class="fa fa-exclamation-triangle"></i> ';
                $warninghtml = '<span class="badge badge-warning bg-warning text-dark">' .
                        $warningicon . $warningtext . '</span>';
                $html .= '<div class="mt-1">' . $warninghtml . '</div>';
            } else {
                $badges = [];
                foreach ($allowedroles as $rolename) {
                    $badges[] = html_writer::span($rolename, 'badge badge-secondary bg-secondary text-white mr-1');
                }
                $allowedlabel = get_string('visible_allowed_roles', 'datalynx');
                $html .= '<div class="mt-1"><small><strong>' . $allowedlabel . ' </strong>' .
                        implode(' ', $badges) . '</small></div>';
            }
            $html .= '</div>';
        } else {
            $html = '<div class="d-inline-block align-middle ml-2">';
            $html .= '<div><small class="text-muted"><strong>' .
                    get_string('dynamic_check', 'datalynx') . '</strong> ' . $desc . '</small></div>';
            $html .= '</div>';
        }

        $mform->addElement(
            'advcheckbox',
            $elementname,
            $label,
            $html,
            ['group' => 1],
            [0, $value]
        );
    }
}
