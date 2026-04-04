<?php

namespace mod_datalynx\form;
class datalynx_advanced_filter_form extends datalynx_filter_base_form {
    /*
     * Definition of the advanced filter form which is part of a view
     */
    public function definition() {
        $filter = $this->filter;
        $view = $this->_customdata['view'];

        $name = empty($filter->name) ? get_string('filternew', 'datalynx') : $filter->name;

        // Get the fields of this view.
        $fields = $view->get_view_fields(true);
        $fieldoptions = [0 => get_string('choose')];
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
        $options = [0 => get_string('choose'), 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6,
                7 => 7, 8 => 8, 9 => 9, 10 => 10, 15 => 15, 20 => 20, 30 => 30, 40 => 40, 50 => 50,
                100 => 100, 200 => 200, 300 => 300, 400 => 400, 500 => 500, 1000 => 1000];
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
        $grp = [];
        $grp[] = $mform->createElement('submit', 'savebutton', get_string('savechanges'));
        $grp[] = $mform->createElement('submit', 'newbutton', get_string('newfilter', 'filters'));
        $mform->addGroup($grp, "afiltersubmit_grp", null, ' ', false);
    }
}