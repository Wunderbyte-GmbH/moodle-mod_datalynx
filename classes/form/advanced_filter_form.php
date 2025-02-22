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

use mod_datalynx\form;

class advanced_filter_form extends form\filter_base_form {

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