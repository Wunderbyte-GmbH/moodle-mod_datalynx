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

namespace mod_datalynx\local\filter;
use html_table;
use html_writer;
use mod_datalynx\form\datalynx_advanced_filter_form;
use mod_datalynx\form\datalynx_customfilter_frontend_form;
use mod_datalynx\form\datalynx_filter_form;
use moodle_url;
use stdClass;

require_once($CFG->libdir . '/formslib.php');

/**
 * Filter manager class
 * @package mod_datalynx
 */
class datalynx_filter_manager {
    const USER_FILTER_MAX_NUM = 5;

    const BLANK_FILTER = -1;

    const USER_FILTER_SET = -2;

    const USER_FILTER_ID_START = -10;

    protected $dl;

    protected $filters;

    /**
     * constructor
     */
    public function __construct($df) {
        $this->dl = $df;
        $this->filters = [];
    }

    /**
     */
    public function get_filter_from_id($filterid = 0, array $options = null) {

        $df = $this->dl;
        $dfid = $df->id();

        // Blank filter.
        if ($filterid == self::BLANK_FILTER) {
            $filter = new stdClass();
            $filter->dataid = $df->id();
            $filter->name = get_string('filternew', 'datalynx');
            $filter->perpage = 0;

            return new datalynx_filter($filter);
        }

        // User filter.
        if ($filterid < 0) {
            // For actual user filters we need a view and whether advanced.
            $view = !empty($options['view']) ? $options['view'] : null;
            $viewid = $view ? $view->id() : 0;
            $advanced = !empty($options['advanced']);
            $customfilter = !empty($options['customfilter']) ? $options['customfilter'] : null;

            // User preferences.
            if (($filterid == self::USER_FILTER_SET || $advanced || $customfilter) && $view && $view->is_active()) {
                $filter = $this->set_user_filter($filterid, $view, $advanced, $customfilter);
                return new datalynx_filter($filter);
            }

            // Retrieve existing user filter (filter id > blank filter).
            if (
                    $filterid != self::USER_FILTER_SET &&
                    $filter = get_user_preferences("datalynxfilter-$dfid-$viewid-$filterid", null)
            ) {
                $filter = unserialize($filter);
                $filter->dataid = $dfid;
                return new datalynx_filter($filter);
            }

            // For all other "negative" cases proceed with defaults.
            $filterid = 0;
        }

        // Datalynx default filter.
        if ($filterid == 0) {
            // If no default return empty.
            if (!$df->data->defaultfilter) {
                $filter = new stdClass();
                $filter->dataid = $df->id();

                return new datalynx_filter($filter);

                // Otherwise assign to filterid for the Existing filter check.
            } else {
                $filterid = $df->data->defaultfilter;
            }
        }

        // Existing filter.
        if ($this->get_filters() && isset($this->filters[$filterid])) {
            return clone($this->filters[$filterid]);
        } else {
            $filter = new stdClass();
            $filter->dataid = $df->id();

            return new datalynx_filter($filter);
        }
    }

    /**
     */
    public function get_filter_from_url($url, $raw = false) {

        $df = $this->dl;
        $dfid = $df->id();

        if ($options = self::get_filter_options_from_url($url)) {
            $options['dataid'] = $dfid;
            $filter = new datalynx_filter((object) $options);

            if ($raw) {
                return $filter->get_filter_obj();
            } else {
                return $filter;
            }
        }
        return null;
    }

    /**
     */
    public function get_filters($exclude = null, $menu = false, $forceget = false) {
        global $DB;
        if (!$this->filters || $forceget) {
            $this->filters = [];
            if ($filters = $DB->get_records('datalynx_filters', ['dataid' => $this->dl->id()], 'name')) {
                foreach ($filters as $filterid => $filterdata) {
                    $this->filters[$filterid] = new datalynx_filter($filterdata);
                }
            }
        }

        if ($this->filters) {
            if (empty($exclude) && !$menu) {
                return $this->filters;
            } else {
                $filters = [];
                foreach ($this->filters as $filterid => $filter) {
                    if (!empty($exclude) && in_array($filterid, $exclude)) {
                        continue;
                    }
                    if ($menu) {
                        if (
                                $filter->visible || has_capability('mod/datalynx:managetemplates', $this->dl->context)
                        ) {
                            $filters[$filterid] = $filter->name;
                        }
                    } else {
                        $filters[$filterid] = $filter;
                    }
                }
                return $filters;
            }
        } else {
            return false;
        }
    }

    /**
     */
    public function process_filters($action, $fids, $confirmed = false) {
        global $DB, $OUTPUT;

        $df = $this->dl;

        $filters = [];
        // TODO may need new roles.
        if (has_capability('mod/datalynx:managetemplates', $df->context)) {
            // Don't need record from database for filter form submission.
            if ($fids) { // Some filters are specified for action.
                $filters = $DB->get_records_select('datalynx_filters', "id IN ($fids)");
            } else {
                if ($action == 'update') {
                    $filters[] = $this->get_filter_from_id(self::BLANK_FILTER);
                }
            }
        }
        $processedfids = [];
        $strnotify = '';

        // TODO update should be roled.
        if (empty($filters)) {
            $df->notifications['bad'][] = get_string("filternoneforaction", 'datalynx');
            return false;
        } else {
            if (!$confirmed) {
                // Print header.
                $df->print_header('filters');

                // Print a confirmation page.
                echo $OUTPUT->confirm(
                    get_string("filtersconfirm$action", 'datalynx', count($filters)),
                    new moodle_url(
                        '/mod/datalynx/filter/index.php',
                        ['d' => $df->id(),
                                        $action => implode(',', array_keys($filters)),
                                        'sesskey' => sesskey(),
                                        'confirmed' => 1]
                    ),
                    new moodle_url('/mod/datalynx/filter/index.php', ['d' => $df->id()])
                );

                echo $OUTPUT->footer();
                exit();
            } else {
                // Go ahead and perform the requested action.
                switch ($action) {
                    case 'update': // Add new or update existing.
                        $filter = reset($filters);
                        $mform = $this->get_filter_form($filter);

                        if ($mform->is_cancelled()) {
                            break;
                        }

                        // Regenerate form and filter to obtain custom search data.
                        $formdata = $mform->get_submitted_data();
                        $filter = $this->get_filter_from_form($filter, $formdata);
                        $filterform = $this->get_filter_form($filter);

                        // Return to form (on reload button press).
                        if ($filterform->no_submit_button_pressed()) {
                            $this->display_filter_form($filterform, $filter);

                            // Process validated.
                        } else {
                            if ($formdata = $filterform->get_data()) {
                                // Get clean filter from formdata.
                                $filter = $this->get_filter_from_form($filter, $formdata, true);

                                if ($filter->id) {
                                    $DB->update_record('datalynx_filters', $filter);
                                    $processedfids[] = $filter->id;
                                    $strnotify = 'filtersupdated';

                                    $other = ['dataid' => $this->dl->id()];
                                    $event = \mod_datalynx\event\field_updated::create(
                                        ['context' => $this->dl->context,
                                                    'objectid' => $filter->id,
                                                    'other' => $other]
                                    );
                                    $event->trigger();
                                } else {
                                    $filter->id = $DB->insert_record('datalynx_filters', $filter, true);
                                    $processedfids[] = $filter->id;
                                    $strnotify = 'filtersadded';

                                    $other = ['dataid' => $this->dl->id()];
                                    $event = \mod_datalynx\event\field_created::create(
                                        ['context' => $this->dl->context,
                                                    'objectid' => $filter->id, 'other' => $other,
                                            ]
                                    );
                                    $event->trigger();
                                }
                                // Update cached filters.
                                $this->filters[$filter->id] = $filter;
                            } else {
                                // Form validation failed so return to form.
                                $this->display_filter_form($filterform, $filter);
                            }
                        }

                        break;

                    case 'duplicate':
                        if (!empty($filters)) {
                            foreach ($filters as $filter) {
                                // TODO: check for limit.
                                // Set new name.
                                while ($df->name_exists('filters', $filter->name)) {
                                    $filter->name = 'Copy of ' . $filter->name;
                                }
                                $filterid = $DB->insert_record('datalynx_filters', $filter);

                                $processedfids[] = $filterid;

                                $other = ['dataid' => $this->dl->id()];
                                $event = \mod_datalynx\event\field_created::create(
                                    ['context' => $this->dl->context,
                                                'objectid' => $filterid, 'other' => $other,
                                        ]
                                );
                                $event->trigger();
                            }
                        }
                        $strnotify = 'filtersadded';
                        break;

                    case 'visible':
                        $updatefilter = new stdClass();
                        foreach ($filters as $filter) {
                            $updatefilter->id = $filter->id;
                            $updatefilter->visible = (int) !$filter->visible;
                            $DB->update_record('datalynx_filters', $updatefilter);
                            // Update cached filters.
                            $filter->visible = $updatefilter->visible;

                            $processedfids[] = $filter->id;

                            $other = ['dataid' => $this->dl->id()];
                            $event = \mod_datalynx\event\field_updated::create(
                                ['context' => $this->dl->context,
                                            'objectid' => $filter->id, 'other' => $other,
                                    ]
                            );
                            $event->trigger();
                        }

                        $strnotify = '';
                        break;

                    case 'delete':
                        foreach ($filters as $filter) {
                            $DB->delete_records('datalynx_filters', ['id' => $filter->id]);

                            // Reset default filter if needed.
                            if ($filter->id == $df->data->defaultfilter) {
                                $df->set_default_filter();
                            }

                            $processedfids[] = $filter->id;

                            $other = ['dataid' => $this->dl->id()];
                            $event = \mod_datalynx\event\field_deleted::create(
                                ['context' => $this->dl->context,
                                            'objectid' => $filter->id, 'other' => $other,
                                    ]
                            );
                            $event->trigger();
                        }
                        $strnotify = 'filtersdeleted';
                        break;

                    default:
                        break;
                }

                if (!empty($strnotify)) {
                    $filtersprocessed = $processedfids ? count($processedfids) : 'No';
                    $df->notifications['good'][] = get_string(
                        $strnotify,
                        'datalynx',
                        $filtersprocessed
                    );
                }
                return $processedfids;
            }
        }
    }

    /**
     */
    public function get_filter_form($filter) {
        global $CFG;

        $formurl = new moodle_url(
            '/mod/datalynx/filter/index.php',
            ['d' => $this->dl->id(), 'fid' => $filter->id, 'update' => 1]
        );
        $mform = new datalynx_filter_form($this->dl, $filter, $formurl);
        return $mform;
    }

    /**
     */
    public function display_filter_form($mform, $filter, $urlparams = null) {
        $streditinga = $filter->id ? get_string('filteredit', 'datalynx', $filter->name) : get_string(
            'filternew',
            'datalynx'
        );
        $heading = html_writer::tag(
            'h2',
            format_string($streditinga),
            ['class' => 'mdl-align']
        );

        $this->dl->print_header(['tab' => 'filters', 'urlparams' => $urlparams]);
        echo $heading;
        $mform->display();
        $this->dl->print_footer();

        exit();
    }

    /**
     */
    public function get_filter_from_form($filter, $formdata, $finalize = false) {
        $filter->name = $formdata->name;
        $filter->description = !empty($formdata->description) ? $formdata->description : '';
        $filter->selection = !empty($formdata->selection) ? $formdata->selection : 0;
        $filter->groupby = !empty($formdata->groupby) ? $formdata->groupby : 0;
        $filter->search = isset($formdata->search) ? $formdata->search : '';
        $filter->customsort = $this->get_sort_options_from_form($formdata);
        $filter->customsearch = $this->get_search_options_from_form($formdata, $finalize);

        // Userpreferences for perpage overwrites filterpreferences.
        $filter->perpage = 0;
        if (isset($formdata->perpage)) {
            $filter->perpage = $formdata->perpage;
        }
        if (isset($formdata->uperpage)) {
            $filter->perpage = $formdata->uperpage;
        }

        if ($filter->customsearch) {
            $filter->search = '';
        }

        return $filter;
    }

    /**
     * Get filter form data from customfilter form
     */
    public function get_filter_from_customfilterform($filter, $formdata, $customfilter) {

        $customfilterfields = json_decode($customfilter->fieldlist);
        $customfilterfieldids = [];
        foreach ($customfilterfields as $fid => $field) {
            $customfilterfieldids[] = $fid;
        }

        $fields = $this->dl->get_fields();
        $searchfields = [];
        foreach ($formdata as $key => $value) {
            $formfieldarray = explode("_", $key);
            if (count($formfieldarray) >= 3) {
                $fieldname = $formfieldarray[2];
                switch ($fieldname) {
                    case ("approve"):
                        if ((int) $value > 0) {
                            $searchfields['approve']['AND'][] = ['', '=', $value];
                        }
                        break;
                    case ("timecreated"):
                    case ("timemodified"):
                        if (count($formfieldarray) == 4 && $formfieldarray[3] == 'from') {
                            if ($formdata->{$key} > 0) {
                                $valuearr = [];
                                $valuearr[] = $key;
                                $tokeyactive = str_replace('_from', '_to', $key);
                                if (isset($formdata->{$tokeyactive}) && $formdata->{$tokeyactive} > $formdata->{$key}) {
                                    $valuearr[] = $formdata->{$tokeyactive};
                                    $searchfields[$fieldname]['AND'][] = ['', 'BETWEEN',
                                            $valuearr];
                                } else {
                                    $searchfields[$fieldname]['AND'][] = ['', '>', [$formdata->{$key}]];
                                }
                            }
                        }
                        break;
                    case ("status"):
                        if ((int) $value > 0) {
                            $searchfields['status']['AND'][] = ['', '=', $value];
                        }
                        break;
                    default:
                        if (in_array($fieldname, $customfilterfieldids)) {
                            $type = $fields[$fieldname]->type;
                            if ($type == "text") {
                                if ($value) {
                                    $searchfields[$fieldname]['AND'][] = ['', 'LIKE', $value];
                                }
                            } else if (($type == "multiselect" || $type == "checkbox") && $value['andor'] == -2) {
                                // If andor is set to -2 operator is ALL_OF.
                                unset($value['andor']);
                                $searchfields[$fieldname]['AND'][] = ['', 'ALL_OF', $value];
                            } else if ($type == "file") {
                                if ($value == '0') {
                                    $searchfields[$fieldname]['AND'][] = ['', '', false];
                                } else if ($value == '1') {
                                    $searchfields[$fieldname]['AND'][] = ['NOT', '', false];
                                }
                            } else {
                                // Analog to advanced filter form:
                                // searchfieldid - searchandor - not - operator - value.
                                // Only add to query when something is chosen, ignore empty values.
                                if ($value) {
                                    $searchfields[$fieldname]['AND'][] = ['', 'ANY_OF', $value];
                                }
                            }
                        }
                }
            } else {
                if ($key == "search" && $value) {
                    $filter->search = $value;
                }

                if ($key == "authorsearch" && $value) {
                    $searchfields['userid']['AND'][] = ['', '=', $value];
                    $filter->authorsearch = $value;
                }
            }
        }
        if ($searchfields) {
            $filter->customsearch = serialize($searchfields);
        }

        return $filter;
    }

    /**
     */
    protected function get_sort_options_from_form($formdata) {
        $sortfields = [];
        $i = 0;
        while (isset($formdata->{"sortfield$i"})) {
            if ($sortfieldid = $formdata->{"sortfield$i"}) {
                $sortfields[$sortfieldid] = $formdata->{"sortdir$i"};
            }
            $i++;
        }
        // TODO should we add the groupby field to the customsort now?
        if ($sortfields) {
            return serialize($sortfields);
        } else {
            return '';
        }
    }

    /**
     */
    protected function get_search_options_from_form($formdata, $finalize = false) {
        if ($fields = $this->dl->get_fields()) {
            $searchfields = [];
            foreach ($formdata as $var => $unused) {
                if (strpos($var, 'searchandor') !== 0) {
                    continue;
                }

                $i = (int) str_replace('searchandor', '', $var);
                // Check if trying to define a search criterion.
                if ($searchandor = $formdata->{"searchandor$i"}) {
                    if ($searchfieldid = $formdata->{"searchfield$i"}) {
                        $not = !empty($formdata->{"searchnot$i"}) ? $formdata->{"searchnot$i"} : '';
                        $operator = isset($formdata->{"searchoperator$i"}) ? $formdata->{"searchoperator$i"} : '';
                        $parsedvalue = $fields[$searchfieldid]->parse_search($formdata, $i);
                        // Don't add empty criteria on cleanup (unless operator
                        // doesn't need an argument/search value (e.g. the "Empty" operator)).
                        if ($finalize && ($fields[$searchfieldid]->get_argument_count($operator) > 0) && !$parsedvalue) {
                            continue;
                        }

                        // If finalizing, aggregate by fieldid and searchandor,.
                        // Otherwise just make a flat array (of arrays).
                        if ($finalize) {
                            if (!isset($searchfields[$searchfieldid])) {
                                $searchfields[$searchfieldid] = [];
                            }
                            if (!isset($searchfields[$searchfieldid][$searchandor])) {
                                $searchfields[$searchfieldid][$searchandor] = [];
                            }
                            $searchfields[$searchfieldid][$searchandor][] = [$not, $operator, $parsedvalue];
                        } else {
                            $searchfields[] = [$searchfieldid, $searchandor, $not, $operator, $parsedvalue];
                        }
                    }
                }
            }
        }

        if ($searchfields) {
            return serialize($searchfields);
        } else {
            return '';
        }
    }

    /**
     * TODO: Returns the search options transmitted by a customfilter form
     */
    protected function get_customfilter_search_options_from_form($formdata, $finalize = false) {
        if ($fields = $this->dl->get_fields()) {
            $searchfields = [];
            foreach ($formdata as $var => $unused) {
                if (strpos($var, 'searchandor') !== 0) {
                    continue;
                }

                $i = (int) str_replace('searchandor', '', $var);
                // Check if trying to define a search criterion.
                if ($searchandor = $formdata->{"searchandor$i"}) {
                    if ($searchfieldid = $formdata->{"searchfield$i"}) {
                        $not = !empty($formdata->{"searchnot$i"}) ? $formdata->{"searchnot$i"} : '';
                        $operator = isset($formdata->{"searchoperator$i"}) ? $formdata->{"searchoperator$i"} : '';
                        $parsedvalue = $fields[$searchfieldid]->parse_search($formdata, $i);
                        // Don't add empty criteria on cleanup (unless operator is Empty and thus
                        // doesn't need search value).
                        if ($finalize && $operator && !$parsedvalue) {
                            continue;
                        }

                        // If finalizing, aggregate by fieldid and searchandor,
                        // otherwise just make a flat array (of arrays).
                        if ($finalize) {
                            if (!isset($searchfields[$searchfieldid])) {
                                $searchfields[$searchfieldid] = [];
                            }
                            if (!isset($searchfields[$searchfieldid][$searchandor])) {
                                $searchfields[$searchfieldid][$searchandor] = [];
                            }
                            $searchfields[$searchfieldid][$searchandor][] = [$not, $operator, $parsedvalue];
                        } else {
                            $searchfields[] = [$searchfieldid, $searchandor, $not, $operator, $parsedvalue];
                        }
                    }
                }
            }
        }

        if ($searchfields) {
            return serialize($searchfields);
        } else {
            return '';
        }
    }

    /**
     */
    public function print_filter_list() {
        global $OUTPUT;

        $df = $this->dl;

        $filterbaseurl = '/mod/datalynx/filter/index.php';
        $linkparams = ['d' => $df->id(), 'sesskey' => sesskey()];

        // Table headings.
        $strfilters = get_string('name');
        $strdescription = get_string('description');
        $strperpage = get_string('filterperpage', 'datalynx');
        $strcustomsort = get_string('filtercustomsort', 'datalynx');
        $strcustomsearch = get_string('filtercustomsearch', 'datalynx');
        $strurlquery = get_string('filterurlquery', 'datalynx');
        $strvisible = get_string('visible');
        $strhide = get_string('hide');
        $strshow = get_string('show');
        $stredit = get_string('edit');
        $strdelete = get_string('delete');
        $strduplicate = get_string('duplicate');
        $strdefault = get_string('default');
        $strchoose = get_string('choose');

        $selectallnone = html_writer::checkbox(
            null,
            null,
            false,
            null,
            ['onclick' => 'select_allnone(\'filter\'&#44;this.checked)']
        );
        $multidelete = html_writer::tag(
            'button',
            $OUTPUT->pix_icon('t/delete', get_string('multidelete', 'datalynx')),
            ['name' => 'multidelete',
                        'onclick' => 'bulk_action(\'filter\'&#44; \'' .
                                htmlspecialchars_decode(new moodle_url($filterbaseurl, $linkparams)) .
                                '\'&#44; \'delete\')']
        );

        $table = new html_table();
        $table->head = [$strfilters, $strdescription, $strperpage, $strcustomsort,
                $strcustomsearch, $strurlquery, $strvisible, $strdefault, $stredit, $strduplicate,
                $multidelete, $selectallnone];
        $table->align = ['left', 'left', 'center', 'left', 'left', 'left', 'center', 'center',
                'center', 'center', 'center'];
        $table->wrap = [false, false, false, false, false, false, false, false, false, false,
                false];
        $table->attributes['align'] = 'center';

        foreach ($this->filters as $filterid => $filter) {
            $filtername = html_writer::link(
                new moodle_url(
                    $filterbaseurl,
                    $linkparams + ['fedit' => $filterid, 'fid' => $filterid]
                ),
                $filter->name
            );
            $filterdescription = shorten_text($filter->description, 30);
            $filteredit = html_writer::link(
                new moodle_url(
                    $filterbaseurl,
                    $linkparams + ['fedit' => $filterid, 'fid' => $filterid]
                ),
                $OUTPUT->pix_icon('t/edit', $stredit)
            );
            $filterduplicate = html_writer::link(
                new moodle_url($filterbaseurl, $linkparams + ['duplicate' => $filterid]),
                $OUTPUT->pix_icon('t/copy', $strduplicate)
            );
            $filterdelete = html_writer::link(
                new moodle_url($filterbaseurl, $linkparams + ['delete' => $filterid]),
                $OUTPUT->pix_icon('t/delete', $strdelete)
            );
            $filterselector = html_writer::checkbox("filterselector", $filterid, false);

            // Visible.
            if ($filter->visible) {
                $visibleicon = $OUTPUT->pix_icon('t/hide', $strhide);
            } else {
                $visibleicon = $OUTPUT->pix_icon('t/show', $strshow);
            }
            $visible = html_writer::link(
                new moodle_url($filterbaseurl, $linkparams + ['visible' => $filterid]),
                $visibleicon
            );

            // Default filter.
            if ($filterid == $df->data->defaultfilter) {
                $defaultfilter = html_writer::link(
                    new moodle_url($filterbaseurl, $linkparams + ['default' => -1]),
                    $OUTPUT->pix_icon('t/clear', '')
                );
            } else {
                $defaultfilter = html_writer::link(
                    new moodle_url($filterbaseurl, $linkparams + ['default' => $filterid]),
                    $OUTPUT->pix_icon('t/switch_whole', $strchoose)
                );
            }
            // Parse custom settings.
            $sortoptions = '';
            $sorturlquery = '';
            $searchoptions = '';
            $searchurlquery = '';

            if ($filter->customsort || $filter->customsearch) {
                // Get field objects.
                $fields = $df->get_fields();

                // CUSTOM SORT.
                $sortfields = [];
                if ($filter->customsort) {
                    $sortfields = unserialize($filter->customsort);
                }

                if ($sortfields) {
                    $sortarr = [];
                    $sorturlarr = [];
                    foreach ($sortfields as $fieldid => $sortdir) {
                        if (empty($fields[$fieldid])) {
                            unset($sortfields[$fieldid]);
                            continue;
                        }

                        // Sort url query.
                        $sorturlarr[] = "$fieldid $sortdir";

                        // Verbose sort criteria.
                        // Check if field participates in default sort.
                        $strsortdir = $sortdir ? 'Descending' : 'Ascending';
                        $sortarr[] = $OUTPUT->pix_icon(
                            't/' . ($sortdir ? 'down' : 'up'),
                            $strsortdir
                        ) . ' ' . $fields[$fieldid]->field->name;
                    }
                    if ($sortfields) {
                        $sortoptions = implode('<br />', $sortarr);
                        $sorturlquery = '&usort=' . urlencode(implode(',', $sorturlarr));
                    }
                }
                $sortoptions = !empty($sortoptions) ? $sortoptions : '---';

                // CUSTOM SEARCH.
                $searchfields = [];
                if ($filter->customsearch) {
                    $searchfields = unserialize($filter->customsearch);
                }

                // Verbose search criteria.
                if ($searchfields) {
                    $searcharr = [];
                    foreach ($searchfields as $fieldid => $searchfield) {
                        if (empty($fields[$fieldid])) {
                            continue;
                        }
                        $fieldoptions = [];
                        if (!empty($searchfield['AND'])) {
                            $options = [];
                            foreach ($searchfield['AND'] as $option) {
                                if ($option) {
                                    $options[] = $fields[$fieldid]->format_search_value($option);
                                }
                            }
                            $fieldoptions[] = '<b>' . $fields[$fieldid]->field->name . '</b>:' .
                                    implode(' <b>and</b> ', $options);
                        }
                        if (!empty($searchfield['OR'])) {
                            $options = [];
                            foreach ($searchfield['OR'] as $option) {
                                if ($option) {
                                    $options[] = $fields[$fieldid]->format_search_value($option);
                                }
                            }
                            $fieldoptions[] = '<b>' . $fields[$fieldid]->field->name . '</b> ' .
                                    implode(' <b>or</b> ', $options);
                        }
                        if ($fieldoptions) {
                            $searcharr[] = implode('<br />', $fieldoptions);
                        }
                    }
                    if ($searcharr) {
                        $searchoptions = implode('<br />', $searcharr);
                    }
                } else {
                    $searchoptions = $filter->search ? $filter->search : '---';
                }
            }
            if (!empty($searchoptions)) {
                $searchurlquery = '&usearch=' . self::get_search_url_query($searchfields);
            }

            // Per page.
            $perpage = empty($filter->perpage) ? '---' : $filter->perpage;

            $table->data[] = [$filtername, $filterdescription, $perpage, $sortoptions,
                    $searchoptions, $sorturlquery . $searchurlquery, $visible, $defaultfilter,
                    $filteredit, $filterduplicate, $filterdelete, $filterselector];
        }

        echo html_writer::table($table);
    }

    /**
     */
    public function print_add_filter() {
        echo html_writer::empty_tag('br');
        echo html_writer::start_tag('div', ['class' => 'fieldadd mdl-align']);
        echo html_writer::link(
            new moodle_url(
                '/mod/datalynx/filter/index.php',
                ['d' => $this->dl->id(), 'sesskey' => sesskey(), 'new' => 1]
            ),
            get_string('filteradd', 'datalynx')
        );
        echo html_writer::end_tag('div');
        echo html_writer::empty_tag('br');
    }

    // ADVANCED FILTER.

    /**
     */
    public function get_advanced_filter_form($filter, $view) {
        global $CFG;

        $formurl = new moodle_url($view->get_baseurl(), ['filter' => self::USER_FILTER_SET, 'afilter' => 1]);
        $mform = new datalynx_advanced_filter_form($this->dl, $filter, $formurl, ['view' => $view]);
        return $mform;
    }

    // CUSTOM FILTER.

    /**
     */
    public function get_customfilter_frontend_form($filter, \mod_datalynx\view\base $view, $customfilter = false) {
        global $CFG;

        $cfilter = isset($customfilter->id) ? $customfilter->id : "1";
        $formurl = new moodle_url($view->get_baseurl(), ['filter' => self::USER_FILTER_SET, 'cfilter' => $cfilter]);
        $mform = new datalynx_customfilter_frontend_form(
            $this->dl,
            $filter,
            $formurl,
            ['view' => $view],
            'post',
            '',
            null,
            true,
            $customfilter
        );
        return $mform;
    }

    /**
     */
    public function get_user_filters_menu($viewid) {
        $filters = [];

        $df = $this->dl;
        $dfid = $df->id();
        if ($filternames = get_user_preferences("datalynxfilter-$dfid-$viewid-userfilters", '')) {
            foreach (explode(';', $filternames) as $filteridname) {
                [$filterid, $name] = explode(' ', $filteridname, 2);
                $filters[$filterid] = $name;
            }
        }
        return $filters;
    }

    /**
     */
    public function set_user_filter($filterid, \mod_datalynx\view\base $view, $advanced = false, $customfilter = false) {
        $df = $this->dl;
        $dfid = $df->id();
        $viewid = $view->id();

        // Advanced filter.
        if ($advanced) {
            $filter = new datalynx_filter((object) ['id' => $filterid, 'dataid' => $dfid]);
            $mform = $this->get_advanced_filter_form($filter, $view);

            // Regenerate form and filter to obtain custom search data.
            $formdata = $mform->get_submitted_data();
            $filter = $this->get_filter_from_form($filter, $formdata);
            $filter->id = $filterid;
            $filterform = $this->get_advanced_filter_form($filter, $view);

            // Return to form (on reload button press).
            if ($filterform->no_submit_button_pressed()) {
                return $filter;

                // Process validated.
            } else {
                if ($formdata = $filterform->get_data()) {
                    // Get clean filter from formdata.
                    $filter = $this->get_filter_from_form($filter, $formdata, true);
                    $modifycurrent = !empty($formdata->savebutton);
                }
            }
        }

        // Custom filter form.
        if ($customfilter) {
            global $DB;
            $filter = new datalynx_filter((object) ['id' => $filterid, 'dataid' => $dfid]);
            $customfilter = $DB->get_record('datalynx_customfilters', ['id' => $customfilter]);
            $filterform = $this->get_customfilter_frontend_form($filter, $view, $customfilter);
            // Return to form (on reload button press).
            if ($filterform->no_submit_button_pressed()) {
                return $filter;
            } else if ($formdata = $filterform->get_data()) { // Process validated.
                $filter = $this->get_filter_from_customfilterform($filter, $formdata, $customfilter);
                $modifycurrent = !empty($formdata->savebutton);
            }
        }

        // Quick filters.
        if (!$advanced && !$customfilter) {
            if ($filterid >= self::USER_FILTER_ID_START) {
                $filter = $this->get_filter_from_id($filterid);
            } else {
                $filter = $this->get_filter_from_url(null, true);
            }
            if (!$filter) {
                return null;
            }
        }

        if (!$customfilter) {
            // Set user filter.
            if ($userfilters = $this->get_user_filters_menu($viewid)) {
                if (empty($modifycurrent) || empty($userfilters[$filterid])) {
                    $filterid = key($userfilters) - 1;
                }
            } else {
                $filterid = self::USER_FILTER_ID_START;
            }

            // If max number of user filters pop the last.
            if (count($userfilters) >= self::USER_FILTER_MAX_NUM) {
                $fids = array_keys($userfilters);
                while (count($fids) >= self::USER_FILTER_MAX_NUM) {
                    $fid = array_pop($fids);
                    unset($userfilters[$fid]);
                    unset_user_preference("datalynxfilter-$dfid-$viewid-$fid");
                }
            }

            // Save the new filter.
            $filter->id = $filterid;
            $filter->dataid = $dfid;
            if (empty($filter->name)) {
                $filter->name = get_string('filtermy', 'datalynx') . ' ' . abs($filterid);
            }
            set_user_preference("datalynxfilter-$dfid-$viewid-$filterid", serialize($filter));

            // Add the new filter to the beginning of the userfilters.
            $userfilters = [$filterid => $filter->name] + $userfilters;
            foreach ($userfilters as $filterid => $name) {
                $userfilters[$filterid] = "$filterid $name";
            }
            set_user_preference("datalynxfilter-$dfid-$viewid-userfilters", implode(';', $userfilters));
        }

        return $filter;
    }

    // HELPERS.

    /**
     */
    public static function get_filter_url_query($filter) {
        $urlquery = [];

        if ($filter->customsort) {
            $urlquery[] = 'usort=' . self::get_sort_url_query(unserialize($filter->customsort));
        }
        if ($filter->customsearch) {
            $urlquery[] = 'usearch=' . self::get_search_url_query(unserialize($filter->customsearch));
        }

        if ($urlquery) {
            return implode('&', $urlquery);
        }
        return '';
    }

    /**
     */
    public static function get_sort_url_query(array $sorties) {
        if ($sorties) {
            $usort = [];
            foreach ($sorties as $fieldid => $dir) {
                $usort[] = "$fieldid $dir";
            }
            return urlencode(implode(',', $usort));
        }
        return '';
    }

    /**
     */
    public static function get_sort_options_from_query($query) {
        return unserialize(urldecode($query));
    }

    /**
     */
    public static function get_search_url_query(array $searchies) {
        $usearch = null;
        if ($searchies) {
            $usearch = [];
            foreach ($searchies as $fieldid => $andor) {
                foreach ($andor as $key => $soptions) {
                    if (empty($soptions)) {
                        continue;
                    }
                    foreach ($soptions as $options) {
                        if (empty($options)) {
                            continue;
                        }
                        [$not, $op, $value] = $options;
                        if (is_array($value) && isset($value['selected'])) {
                            $searchvalue = is_array($value['selected']) ? implode(
                                '|',
                                $value['selected']
                            ) : $value['selected'];
                        } else {
                            $searchvalue = is_array($value) ? implode('|', $value) : $value;
                        }
                        $usearch[] = "$fieldid:$key:$not,$op,$searchvalue";
                    }
                }
            }
            $usearch = implode('@', $usearch);
            $usearch = urlencode($usearch);
        }
        return $usearch;
    }

    /**
     */
    public static function get_search_options_from_query($query) {
        $soptions = [];
        if ($query) {
            $usearch = urldecode($query);
            $searchies = explode('@', $usearch);
            foreach ($searchies as $key => $searchy) {
                [$fieldid, $andor, $options] = explode(':', $searchy);
                $soptions[$fieldid] = [
                        $andor => array_map(function ($a) {
                            return explode(',', $a);
                        }, explode('#', $options)),
                ];
            }
        }
        return $soptions;
    }

    /**
     */
    public static function get_filter_options_from_url($url = null) {
        $filteroptions = [      // Left: filteroption-names, right: urlparameter-names.
                'filterid' => ['filter', 0, PARAM_INT],
                'perpage' => ['uperpage', 0, PARAM_INT],
                'selection' => ['uselection', 0, PARAM_INT],
                'groupby' => ['ugroupby', 0, PARAM_INT],
                'customsort' => ['usort', '', PARAM_RAW],
                'customsearch' => ['usearch', '', PARAM_RAW],
                'page' => ['page', 0, PARAM_INT],
                'eids' => ['eids', 0, PARAM_SEQUENCE],
                'users' => ['users', '', PARAM_SEQUENCE],
                'groups' => ['groups', '', PARAM_SEQUENCE],
                'afilter' => ['afilter', 0, PARAM_INT],
                'cfilter' => ['cfilter', 0, PARAM_INT],
                'usersearch' => ['usersearch', 0, PARAM_RAW]];

        $options = [];

        // Url provided.
        if ($url) {
            if ($url instanceof moodle_url) {
                foreach ($filteroptions as $option => $args) {
                    [$name, , ] = $args;
                    if ($val = $url->get_param($name)) {
                        if ($option == 'customsort') {
                            $options[$option] = self::get_sort_options_from_query($val);
                        } else {
                            if ($option == 'customsearch') {
                                $searchoptions = self::get_search_options_from_query($val);
                                if (is_array($searchoptions)) {
                                    $options['customsearch'] = $searchoptions;
                                } else {
                                    $options['search'] = $searchoptions;
                                }
                            } else {
                                $options[$option] = $val;
                            }
                        }
                    }
                }
            }
            return $options;
        }

        // Optional params.
        foreach ($filteroptions as $option => $args) {
            [$name, $default, $type] = $args;
            if ($val = optional_param($name, $default, $type)) {
                if ($option == 'customsort') {
                    $options[$option] = self::get_sort_options_from_query($val);
                } else {
                    if ($option == 'customsearch') {
                        $searchoptions = self::get_search_options_from_query($val);
                        if (is_array($searchoptions)) {
                            $options['customsearch'] = $searchoptions;
                        } else {
                            $options['search'] = $searchoptions;
                        }
                    } else {
                        $options[$option] = $val;
                    }
                }
            }
        }

        return $options;
    }

    public static function get_filter_options_from_userpreferences() {
        $filteroptions = [   // Left: urlparam-names, right: userpreferences-names.
                'perpage' => 'uperpage',
                'selection' => 'uselection',
                'groupby' => 'ugroupby',
                'customsort' => 'usort',
                'customsearch' => 'usearch',
                'page' => 'page',
                'eids' => 'eids',
                'users' => 'users',
                'groups' => 'groups',
                'afilter' => 'afilter',
                'usersearch' => 'usersearch',
        ];

        $options = [];

        $userfilter = false;
        $filterid = optional_param('filter', 0, PARAM_INT);
        if ($filterid < 0) {
            $viewid = optional_param('view', 0, PARAM_INT);
            $dfid = optional_param('d', 0, PARAM_INT);
            if ($viewid) {
                $userfilter = get_user_preferences("datalynxfilter-$dfid-$viewid-$filterid", null);
                $userfilter = unserialize($userfilter);
            }
        }

        if ($userfilter) {
            // Optional params.
            foreach ($filteroptions as $option => $name) {
                if ($val = $userfilter->$name) {
                    if ($option == 'customsort') {
                        $options[$option] = self::get_sort_options_from_query($val);
                    } else {
                        if ($option == 'customsearch') {
                            $searchoptions = self::get_search_options_from_query($val);
                            if (is_array($searchoptions)) {
                                $options['customsearch'] = $searchoptions;
                            } else {
                                $options['search'] = $searchoptions;
                            }
                        } else {
                            if ($option == 'usersearch') {
                                $options['search'] = $val;
                            } else {
                                $options[$option] = $val;
                            }
                        }
                    }
                }
            }
        }

        return $options;
    } // End function.
}
