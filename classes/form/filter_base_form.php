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

use context;
use core_form\dynamic_form;
use mod_datalynx\datalynx;
use moodle_url;

/**
 *
 */
abstract class filter_base_form extends dynamic_form {

    protected $_filter = null;
    protected $_customfilter = null;

    /**
     *
     * @var datalynx null
     */
    protected ?datalynx $_df = null;

    public function get_context_for_dynamic_submission(): context {
        //return context_module::instance($this->_df->cm->id);
        return \context_system::instance();
    }

    public function check_access_for_dynamic_submission(): void {
        return;
        //return has_capability('mod/datalynx:edit', $this->get_context_for_dynamic_submission());
        require_capability('moodle/site:config', \context_system::instance());
    }

    /**
     * Set initial data.
     *
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        $datalynxid = $this->_ajaxformdata["d"];
        $filterid = $this->_ajaxformdata["fid"];

        if ($datalynxid == null || $filterid == null) {
            return;
        }

        $this->_df = datalynx::get_datalynx_by_instance($datalynxid);
        $fm = $this->_df->get_filter_manager();
        $this->_filter = $fm->get_filter_from_id($filterid);
        if ($this->_ajaxformdata["update"] && confirm_sesskey()) {
            $procesedfilters = $fm->process_filters_for_ajax_refresh('update', $filterid, $this, true);
            $this->_filter = $procesedfilters[0];
        }
    }

    /**
     * Process submitted data.
     *
     * @return array|array[]|mixed|void
     */
    public function process_dynamic_submission(): array {
        $datalynxid = $this->_ajaxformdata["d"];
        $filterid = $this->_ajaxformdata["fid"];

        if ($datalynxid == null || $filterid == null) {
            return [];
        }

        $this->_df = datalynx::get_datalynx_by_instance($datalynxid);
        $fm = $this->_df->get_filter_manager();
        $this->_filter = $fm->get_filter_from_id($filterid);

        if ($this->_ajaxformdata["update"] && confirm_sesskey()) {
            $procesedfilters = $fm->process_filters_for_ajax_submission('update', $filterid, $this, true);
            $this->_filter = $procesedfilters[0];
        }

        return $this->_df->notifications;
    }

    public function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/mod/datalynx/view.php', array('id' => $this->_df->cm->id));
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