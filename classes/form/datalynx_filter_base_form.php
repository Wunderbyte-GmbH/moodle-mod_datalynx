<?php

namespace mod_datalynx\form;
use datalynx;
use moodleform;

/**
 *
 */
abstract class datalynx_filter_base_form extends moodleform {
    protected $filter = null;
    protected $customfilter = null;

    /**
     *
     * @var datalynx null
     */
    protected $dl = null;

    /*
     *
     */
    public function __construct(
            $df,
            $filter,
            $action = null,
            $customdata = null,
            $method = 'post',
            $target = '',
            $attributes = null,
            $editable = true,
            $customfilter = false
    ) {
        $this->filter = $filter;
        $this->customfilter = $customfilter;
        $this->dl = $df;

        parent::__construct($action, $customdata, $method, $target, $attributes, $editable);
    }

    /*
     *
     */
    public function custom_sort_definition($customsort, $fields, $fieldoptions, $showlabel = false, $customfilter = false) {
        $mform = &$this->_form;

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
        $df = $this->dl;

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
                    $operatoroptions = $df->get_field_from_id($fieldid)->get_supported_search_operators();
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

    /**
     */
    public function html() {
        return $this->_form->toHtml();
    }
}