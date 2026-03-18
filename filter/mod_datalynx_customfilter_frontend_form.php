<?php
/**
 * Custom filter frontend form class.
 *
 * @package    mod_datalynx
 * @copyright  2014 onwards by edulabs.org and associated programmers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
/**
 * Custom filter frontend form class.
 */
class mod_datalynx_customfilter_frontend_form extends mod_datalynx_filter_base_form {
    /**
     * Form definition.
     */
    public function definition() {
        $view = $this->_customdata['view'];
        if (!$customfilter = $this->_customfilter) {
            throw new moodle_exception('nocustomfilter', 'datalynx');
        }
        $customfilterfieldlistfields = [];
        if ($customfilter->fieldlist) {
            $customfilterfieldlistfields = json_decode($customfilter->fieldlist);
        }
        $fields = $view->get_view_fields();
        $fieldoptions = [];
        $sortfields = [];
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
        if (count($fieldoptions) > 0) {
            $mform->addElement('header', 'customsearchhdr', get_string('filtercustomsearch', 'datalynx'));
            $mform->setExpanded('customsearchhdr');
            $this->customfilter_search_definition($fields, $fieldoptions);
        }
        if ($customfilter->perpage) {
            $mform->addElement('header', 'customperpagehdr', get_string('filterperpage', 'datalynx'));
            $mform->setExpanded('customperpagehdr');
            // Entries per page.
            $options = [0 => get_string('choose'), 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6,
                    7 => 7, 8 => 8, 9 => 9, 10 => 10, 15 => 15, 20 => 20, 30 => 30, 40 => 40, 50 => 50,
                    100 => 100, 200 => 200, 300 => 300, 400 => 400, 500 => 500, 1000 => 1000];
            $mform->addElement('select', 'uperpage', get_string('viewperpage', 'datalynx'), $options);
            $mform->setDefault('uperpage', $this->_filter->perpage);
        }
        if (count($sortfields) > 0) {
            $mform->addElement('header', 'customsorthdr', get_string('filtercustomsort', 'datalynx'));
            $mform->setExpanded('customsorthdr');
            $this->custom_sort_definition($this->_filter->customsort, $fields, $sortfields, false, true);
        }
        // Save button.
        $mform->addElement('submit', 'savebutton', $customfilter->submitlabel);
    }
}
