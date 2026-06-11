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

namespace mod_datalynx\form;

use html_writer;
use mod_datalynx\local\field\datalynxfield_behavior;

/**
 * Shared element builders and data transforms for the field-behavior forms.
 *
 * Used by both the legacy full-page {@see datalynxfield_behavior_form} and the AJAX
 * {@see datalynxfield_behavior_dynamic_form} so they render identical elements and serialise behaviors
 * the same way. The host class must provide a `get_dlx()` returning a {@see \mod_datalynx\datalynx},
 * expose the standard moodleform `$_form`, and hold a `$storedconditions` array.
 *
 * @package mod_datalynx
 * @copyright 2026 David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait behavior_form_elements {
    /** @var int Maximum number of availability condition rows offered in the form. */
    private static $maxconditions = 5;

    /** @var array Stored conditions (from set_data) used to seed the dynamic condition rows. */
    private array $storedconditions = ['match' => 'all', 'rules' => []];

    /**
     * Build the static behavior form elements (everything except the dynamic condition rows).
     *
     * @param bool $new Whether this is a new (unsaved) behavior.
     * @return void
     */
    protected function behavior_definition(bool $new) {
        $mform = &$this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'd', $this->get_dlx()->id());
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
            get_string('dynamiccheckauthor_desc', 'datalynx')
        );
        $this->add_permission_checkbox(
            $mform,
            'visibletopermission_32',
            get_string('mentor', 'datalynx'),
            '',
            32,
            false,
            get_string('dynamiccheckmentor_desc', 'datalynx')
        );

        // Interface for single user, this overrules other visibility options.
        $allusers = $this->get_allusers();
        $mform->addElement(
            'autocomplete',
            'visibletouser',
            get_string('otheruser', 'datalynx'),
            $allusers,
            ["multiple" => true]
        );
        $mform->setType('visibletouser', PARAM_INT);

        // Interface for teammemberselect fields.
        $teammemberselect = $this->get_teammemberselect_fields();
        $mform->addElement(
            'autocomplete',
            'visibletoteammember',
            get_string('teammemberselect', 'datalynx'),
            $teammemberselect,
            ["multiple" => true]
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
            get_string('dynamiccheckauthor_desc', 'datalynx')
        );
        $this->add_permission_checkbox(
            $mform,
            'editableby_32',
            get_string('mentor', 'datalynx'),
            '',
            32,
            false,
            get_string('dynamiccheckmentor_desc', 'datalynx')
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

        $mform->addElement('advcheckbox', 'editableafterfinal', get_string('editableafterfinal', 'datalynx'));
        $mform->addHelpButton('editableafterfinal', 'editableafterfinal', 'datalynx');
        if ($new) {
            $mform->setDefault('editableafterfinal', false);
        }
        $mform->disabledIf('editableafterfinal', 'editable', 'notchecked');

        // AVAILABILITY CONDITIONS.
        // The individual condition rows (operator + value widgets, which depend on the chosen source
        // field) are built in definition_after_data() so they can reuse each field's own search widget.
        $mform->addElement('header', 'conditionsheader', get_string('conditions', 'datalynx'));
        $mform->addHelpButton('conditionsheader', 'conditions', 'datalynx');
        $mform->setExpanded('conditionsheader');

        $mform->addElement('select', 'conditionmatch', get_string('conditionmatch', 'datalynx'), [
                'all' => get_string('conditionmatchall', 'datalynx'),
                'any' => get_string('conditionmatchany', 'datalynx'),
        ]);
        $mform->setDefault('conditionmatch', 'all');

        $mform->registerNoSubmitButton('reloadconditions');
    }

    /**
     * Build the dynamic availability-condition rows.
     *
     * Each row reuses the source field's own search machinery: the operator list comes from
     * {@see datalynxfield_base::get_supported_search_operators()} and the value input from the field
     * renderer's {@see datalynxfield_renderer::render_search_mode()}. They are built after data because
     * they depend on the field selected in the same form.
     *
     * @param bool $addactionbuttons Whether to append the standard Save/Cancel buttons (legacy form only).
     * @return void
     */
    protected function behavior_definition_after_data(bool $addactionbuttons) {
        $mform = &$this->_form;
        $dlx = $this->get_dlx();

        $sourcefields = $this->get_condition_source_fields();
        $isnotoptions = ['' => get_string('is', 'datalynx'), 'NOT' => get_string('not', 'datalynx')];
        $rules = $this->storedconditions['rules'] ?? [];
        $issubmitted = $mform->isSubmitted();

        for ($i = 0; $i < self::$maxconditions; $i++) {
            $storedrule = $rules[$i] ?? null;

            // Determine the chosen source field: submitted value wins, otherwise the stored rule.
            $submitted = $mform->getSubmitValue("condfield$i");
            if ($submitted !== null && $submitted !== '') {
                $fieldid = (int) $submitted;
            } else {
                $fieldid = $storedrule ? (int) $storedrule['sourcefieldid'] : 0;
            }
            $field = $fieldid ? $dlx->get_field_from_id($fieldid) : false;

            $rowelements = [];
            $rowelements[] = $mform->createElement('select', "condfield$i", '', $sourcefields);
            $rowelements[] = $mform->createElement('select', "condnot$i", '', $isnotoptions);

            $value = '';
            if ($field && in_array($field->type, datalynxfield_behavior::CONDITION_SOURCE_TYPES, true)) {
                $rowelements[] = $mform->createElement(
                    'select',
                    "searchoperator$i",
                    '',
                    $field->get_supported_search_operators()
                );
                if (!$issubmitted && $storedrule && isset($storedrule['value'])) {
                    $value = is_array($storedrule['value']) ? json_encode($storedrule['value']) : (string) $storedrule['value'];
                }
                [$valueelements] = $field->renderer()->render_search_mode($mform, $i, $value);
                $rowelements = array_merge($rowelements, $valueelements);
            }

            $label = get_string('conditionrowlabel', 'datalynx', $i + 1);
            $mform->addGroup($rowelements, "condrow$i", $label, ' ', false);
            $mform->setType("condfield$i", PARAM_INT);

            // Seed defaults from the stored rule on initial (non-submitted) display.
            if (!$issubmitted) {
                $mform->setDefault("condfield$i", $fieldid);
                if ($storedrule) {
                    $mform->setDefault("condnot$i", $storedrule['not'] ?? '');
                    if ($field) {
                        $mform->setDefault("searchoperator$i", $storedrule['operator'] ?? '');
                    }
                }
            }
        }

        $mform->addElement('submit', 'reloadconditions', get_string('conditionreload', 'datalynx'));

        if ($addactionbuttons) {
            $this->add_action_buttons();
        }
    }

    /**
     * Collapse submitted permission checkboxes and condition rows into the behavior data structure.
     *
     * @param \stdClass $data Validated form data from parent::get_data().
     * @return \stdClass
     */
    protected function transform_behavior_data($data) {
        $dlx = $this->get_dlx();
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
            $data->editableafterfinal = false;
        }
        if (!isset($data->required)) {
            $data->required = false;
        }
        if (!isset($data->editableafterfinal)) {
            $data->editableafterfinal = false;
        }

        // Collapse the dynamic condition rows into a single conditions structure.
        $rules = [];
        for ($i = 0; $i < self::$maxconditions; $i++) {
            $fieldid = (int) ($data->{"condfield$i"} ?? 0);
            if (!$fieldid) {
                continue;
            }
            $field = $dlx->get_field_from_id($fieldid);
            if (!$field || !in_array($field->type, datalynxfield_behavior::CONDITION_SOURCE_TYPES, true)) {
                continue;
            }
            $operator = isset($data->{"searchoperator$i"}) ? $data->{"searchoperator$i"} : '';
            $not = !empty($data->{"condnot$i"}) ? 'NOT' : '';
            $value = $field->parse_search($data, $i);
            // Skip rows whose operator needs a value but none was provided.
            if ($field->get_argument_count($operator) > 0 && ($value === false || $value === '' || $value === null)) {
                continue;
            }
            if ($value === false) {
                $value = '';
            }
            $rules[] = ['sourcefieldid' => $fieldid, 'not' => $not, 'operator' => $operator, 'value' => $value];
        }
        $data->conditions = ['match' => $data->conditionmatch ?? 'all', 'rules' => $rules];

        return $data;
    }

    /**
     * Expand stored behavior data into the per-permission checkbox fields and stash the conditions.
     *
     * @param array|\stdClass $data
     * @return void
     */
    protected function prepare_behavior_data($data) {
        if (!isset($data->visibletopermission)) {
            $data->visibletopermission = [];
        }
        if (!isset($data->editableby)) {
            $data->editableby = [];
        }

        $permissions = [1, 2, 4, 8, 16, 32];
        foreach ($permissions as $perm) {
            $data->{"visibletopermission_{$perm}"} = in_array($perm, $data->visibletopermission) ? $perm : 0;
            $data->{"editableby_{$perm}"} = in_array($perm, $data->editableby) ? $perm : 0;
        }

        if (empty($data->editableby)) {
            $data->editable = false;
        } else {
            $data->editable = true;
        }
        if (!isset($data->required)) {
            $data->required = false;
        }
        if (!isset($data->editableafterfinal)) {
            $data->editableafterfinal = false;
        }

        // Stash conditions so definition_after_data() can seed the dynamic rows, and preselect the match mode.
        if (isset($data->conditions) && is_array($data->conditions)) {
            $this->storedconditions = $data->conditions + ['match' => 'all', 'rules' => []];
        } else {
            $this->storedconditions = ['match' => 'all', 'rules' => []];
        }
        $data->conditionmatch = $this->storedconditions['match'] ?? 'all';
    }

    /**
     * Shared validation: name required, no pipe symbol, unique within the datalynx instance.
     *
     * @param array $data
     * @return array
     */
    protected function validate_behavior($data) {
        global $DB;
        $errors = [];
        if (!$data['name']) {
            $errors['name'] = "You must supply a value here.";
        }
        if (strpos($data['name'], '|') !== false) {
            $errors['name'] = "Behavior name may not contain the pipe symbol \" | \".";
        }
        if ($data['id'] == 0) {
            if ($DB->record_exists('datalynx_behaviors', ['name' => $data['name'], 'dataid' => $data['d']])) {
                $errors['name'] = get_string('duplicatename', 'datalynx');
            }
        } else {
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
     * Get the source-field options for condition rows, limited to supported source field types.
     *
     * @return array fieldid => field name (with a leading "choose" entry keyed 0).
     */
    protected function get_condition_source_fields(): array {
        $options = [0 => get_string('choosedots')];
        foreach ($this->get_dlx()->get_fields() as $fieldid => $field) {
            if (in_array($field->type, datalynxfield_behavior::CONDITION_SOURCE_TYPES, true)) {
                $options[$fieldid] = $field->field->name;
            }
        }
        return $options;
    }

    /**
     * Get all teammemberselect fields in datalynx.
     *
     * @return array fieldid => fieldname
     */
    public function get_teammemberselect_fields(): array {
        $allfields = $this->get_dlx()->get_fields();
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
     *
     * @return array with userid -> firstname lastname.
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
     * Get the names of the roles that have a capability allowed in the current context.
     *
     * @param string $capability
     * @return array List of localized role names.
     */
    protected function get_allowed_role_names($capability) {
        $context = $this->get_dlx()->context;
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
     * @param bool $iscapability
     * @param string $desc
     */
    protected function add_permission_checkbox(
        $mform,
        $elementname,
        $label,
        $capability,
        $value,
        $iscapability = true,
        $desc = ''
    ) {
        if ($iscapability) {
            $allowedroles = $this->get_allowed_role_names($capability);

            $html = '<div class="d-inline-block align-middle ml-2">';
            $html .= '<div><small class="text-muted">' .
                    get_string('visiblecapability', 'datalynx', $capability) . '</small></div>';

            if (empty($allowedroles)) {
                $warningtext = get_string('visiblenoroleswarning', 'datalynx');
                $warningicon = '<i class="fa fa-exclamation-triangle"></i> ';
                $warninghtml = '<span class="badge badge-warning bg-warning text-dark">' .
                        $warningicon . $warningtext . '</span>';
                $html .= '<div class="mt-1">' . $warninghtml . '</div>';
            } else {
                $badges = [];
                foreach ($allowedroles as $rolename) {
                    $badges[] = html_writer::span($rolename, 'badge badge-secondary bg-secondary text-white mr-1');
                }
                $allowedlabel = get_string('visibleallowedroles', 'datalynx');
                $html .= '<div class="mt-1"><small><strong>' . $allowedlabel . ' </strong>' .
                        implode(' ', $badges) . '</small></div>';
            }
            $html .= '</div>';
        } else {
            $html = '<div class="d-inline-block align-middle ml-2">';
            $html .= '<div><small class="text-muted"><strong>' .
                    get_string('dynamiccheck', 'datalynx') . '</strong> ' . $desc . '</small></div>';
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
