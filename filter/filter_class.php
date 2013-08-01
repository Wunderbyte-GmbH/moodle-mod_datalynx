<?php
// This file is part of Moodle - http://moodle.org/.
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
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.
 
/**
 * @package mod
 * @subpackage dataform
 * @copyright 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Filter class
 */
class dataform_filter {

    public $id;
    public $dataid;
    public $name;
    public $description;
    public $visible;
    
    public $perpage;
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

    /**
     *
     */
    public static function get_sort_url_query(array $sorties) {
        $usort = null;
        if ($sorties) {
            $usort = implode(',', array_map(function($a) {return implode(' ', $a);}, $sorties));
            $usort = urlencode($usort);
        }
        return $usort;
    }
    
    /**
     *
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
                    $options = implode('#', array_map(function($a) {return implode(',', $a);}, $soptions));
                    $usearch[] = "$fieldid:$key:$options";
                }
            }
            $usearch = implode('@', $usearch);
            $usearch = urlencode($usearch);
        }
        return $usearch;
    }
    
    /**
     * constructor
     */
    public function __construct($filterdata) {
        $this->id = empty($filterdata->id) ? 0 : $filterdata->id;
        $this->dataid = $filterdata->dataid; // required
        $this->name = empty($filterdata->name) ? '' : $filterdata->name;
        $this->description = empty($filterdata->description) ? '' : $filterdata->description;
        $this->visible = !isset($filterdata->visible) ? 1 : $filterdata->visible;

        $this->perpage = empty($filterdata->perpage) ? 0 : $filterdata->perpage;
        $this->selection = empty($filterdata->selection) ? 0 : $filterdata->selection;
        $this->groupby = empty($filterdata->groupby) ? '' : $filterdata->groupby;
        $this->customsort = empty($filterdata->customsort) ? '' : $filterdata->customsort;
        $this->customsearch = empty($filterdata->customsearch) ? '' : $filterdata->customsearch;
        $this->search = empty($filterdata->search) ? '' : $filterdata->search;
        $this->contentfields = empty($filterdata->contentfields) ? null : $filterdata->contentfields;

        $this->eids = empty($filterdata->eids) ? null : $filterdata->eids;
        $this->users = empty($filterdata->users) ? null : $filterdata->users;
        $this->groups = empty($filterdata->groups) ? null : $filterdata->groups;
        $this->page = empty($filterdata->page) ? 0 : $filterdata->page;
    }

    /**
     *
     */
    public function get_sql($fields) {
        $this->init_filter_sql();
        
        // SEARCH sql
        list($searchtables, $wheresearch, $searchparams) = $this->get_search_sql($fields);
        // SORT sql
        list($sorttables, $sortorder, $sortparams) = $this->get_sort_sql($fields);
        // CONTENT sql ($dataformcontent is an array of fieldid whose content needs to be fetched)
        list($dataformcontent, $whatcontent, $contenttables, $contentparams) = $this->get_content_sql($fields);
    
        return array(
            " $searchtables $sorttables $contenttables ",
            $wheresearch,
            $sortorder,
            $whatcontent,
            array_merge($searchparams, $sortparams, $contentparams),
            $dataformcontent
        );
    }

    /**
     *
     */
    public function init_filter_sql() {
        $this->_filteredtables = null;
        $this->_searchfields = $this->customsearch ? unserialize($this->customsearch) : array();
        $this->_sortfields = $this->customsort ? unserialize($this->customsort) : array();
        $this->_joins = array();
    }
    
    /**
     *
     */
    public function get_search_sql($fields) {
        global $DB;
        
        $searchfrom = array();
        $searchwhere = array();
        $searchparams = array(); // named params array
        
        $searchfields = $this->_searchfields;
        $simplesearch = $this->search;
        $searchtables = '';

        if ($searchfields) {

            $whereand = array();
            $whereor = array();
            foreach($searchfields as $fieldid => $searchfield) {
                // if we got this far there must be some actual search values
                $field = $fields[$fieldid];

                // Search from is required only for non-internal fields
                if (!$field::is_internal()) {
                    $searchfrom[] = $fieldid;
                }
                
                // Register join field if applicable
                $this->register_join_field($field);

                // Add AND search clauses
                if (!empty($searchfield['AND'])) {
                    foreach ($searchfield['AND'] as $option) {
                        if ($fieldsqloptions = $field->get_search_sql($option)) {
                            list($fieldsql, $fieldparams) = $fieldsqloptions;
                            $whereand[] = $fieldsql;
                            $searchparams = array_merge($searchparams, $fieldparams);
                        }
                    }
                }

                // add OR search clause
                if (!empty($searchfield['OR'])) {
                    foreach ($searchfield['OR'] as $option) {
                        if ($fieldsqloptions = $field->get_search_sql($option)) {
                            list($fieldsql, $fieldparams) = $fieldsqloptions;
                            $whereor[] = $fieldsql;
                            $searchparams = array_merge($searchparams, $fieldparams);
                        }
                    }
                }
            }

            if ($searchfrom) {
                $searchwhere[] = implode(' AND ', array_map(function($fieldid) {return " c$fieldid.fieldid = $fieldid ";}, $searchfrom));
            }
            if ($whereand) {
                $searchwhere[] = implode(' AND ', $whereand);
            }
            if ($whereor) {
                $searchwhere[] = '('. implode(' OR ', $whereor). ')';
            }

            // compile sql for search settings
            if ($searchfrom) {
                foreach ($searchfrom as $fieldid) {
                    // add only tables which are not already added
                    if (empty($this->_filteredtables) or !in_array($fieldid, $this->_filteredtables)) {
                        $this->_filteredtables[] = $fieldid;
                        $searchtables .= $fields[$fieldid]->get_search_from_sql();
                    } 
                }
            }

        } else if ($simplesearch) {
            $searchtables .= " JOIN {dataform_contents} cs ON cs.entryid = e.id ";
            $searchlike = array(
                'search1' => $DB->sql_like('cs.content', ':search1', false),
                'search2' => $DB->sql_like('u.firstname', ':search2', false),
                'search3' => $DB->sql_like('u.lastname', ':search3', false),
                'search4' => $DB->sql_like('u.username', ':search4', false)
            );
            $searchwhere[] = ' ('. implode(' OR ', $searchlike). ') ';
            foreach ($searchlike as $namekey => $unused) {
                $searchparams[$namekey] = "%$simplesearch%";
            }
        }
    
        $wheresearch = $searchwhere ? ' AND '. implode(' AND ', $searchwhere) : '';

        // register referred tables
        $this->_filteredtables = $searchfrom;
        
        return array($searchtables, $wheresearch, $searchparams);
    }

    /**
     * 
     */
    public function get_sort_sql($fields) {
        $sorties = array();
        $orderby = array("e.timecreated ASC");
        $params = array();

        $sortfields = $this->_sortfields;

        if ($sortfields) {

            $orderby = array();
            foreach ($sortfields as $fieldid => $sortdir) {
                $field = $fields[$fieldid];
                
                $sortname = $field->get_sort_sql();
                // Add non-internal fields to sorties
                if (!$field::is_internal()) {
                    $sorties[$fieldid] = $sortname;
                }
                $orderby[] = "$sortname ". ($sortdir ? 'DESC' : 'ASC');
                
                // Register join field if applicable
                $this->register_join_field($field);                
            }
        }

        // compile sql for sort settings
        $sorttables = '';
        $sortorder = ' ORDER BY '. implode(', ', $orderby). ' ';
        if ($sorties) {
            $sortfrom = array_keys($sorties);
            $paramcount = 0;
            foreach ($sortfrom as $fieldid) {
                // add only tables which are not already added
                if (empty($this->_filteredtables) or !in_array($fieldid, $this->_filteredtables)) {
                    $this->_filteredtables[] = $fieldid; 
                    list($fromsql, $params["sortie$paramcount"]) = 
                            $fields[$fieldid]->get_sort_from_sql('sortie', $paramcount);
                    $sorttables .= $fromsql;
                    $paramcount++;
                }
            }
        }
        return array($sorttables, $sortorder, $params);
    }

    /**
     *
     */
    public function get_content_sql($fields) {

        $contentfields = $this->contentfields;

        $params = array();
        $whatcontent = ' ';
        $contenttables = ' ';
        
        if ($contentfields) {
            $dataformcontent = array();
            $whatcontent = array();
            $contentfrom = array();
            $paramcount = 0;
            foreach ($contentfields as $fieldid) {
                // Skip non-selectable fields (some of the internal fields e.g. _user which are included in the select clause by default)
                if (!isset($fields[$fieldid]) or !$selectsql = $fields[$fieldid]->get_select_sql()) {
                    continue;
                }
                
                $field = $fields[$fieldid];

                // Register join field if applicable
                if ($this->register_join_field($field)) {
                    // Processing is done separately
                    continue;
                }
                
                // Add what content if field already included for sort or search
                if (in_array($fieldid, $this->_filteredtables)) {
                    $whatcontent[] = $selectsql;
                
                // If not in sort or search separate dataform_contents content b/c of limit on joins
                // This content would be fetched after the entries and added to the entries
                } else { 
                    if ($field->is_dataform_content()) {
                        $dataformcontent[] = $fieldid;
                    } else {                    
                        $whatcontent[] = $selectsql;
                        $this->_filteredtables[] = $fieldid; 
                        list($contentfrom[$fieldid], $params["contentie$paramcount"]) = 
                                $field->get_sort_from_sql('contentie', $paramcount);
                        $paramcount++;
                    }
                }
            }
            
            // Process join fields
            foreach ($this->_joins as $joinfield) {
                $whatcontent[] = $field->get_select_sql();
                list($sqlfrom, $fieldparams) = $field->get_join_sql();
                $contentfrom[$fieldid] = $sqlfrom;
                $params = array_merge($params, $fieldparams);
            }
                
            $whatcontent = !empty($whatcontent) ? ', '. implode(', ', $whatcontent) : ' ';
            $contenttables = ' '. implode(' ', $contentfrom);
        }
        return array($dataformcontent, $whatcontent, $contenttables, $params);
    }

    /**
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
     *
     */
    public function append_sort_options(array $sorties) {
        if ($sorties) {
            $sortoptions = $this->customsort ? unserialize($this->customsort) : array();
            foreach ($sorties as $sorty) {
                list($fieldid, $sortdir) = $sorty;
                $sortoptions[$fieldid] = $sortdir;
            }
            $this->customsort = serialize($sortoptions);
        }
    }
    // Prepend sort option

    /**
     *
     */
    public function append_search_options(array $searchies) {
        if ($searchies) {
            $searchoptions = $this->customsearch ? unserialize($this->customsearch) : array();
            foreach ($searchies as $fieldid => $searchy) {
                if (empty($searchoptions[$fieldid])) {
                    $searchoptions[$fieldid] = $searchies[$fieldid];
                } else {
                    // TODO add capability
                    // Check
                }
            }
            $this->customsearch = serialize($searchoptions);
        }
    }
    // Prepend search option

}

/**
 * Filter manager class
 */
class dataform_filter_manager {

    const USER_FILTER = -1;
    const USER_FILTER_SET = -2;
    const USER_FILTER_RESET = -3;
    const BLANK_FILTER = -9;
    
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
     *
     */
    public function get_filter_from_id($filterid = 0) {
        global $DB;
        
        $df = $this->_df;

        // Set user preferences
        if ($filterid == self::USER_FILTER_SET) {
            set_user_preference('dataform_'. $df->id(). '_perpage', optional_param('userperpage', get_user_preferences('dataform_'. $df->id(). '_perpage', 0), PARAM_INT));
            set_user_preference('dataform_'. $df->id(). '_selection', optional_param('userselection', get_user_preferences('dataform_'. $df->id(). '_selection', 0), PARAM_INT));
            set_user_preference('dataform_'. $df->id(). '_groupby', optional_param('usergroupby', get_user_preferences('dataform_'. $df->id(). '_groupby', ''), PARAM_INT));
            set_user_preference('dataform_'. $df->id(). '_search', optional_param('usersearch', get_user_preferences('dataform_'. $df->id(). '_search', ''), PARAM_NOTAGS));
            set_user_preference('dataform_'. $df->id(). '_customsort', optional_param('usercustomsort', get_user_preferences('dataform_'. $df->id(). '_customsort', ''), PARAM_NOTAGS));
            set_user_preference('dataform_'. $df->id(). '_customsearch', optional_param('usercustomsearch', get_user_preferences('dataform_'. $df->id(). '_customsearch', ''), PARAM_NOTAGS));
            $filterid = self::USER_FILTER;
        // Reset user preferences
        } else if ($filterid == self::USER_FILTER_RESET) {
            unset_user_preference('dataform_'. $df->id(). '_perpage');
            unset_user_preference('dataform_'. $df->id(). '_selection');
            unset_user_preference('dataform_'. $df->id(). '_groupby');
            unset_user_preference('dataform_'. $df->id(). '_search');
            unset_user_preference('dataform_'. $df->id(). '_customsort');
            unset_user_preference('dataform_'. $df->id(). '_customsearch');
            $filterid = 0;
        }
        
        // User preferences
        if ($filterid == self::USER_FILTER) {
            $filter = new object;
            $filter->id = $filterid;
            $filter->dataid = $df->id();
            $filter->perpage = get_user_preferences('dataform_'. $df->id(). '_perpage', 0);
            $filter->selection = get_user_preferences('dataform_'. $df->id(). '_selection', 0);
            $filter->groupby = get_user_preferences('dataform_'. $df->id(). '_groupby', '');
            $filter->search = trim(get_user_preferences('dataform_'. $df->id(). '_search', ''));
            $filter->customsort = trim(get_user_preferences('dataform_'. $df->id(). '_customsort', ''));
            $filter->customsearch = trim(get_user_preferences('dataform_'. $df->id(). '_customsearch', ''));
            return new dataform_filter($filter);
        }
            
        // Blank filter
        if ($filterid == self::BLANK_FILTER) {
            $filter = new object;
            $filter->dataid = $df->id();
            $filter->name = get_string('filternew', 'dataform');
            $filter->perpage = 10;

            return new dataform_filter($filter);
        }
            
        // Dataform default filter
        if ($filterid == 0) {
            // If no default return empty
            if (!$df->data->defaultfilter) {
                $filter = new object;
                $filter->dataid = $df->id();
            
                return new dataform_filter($filter);
                
            // otherwise assign to filterid for the Existing filter check
            } else {
                $filterid = $df->data->defaultfilter;
            }
        }
        
        // Existing filter
        if ($this->get_filters() and isset($this->_filters[$filterid])) {
            return clone($this->_filters[$filterid]);
        } else {
            throw new moodle_exception("Filter $filterid not found for Dataform ". $df->id());
        }
    }

    /**
     *
     */
    public function get_filters($exclude = null, $menu = false, $forceget = false) {
        global $DB;
        if (!$this->_filters or $forceget) {
            $this->_filters = array();
            if ($filters = $DB->get_records('dataform_filters', array('dataid' => $this->_df->id()))) {
                foreach ($filters as $filterid => $filterdata) {
                    $this->_filters[$filterid] = new dataform_filter($filterdata);
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
                        if ($filter->visible or has_capability('mod/dataform:managetemplates', $this->_df->context)) {
                            $filters[$filterid] = $filter->name;
                        }
                    } else {
                        $filters[$filterid]= $filter;
                    }
                }
                return $filters;
            }
        } else {
            return false;
        }
    }

    /**
     *
     */
    public function process_filters($action, $fids, $confirmed = false) {
        global $CFG, $DB, $OUTPUT;

        $df = $this->_df;

        $filters = array();
        // TODO may need new roles
        if (has_capability('mod/dataform:managetemplates', $df->context)) {
            // don't need record from database for filter form submission
            if ($fids) { // some filters are specified for action
                $filters = $DB->get_records_select('dataform_filters', "id IN ($fids)");
            } else if ($action == 'update') {
                $filters[] = $this->get_filter_from_id(self::BLANK_FILTER);
            }
        }
        $processedfids = array();
        $strnotify = '';

        // TODO update should be roled
        if (empty($filters)) {
            $df->notifications['bad'][] = get_string("filternoneforaction", 'dataform');
            return false;
        } else {
            if (!$confirmed) {
                // print header
                $df->print_header('filters');

                // Print a confirmation page
                echo $OUTPUT->confirm(get_string("filtersconfirm$action", 'dataform', count($filters)),
                        new moodle_url('/mod/dataform/filter/index.php', array('d' => $df->id(),
                                                                        $action => implode(',', array_keys($filters)),
                                                                        'sesskey' => sesskey(),
                                                                        'confirmed' => 1)),
                        new moodle_url('/mod/dataform/filter/index.php', array('d' => $df->id())));

                echo $OUTPUT->footer();
                exit;

            } else {
                // go ahead and perform the requested action
                switch ($action) {
                    case 'update':     // add new or update existing
                        $filter = reset($filters);
                        $mform = $this->get_filter_form($filter);

                        if ($mform->is_cancelled()) {
                            break;
                        }
                        
                        // Generate filter from form data
                        $formdata = $mform->get_submitted_data();
                        $filter = $this->get_filter_from_form($filter, $formdata);
                        
                        // Regenerate form and filter to obtain custom search data
                        $filterform = $this->get_filter_form($filter);
                        $formdata = $filterform->get_submitted_data();
                        
                        // return to form (on reload button press)
                        if ($mform->no_submit_button_pressed()) {
                            // Get raw filter from formdata
                            $filter = $this->get_filter_from_form($filter, $formdata);
                            $this->display_filter_form($filterform, $filter);

                        // process validated
                        } else if ($mform->get_data()) {
                            // Get clean filter from formdata
                            $filter = $this->get_filter_from_form($filter, $formdata, true);

                            if ($filter->id) {
                                $DB->update_record('dataform_filters', $filter);
                                $processedfids[] = $filter->id;
                                $strnotify = 'filtersupdated';
                            } else {
                                $filter->id = $DB->insert_record('dataform_filters', $filter, true);
                                $processedfids[] = $filter->id;
                                $strnotify = 'filtersadded';
                            }
                            // Update cached filters
                            $this->_filters[$filter->id] = $filter;                           
                        }
                        
                        break;

                    case 'duplicate':
                        if (!empty($filters)) {
                            foreach ($filters as $filter) {
                                // TODO: check for limit
                                // set new name
                                while ($df->name_exists('filters', $filter->name)) {
                                    $filter->name = 'Copy of '. $filter->name;
                                }
                                $filterid = $DB->insert_record('dataform_filters', $filter);

                                $processedfids[] = $filterid;
                            }
                        }
                        $strnotify = 'filtersadded';
                        break;

                    case 'visible':
                        $updatefilter = new object();
                        foreach ($filters as $filter) {
                            $updatefilter->id = $filter->id;
                            $updatefilter->visible = (int) !$filter->visible;
                            $DB->update_record('dataform_filters', $updatefilter);
                            // Update cached filters
                            $filter->visible = $updatefilter->visible;

                            $processedfids[] = $filter->id;
                        }

                        $strnotify = '';
                        break;

                    case 'delete':
                        foreach ($filters as $filter) {
                            $DB->delete_records('dataform_filters', array('id' => $filter->id));

                            // reset default filter if needed
                            if ($filter->id == $df->data->defaultfilter) {
                                $df->set_default_filter();
                            }

                            $processedfids[] = $filter->id;
                        }
                        $strnotify = 'filtersdeleted';
                        break;

                    default:
                        break;
                }

                add_to_log($df->course->id, 'dataform', 'filter '. $action, 'filter/index.php?id='. $df->cm->id, $df->id(), $df->cm->id);
                if (!empty($strnotify)) {
                    $filtersprocessed = $processedfids ? count($processedfids) : 'No';
                    $df->notifications['good'][] = get_string($strnotify, 'dataform', $filtersprocessed);
                }
                return $processedfids;
            }
        }
    }

    /**
     *
     */
    public function get_filter_form($filter) {
        global $CFG;

        require_once("$CFG->dirroot/mod/dataform/filter/filter_form.php");

        $formurl = new moodle_url(
            '/mod/dataform/filter/index.php',
            array('d' => $this->_df->id(), 'fid' => $filter->id, 'update' => 1)
        );
        $mform = new mod_dataform_filter_form($formurl, array('df' => $this->_df, 'filter' => $filter));
        return $mform;        
    }

    /**
     *
     */
    public function display_filter_form($mform, $filter, $urlparams = null) {

        $streditinga = $filter->id ? get_string('filteredit', 'dataform', $filter->name) : get_string('filternew', 'dataform');
        $heading = html_writer::tag('h2', format_string($streditinga), array('class' => 'mdl-align'));

        $this->_df->print_header(array('tab' => 'filters', 'urlparams' => $urlparams));
        echo $heading;
        $mform->display();
        $this->_df->print_footer();

        exit;
    }

    /**
     *
     */
    protected function get_filter_from_form($filter, $formdata, $finalize = false) {
        $filter->name = $formdata->name;
        $filter->description = $formdata->description;
        $filter->perpage = $formdata->perpage;
        $filter->selection = !empty($formdata->selection) ? $formdata->selection : 0;
        $filter->groupby = $formdata->groupby;
        $filter->search = isset($formdata->search) ? $formdata->search : '';
        $filter->customsort = $this->get_sort_options_from_form($formdata);
        $filter->customsearch = $this->get_search_options_from_form($formdata, $finalize);

        if ($filter->customsearch) {
            $filter->search = '';
        }

        return $filter;
    }

    /**
     *
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
     *
     */
    protected function get_search_options_from_form($formdata, $finalize = false) {
        if ($fields = $this->_df->get_fields()) {
            $searchfields = array();
            foreach ($formdata as $var => $unused) {
                if (strpos($var, 'searchandor') !== 0) {
                    continue;
                }

                $i = (int) str_replace('searchandor', '', $var);
                // check if trying to define a search criterion
                if ($searchandor = $formdata->{"searchandor$i"}) {
                    if ($searchfieldid = $formdata->{"searchfield$i"}) {
                        $parsedvalue = $fields[$searchfieldid]->parse_search($formdata, $i);
                        $not = isset($formdata->{"searchnot$i"}) ? 'NOT' : '';
                        $operator = isset($formdata->{"searchoperator$i"}) ? $formdata->{"searchoperator$i"} : '';
                        // Don't add empty criteria on cleanup
                        if ($finalize and !$parsedvalue) {
                            continue;
                        }

                        // If finalizing, aggregate by fieldid and searchandor,
                        // otherwise just make a flat array (of arrrays)
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
     *
     */
    public function print_filter_list(){
        global $OUTPUT;
        
        $df = $this->_df;
        
        $filterbaseurl = '/mod/dataform/filter/index.php';
        $linkparams = array('d' => $df->id(), 'sesskey' => sesskey());
                        
        // table headings
        $strfilters = get_string('name');
        $strdescription = get_string('description');
        $strperpage = get_string('filterperpage', 'dataform');
        $strcustomsort = get_string('filtercustomsort', 'dataform');
        $strcustomsearch = get_string('filtercustomsearch', 'dataform');
        $strurlquery = get_string('filterurlquery', 'dataform');
        $strvisible = get_string('visible');
        $strhide = get_string('hide');
        $strshow = get_string('show');
        $stredit = get_string('edit');
        $strdelete = get_string('delete');
        $strduplicate =  get_string('duplicate');
        $strdefault = get_string('default');
        $strchoose = get_string('choose');

        $selectallnone = html_writer::checkbox(null, null, false, null, array('onclick' => 'select_allnone(\'filter\'&#44;this.checked)'));
        $multidelete = html_writer::tag('button', 
                                    $OUTPUT->pix_icon('t/delete', get_string('multidelete', 'dataform')), 
                                    array('name' => 'multidelete',
                                            'onclick' => 'bulk_action(\'filter\'&#44; \''. htmlspecialchars_decode(new moodle_url($filterbaseurl, $linkparams)). '\'&#44; \'delete\')'));
    

        $table = new html_table();
        $table->head = array($strfilters, $strdescription, $strperpage, 
                            $strcustomsort, $strcustomsearch, $strurlquery,
                            $strvisible, $strdefault, $stredit, $strduplicate, $multidelete, $selectallnone);
        $table->align = array('left', 'left', 'center', 'left', 'left', 'left', 'center', 'center', 'center', 'center', 'center');
        $table->wrap = array(false, false, false, false, false, false, false, false, false, false, false);
        $table->attributes['align'] = 'center';
        
        foreach ($this->_filters as $filterid => $filter) {
            $filtername = html_writer::link(new moodle_url($filterbaseurl, $linkparams + array('fedit' => $filterid, 'fid' => $filterid)), $filter->name);
            $filterdescription = shorten_text($filter->description, 30);
            $filteredit = html_writer::link(new moodle_url($filterbaseurl, $linkparams + array('fedit' => $filterid, 'fid' => $filterid)),
                            $OUTPUT->pix_icon('t/edit', $stredit));
            $filterduplicate = html_writer::link(new moodle_url($filterbaseurl, $linkparams + array('duplicate' => $filterid)),
                            $OUTPUT->pix_icon('t/copy', $strduplicate));
            $filterdelete = html_writer::link(new moodle_url($filterbaseurl, $linkparams + array('delete' => $filterid)),
                            $OUTPUT->pix_icon('t/delete', $strdelete));
            $filterselector = html_writer::checkbox("filterselector", $filterid, false);

            // visible
            if ($filter->visible) {
                $visibleicon = $OUTPUT->pix_icon('t/hide', $strhide);
            } else {
                $visibleicon = $OUTPUT->pix_icon('t/show', $strshow);
            }
            $visible = html_writer::link(new moodle_url($filterbaseurl, $linkparams + array('visible' => $filterid)), $visibleicon);

            // default filter
            if ($filterid == $df->data->defaultfilter) {
                $defaultfilter = html_writer::link(new moodle_url($filterbaseurl, $linkparams + array('default' => -1)), $OUTPUT->pix_icon('t/clear', ''));
            } else {
                $defaultfilter = html_writer::link(new moodle_url($filterbaseurl, $linkparams + array('default' => $filterid)), $OUTPUT->pix_icon('t/switch_whole', $strchoose));
            }
            // parse custom settings
            $sortoptions = '';
            $sorturlquery = '';
            $searchoptions = '';
            $searchurlquery = '';
            
            if ($filter->customsort or $filter->customsearch) {
                // Get field objects
                $fields = $df->get_fields();
                
                // Parse filter sort settings
                $sortfields = array();
                if ($filter->customsort) {
                    $sortfields = unserialize($filter->customsort);
                }
                
                if ($sortfields) {
                    $sortarr = array();
                    $sorturlarr = array();
                    foreach ($sortfields as $fieldid => $sortdir) {
                        if (empty($fields[$fieldid])) {
                            continue;
                        }
                        
                        // Sort url query
                        $sorturlarr[] = "$fieldid $sortdir";

                        // Verbose sort criteria
                        // check if field participates in default sort
                        $strsortdir = $sortdir ? 'Descending' : 'Ascending';
                        $sortarr[] = $OUTPUT->pix_icon('t/'. ($sortdir ? 'down' : 'up'), $strsortdir). ' '. $fields[$fieldid]->field->name;
                    }
                    $sortoptions = implode('<br />', $sortarr);
                    $sorturlquery = implode(',', $sorturlarr);
                }            
                
                // Parse filter search settings
                $searchfields = array();
                if ($filter->customsearch) {
                    $searchfields = unserialize($filter->customsearch);
                }

                // Verbose search criteria
                if ($searchfields) {
                    $searcharr = array();
                    $searchurlarr = array();
                    foreach ($searchfields as $fieldid => $searchfield) {
                        if (empty($fields[$fieldid])) {
                            continue;
                        }
                        $fieldoptions = array();
                        if (!empty($searchfield['AND'])) {
                            //$andoptions = array_map("$fields[$fieldid]->format_search_value", $searchfield['AND']);
                            $options = array();
                            foreach ($searchfield['AND'] as $option) {
                                if ($option) {
                                    $options[] = $fields[$fieldid]->format_search_value($option);
                                }
                            }
                            $fieldoptions[] = '<b>'. $fields[$fieldid]->field->name. '</b>:'. implode(' <b>and</b> ', $options);
                            $searchurlarr[] = "$fieldid:AND:". implode('#', array_map(function($a) {return implode(',', $a);}, $searchfield['AND']));
                        }
                        if (!empty($searchfield['OR'])) {
                            //$oroptions = array_map("$fields[$fieldid]->format_search_value", $searchfield['OR']);
                            $options = array();
                            foreach ($searchfield['OR'] as $option) {
                                if ($option) {
                                    $options[] = $fields[$fieldid]->format_search_value($option);
                                }
                            }
                            $fieldoptions[] = '<b>'. $fields[$fieldid]->field->name. '</b> '. implode(' <b>or</b> ', $options);
                            $searchurlarr[] = "$fieldid:OR:". implode('#', array_map(function($a) {return implode(',', $a);}, $searchfield['OR']));
                        }
                        if ($fieldoptions) {
                            $searcharr[] = implode('<br />', $fieldoptions);
                        }
                    }
                    if ($searcharr) {
                        $searchoptions = implode('<br />', $searcharr);
                    }
                } else {
                    $searchoptions = $filter->search ? $filter->search : '';
                }
                
                if ($searchurlarr) {
                    $searchurlquery = implode('@', $searchurlarr);
                }
            }
            
            $sortoptions = !empty($sortoptions) ? $sortoptions : '---';
            $searchoptions = !empty($searchoptions) ? $searchoptions : '---';
            
            // Per page
            $perpage = empty($filter->perpage) ?  '---' : $filter->perpage;

            // Url query
            $urlquery = '';
            if ($sorturlquery)  {
                $urlquery .= '&usort='. urlencode($sorturlquery);
            }
            if ($searchurlquery)  {
                $urlquery .= '&usearch='. urlencode($searchurlquery);
            }

            $table->data[] = array(
                $filtername,
                $filterdescription,
                $perpage,
                $sortoptions,
                $searchoptions,
                $urlquery,
                $visible,
                $defaultfilter,
                $filteredit,
                $filterduplicate,
                $filterdelete,
                $filterselector
            );
        }
                 
        echo html_writer::table($table);
    }

    /**
     *
     */
    public function print_add_filter() {
        echo html_writer::empty_tag('br');
        echo html_writer::start_tag('div', array('class'=>'fieldadd mdl-align'));
        echo html_writer::link(new moodle_url('/mod/dataform/filter/index.php', array('d' => $this->_df->id(), 'sesskey' => sesskey(), 'new' => 1)), get_string('filteradd','dataform'));
        //echo $OUTPUT->help_icon('filteradd', 'dataform');
        echo html_writer::end_tag('div');
        echo html_writer::empty_tag('br');
    }

}
