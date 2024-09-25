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
use core_form\dynamic_form;

/**
 *
 */
abstract class mod_datalynx_filter_base_form extends dynamic_form {

    protected $_filter = null;
    protected $_customfilter = null;

    /**
     *
     * @var datalynx null
     */
    protected $_df = null;

    public function get_context_for_dynamic_submission(): context {
        //return context_module::instance($this->_df->cm->id);
        return \context_system::instance();
    }

    public function check_access_for_dynamic_submission(): void {
        return;
        //return has_capability('mod/datalynx:edit', $this->get_context_for_dynamic_submission());
        require_capability('moodle/site:config', \context_system::instance());
    }

    public function set_data_for_dynamic_submission(): void {
        $datalynx_id = $this->_ajaxformdata["d"];
        $filter_id = $this->_ajaxformdata["fid"];

        if ($datalynx_id == null || $filter_id == null) {
            return;
        }

        $this->_df = \mod_datalynx\datalynx::get_datalynx_by_instance($datalynx_id);
        $fm = $this->_df->get_filter_manager();
        $this->_filter = $fm->get_filter_from_id($filter_id);

        // Update filter parameters based on the current form data (in order to dynamically render new form fields for filter details):
        if ($this->_ajaxformdata["update"] && confirm_sesskey()) {
            $procesedfilters = $fm->process_filters_for_ajax_refresh('update', $filter_id, $this, true);
            $this->_filter = $procesedfilters[0];
        }
    }

    public function process_dynamic_submission() {

        $datalynx_id = $this->_ajaxformdata["d"];
        $filter_id = $this->_ajaxformdata["fid"];

        if ($datalynx_id == null || $filter_id == null) {
            return;
        }

        $this->_df = \mod_datalynx\datalynx::get_datalynx_by_instance($datalynx_id);
        $fm = $this->_df->get_filter_manager();
        $this->_filter = $fm->get_filter_from_id($filter_id);

        if ($this->_ajaxformdata["update"] && confirm_sesskey()) { // Add/update a new filter.
            $procesedfilters = $fm->process_filters_for_ajax_submission('update', $filter_id, $this, true);
            $this->_filter = $procesedfilters[0];
        }

        return $this->_df->notifications;
    }

    public function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/mod/datalynx/view.php', array('id' => $this->_df->id));
    }

    /*
     *
     */
    public function custom_sort_definition($customsort, $fields, $fieldoptions, $showlabel = false, $customfilter = false) {
        $mform = &$this->_form;

        $diroptions = array(0 => get_string('ascending', 'datalynx'),
                1 => get_string('descending', 'datalynx'));

        $fieldlabel = get_string('filtersortfieldlabel', 'datalynx');
        $count = 0;

        // Add current options.
        if ($customsort) {

            $sortfields = unserialize($customsort);

            foreach ($sortfields as $fieldid => $sortdir) {
                if (empty($fields[$fieldid])) {
                    continue;
                }

                $i = $count + 1;
                $label = $showlabel ? "$fieldlabel$i" : '';

                $optionsarr = array();
                $optionsarr[] = &$mform->createElement('select', 'sortfield' . $count, '', $fieldoptions);
                $optionsarr[] = &$mform->createElement('select', 'sortdir' . $count, '',
                        $diroptions);
                $mform->addGroup($optionsarr, 'sortoptionarr' . $count, $label, ' ', false);
                $mform->setDefault('sortfield' . $count, $fieldid);
                $mform->setDefault('sortdir' . $count, $sortdir);
                $count++;
            }
        }

        // Add 3 more options.
        for ($prevcount = $count; $count < ($prevcount + 3); $count++) {
            $i = $count + 1;
            $label = $showlabel ? "$fieldlabel$i" : '';
            $optionsarr = array();
            $optionsarr[] = &$mform->createElement('select', 'sortfield' . $count, '', $fieldoptions);
            $optionsarr[] = &$mform->createElement('select', 'sortdir' . $count, '', $diroptions);
            $mform->addGroup($optionsarr, 'sortoptionarr' . $count, $label, ' ', false);
            $mform->disabledIf('sortdir' . $count, 'sortfield' . $count, 'eq', 0);
            if ($count > $prevcount) {
                $mform->disabledIf('sortoptionarr' . $count, 'sortfield' . ($count - 1), 'eq', 0);
            }
        }
    }

    /**
     *
     * @param string $customsearch
     * @param array $fields
     * @param array $fieldoptions
     * @param boolean $showlabel
     */
    public function custom_search_definition($customsearch, $fields, $fieldoptions,
            $showlabel = false) {
        $mform = &$this->_form;
        $df = $this->_df;

        $andoroptions = array(0 => get_string('andor', 'datalynx'),
            'AND' => get_string('and', 'datalynx'), 'OR' => get_string('or', 'datalynx'));
        $isnotoptions = array('' => get_string('is', 'datalynx'),
            'NOT' => get_string('not', 'datalynx'));

        $fieldlabel = get_string('filtersearchfieldlabel', 'datalynx');
        $count = 0;

        // Add current options.
        if ($customsearch) {

            $searchfields = unserialize($customsearch);
            // If not from form then the searchfields is aggregated and we need.
            // To flatten them. An aggregated array should have a non-zero key.
            // (fieldid) in the first element.
            if (key($searchfields)) {
                $searcharr = array();
                foreach ($searchfields as $fieldid => $searchfield) {
                    if (empty($fields[$fieldid])) {
                        continue;
                    }

                    foreach ($searchfield as $andor => $searchoptions) {
                        foreach ($searchoptions as $searchoption) {
                            if ($searchoption) {
                                list($not, $operator, $value) = $searchoption;
                                if (is_array($value)) {
                                    $value = json_encode($value);
                                }
                            } else {
                                list($not, $operator, $value) = array('', '', '');
                            }
                            $searcharr[] = array($fieldid, $andor, $not, $operator, $value);
                        }
                    }
                }
                $searchfields = $searcharr;
            }

            foreach ($searchfields as $searchcriterion) {
                if (count($searchcriterion) != 5) {
                    continue;
                }

                $i = $count + 1;
                $label = $showlabel ? "$fieldlabel$i" : '';

                list($fieldid, $andor, $not, $operator, $value) = $searchcriterion;

                $arr = array();
                // And/or option.
                $arr[] = &$mform->createElement('select', 'searchandor' . $count, '', $andoroptions);
                $mform->setDefault('searchandor' . $count, $andor);
                // Search field.
                $arr[] = &$mform->createElement('select', 'searchfield' . $count, '', $fieldoptions);
                $mform->setDefault('searchfield' . $count, $fieldid);
                // Not option.
                $arr[] = &$mform->createElement('select', 'searchnot' . $count, null, $isnotoptions);
                $mform->setDefault('searchnot' . $count, $not);
                // Search operator.
                if ($fieldid) {
                    $operatoroptions = $df->get_field_from_id($fieldid)->get_supported_search_operators();
                }
                $arr[] = &$mform->createElement('select', 'searchoperator' . $count, '',
                        $operatoroptions);
                $mform->setDefault('searchoperator' . $count, $operator);
                // Field search elements.
                // For select options $value is an arry, we have to convert it to string, function param only accepts strings.
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                list($elems, $separators) = $fields[$fieldid]->renderer()->render_search_mode(
                        $mform, $count, $value);

                $arr = array_merge($arr, $elems);
                if ($separators) {
                    $sep = array_merge(array(' ', ' ', ' '), $separators);
                } else {
                    $sep = ' ';
                }
                $mform->addGroup($arr, "customsearcharr$count", $label, $sep, false);

                $count++;
            }
        }

        // Add 3 more options.
        for ($prevcount = $count; $count < ($prevcount + 3); $count++) {
            $i = $count + 1;
            $label = $showlabel ? "$fieldlabel$i" : '';

            $arr = array();
            $arr[] = &$mform->createElement('select', "searchandor$count", '', $andoroptions);
            $arr[] = &$mform->createElement('select', "searchfield$count", '', $fieldoptions);
            $mform->addGroup($arr, "customsearcharr$count", $label, ' ', false);
            $mform->disabledIf('searchfield' . $count, 'searchandor' . $count, 'eq', 0);
            if ($count > $prevcount) {
                $mform->disabledIf("searchoption$count", 'searchandor' . ($count - 1), 'eq', 0);
            }
        }
    }

    /*
     *
     */
    public function customfilter_search_definition($fields, $fieldoptions) {
        $mform = &$this->_form;

        // List user fields.
        $count = 1;
        foreach ($fieldoptions as $fieldid => $fieldname) {

            $label = $fieldname;

            $value = '';

            list($elems, $separators) = $fields[$fieldid]->renderer()->render_search_mode($mform, $count, $value);

            if ($separators) {
                $sep = array_merge(array(' ', ' ', ' '), $separators);
            } else {
                $sep = ' ';
            }
            $mform->addGroup($elems, "customsearcharr$count", $label, $sep, false);

            $count++;
        }
    }

    /**
     */
    public function html() {
        return $this->_form->toHtml();
    }
}

/*
 *
 */

class mod_datalynx_filter_form extends mod_datalynx_filter_base_form {

    public function definition() {}

    /*
     *
     */
    public function definition_after_data() {
        $df = $this->_df;
        $filter = $this->_filter;
        $name = empty($filter->name) ? get_string('filternew', 'datalynx') : $filter->name;
        $description = empty($filter->description) ? '' : $filter->description;
        $visible = !isset($filter->visible) ? 1 : $filter->visible;

        $fields = $df->get_fields();
        $fieldoptions = array(0 => get_string('choose')) + $df->get_fields(array('entry'), true);

        $mform = &$this->_form;

        // Buttons.
        $this->add_action_buttons(true);

        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Name and description.
        $mform->addElement('text', 'name', get_string('name'));
        $mform->addElement('text', 'description', get_string('description'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->setType('description', PARAM_TEXT);
        $mform->setDefault('name', $name);
        $mform->setDefault('description', $description);

        // Visibility.
        $visibilityoptions = array(0 => 'hidden', 1 => 'visible');
        $mform->addElement('select', 'visible', get_string('visible'), $visibilityoptions);
        $mform->setDefault('visible', $visible);

        $mform->addElement('header', 'filterhdr', get_string('viewfilter', 'datalynx'));
        $mform->setExpanded('filterhdr');

        // Entries per page.
        $options = array(0 => get_string('choose'), 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6,
                7 => 7, 8 => 8, 9 => 9, 10 => 10, 15 => 15, 20 => 20, 30 => 30, 40 => 40, 50 => 50,
                100 => 100, 200 => 200, 300 => 300, 400 => 400, 500 => 500, 1000 => 1000);
        $mform->addElement('select', 'perpage', get_string('viewperpage', 'datalynx'), $options);
        $mform->setDefault('perpage', $filter->perpage);

        // Selection method.
        $options = array(0 => get_string('filterbypage', 'datalynx'), 1 => get_string('random', 'datalynx'));
        $mform->addElement('select', 'selection', get_string('filterselection', 'datalynx'), $options);
        $mform->setDefault('selection', $filter->selection);
        $mform->disabledIf('selection', 'perpage', 'eq', '0');

        // Group by.
        $groupbyfieldoptions = array(0 => get_string('choose'));
        foreach ($fields as $field) {
            if ($field->supports_group_by()) {
                $groupbyfieldoptions[$field->id()] = $field->name();
            }
        }
        $mform->addElement('select', 'groupby', get_string('filtergroupby', 'datalynx'), $groupbyfieldoptions);
        $mform->setDefault('groupby', $filter->groupby);

        // Search.
        $mform->addElement('text', 'search', get_string('search'));
        $mform->setType('search', PARAM_TEXT);
        $mform->setDefault('search', $filter->search);

        // Custom sort.
        $mform->addElement('header', 'customsorthdr', get_string('filtercustomsort', 'datalynx'));
        $mform->setExpanded('customsorthdr');

        $this->custom_sort_definition($filter->customsort, $fields, $fieldoptions, true);

        // Custom search.
        $mform->addElement('header', 'customsearchhdr', get_string('filtercustomsearch', 'datalynx'));
        $mform->setExpanded('customsearchhdr');

        $this->custom_search_definition($filter->customsearch, $fields, $fieldoptions, true);

        // Hidden fields to track the Datalynx instance and the filter id.
        if ($df !== null) {
            $mform->addElement('hidden', 'd', $df->id());
        }
        if ($filter !== null) {
            $mform->addElement('hidden', 'fid', $filter->id);
        }
        $mform->addElement('hidden', 'refreshonly', '0');
        $mform->addElement('hidden', 'update', '1');

        // Buttons.
        $this->add_action_buttons(true);
    }

    public function get_ajax_form_data() {
        // Convert to stdClass:
        return json_decode(json_encode($this->_ajaxformdata));
    }

    /**
     * @param array $data
     * @param array $files
     * @return array
     * @throws coding_exception
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (array_key_exists('refreshonly', $data)) {
            if ($data['refreshonly'] == '0') {

                $df = $this->_df;
                $filter = $this->_filter;

                // Validate unique name.
                if (empty($data['name']) || $df->name_exists('filters', $data['name'], $filter->id)) {
                    $errors['name'] = get_string('invalidname', 'datalynx',
                            get_string('filter', 'datalynx'));
                }
            } else {
                // If we do not return any error after a submission, the form will 
                // be regarded as submitted and will render empty. 
                // We need to return a dummy error to prevent this.
                // This also prevents process_dynamic_submission from being executed in this case, 
                // as the form gets no validated flag:
                $errors['dummy_error_for_refreshing'] = 'dummy_error_for_refreshing';
            }
        }
        return $errors;
    }
}

/*
 *
 */

class mod_datalynx_advanced_filter_form extends mod_datalynx_filter_base_form {

    /*
     * Definition of the advanced filter form which is part of a view
     */
    public function definition() {
        $filter = $this->_filter;
        $view = $this->_customdata['view'];

        $name = empty($filter->name) ? get_string('filternew', 'datalynx') : $filter->name;

        // Get the fields of this view.
        $fields = $view->get_view_fields(true);
        $fieldoptions = array(0 => get_string('choose'));
        foreach ($fields as $fieldid => $field) {
            $fieldoptions[$fieldid] = $field->name();
        }

        $mform = &$this->_form;

        $mform->addElement('header', 'advancedfilterhdr', get_string('filteradvanced', 'datalynx'));
        $mform->setExpanded('advancedfilterhdr', false);

        // Name and description.
        $mform->addElement('text', 'name', get_string('name'));
        $mform->setType('name', PARAM_TEXT);
        $mform->setDefault('name', $name);

        // Entries per page.
        $options = array(0 => get_string('choose'), 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6,
                7 => 7, 8 => 8, 9 => 9, 10 => 10, 15 => 15, 20 => 20, 30 => 30, 40 => 40, 50 => 50,
                100 => 100, 200 => 200, 300 => 300, 400 => 400, 500 => 500, 1000 => 1000);
        $mform->addElement('select', 'uperpage', get_string('viewperpage', 'datalynx'), $options);
        $mform->setDefault('uperpage', $filter->perpage);

        // Search.
        $mform->addElement('text', 'search', get_string('search'));
        $mform->setType('search', PARAM_TEXT);
        $mform->setDefault('search', $filter->search);

        // Custom sort.
        $this->custom_sort_definition($filter->customsort, $fields, $fieldoptions, true);

        // Custom search.
        $this->custom_search_definition($filter->customsearch, $fields, $fieldoptions, true);

        // Save button.
        $grp = array();
        $grp[] = $mform->createElement('submit', 'savebutton', get_string('savechanges'));
        $grp[] = $mform->createElement('submit', 'newbutton', get_string('newfilter', 'filters'));
        $mform->addGroup($grp, "afiltersubmit_grp", null, ' ', false);
    }
}

/**
 * Class customfilter_frontend_form to display the customfilter options in browse mode
 *
 */
class mod_datalynx_customfilter_frontend_form extends mod_datalynx_filter_base_form {

    /*
     * This customfilter form  predefined by the admin is displayed
     */
    public function definition() {
        $view = $this->_customdata['view'];

        if (!$customfilter = $this->_customfilter) {
            throw new moodle_exception('nocustomfilter', 'datalynx');
        }

        $customfilterfieldlistfields = array();
        if ($customfilter->fieldlist) {
            $customfilterfieldlistfields = json_decode($customfilter->fieldlist);
        }
        $fields = $view->get_view_fields();
        $fieldoptions = array();
        $sortfields = array();
        foreach ($fields as $fieldid => $field) {
            $select = false;
            foreach ($customfilterfieldlistfields as $fid => $listfield) {
                if ($field->field->id == $fid) {
                    $select = true;
                    if ($listfield->sortable) {
                        $sortfields[$fid] = $listfield->name;
                    }
                    break;
                }
            }
            if ($select == false) {
                switch ($field->field->name) {
                    case (get_string("approved", "datalynx")):
                        if ($customfilter->approve) {
                            $select = true;
                        }
                        break;
                    case (get_string("timecreated", "datalynx")):
                        if ($customfilter->timecreated) {
                            $select = true;
                        }
                        if ($customfilter->timecreated_sortable) {
                            $sortfields[$fieldid] = $field->field->name;
                        }
                        break;
                    case (get_string("timemodified", "datalynx")):
                        if ($customfilter->timemodified) {
                            $select = true;
                        }
                        if ($customfilter->timemodified_sortable) {
                            $sortfields[$fieldid] = $field->field->name;
                        }
                        break;
                    case (get_string("status", "datalynx")):
                        if ($customfilter->status) {
                            $select = true;
                        }
                        break;
                }
            }
            if ($select) {
                $fieldoptions[$fieldid] = $field->field->name;
            }
        }

        $mform = &$this->_form;
        $mform->addElement('header', 'collapseCustomfilter', get_string('search'));
        $mform->setExpanded('collapseCustomfilter', false);

        if ($customfilter->fulltextsearch) {
            $mform->addElement('text', 'search', get_string('search'));
            $mform->setType('search', PARAM_TEXT);
        }

        // Search for author.
        if (isset($customfilter->authorsearch) && $customfilter->authorsearch) {

            // Add users that have written an entry in the current datalynx instance to list.
            global $DB, $PAGE;
            $entryauthors = $DB->get_records_sql('SELECT DISTINCT userid, firstname, lastname
                FROM {datalynx_entries}
                INNER JOIN {user} on {datalynx_entries}.userid = {user}.id
                WHERE {datalynx_entries}.dataid = '. $this->_df->id() .';');

            $menu = array();
            foreach ($entryauthors as $userid => $author) {
                $menu[$userid] = $author->firstname . " " . $author->lastname;
            }
            $options = array('multiple' => true);
            $mform->addElement('autocomplete', 'authorsearch', get_string('authorsearch', 'datalynx'), $menu, $options);
            $mform->setType('authorsearch', PARAM_INT);
        }

        // Custom search.
        if ($customfilter->fieldlist) {
            $this->customfilter_search_definition($fields, $fieldoptions);
        }

        if (!empty($sortfields)) {
            // Important, keep fieldids intact.
            $sortfields = array(0 => get_string('choosedots')) + $sortfields;

            $grp = array();
            $grp[] = $mform->createElement('select', 'customfiltersortfield', '', $sortfields);
            $directions = array( "0" => get_string('asc'), "1" => get_string('desc'));

            $grp[] = $mform->createElement('select', 'customfiltersortdirection', '', $directions);
            $mform->addGroup($grp, "customfiltersort_grp", get_string('sortby'), ' ', false);
        }

        // Show buttons in line with each other.
        $buttonarray = array();
        $buttonarray[] = &$mform->createElement('submit', 'customsearch', get_string("search"));

        // Add a button that resets all custom filter values at once.
        $clearcustomsearch = '<a class="btn btn-secondary" href="';
        $clearcustomsearch .= new moodle_url('/mod/datalynx/view.php',
            array('id' => $this->_df->cm->id, 'view' => $view->view->id, 'filter' => 0));
        $clearcustomsearch .= '"> ' . get_string('resetsettings', 'datalynx') . '</a>';
        $buttonarray[] = &$mform->createElement('static', 'clearcustomsearch', '',  $clearcustomsearch);

        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
    }
}
