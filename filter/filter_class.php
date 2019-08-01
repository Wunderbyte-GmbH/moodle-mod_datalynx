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
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

/**
 * Filter class
 */
class datalynx_filter {

    public $id;

    public $dataid;

    public $name;

    public $description;

    public $visible;

    public $perpage;

    public $pagenum;

    public $selection;

    public $groupby;

    public $customsort;

    public $customsearch;

    public $search;

    public $contentfields;

    public $eids;

    public $users;

    public $page;

    protected $_filteredtables = null;

    protected $_searchfields = null;

    protected $_sortfields = null;

    protected $_joins = null;

    protected $_entriesexcluded = array();

    /**
     * constructor
     */
    public function __construct($filterdata) {
        $this->id = empty($filterdata->id) ? 0 : $filterdata->id;
        $this->dataid = $filterdata->dataid; // Required.
        $this->name = empty($filterdata->name) ? '' : $filterdata->name;
        $this->description = empty($filterdata->description) ? '' : $filterdata->description;
        $this->visible = !isset($filterdata->visible) ? 1 : $filterdata->visible;

        $this->perpage = empty($filterdata->perpage) ? 0 : $filterdata->perpage;
        $this->selection = empty($filterdata->selection) ? 0 : $filterdata->selection;
        $this->groupby = empty($filterdata->groupby) ? '' : $filterdata->groupby;
        $this->customsort = empty($filterdata->customsort) ? '' : $filterdata->customsort;
        $this->customsearch = empty($filterdata->customsearch) ? '' : $filterdata->customsearch;
        if (empty($filterdata->search)) {
            if (empty($filterdata->usersearch)) {
                $this->search = '';
            } else {
                $this->search = $filterdata->usersearch;
            }
        } else {
            $this->search = $filterdata->search;
        }
        $this->contentfields = empty($filterdata->contentfields) ? null : $filterdata->contentfields;

        $this->eids = empty($filterdata->eids) ? null : $filterdata->eids;
        $this->users = empty($filterdata->users) ? null : $filterdata->users;
        $this->groups = empty($filterdata->groups) ? null : $filterdata->groups;
        $this->page = empty($filterdata->page) ? 0 : $filterdata->page;
    }

    /**
     */
    public function get_filter_obj() {
        $filter = new stdClass();
        $filter->id = $this->id;
        $filter->dataid = $this->dataid;
        $filter->name = $this->name;
        $filter->description = $this->description;
        $filter->visible = $this->visible;

        $filter->perpage = $this->perpage;
        $filter->selection = $this->selection;
        $filter->groupby = $this->groupby;
        $filter->customsort = $this->customsort;
        $filter->customsearch = $this->customsearch;
        $filter->search = $this->search;

        return $filter;
    }

    /**
     */
    public function get_sql($fields) {
        $this->init_filter_sql();

        // SEARCH sql.
        list($searchtables, $wheresearch, $searchparams) = $this->get_search_sql($fields);
        // SORT sql.
        list($sorttables, $sortorder, $sortparams) = $this->get_sort_sql($fields);
        // CONTENT sql ($datalynxcontent is an array of fieldid whose content needs to be fetched).
        list($datalynxcontent, $whatcontent, $contenttables, $contentparams) = $this->get_content_sql(
                $fields);

        return array(" $searchtables $sorttables $contenttables ", $wheresearch, $sortorder,
                $whatcontent, array_merge($searchparams, $sortparams, $contentparams), $datalynxcontent);
    }

    /**
     * TODO: Write comment
     */
    public function init_filter_sql() {
        $this->_filteredtables = null;
        $this->_searchfields = array();
        $this->_sortfields = array();
        $this->_joins = array();

        if ($this->customsearch) {
            $this->_searchfields = is_array($this->customsearch) ? $this->customsearch : unserialize(
                    $this->customsearch);
        }
        if ($this->customsort) {
            $this->_sortfields = is_array($this->customsort) ? $this->customsort : unserialize(
                    $this->customsort);
        }
        // Defines what field should be sorted by.
        $customfiltersortfield = optional_param('customfiltersortfield', null, PARAM_INT);
        if ($customfiltersortfield) {
            $customfiltersortdirection = optional_param('customfiltersortdirection', '0', PARAM_INT);
            $customfiltersort = array($customfiltersortfield => $customfiltersortdirection);
            if ($this->customsort) {
                $this->_sortfields = array_merge($this->_sortfields, $customfiltersort);
            } else {
                $this->_sortfields = $customfiltersort;
            }
        }
    }

    /**
     */
    public function get_search_sql($fields) {
        global $DB;

        $searchfrom = array();
        $searchwhere = array();
        $searchparams = array(); // Named params array.

        $searchfields = $this->_searchfields;
        $simplesearch = $this->search;
        $searchtables = '';

        if ($searchfields) {
            $whereand = array();
            $whereor = array();
            foreach ($searchfields as $fieldid => $searchfield) {
                // If we got this far there must be some actual search values.
                if (empty($fields[$fieldid])) {
                    continue;
                }

                $field = $fields[$fieldid];
                $internalfield = $field::is_internal();

                // Register join field if applicable.
                $this->register_join_field($field);

                // Add AND search clauses.
                if (!empty($searchfield['AND'])) {
                    foreach ($searchfield['AND'] as $option) {

                        if ($fieldsqloptions = $field->get_search_sql($option)) {
                            list($fieldsql, $fieldparams, $fromcontent) = $fieldsqloptions;
                            if ($fieldsql) {
                                // If we use values from content we make it an implied AND statement.
                                if (is_numeric($fieldid) && $this->add_fieldid($option, $field)) {
                                    $whereand[] = " ( " . $fieldsql . " AND c$fieldid.fieldid = $fieldid )";
                                } else {
                                    $whereand[] = $fieldsql;
                                }
                                $searchparams = array_merge($searchparams, $fieldparams);

                                // Add searchfrom (JOIN) only for search in datalynx content or external.
                                // Tables.
                                if (!$internalfield and $fromcontent) {
                                    $searchfrom[$fieldid] = $fieldid;
                                }
                            }
                        }
                    }
                }

                // Add OR search clause.
                if (!empty($searchfield['OR'])) {
                    foreach ($searchfield['OR'] as $option) {
                        if ($fieldsqloptions = $field->get_search_sql($option)) {
                            list($fieldsql, $fieldparams, $fromcontent) = $fieldsqloptions;
                            // If we use values from content we make it an implied AND statement.
                            if (is_numeric($fieldid) && $this->add_fieldid($option, $field)) {
                                 $whereor[] = " ( " . $fieldsql . " AND c$fieldid.fieldid = $fieldid )";
                            } else {
                                $whereor[] = $fieldsql;
                            }
                            $searchparams = array_merge($searchparams, $fieldparams);

                            // Add searchfrom (JOIN) only for search in datalynx content or external.
                            // Tables.
                            if (!$internalfield and $fromcontent) {
                                $searchfrom[$fieldid] = $fieldid;
                            }
                        }
                    }
                }
            }

            // Compile sql for search settings.
            if ($searchfrom) {
                foreach ($searchfrom as $fieldid) {
                    // Add only tables which are not already added.
                    if (empty($this->_filteredtables) or !in_array($fieldid, $this->_filteredtables)) {
                        $this->_filteredtables[] = $fieldid;
                        // Here the LEFT JOIN query is built within field class.
                        $searchtables .= $fields[$fieldid]->get_search_from_sql();
                    }
                }
            }

            if ($whereand) {
                $searchwhere[] = implode(' AND ', $whereand);
            }
            if ($whereor) {
                $searchwhere[] = '(' . implode(' OR ', $whereor) . ')';
            }
        }
        if ($simplesearch) {
            $searchtables .= " JOIN {datalynx_contents} cs ON cs.entryid = e.id ";
            $searchtables .= " JOIN {datalynx_fields} f ON cs.fieldid = f.id ";
            $searchlike = array('search1' => $DB->sql_like('cs.content', ':search1', false, false),
                'search2' => $DB->sql_like('u.firstname', ':search2', false, false),
                'search3' => $DB->sql_like('u.lastname', ':search3', false, false),
                'search4' => $DB->sql_like('u.username', ':search4', false, false));
            foreach (array_keys($searchlike) as $namekey) {
                $searchparams[$namekey] = '%' . $DB->sql_like_escape($simplesearch) . '%';
            }

            // Add search for option fields, which store option IDs.
            $i = 0;
            foreach ($fields as $field) {
                if ($field instanceof datalynxfield_option_multiple) {
                    foreach ($field->get_options() as $id => $option) {
                        if (stripos($option, $simplesearch) !== false) {
                            $paramlike = "fieldquicksearch$i";
                            $paramid = "fieldid$i";
                            $searchlike[$paramlike] = "(" .
                                    $DB->sql_like("cs.content", ":$paramlike", false, false) .
                                    " AND f.id = :$paramid)";
                            $searchparams[$paramlike] = "%#{$id}%#";
                            $searchparams[$paramid] = $field->id();
                            $i++;
                        }
                    }
                } else {
                    if ($field instanceof datalynxfield_option_single) {
                        foreach ($field->get_options() as $id => $option) {
                            if (stripos($option, $simplesearch) !== false) {
                                $paramlike = "fieldquicksearch$i";
                                $paramid = "fieldid$i";
                                $searchlike[$paramlike] = "(cs.content = :$paramlike AND f.id = :$paramid)";
                                $searchparams[$paramlike] = "$id";
                                $searchparams[$paramid] = $field->id();
                                $i++;
                            }
                        }
                    } else {
                        if ($field instanceof datalynxfield_teammemberselect) {
                            foreach ($field->options_menu() as $id => $option) {
                                if (stripos($option, $simplesearch) !== false) {
                                    $paramlike = "fieldquicksearch$i";
                                    $paramid = "fieldid$i";
                                    $searchlike[$paramlike] = "(" .
                                            $DB->sql_like("cs.content", ":$paramlike", false, false) .
                                            " AND f.id = :$paramid)";
                                    $searchparams[$paramlike] = "%\"$id\"%";
                                    $searchparams[$paramid] = $field->id();
                                    $i++;
                                }
                            }
                        } else {
                            if ($field instanceof datalynxfield_userinfo) {
                                $paramlike = "fieldquicksearch$i";
                                $paramid = "fieldid$i";
                                $searchlike[$paramlike] = "(" .
                                        $DB->sql_like("c{$field->id()}.data", ":$paramlike", false,
                                                false) . ")";
                                $searchparams[$paramlike] = '%' . $DB->sql_like_escape(
                                        $simplesearch) . '%';
                                $searchparams[$paramid] = $field->id();
                                $i++;
                            }
                        }
                    }
                }
            }

            $searchwhere[] = ' (' . implode(' OR ', $searchlike) . ') ';
        }

        $wheresearch = $searchwhere ? ' AND (' . implode(' OR ', $searchwhere) .')' : '';

        // Register referred tables.
        $this->_filteredtables = $searchfrom;

        return array($searchtables, $wheresearch, $searchparams);
    }

    /**
     * Check if we should add this fieldid to our whereand and whereor clause.
     * Catches all field situations that cause problems.
     */
    public function add_fieldid($option, $field) {

        // If the field says it needs no content we trust its judgement.
        if (!$field->is_datalynx_content()) {
            return false;
        }
        // The operator "" means we look for empty fields, don't add fieldids.
        if ($option[1] == "") {
            return false;
        }
        // Exclude tags because they use an intermediate db query.
        if ($field->type == 'tag') {
            return false;
        }

        return true;
    }

    /**
     */
    public function get_sort_sql($fields) {
        global $DB;

        $sorties = array();
        $orderby = array("e.timecreated ASC");
        $params = array();
        $sorttables = '';
        $stringindexed = false;

        $sortfields = $this->_sortfields; // Stores fieldids like in the db.

        if ($sortfields) {

            $orderby = array();
            foreach ($sortfields as $fieldid => $sortdir) {
                if (empty($fields[$fieldid])) {
                    continue;
                }

                $field = $fields[$fieldid];

                $sortname = $field->get_sort_sql();
                // Add non-internal fields to sorties.
                if (!$field::is_internal()) {
                    $sorties[$fieldid] = $sortname;
                }

                // Here we can check if fields are special.
                if ($field instanceof datalynxfield_option_multiple ||
                        $field instanceof datalynxfield_option_single) {
                    // Read values of field from database.
                    $fieldvalues = $DB->get_field('datalynx_fields', 'param1',
                            array('id' => $fieldid), MUST_EXIST);
                    $fieldvalues = explode("\n", $fieldvalues);

                    $replacestring = $sortname; // Works only for single values yet.
                    foreach ($fieldvalues as $key => $value) {
                        $replacestring = "REPLACE(" . $replacestring . ",'#" . ($key + 1) . "#', '" .
                                $value . "')";
                    }
                    $orderby[] = $replacestring . ($sortdir ? 'DESC' : 'ASC');
                    $stringindexed = true;
                } else {
                    $orderby[] = "$sortname " . ($sortdir ? 'DESC' : 'ASC');
                }

                // Register join field if applicable.
                $this->register_join_field($field);
            }
        }

        // Compile sql for sort settings.
        $sortorder = ' ORDER BY ' . implode(', ', $orderby) . ' ';
        if ($sorties) {
            $sortfrom = array_keys($sorties);
            $paramcount = 0;
            foreach ($sortfrom as $fieldid) {
                // Add only tables which are not already added.
                if (empty($this->_filteredtables) or !in_array($fieldid, $this->_filteredtables)) {
                    $this->_filteredtables[] = $fieldid;
                    list($fromsql, $params["sortie$paramcount"]) = $fields[$fieldid]->get_sort_from_sql(
                            'sortie', $paramcount);
                    $sorttables .= $fromsql;
                    $paramcount++;
                }
            }
        }

        // If one of the sort vars needs the indexed values join fields.
        if ($stringindexed) {
            $sorttables .= " LEFT JOIN {datalynx_fields} f ON c$fieldid.fieldid = f.id ";
        }

        return array($sorttables, $sortorder, $params);
    }

    /**
     */
    public function get_content_sql($fields) {
        $contentfields = $this->contentfields;

        $params = array();
        $datalynxcontent = array();
        $whatcontent = ' ';
        $contenttables = ' ';

        if ($contentfields) {
            $whatcontent = array();
            $contentfrom = array();
            $paramcount = 0;
            foreach ($contentfields as $fieldid) {
                // Skip non-selectable fields (some of the internal fields e.g. entryauthor which
                // are included in the select clause by default).
                if (!isset($fields[$fieldid]) or !$selectsql = $fields[$fieldid]->get_select_sql()) {
                    continue;
                }

                $field = $fields[$fieldid];

                // Register join field if applicable.
                if ($this->register_join_field($field)) {
                    // Processing is done separately.
                    continue;
                }

                // Add what content if field already included for sort or search.
                if (in_array($fieldid, $this->_filteredtables)) {
                    $whatcontent[] = $selectsql;

                    // If not in sort or search separate datalynx_contents content b/c of limit on.
                    // Joins.
                    // This content would be fetched after the entries and added to the entries.
                } else {
                    if ($field->is_datalynx_content()) {
                        $datalynxcontent[] = $fieldid;
                    } else {
                        $whatcontent[] = $selectsql;
                        $this->_filteredtables[] = $fieldid;
                        list($contentfrom[$fieldid], $params["contentie$paramcount"]) = $field->get_sort_from_sql(
                                'contentie', $paramcount);
                        $paramcount++;
                    }
                }
            }

            // Process join fields.
            foreach ($this->_joins as $joinfield) {
                $whatcontent[] = $field->get_select_sql();
                list($sqlfrom, $fieldparams) = $field->get_join_sql();
                $contentfrom[$fieldid] = $sqlfrom;
                $params = array_merge($params, $fieldparams);
            }

            $whatcontent = !empty($whatcontent) ? ', ' . implode(', ', $whatcontent) : ' ';
            $contenttables = ' ' . implode(' ', $contentfrom);
        }
        return array($datalynxcontent, $whatcontent, $contenttables, $params);
    }

    /**
     *
     * @return bool True if the field is registered, false otherwise
     */
    public function register_join_field($field) {
        if ($field->is_joined()) {
            if (!isset($this->_joins[$field->type])) {
                $this->_joins[$field->type] = $field;
            }
            return true;
        }
        return false;
    }

    /**
     */
    public function append_sort_options(array $sorties) {
        if ($sorties) {
            $sortoptions = $this->customsort ? unserialize($this->customsort) : array();
            foreach ($sorties as $fieldid => $sortdir) {
                $sortoptions[$fieldid] = $sortdir;
            }
            $this->customsort = serialize($sortoptions);
        }
    }
    // Prepend sort option.

    /**
     */
    public function append_search_options(array $searchies) {
        if ($searchies) {
            $searchoptions = $this->customsearch ? unserialize($this->customsearch) : array();
            foreach ($searchies as $fieldid => $searchy) {
                if (empty($searchoptions[$fieldid])) {
                    $searchoptions[$fieldid] = $searchies[$fieldid];
                } // TODO add capability check in else-branch.
            }
            $this->customsearch = serialize($searchoptions);
        }
    }
    // Prepend search option.
}

/**
 * Filter manager class
 */
class datalynx_filter_manager {

    const USER_FILTER_MAX_NUM = 5;

    const BLANK_FILTER = -1;

    const USER_FILTER_SET = -2;

    const USER_FILTER_ID_START = -10;

    protected $_df;

    protected $_filters;

    /**
     * constructor
     */
    public function __construct($df) {
        $this->_df = $df;
        $this->_filters = array();
    }

    /**
     */
    public function get_filter_from_id($filterid = 0, array $options = null) {

        $df = $this->_df;
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
            $customfilter = $options['customfilter'];

            // User preferences.
            if (($filterid == self::USER_FILTER_SET or $advanced or $customfilter) and $view and $view->is_active()) {
                $filter = $this->set_user_filter($filterid, $view, $advanced, $customfilter);
                return new datalynx_filter($filter);
            }

            // Retrieve existing user filter (filter id > blank filter).
            if ($filterid != self::USER_FILTER_SET and
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
        if ($this->get_filters() and isset($this->_filters[$filterid])) {
            return clone ($this->_filters[$filterid]);
        } else {
            $filter = new stdClass();
            $filter->dataid = $df->id();

            return new datalynx_filter($filter);
        }
    }

    /**
     */
    public function get_filter_from_url($url, $raw = false) {

        $df = $this->_df;
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
        if (!$this->_filters or $forceget) {
            $this->_filters = array();
            if ($filters = $DB->get_records('datalynx_filters', array('dataid' => $this->_df->id()))) {
                foreach ($filters as $filterid => $filterdata) {
                    $this->_filters[$filterid] = new datalynx_filter($filterdata);
                }
            }
        }

        if ($this->_filters) {
            if (empty($exclude) and !$menu) {
                return $this->_filters;
            } else {
                $filters = array();
                foreach ($this->_filters as $filterid => $filter) {
                    if (!empty($exclude) and in_array($filterid, $exclude)) {
                        continue;
                    }
                    if ($menu) {
                        if ($filter->visible or
                                has_capability('mod/datalynx:managetemplates', $this->_df->context)
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

        $df = $this->_df;

        $filters = array();
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
        $processedfids = array();
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
                        new moodle_url('/mod/datalynx/filter/index.php',
                                array('d' => $df->id(),
                                        $action => implode(',', array_keys($filters)),
                                        'sesskey' => sesskey(), 'confirmed' => 1)),
                        new moodle_url('/mod/datalynx/filter/index.php', array('d' => $df->id())));

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

                                    $other = array('dataid' => $this->_df->id());
                                    $event = \mod_datalynx\event\field_updated::create(
                                            array('context' => $this->_df->context,
                                                    'objectid' => $filter->id, 'other' => $other));
                                    $event->trigger();
                                } else {
                                    $filter->id = $DB->insert_record('datalynx_filters', $filter, true);
                                    $processedfids[] = $filter->id;
                                    $strnotify = 'filtersadded';

                                    $other = array('dataid' => $this->_df->id());
                                    $event = \mod_datalynx\event\field_created::create(
                                            array('context' => $this->_df->context,
                                                    'objectid' => $filter->id, 'other' => $other
                                            ));
                                    $event->trigger();
                                }
                                // Update cached filters.
                                $this->_filters[$filter->id] = $filter;
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

                                $other = array('dataid' => $this->_df->id());
                                $event = \mod_datalynx\event\field_created::create(
                                        array('context' => $this->_df->context,
                                                'objectid' => $filterid, 'other' => $other
                                        ));
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

                            $other = array('dataid' => $this->_df->id());
                            $event = \mod_datalynx\event\field_updated::create(
                                    array('context' => $this->_df->context,
                                            'objectid' => $filter->id, 'other' => $other
                                    ));
                            $event->trigger();
                        }

                        $strnotify = '';
                        break;

                    case 'delete':
                        foreach ($filters as $filter) {
                            $DB->delete_records('datalynx_filters', array('id' => $filter->id));

                            // Reset default filter if needed.
                            if ($filter->id == $df->data->defaultfilter) {
                                $df->set_default_filter();
                            }

                            $processedfids[] = $filter->id;

                            $other = array('dataid' => $this->_df->id());
                            $event = \mod_datalynx\event\field_deleted::create(
                                    array('context' => $this->_df->context,
                                            'objectid' => $filter->id, 'other' => $other
                                    ));
                            $event->trigger();
                        }
                        $strnotify = 'filtersdeleted';
                        break;

                    default:
                        break;
                }

                if (!empty($strnotify)) {
                    $filtersprocessed = $processedfids ? count($processedfids) : 'No';
                    $df->notifications['good'][] = get_string($strnotify, 'datalynx',
                            $filtersprocessed);
                }
                return $processedfids;
            }
        }
    }

    /**
     */
    public function get_filter_form($filter) {
        global $CFG;

        require_once("$CFG->dirroot/mod/datalynx/filter/filter_form.php");
        $formurl = new moodle_url('/mod/datalynx/filter/index.php',
                array('d' => $this->_df->id(), 'fid' => $filter->id, 'update' => 1));
        $mform = new mod_datalynx_filter_form($this->_df, $filter, $formurl);
        return $mform;
    }

    /**
     */
    public function display_filter_form($mform, $filter, $urlparams = null) {
        $streditinga = $filter->id ? get_string('filteredit', 'datalynx', $filter->name) : get_string(
                'filternew', 'datalynx');
        $heading = html_writer::tag('h2', format_string($streditinga),
                array('class' => 'mdl-align'));

        $this->_df->print_header(array('tab' => 'filters', 'urlparams' => $urlparams));
        echo $heading;
        $mform->display();
        $this->_df->print_footer();

        exit();
    }

    /**
     */
    public function get_filter_from_form($filter, $formdata, $finalize = false) {
        $filter->name = $formdata->name;
        $filter->description = !empty($formdata->description) ? $formdata->description : '';
        $filter->perpage = !empty($formdata->uperpage) ? $formdata->uperpage : 0;
        $filter->selection = !empty($formdata->selection) ? $formdata->selection : 0;
        $filter->groupby = !empty($formdata->groupby) ? $formdata->groupby : 0;
        $filter->search = isset($formdata->search) ? $formdata->search : '';
        $filter->customsort = $this->get_sort_options_from_form($formdata);
        $filter->customsearch = $this->get_search_options_from_form($formdata, $finalize);

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
        $customfilterfieldids = array();
        foreach ($customfilterfields as $fid => $field) {
            $customfilterfieldids[] = $fid;
        }

        $fields = $this->_df->get_fields();
        $searchfields = array();
        foreach ($formdata as $key => $value) {
            $formfieldarray = explode("_", $key);
            if (count($formfieldarray) >= 3) {
                $fieldname = $formfieldarray[2];
                switch ($fieldname) {
                    case ("approve"):
                        if ((int) $value > 0) {
                            $searchfields['approve']['AND'][] = array('', '=', $value);
                        }
                        break;
                    case ("timecreated"):
                    case ("timemodified"):
                        if (count($formfieldarray) > 4 && $formfieldarray[3] == 'from' &&
                                $formfieldarray[4] == 'active') {
                            if ($formdata->{$key}) {
                                $valuearr = array();
                                $fromkey = str_replace('_active', '', $key);
                                $valuearr[] = $formdata->{$fromkey};
                                $tokeyactive = str_replace('_from', '_to', $key);
                                if (isset($formdata->{$tokeyactive})) {
                                    $tokey = str_replace('_active', '', $tokeyactive);
                                    $valuearr[] = $formdata->{$tokey};
                                    $searchfields[$fieldname]['AND'][] = array('', 'BETWEEN',
                                        $valuearr);
                                } else {
                                    $searchfields[$fieldname]['AND'][] = array('', '>=',
                                        $valuearr[0]);
                                }
                            }
                        }
                        break;
                    case ("status"):
                        if ((int) $value > 0) {
                            $searchfields['status']['AND'][] = array('', '=', $value);
                        }
                        break;
                    default:
                        if (in_array($fieldname, $customfilterfieldids)) {
                            $type = $fields[$fieldname]->type;
                            if ($type == "text") {
                                if ($value) {
                                    $searchfields[$fieldname]['AND'][] = array('', 'LIKE', $value);
                                }
                            } else if ($type == "file") {
                                if ($value == '0') {
                                    $searchfields[$fieldname]['AND'][] = array('', '', false);
                                } else if ($value == '1') {
                                    $searchfields[$fieldname]['AND'][] = array('NOT', '', false);
                                }
                            } else {
                                // Analog to advanced filter form:
                                // searchfieldid - searchandor - not - operator - value.
                                // Only add to query when something is chosen, ignore empty values.
                                if ($value) {
                                    $searchfields[$fieldname]['AND'][] = array('', 'ANY_OF', $value);
                                }
                            }
                        }
                }
            } else {
                if ($key == "search" && $value) {
                    $filter->search = $value;
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
        $sortfields = array();
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
        if ($fields = $this->_df->get_fields()) {
            $searchfields = array();
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
                        // Don't add empty criteria on cleanup (unless operator is Empty and thus.
                        // Doesn't need search value).
                        if ($finalize and $operator and !$parsedvalue) {
                            continue;
                        }

                        // If finalizing, aggregate by fieldid and searchandor,.
                        // Otherwise just make a flat array (of arrays).
                        if ($finalize) {
                            if (!isset($searchfields[$searchfieldid])) {
                                $searchfields[$searchfieldid] = array();
                            }
                            if (!isset($searchfields[$searchfieldid][$searchandor])) {
                                $searchfields[$searchfieldid][$searchandor] = array();
                            }
                            $searchfields[$searchfieldid][$searchandor][] = array($not, $operator, $parsedvalue);
                        } else {
                            $searchfields[] = array($searchfieldid, $searchandor, $not, $operator, $parsedvalue);
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
        if ($fields = $this->_df->get_fields()) {
            $searchfields = array();
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
                        if ($finalize and $operator and !$parsedvalue) {
                            continue;
                        }

                        // If finalizing, aggregate by fieldid and searchandor,
                        // otherwise just make a flat array (of arrays).
                        if ($finalize) {
                            if (!isset($searchfields[$searchfieldid])) {
                                $searchfields[$searchfieldid] = array();
                            }
                            if (!isset($searchfields[$searchfieldid][$searchandor])) {
                                $searchfields[$searchfieldid][$searchandor] = array();
                            }
                            $searchfields[$searchfieldid][$searchandor][] = array($not, $operator, $parsedvalue);
                        } else {
                            $searchfields[] = array($searchfieldid, $searchandor, $not, $operator, $parsedvalue);
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

        $df = $this->_df;

        $filterbaseurl = '/mod/datalynx/filter/index.php';
        $linkparams = array('d' => $df->id(), 'sesskey' => sesskey());

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

        $selectallnone = html_writer::checkbox(null, null, false, null,
                array('onclick' => 'select_allnone(\'filter\'&#44;this.checked)'));
        $multidelete = html_writer::tag('button',
                $OUTPUT->pix_icon('t/delete', get_string('multidelete', 'datalynx')),
                array('name' => 'multidelete',
                    'onclick' => 'bulk_action(\'filter\'&#44; \'' .
                    htmlspecialchars_decode(new moodle_url($filterbaseurl, $linkparams)) .
                    '\'&#44; \'delete\')'));

        $table = new html_table();
        $table->head = array($strfilters, $strdescription, $strperpage, $strcustomsort,
            $strcustomsearch, $strurlquery, $strvisible, $strdefault, $stredit, $strduplicate,
            $multidelete, $selectallnone);
        $table->align = array('left', 'left', 'center', 'left', 'left', 'left', 'center', 'center',
            'center', 'center', 'center');
        $table->wrap = array(false, false, false, false, false, false, false, false, false, false,
            false);
        $table->attributes['align'] = 'center';

        foreach ($this->_filters as $filterid => $filter) {
            $filtername = html_writer::link(
                    new moodle_url($filterbaseurl,
                            $linkparams + array('fedit' => $filterid, 'fid' => $filterid)),
                    $filter->name);
            $filterdescription = shorten_text($filter->description, 30);
            $filteredit = html_writer::link(
                    new moodle_url($filterbaseurl,
                            $linkparams + array('fedit' => $filterid, 'fid' => $filterid)),
                    $OUTPUT->pix_icon('t/edit', $stredit));
            $filterduplicate = html_writer::link(
                    new moodle_url($filterbaseurl, $linkparams + array('duplicate' => $filterid)),
                    $OUTPUT->pix_icon('t/copy', $strduplicate));
            $filterdelete = html_writer::link(
                    new moodle_url($filterbaseurl, $linkparams + array('delete' => $filterid)),
                    $OUTPUT->pix_icon('t/delete', $strdelete));
            $filterselector = html_writer::checkbox("filterselector", $filterid, false);

            // Visible.
            if ($filter->visible) {
                $visibleicon = $OUTPUT->pix_icon('t/hide', $strhide);
            } else {
                $visibleicon = $OUTPUT->pix_icon('t/show', $strshow);
            }
            $visible = html_writer::link(
                    new moodle_url($filterbaseurl, $linkparams + array('visible' => $filterid)),
                    $visibleicon);

            // Default filter.
            if ($filterid == $df->data->defaultfilter) {
                $defaultfilter = html_writer::link(
                        new moodle_url($filterbaseurl, $linkparams + array('default' => -1)),
                        $OUTPUT->pix_icon('t/clear', ''));
            } else {
                $defaultfilter = html_writer::link(
                        new moodle_url($filterbaseurl, $linkparams + array('default' => $filterid)),
                        $OUTPUT->pix_icon('t/switch_whole', $strchoose));
            }
            // Parse custom settings.
            $sortoptions = '';
            $sorturlquery = '';
            $searchoptions = '';
            $searchurlquery = '';

            if ($filter->customsort or $filter->customsearch) {
                // Get field objects.
                $fields = $df->get_fields();

                // CUSTOM SORT.
                $sortfields = array();
                if ($filter->customsort) {
                    $sortfields = unserialize($filter->customsort);
                }

                if ($sortfields) {
                    $sortarr = array();
                    $sorturlarr = array();
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
                        $sortarr[] = $OUTPUT->pix_icon('t/' . ($sortdir ? 'down' : 'up'),
                                $strsortdir) . ' ' . $fields[$fieldid]->field->name;
                    }
                    if ($sortfields) {
                        $sortoptions = implode('<br />', $sortarr);
                        $sorturlquery = '&usort=' . urlencode(implode(',', $sorturlarr));
                    }
                }
                $sortoptions = !empty($sortoptions) ? $sortoptions : '---';

                // CUSTOM SEARCH.
                $searchfields = array();
                if ($filter->customsearch) {
                    $searchfields = unserialize($filter->customsearch);
                }

                // Verbose search criteria.
                if ($searchfields) {
                    $searcharr = array();
                    foreach ($searchfields as $fieldid => $searchfield) {
                        if (empty($fields[$fieldid])) {
                            continue;
                        }
                        $fieldoptions = array();
                        if (!empty($searchfield['AND'])) {
                            $options = array();
                            foreach ($searchfield['AND'] as $option) {
                                if ($option) {
                                    $options[] = $fields[$fieldid]->format_search_value($option);
                                }
                            }
                            $fieldoptions[] = '<b>' . $fields[$fieldid]->field->name . '</b>:' .
                                    implode(' <b>and</b> ', $options);
                        }
                        if (!empty($searchfield['OR'])) {
                            $options = array();
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

            $table->data[] = array($filtername, $filterdescription, $perpage, $sortoptions,
                $searchoptions, $sorturlquery . $searchurlquery, $visible, $defaultfilter,
                $filteredit, $filterduplicate, $filterdelete, $filterselector);
        }

        echo html_writer::table($table);
    }

    /**
     */
    public function print_add_filter() {
        echo html_writer::empty_tag('br');
        echo html_writer::start_tag('div', array('class' => 'fieldadd mdl-align'));
        echo html_writer::link(
                new moodle_url('/mod/datalynx/filter/index.php',
                        array('d' => $this->_df->id(), 'sesskey' => sesskey(), 'new' => 1)),
                get_string('filteradd', 'datalynx'));
        echo html_writer::end_tag('div');
        echo html_writer::empty_tag('br');
    }

    // ADVANCED FILTER.

    /**
     */
    public function get_advanced_filter_form($filter, $view) {
        global $CFG;

        require_once("$CFG->dirroot/mod/datalynx/filter/filter_form.php");
        $formurl = new moodle_url($view->get_baseurl(), array('filter' => self::USER_FILTER_SET, 'afilter' => 1));
        $mform = new mod_datalynx_advanced_filter_form($this->_df, $filter, $formurl, array('view' => $view));
        return $mform;
    }

    // CUSTOM FILTER.

    /**
     */
    public function get_customfilter_frontend_form($filter, $view, $customfilter = false) {
        global $CFG;

        require_once("$CFG->dirroot/mod/datalynx/filter/filter_form.php");
        $cfilter = isset($customfilter->id) ? $customfilter->id : "1";
        $formurl = new moodle_url($view->get_baseurl(), array('filter' => self::USER_FILTER_SET, 'cfilter' => $cfilter));
        $mform = new mod_datalynx_customfilter_frontend_form($this->_df, $filter, $formurl,
                array('view' => $view), 'post', '', null, true, $customfilter);
        return $mform;
    }

    /**
     */
    public function get_user_filters_menu($viewid) {
        $filters = array();

        $df = $this->_df;
        $dfid = $df->id();
        if ($filternames = get_user_preferences("datalynxfilter-$dfid-$viewid-userfilters", '')) {
            foreach (explode(';', $filternames) as $filteridname) {
                list($filterid, $name) = explode(' ', $filteridname, 2);
                $filters[$filterid] = $name;
            }
        }
        return $filters;
    }

    /**
     */
    public function set_user_filter($filterid, $view, $advanced = false, $customfilter = false) {
        $df = $this->_df;
        $dfid = $df->id();
        $viewid = $view->id();

        // Advanced filter.
        if ($advanced) {
            $filter = new datalynx_filter((object) array('id' => $filterid, 'dataid' => $dfid));
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
            $filter = new datalynx_filter((object) array('id' => $filterid, 'dataid' => $dfid));
            $customfilter = $DB->get_record('datalynx_customfilters', array('id' => $customfilter));
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
        if (!$advanced and !$customfilter) {
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
                if (empty($modifycurrent) or empty($userfilters[$filterid])) {
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
            $userfilters = array($filterid => $filter->name) + $userfilters;
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
        $urlquery = array();

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
            $usort = array();
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
            $usearch = array();
            foreach ($searchies as $fieldid => $andor) {
                foreach ($andor as $key => $soptions) {
                    if (empty($soptions)) {
                        continue;
                    }
                    foreach ($soptions as $options) {
                        if (empty($options)) {
                            continue;
                        }
                        list($not, $op, $value) = $options;
                        if (is_array($value) && isset($value['selected'])) {
                            $searchvalue = is_array($value['selected']) ? implode('|',
                                    $value['selected']) : $value['selected'];
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
        $soptions = array();
        if ($query) {
            $usearch = urldecode($query);
            $searchies = explode('@', $usearch);
            foreach ($searchies as $key => $searchy) {
                list($fieldid, $andor, $options) = explode(':', $searchy);
                $soptions[$fieldid] = array(
                        $andor => array_map(function($a) {
                            return explode(',', $a);
                        }, explode('#', $options))
                );
            }
        }
        return $soptions;
    }

    /**
     */
    public static function get_filter_options_from_url($url = null) {
        $filteroptions = array(      // Left: filteroption-names, right: urlparameter-names.
                'filterid' => array('filter', 0, PARAM_INT),
                'perpage' => array('uperpage', 0, PARAM_INT),
                'selection' => array('uselection', 0, PARAM_INT),
                'groupby' => array('ugroupby', 0, PARAM_INT),
                'customsort' => array('usort', '', PARAM_RAW),
                'customsearch' => array('usearch', '', PARAM_RAW),
                'page' => array('page', 0, PARAM_INT),
                'eids' => array('eids', 0, PARAM_SEQUENCE),
                'users' => array('users', '', PARAM_SEQUENCE),
                'groups' => array('groups', '', PARAM_SEQUENCE),
                'afilter' => array('afilter', 0, PARAM_INT),
                'cfilter' => array('cfilter', 0, PARAM_INT),
                'usersearch' => array('usersearch', 0, PARAM_RAW));

        $options = array();

        // Url provided.
        if ($url) {
            if ($url instanceof moodle_url) {
                foreach ($filteroptions as $option => $args) {
                    list($name, , ) = $args;
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
            list($name, $default, $type) = $args;
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
        $filteroptions = array(   // Left: urlparam-names, right: userpreferences-names.
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
                'usersearch' => 'usersearch'
        );

        $options = array();

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
