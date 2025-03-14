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
 * @package datalynxview
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * This class provides the form for editing the general settings of a view, that are common for all
 * view types.
 * It should be extended by the specific view type in order to reflect the specific view type
 * settings
 */
class datalynxview_base_form extends moodleform {

    protected $_view = null;

    protected $_df = null;

    public function __construct($view, $action = null, $customdata = null, $method = 'post', $target = '',
            $attributes = null, $editable = true) {
        $this->_view = $view;
        $this->_df = $view->get_dl();
        $attributes['id'] = 'datalynx-view-edit-form';
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable);
    }

    /**
     */
    public function definition() {
        global $CFG, $DB;
        $view = $this->_view;
        $df = $this->_df;
        $editoroptions = $view->editors();
        $mform = &$this->_form;

        // Buttons.
        $this->add_action_buttons();

        // General.
        $mform->addElement('header', 'general', get_string('viewgeneral', 'datalynx'));
        $mform->addHelpButton('general', 'viewgeneral', 'datalynx');

        // Name and description.
        $mform->addElement('text', 'name', get_string('name'), array('size' => '64'));
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'description', get_string('description'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_NOTAGS);
            $mform->setType('description', PARAM_NOTAGS);
        } else {
            $mform->setType('name', PARAM_TEXT);
            $mform->setType('description', PARAM_TEXT);
        }

        $visiblegrp = [];
        $visiblegrp[] = $mform->createElement('advcheckbox', 'visible1', '', get_string('visible1', 'datalynx'), ['group' => 1], [0, 1]);
        $visiblegrp[] = $mform->createElement('advcheckbox', 'visible2', '', get_string('visible2', 'datalynx'), ['group' => 1], [0, 2]);
        $visiblegrp[] = $mform->createElement('advcheckbox', 'visible4', '', get_string('visible4', 'datalynx'), ['group' => 1], [0, 4]);
        $visiblegrp[] = $mform->createElement('advcheckbox', 'visible8', '', get_string('visible8', 'datalynx'), ['group' => 1], [0, 8]);
        $mform->addGroup($visiblegrp, 'visiblegroup', get_string('visibleto', 'datalynx'), null, false);

        // Filter.
        $filtersmenu = $df->get_filter_manager()->get_filters(null, true);
        if (!$filtersmenu) {
            $filtersmenu = array(0 => get_string('filtersnonedefined', 'datalynx'));
        } else {
            $filtersmenu = array(0 => get_string('choose')) + $filtersmenu;
        }
        $mform->addElement('select', '_filter', get_string('viewfilter', 'datalynx'), $filtersmenu);
        $mform->setDefault('_filter', 0);

        // Overridefilter.
        $mform->addElement('advcheckbox', 'param5', get_string('viewfilteroverride', 'datalynx'),
            get_string('viewfoverride', 'datalynx'));
        $mform->addHelpButton('param5', 'viewfoverride', 'datalynx');
        $mform->setType('param5', PARAM_INT);
        $mform->setDefault('param5', 0);

        $mform->addElement('header', 'redirectsettings', get_string('redirectsettings', 'datalynx'));
        $mform->addHelpButton('redirectsettings', 'redirectsettings', 'datalynx');
        $mform->addElement('select', 'param10', get_string('redirectto', 'datalynx'), $this->get_view_menu());
        $mform->setDefault('param10', $DB->get_field('datalynx', 'defaultview', array('id' => $this->_df->id())));
        $mform->setType('param10', PARAM_INT);

        // View specific definition.
        $this->view_definition_before_gps();

        // View template: header and editor for view template.
        $mform->addElement('header', 'viewtemplatehdr', get_string('viewtemplate', 'datalynx'));
        $mform->addHelpButton('viewtemplatehdr', 'viewtemplate', 'datalynx');
        $mform->addElement('editor', 'esection_editor', '', null, $editoroptions['section']);
        $this->add_tags_selector('esection_editor', 'general');

        // View specific definition.
        $this->view_definition_after_gps();

        // Buttons.
        $this->add_action_buttons();
    }

    public function get_data() {
        $data = parent::get_data();
        if (isset($data)) {
            $visiblesum = 0;
            $visiblesum += (int)$data->visible1;
            $visiblesum += (int)$data->visible2;
            $visiblesum += (int)$data->visible4;
            $visiblesum += (int)$data->visible8;
            // Store the sum in the visible field.
            $data->visible = $visiblesum;
        }
        if (isset($data->_filter)) {
            $data->filter = $data->_filter;
            unset($data->_filter);
        }
        return $data;
    }

    public function set_data($data) {
        // The checkboxes of the view form have an initial value of unchecked.
        $data->visible1 = 0;
        $data->visible2 = 0;
        $data->visible4 = 0;
        $data->visible8 = 0;
        if ($data->visible > 0) {
            // Data saved is the sum of all checkbox values. Based on the sum we find out which checkboxes are checked.
            $sum = $data->visible;
            // Define the values corresponding to each checkbox.
            $checkboxvalues = [
                    'visible1' => 1,
                    'visible2' => 2,
                    'visible4' => 4,
                    'visible8' => 8,
            ];
            // Loop through the checkbox values in reverse order.
            foreach (array_reverse($checkboxvalues) as $checkbox => $value) {
                if ($sum >= $value) {
                    $data->$checkbox = $value; // Mark checkbox as checked.
                    $sum -= $value; // Subtract the value from the sum.
                }
            }
        }
        if (isset($data->filter)) {
            $data->_filter = $data->filter;
            unset($data->filter);
        }
        parent::set_data($data);
    }

    public function get_view_menu() {
        global $DB;
        $viewid = $this->_view->view->id;
        $dataid = $this->_df->id();
        $query = "SELECT dv.id, dv.name
                    FROM {datalynx_views} dv
                   WHERE dv.dataid = :dataid";
        $dviewid = $DB->get_field('datalynx', 'defaultview', array('id' => $dataid));
        $eviewid = $DB->get_field('datalynx', 'singleedit', array('id' => $dataid));
        $mviewid = $DB->get_field('datalynx', 'singleview', array('id' => $dataid));
        $menu = $DB->get_records_sql_menu($query, array('dataid' => $dataid));
        if (isset($menu[$dviewid])) {
            $menu[$dviewid] .= ' ' . get_string('targetviewdefault', 'datalynx');
        }
        if (isset($menu[$eviewid])) {
            $menu[$eviewid] .= ' ' . get_string('targetviewedit', 'datalynx');
        }
        if (isset($menu[$mviewid])) {
            $menu[$mviewid] .= ' ' . get_string('targetviewmore', 'datalynx');
        }
        if (!$viewid) {
            $menu = array(0 => get_string('targetviewthisnew', 'datalynx')) + $menu;
        } else {
            $menu[$viewid] .= ' ' . get_string('targetviewthis', 'datalynx');
        }
        return $menu;
    }

    /**
     * To be used by a specific view
     * Settings that apply before the "view template"
     */
    public function view_definition_before_gps() {
    }

    /**
     * To be used by a specific view
     * Settings that apply after the "view template"
     */
    public function view_definition_after_gps() {
    }

    /**
     * override standard moodle action buttons
     *
     * @see moodleform::add_action_buttons()
     */
    public function add_action_buttons($cancel = true, $submit = null) {
        $mform = &$this->_form;

        $buttonarray = [];
        // Save and display.
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        // Save and continue.
        $buttonarray[] = &$mform->createElement('submit', 'submitreturnbutton',
                get_string('savecontinue', 'datalynx'));
        // Reset to default.
        $buttonarray[] = &$mform->createElement('submit', 'resetdefaultbutton',
                get_string('viewresettodefault', 'datalynx'));
        $mform->registerNoSubmitButton('resetdefaultbutton');
        // Switch editor.
        // Cancel.
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Prepares dropdown menu for inserting tags in the editorfield, that is usually placed above
     * the dropdown menu
     * Selecting a dropdown option places a tag into the editor field
     *
     * @param string $editorname
     * @param string $tagstype
     */
    public function add_tags_selector($editorname, $tagstype) {
        $view = $this->_view;
        $mform = &$this->_form;
        switch ($tagstype) {
            case 'general':
                $tags = $view->patternclass()->get_menu();
                $label = get_string('viewgeneraltags', 'datalynx');
                break;

            case 'field':
                $tags = $view->field_tags();
                $label = get_string('viewfieldtags', 'datalynx');
                break;

            case 'character':
                $tags = $view->character_tags();
                $label = get_string('viewcharactertags', 'datalynx');
                break;

            default:
                $tags = null;
        }

        if (!empty($tags)) {
            $name = "{$editorname}_{$tagstype}_tag_menu";
            $grp = [];
            $grp[] = &$mform->createElement('html',
                    html_writer::start_tag('div', array('class' => 'fitem')));
            $grp[] = &$mform->createElement('html',
                    '<div class="fitemtitle"><label>' . $label . '</label></div>');
            $grp[] = &$mform->createElement('html',
                    '<div class="felement fselect">' .
                    html_writer::select($tags, $name, '', array('' => 'choosedots'),
                            array('id' => $name)) . '</div>');
            $grp[] = &$mform->createElement('html', html_writer::end_tag('div'));
            $mform->addGroup($grp, "{$editorname}{$tagstype}tagsgrp", '', array(' '), false);
        }
    }

    /**
     * Process saved data before it is put into the form.
     *
     * @param $data
     * @return void
     */
    public function data_preprocessing(&$data) {
    }

    /**
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $view = $this->_view;
        $df = $this->_df;

        // Check if the view name is already used.
        if ($df->name_exists('views', $data['name'], $view->id())) {
            $errors['name'] = get_string('invalidname', 'datalynx', get_string('view', 'datalynx'));
        }

        // Check if a field is used multiple times in entryview.
        $entryview = '';
        if (array_key_exists('eparam2_editor', $data)) {
            $entryview = $data['eparam2_editor']['text'];
        }

        // We check if fieldgroups is used multiple times or if subfields are repeated.
        if (array_key_exists('Fieldgroups', $view->field_tags())) {

            $visiblefieldgroups = 0;
            foreach ($view->field_tags()['Fieldgroups']['Fieldgroups'] as $fieldgroup) {

                // Stop if the fieldgroup is not used in this entryview.
                if (strpos($entryview, $fieldgroup) === false) {
                    continue;
                }

                $visiblefieldgroups++;

                $fieldid = array_search(substr($fieldgroup, 2, -2), $df->get_fieldnames());
                $subfields = $df->get_field_from_id($fieldid);

                $lookup = '';
                foreach ($subfields->fieldids as $subfieldid) {
                    $subfield = $df->get_field_from_id($subfieldid);
                    $lookup .= " [[".$subfield->field->name."]]";
                }

                // Find in view and append tags for individual fields.
                $entryview = str_replace($fieldgroup, $lookup, $entryview);
            }

            // Don't allow multiple visible fieldgroups in a view.
            if ($visiblefieldgroups > 1) {
                $errors['eparam2_editor'] = get_string('viewmultiplefieldgroups', 'datalynx');
            }

        }

        // Normalise fields that have filters or behaviours attached.
        $regex = $replace = [];
        $regex[0] = '/:.*?]]/';
        $regex[1] = '/\|.*?]]/'; // Behaviours and renderers.
        $replace[0] = ']]';
        $replace[1] = ']]';
        $entryview = preg_replace($regex, $replace, $entryview);
        $fields = $view->field_tags();

        if (!empty($fields['Fields'])) {
            foreach ($fields['Fields']['Fields'] as $field) {

                // Error when we find more than one instance of this tag.
                if (is_array($field)) {

                    // Make sure multiple errors are shown.
                    if (!array_key_exists('eparam2_editor', $errors)) {
                        $errors['eparam2_editor'] = get_string('viewrepeatedfields', 'datalynx', substr($field[0], 2, -2));
                    } else {
                        $errors['eparam2_editor'] .= "<br>" . get_string('viewrepeatedfields', 'datalynx', substr($field[0], 2, -2));
                    }

                }
            }
        }
        return $errors;
    }
}
