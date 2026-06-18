<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_datalynx\form;

/**
 * Shared custom sort/search row builders for the datalynx filter forms.
 *
 * Used by both the legacy full-page {@see datalynx_filter_base_form} and the AJAX
 * {@see datalynx_filter_dynamic_form} so the rendered element names and search/sort semantics are
 * guaranteed identical (and therefore parse back through the same serialization untouched).
 *
 * The host class must expose a `$dlx` property (a {@see \mod_datalynx\datalynx}) and the standard
 * moodleform `$_form` property.
 *
 * @package mod_datalynx
 * @copyright 2026 David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait filter_form_elements {
    /**
     * Adds custom sort field elements to the form.
     *
     * @param mixed $customsort
     * @param array $fields
     * @param array $fieldoptions
     * @param bool $showlabel
     * @param bool $customfilter
     * @return void
     */
    public function custom_sort_definition($customsort, $fields, $fieldoptions, $showlabel = false, $customfilter = false) {
        $mform = &$this->_form;

        // Drop fields that cannot be sorted (e.g. the submit/cancel button fields).
        $fieldoptions = $this->keep_sortable_fieldoptions($fieldoptions);

        $diroptions = [0 => get_string('ascending', 'datalynx'),
                1 => get_string('descending', 'datalynx')];

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

                $optionsarr = [];
                $optionsarr[] = &$mform->createElement('select', 'sortfield' . $count, '', $fieldoptions);
                $optionsarr[] = &$mform->createElement(
                    'select',
                    'sortdir' . $count,
                    '',
                    $diroptions
                );
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
            $optionsarr = [];
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
    public function custom_search_definition(
        $customsearch,
        $fields,
        $fieldoptions,
        $showlabel = false
    ) {
        $mform = &$this->_form;
        $dlx = $this->dlx;

        // Drop fields that cannot be searched (e.g. the submit/cancel button fields).
        $fieldoptions = $this->keep_searchable_fieldoptions($fieldoptions);

        $andoroptions = [0 => get_string('andor', 'datalynx'),
                'AND' => get_string('and', 'datalynx'), 'OR' => get_string('or', 'datalynx')];
        $isnotoptions = ['' => get_string('is', 'datalynx'),
                'NOT' => get_string('not', 'datalynx')];

        $fieldlabel = get_string('filtersearchfieldlabel', 'datalynx');
        $count = 0;

        // Add current options.
        if ($customsearch) {
            $searchfields = unserialize($customsearch);
            // If not from form then the searchfields is aggregated and we need.
            // To flatten them. An aggregated array should have a non-zero key.
            // (fieldid) in the first element.
            if (key($searchfields)) {
                $searcharr = [];
                foreach ($searchfields as $fieldid => $searchfield) {
                    if (empty($fields[$fieldid])) {
                        continue;
                    }

                    foreach ($searchfield as $andor => $searchoptions) {
                        foreach ($searchoptions as $searchoption) {
                            if ($searchoption) {
                                [$not, $operator, $value] = $searchoption;
                                if (is_array($value)) {
                                    $value = json_encode($value);
                                }
                            } else {
                                [$not, $operator, $value] = ['', '', ''];
                            }
                            $searcharr[] = [$fieldid, $andor, $not, $operator, $value];
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

                [$fieldid, $andor, $not, $operator, $value] = $searchcriterion;

                $arr = [];
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
                    $operatoroptions = $dlx->get_field_from_id($fieldid)->get_supported_search_operators();
                }
                $arr[] = &$mform->createElement(
                    'select',
                    'searchoperator' . $count,
                    '',
                    $operatoroptions
                );
                $mform->setDefault('searchoperator' . $count, $operator);
                // Field search elements.
                // For select options $value is an arry, we have to convert it to string, function param only accepts strings.
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                [$elems, $separators] = $fields[$fieldid]->renderer()->render_search_mode(
                    $mform,
                    $count,
                    $value
                );

                $arr = array_merge($arr, $elems);
                if ($separators) {
                    $sep = array_merge([' ', ' ', ' '], $separators);
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

            $arr = [];
            $arr[] = &$mform->createElement('select', "searchandor$count", '', $andoroptions);
            $arr[] = &$mform->createElement('select', "searchfield$count", '', $fieldoptions);
            $mform->addGroup($arr, "customsearcharr$count", $label, ' ', false);
            $mform->disabledIf('searchfield' . $count, 'searchandor' . $count, 'eq', 0);
            if ($count > $prevcount) {
                $mform->disabledIf("searchoption$count", 'searchandor' . ($count - 1), 'eq', 0);
            }
        }

        $mform->registerNoSubmitButton('addsearchsettings');
        $mform->addElement('submit', 'addsearchsettings', get_string('reload'));
    }

    /**
     * Removes non-searchable fields from a field-options menu.
     *
     * Only fields that declare support for searching (see {@see datalynxfield_base::supports_search()})
     * may be offered as filter criteria. Internal helper fields that hold no content, such as the
     * submit and cancel button fields, are therefore excluded. The reserved "choose" entry (key 0)
     * and any unresolvable key are kept as-is.
     *
     * @param array $fieldoptions fieldid => name menu
     * @return array the filtered menu
     */
    protected function keep_searchable_fieldoptions(array $fieldoptions): array {
        $dlx = $this->dlx;
        $searchable = [];
        foreach ($fieldoptions as $fieldid => $fieldname) {
            if ($fieldid && ($field = $dlx->get_field_from_id($fieldid)) && !$field->supports_search()) {
                continue;
            }
            $searchable[$fieldid] = $fieldname;
        }
        return $searchable;
    }

    /**
     * Removes non-sortable fields from a field-options menu.
     *
     * Only fields that declare support for sorting (see {@see datalynxfield_base::supports_sort()})
     * may be offered as sort criteria. Pure action fields that carry no content, such as the submit
     * and cancel button fields, are therefore excluded. The reserved "choose" entry (key 0) and any
     * unresolvable key are kept as-is.
     *
     * @param array $fieldoptions fieldid => name menu
     * @return array the filtered menu
     */
    protected function keep_sortable_fieldoptions(array $fieldoptions): array {
        $dlx = $this->dlx;
        $sortable = [];
        foreach ($fieldoptions as $fieldid => $fieldname) {
            if ($fieldid && ($field = $dlx->get_field_from_id($fieldid)) && !$field->supports_sort()) {
                continue;
            }
            $sortable[$fieldid] = $fieldname;
        }
        return $sortable;
    }

    /**
     * Adds custom search field elements to the form.
     *
     * @param array $fields
     * @param array $fieldoptions
     * @return void
     */
    public function customfilter_search_definition($fields, $fieldoptions) {
        $mform = &$this->_form;
        $mform->customfilter = true;

        // Drop fields that cannot be searched (e.g. the submit/cancel button fields).
        $fieldoptions = $this->keep_searchable_fieldoptions($fieldoptions);

        // List user fields.
        $count = 1;
        foreach ($fieldoptions as $fieldid => $fieldname) {
            $label = $fieldname;

            $value = '';

            [$elems, $separators] = $fields[$fieldid]->renderer()->render_search_mode($mform, $count, $value);

            if ($separators) {
                $sep = array_merge([' ', ' ', ' '], $separators);
            } else {
                $sep = ' ';
            }
            $mform->addGroup($elems, "customsearcharr$count", $label, $sep, false);

            $count++;
        }
    }
}
