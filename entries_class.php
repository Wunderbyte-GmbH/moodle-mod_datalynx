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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @package mod
 * @subpackage datalynx
 * @copyright 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 */
class datalynx_entries {

    const SELECT_FIRST_PAGE = 0;

    const SELECT_LAST_PAGE = -1;

    const SELECT_NEXT_PAGE = -2;

    const SELECT_RANDOM_PAGE = -3;

    /**
     *
     * @var datalynx
     */
    protected $datalynx = null;
 // datalynx object
    /**
     *
     * @var datalynx_filter|null
     */
    protected $filter = null;

    protected $_entries = null;

    protected $_entriestotalcount = 0;

    protected $_entriesfiltercount = 0;

    /**
     * Constructor
     * View or datalynx or both, each can be id or object
     */
    public function __construct(datalynx $datalynx, datalynx_filter $filter = null) {
        if (empty($datalynx)) {
            throw new coding_exception(
                    'Datalynx id or object must be passed to entries constructor.');
        }
        
        $this->datalynx = $datalynx;
        $this->filter = $filter;
    }
    /**
     * Populate the entries with content of the content table datalynx_contents. Gets the raw content
     * for each field for the entry and sets the content in $this->_entries
     * Performs entries count in order to display number of entries. Updates user profiels if
     * plugin local_userinfosync is installed
     * @param array $options
     */
    public function set_content(array $options = array()) {
        global $CFG;
        
        if (isset($options['entriesset'])) {
            $entriesset = $options['entriesset'];
        } else if (!empty($options['user'])) {
            $entriesset = $this->get_entries(array('search' => array('userid' => $options['user'])));
        } else {
            if (!optional_param('new', 0, PARAM_INT)) {
                $entriesset = $this->get_entries($options);
            }
        }
        
        $this->_entries = !empty($entriesset->entries) ? $entriesset->entries : array();
        $this->_entriestotalcount = !empty($entriesset->max) ? $entriesset->max : count(
                $this->_entries);
        $this->_entriesfiltercount = !empty($entriesset->found) ? $entriesset->found : count(
                $this->_entries);
        
        $pluginmananger = core_plugin_manager::instance();
        $plugininfo = $pluginmananger->get_plugin_info('local_userinfosync');
        if ($plugininfo && $plugininfo->rootdir) {
            require_once ($plugininfo->rootdir . '/lib.php');
            
            $userids = array();
            foreach ($this->_entries as $entry) {
                $userids[] = $entry->userid;
            }
            array_unique($userids);
            
            userinfosync::update_user_fields($userids);
        }
    }

    /**
     * retrieve all entries depending on the options passed and
     * the permissions of the user viewing the view and other conditions
     * @param array $options array of strings
     * @return object retrieved entries
     */
    public function get_entries($options = null) {
        global $DB, $USER;
        
        $datalynx = &$this->datalynx;
        $fields = $datalynx->get_fields();
        
        // Get the filter
        if (empty($options['filter'])) {
            $filter = $this->filter;
        } else {
            $filter = $options['filter'];
        }
        
        // Filter sql
        list($filtertables, $wheresearch, $sortorder, $whatcontent, $filterparams, $datalynxcontent) =
                $filter->get_sql($fields);
        
        // named params array for the sql
        $params = array();
        
        // USER filtering
        $whereuser = '';
        if (!$datalynx->user_can_view_all_entries()) {
            // include only the user's entries
            $whereuser = " AND e.userid = :{$this->sqlparams($params, 'userid', $USER->id)} ";
        } else {
            // specific users requested
            if (!empty($filter->users)) {
                list($inusers, $userparams) = $DB->get_in_or_equal($filter->users, SQL_PARAMS_NAMED, 
                        'users');
                $whereuser .= " AND e.userid $inusers ";
                $params = array_merge($params, array('users' => $userparams));
            }
            
            // exclude guest/anonymous
            if (!has_capability('mod/datalynx:viewanonymousentry', $datalynx->context)) {
                $whereuser .= " AND e.userid <> :{$this->sqlparams($params, 'guestid', 1)} ";
            }
        }
        
        // GROUP filtering
        $wheregroup = '';
        if ($datalynx->currentgroup) {
            $wheregroup = " AND e.groupid = :{$this->sqlparams($params, 'groupid', $datalynx->currentgroup)} ";
        } else {
            // specific groups requested
            if (!empty($filter->groups)) {
                list($ingroups, $groupparams) = $DB->get_in_or_equal($filter->groups, 
                        SQL_PARAMS_NAMED, 'groups');
                $whereuser .= " AND e.userid $ingroups ";
                $params = array_merge($params, array('groups' => $groupparams));
            }
        }
        
        // APPROVE filtering
        $whereapprove = '';
        if ($datalynx->data->approval and
                 !has_capability('mod/datalynx:manageentries', $datalynx->context)) {
            if (isloggedin()) {
                $whereapprove = " AND (e.approved = :{$this->sqlparams($params, 'approved', 1)} 
                                        OR e.userid = :{$this->sqlparams($params, 'userid', $USER->id)}) ";
            } else {
                $whereapprove = " AND e.approved = :{$this->sqlparams($params, 'approved', 1)} ";
            }
        }
        
        // STATUS filtering (visibility)
        $wherestatus = '';
        if (!has_capability('mod/datalynx:viewdrafts', $datalynx->context)) {
            $wherestatus = " AND (e.status <> :{$this->sqlparams($params, 'status', datalynxfield__status::STATUS_DRAFT)}
                              OR  e.userid = :{$this->sqlparams($params, 'userid', $USER->id)}) ";
        }
        
        // sql for fetching the entries
        $what = ' DISTINCT ' .
                // entry
                ' e.id, e.approved, e.timecreated, e.timemodified, e.userid, e.groupid, e.status, ' .
                // user
                user_picture::fields('u', array('idnumber', 'username'
                ), 'uid ') . ', ' .
                // group (TODO g.description AS groupdesc need to be varchar for MSSQL)
                'g.name AS groupname, g.hidepicture AS grouphidepic, g.picture AS grouppic ' .
                // content (including ratings and comments if required)
                $whatcontent;
        $count = ' COUNT(e.id) ';
        $tables = ' {datalynx_entries} e
                    JOIN {user} u ON u.id = e.userid 
                    LEFT JOIN {groups} g ON g.id = e.groupid ';
        $wheredfid = " e.dataid = :{$this->sqlparams($params, 'dataid', $datalynx->id())} ";
        $whereoptions = '';
        if (!empty($options['search'])) {
            foreach ($options['search'] as $key => $val) {
                $whereoptions .= " e.$key = :{$this->sqlparams($params, $key, $val)} ";
            }
        }
        
        $fromsql = " $tables $filtertables ";
        $wheresql = " $wheredfid $whereoptions $whereuser $wheregroup $whereapprove $wherestatus $wheresearch";
        $sqlselect = "SELECT $what FROM $fromsql WHERE $wheresql $sortorder";
        
        // total number of entries the user is authorized to view (without additional filtering)
        $sqlmax = "SELECT $count FROM $tables WHERE $wheredfid $whereoptions $whereuser $wheregroup $whereapprove $wherestatus";
        // number of entries in this particular view call (with filtering)
        $sqlcount = "SELECT $count FROM $fromsql WHERE $wheresql";
        // base params + search params
        $baseparams = array();
        foreach ($params as $paramset) {
            $baseparams = array_merge($paramset, $baseparams);
        }
        $allparams = array_merge($baseparams, $filterparams);
        
        // count prospective entries
        if (empty($wheresearch)) {
            $maxcount = $searchcount = $DB->count_records_sql($sqlmax, $baseparams);
        } else {
            if ($maxcount = $DB->count_records_sql($sqlmax, $baseparams)) {
                $searchcount = $DB->count_records_sql($sqlcount, $allparams);
            } else {
                $searchcount = 0;
            }
        }
        
        // initialize returned object
        $entries = new stdClass();
        $entries->max = $maxcount;
        $entries->found = $searchcount;
        $entries->entries = null;
        
        if ($searchcount) {
            // if specific entries requested (eids)
            if (!empty($filter->eids)) {
                list($ineids, $eidparams) = $DB->get_in_or_equal($filter->eids, SQL_PARAMS_NAMED, 
                        'eid');
                $andwhereeid = " AND e.id $ineids ";
                
                $sqlselect = "SELECT $what $whatcontent                                  
                              FROM $fromsql 
                              WHERE $wheresql $andwhereeid $sortorder";
                
                if ($entries->entries = $DB->get_records_sql($sqlselect, $allparams + $eidparams)) {
                    // if one entry was requested get its position
                    if (!is_array($filter->eids) or count($filter->eids) == 1) {
                        $sqlselect = "$sqlcount AND e.id $ineids";
                        $eidposition = $DB->get_records_sql($sqlselect, $allparams + $eidparams);
                        
                        $filter->page = key($eidposition) - 1;
                    }
                }
                
                // get perpage subset
            } else if (!$filter->groupby and $perpage = $filter->perpage) {
                
                // a random set (filter->selection == 1)
                if (!empty($filter->selection)) {
                    // get ids of found entries
                    $sqlselect = "SELECT DISTINCT e.id FROM $fromsql WHERE $wheresql";
                    $entryids = $DB->get_records_sql($sqlselect, $allparams);
                    // get a random subset of ids
                    $randids = array_rand($entryids, min($perpage, count($entryids)));
                    // get the entries
                    list($insql, $paramids) = $DB->get_in_or_equal($randids, SQL_PARAMS_NAMED, 
                            'rand');
                    $andwhereids = " AND e.id $insql ";
                    $sqlselect = "SELECT $what FROM $fromsql WHERE $wheresql $andwhereids";
                    $entries->entries = $DB->get_records_sql($sqlselect, $allparams + $paramids);
                    
                    // by page
                } else {
                    $page = isset($filter->page) ? $filter->page : 0;
                    $numpages = $searchcount > $perpage ? ceil($searchcount / $perpage) : 1;
                    
                    if (isset($filter->onpage)) {
                        // first page
                        if ($filter->onpage == self::SELECT_FIRST_PAGE) {
                            $page = 0;
                            
                            // last page
                        } else if ($filter->onpage == self::SELECT_LAST_PAGE) {
                            $page = $numpages - 1;
                            
                            // next page
                        } else if ($filter->onpage == self::SELECT_NEXT_PAGE) {
                            $page = $filter->page = ($page % $numpages);
                            
                            // random page
                        } else if ($filter->onpage == self::SELECT_RANDOM_PAGE) {
                            $page = $numpages > 1 ? rand(0, ($numpages - 1)) : 0;
                        }
                    }
                    $entries->entries = $DB->get_records_sql($sqlselect, $allparams, $page * $perpage, $perpage);
                }
                // get everything
            } else {
                $entries->entries = $DB->get_records_sql($sqlselect, $allparams);
            }
            // Now get the contents if required and add it to the entry objects
            if ($datalynxcontent && $entries->entries) {
                // get the node content of the requested entries
                list($fids, $fparams) = $DB->get_in_or_equal($datalynxcontent, SQL_PARAMS_NAMED);
                list($eids, $eparams) = $DB->get_in_or_equal(array_keys($entries->entries), 
                        SQL_PARAMS_NAMED);
                $params = array_merge($eparams, $fparams);
                $contents = $DB->get_records_select('datalynx_contents', 
                        "entryid {$eids} AND fieldid {$fids}", $params);
                
                foreach ($contents as $contentid => $content) {
                    $entry = $entries->entries[$content->entryid];
                    $fieldid = $content->fieldid;
                    $varcontentid = "c{$fieldid}_id";
                    $entry->$varcontentid = $contentid;
                    foreach ($fields[$fieldid]->get_content_parts() as $part) {
                        $varpart = "c{$fieldid}_$part";
                        $entry->$varpart = $content->$part;
                    }
                    $entries->entries[$content->entryid] = $entry;
                }
            }
        }
        
        return $entries;
    }

    /**
     * get all entries created by the user with $userid
     * @param integer $userid the user id of the user who created the entry (or was assigned as author of the entry)
     * @return object
     */
    public function get_user_entries($userid = null) {
        global $USER;
        
        if (empty($userid)) {
            $userid = $USER->id;
        }
        return $this->get_entries(array('search' => array('userid' => $userid)));
    }

    /**
     * count number of entries 
     * @param boolean $filtered true for filtered only, false for total number of entries
     * @return number
     */
    public function get_count($filtered = false) {
        if ($filtered) {
            return $this->_entriesfiltercount;
        } else {
            return (!empty($this->_entries) ? count($this->_entries) : 0);
        }
    }

    /**
     * return entries
     * @return multitype:
     */
    public function entries() {
        return $this->_entries;
    }

    /**
     * Retrieves stored files which are embedded in the current content
     * set_content must have been called
     *
     * @return array of stored files
     */
    public function get_embedded_files(array $fids) {
        $files = array();
        
        if (!empty($fids) and !empty($this->_entries)) {
            $fs = get_file_storage();
            foreach ($this->_entries as $entry) {
                foreach ($fids as $fieldid) {
                    // get the content id of the requested field
                    $contentid = isset($entry->{"c{$fieldid}_id"}) ? $entry->{"c{$fieldid}_id"} : null;
                    // the field may not hold any content
                    if ($contentid) {
                        // retrieve the files (no dirs) from file area
                        // TODO for Picture fields this does not distinguish between the images and
                        // their thumbs
                        // but the view may not necessarily display both
                        $files = array_merge($files, 
                                $fs->get_area_files($this->datalynx->context->id, 'mod_datalynx', 
                                        'content', $contentid, 
                                        'sortorder, itemid, filepath, filename', false));
                    }
                }
            }
        }
        
        return $files;
    }

    /**
     *
     * @return array notification string, list of processed ids
     */
    public function process_entries($action, $eids, $data = null, $confirmed = false) {
        global $CFG, $DB, $USER, $OUTPUT, $PAGE;
        $df = $this->datalynx;
        
        $entries = array();
        // some entries may be specified for action
        if ($eids) {
            $importentryids = array();
            // adding or updating entries
            if ($action == 'update') {
                if (!is_array($eids)) {
                    // adding new entries
                    if ($eids < 0) {
                        $eids = array_reverse(range($eids, -1));
                        // editing existing entries
                    } else {
                        $eids = explode(',', $eids);
                    }
                }
                
                // TODO Prepare counters for adding new entries
                $addcount = 0;
                $addmax = $df->data->maxentries;
                $perinterval = ($df->data->intervalcount > 1);
                if ($addmax != -1 and has_capability('mod/datalynx:manageentries', $df->context)) {
                    $addmax = -1;
                } else if ($addmax != -1) {
                    $addmax = max(0, $addmax - $df->user_num_entries($perinterval));
                }
                
                // Prepare the entries to process
                foreach ($eids as $eid) {
                    $entry = new stdClass();
                    
                    // existing entry from view
                    if ($eid > 0 and isset($this->_entries[$eid])) {
                        $entries[$eid] = $this->_entries[$eid];
                        
                        // TODO existing entry *not* from view (import)
                    } else if ($eid > 0) {
                        $importentryids[] = $eid;
                        
                        // new entries ($eid is the number of new entries
                    } else if ($eid < 0) {
                        $addcount++;
                        if ($addmax == -1 || $addmax >= $addcount) {
                            $entry->id = 0;
                            $entry->groupid = $df->currentgroup;
                            $entry->userid = $USER->id;
                            $entries[$eid] = $entry;
                        }
                    }
                }
                
                // all other types of processing must refer to specific entry ids
            } else {
                $entries = $DB->get_records_select('datalynx_entries', 
                        "dataid = ? AND id IN ($eids)", array($df->id()
                        ));
            }
            
            if (!empty($importentryids)) {
                $filterdata = array('dataid' => $df->id(), 'eids' => $importentryids);
                $filter = new datalynx_filter((object) $filterdata);
                $entries += $this->get_entries(array('filter' => $filter))->entries;
            }
            
            if ($entries) {
                foreach ($entries as $eid => $entry) {
                    // filter approvable entries
                    if (($action == 'approve' or $action == 'disapprove') and
                             !has_capability('mod/datalynx:approve', $df->context)) {
                        unset($entries[$eid]);
                        
                        // filter managable entries
                    } else if (!$df->user_can_manage_entry($entry)) {
                        unset($entries[$eid]);
                    }
                }
            }
        }
        
        if (empty($entries)) {
            return array(get_string("entrynoneforaction", 'datalynx'), '');
        } else {
            if (!$confirmed) {
                
                // Print a confirmation page
                echo $OUTPUT->header();
                echo $OUTPUT->confirm(
                        get_string("entriesconfirm$action", 'datalynx', count($entries)), 
                        new moodle_url($PAGE->url, 
                                array($action => implode(',', array_keys($entries)), 
                                    'sesskey' => sesskey(), 'confirmed' => true, 
                                    'sourceview' => optional_param('sourceview', null, PARAM_INT)
                                )), new moodle_url($PAGE->url));
                
                echo $OUTPUT->footer();
                exit(0);
            } else {
                $processed = array();
                $completiontype = COMPLETION_UNKNOWN;
                $strnotify = '';
                
                switch ($action) {
                    case 'update':
                        $completiontype = COMPLETION_UNKNOWN;
                        $strnotify = 'entriesupdated';
                        
                        if (!is_null($data)) {
                            $fields = $df->get_fields();
                            
                            // first parse the data to collate content in an array for each
                            // recognized field
                            $contents = array_fill_keys(array_keys($entries), 
                                    array('info' => array(), 'fields' => array()
                                    ));
                            $calculations = array();
                            $entryinfo = array(datalynxfield__entry::_ENTRY, 
                                datalynxfield__time::_TIMECREATED, 
                                datalynxfield__time::_TIMEMODIFIED, 
                                datalynxfield__approve::_APPROVED, 
                                datalynxfield_entryauthor::_USERID, 
                                datalynxfield_entryauthor::_USERNAME, 
                                datalynxfield_entrygroup::_GROUP, datalynxfield__status::_STATUS
                            );
                            
                            $skipnotification = array();
                            $drafttofinal = array();
                            
                            // Iterate the data and extract entry and fields content
                            foreach ($data as $name => $value) {
                                // assuming only field names contain field_
                                if (strpos($name, 'field_') !== false) {
                                    list(, $fieldid, $entryid) = explode('_', $name);
                                    if (array_key_exists($fieldid, $fields)) {
                                        $field = $fields[$fieldid];
                                    } else {
                                        continue;
                                    }
                                    // Entry info
                                    if (in_array($fieldid, $entryinfo)) {
                                        // TODO
                                        if ($fieldid == datalynxfield_entryauthor::_USERID or
                                                 $fieldid == datalynxfield_entryauthor::_USERNAME) {
                                            $entryvar = 'userid';
                                        } else {
                                            $entryvar = $field->get_internalname();
                                        }
                                        if ($fieldid == datalynxfield__status::_STATUS &&
                                                 $value == datalynxfield__status::STATUS_DRAFT) {
                                            $skipnotification[] = $entryid;
                                        }
                                        if ($fieldid == datalynxfield__status::_STATUS &&
                                                 $value ==
                                                 datalynxfield__status::STATUS_FINAL_SUBMISSION &&
                                                 isset($entry->status) &&
                                                 $entry->status ==
                                                 datalynxfield__status::STATUS_DRAFT) {
                                            $drafttofinal[] = $entryid;
                                        }
                                        
                                        $contents[$entryid]['info'][$entryvar] = $value;
                                        
                                        // Entry content
                                    } else if (!array_key_exists($fieldid, 
                                            $contents[$entryid]['fields'])) {
                                        $contents[$entryid]['fields'][$fieldid] = $field->get_content_from_data(
                                                $entryid, $data);
                                    }
                                }
                            }
                            
                            $firstentryid = min(array_keys($contents));
                            $bulkeditfields = array();
                            foreach ($contents[$firstentryid]['fields'] as $fieldid => $value) {
                                if (optional_param("field_{$fieldid}_bulkedit", 0, PARAM_BOOL)) {
                                    $bulkeditfields[] = $fieldid;
                                }
                            }
                            $newcontents = array();
                            foreach ($contents as $entryid => $oldcontent) {
                                $newcontents[$entryid] = array();
                                if ($entryid != $firstentryid) {
                                    $newcontents[$entryid]['info'] = $oldcontent['info'];
                                    $newfields = array();
                                    foreach ($contents[$firstentryid]['fields'] as $fieldid => $value) {
                                        if (array_search($fieldid, $bulkeditfields) !== false) {
                                            $newfields[$fieldid] = $contents[$firstentryid]['fields'][$fieldid];
                                        } else {
                                            $newfields[$fieldid] = $oldcontent['fields'][$fieldid];
                                        }
                                    }
                                    $newcontents[$entryid]['fields'] = $newfields;
                                } else {
                                    $newcontents[$entryid] = $oldcontent;
                                }
                            }
                            $contents = $newcontents;
                            
                            global $DB;
                            // now update entry and contents TODO: TEAM_CHANGED - check this!
                            $addorupdate = '';
                            
                            foreach ($entries as $eid => $entry) {
                                if ($entry->id = $this->update_entry($entry, 
                                        $contents[$eid]['info'])) {
                                    // $eid should be different from $entryid only in new entries
                                    foreach ($contents[$eid]['fields'] as $fieldid => $content) {
                                        $field = $DB->get_record('datalynx_fields', 
                                                array('id' => $fieldid));
                                        if ($field->type == 'teammemberselect') {
                                            $oldcontent = json_decode(
                                                    $DB->get_field('datalynx_contents', 'content', 
                                                            array('fieldid' => $fieldid, 
                                                                'entryid' => $eid
                                                            )), true);
                                            $newcontent = $content[''];
                                            $this->notify_team_members($entry, $field, $oldcontent, 
                                                    $newcontent);
                                        }
                                        $fields[$fieldid]->update_content($entry, $content);
                                    }
                                    $processed[$entry->id] = $entry;
                                    
                                    if (!$addorupdate) {
                                        $addorupdate = $eid < 0 ? 'added' : 'updated';
                                    }
                                }
                            }
                            if ($processed) {
                                $eventdata = (object) array('items' => $processed);
                                $df->events_trigger("entry$addorupdate", $eventdata);
                            }
                        }
                        break;
                    
                    case 'duplicate':
                        $completiontype = COMPLETION_COMPLETE;
                        foreach ($entries as $entry) {
                            // can user add anymore entries?
                            if (!$df->user_can_manage_entry()) {
                                // TODO: notify something
                                break;
                            }
                            
                            // Get content of entry to duplicate
                            $contents = $DB->get_records('datalynx_contents', array('entryid' => $entry->id));
                            
                            // Add a duplicated entry and content
                            $newentry = $entry;
                            $newentry->userid = $USER->id;
                            $newentry->dataid = $df->id();
                            $newentry->groupid = $df->currentgroup;
                            $newentry->timecreated = $newentry->timemodified = time();
                            
                            if ($df->data->approval and
                                     !has_capability('mod/datalynx:approve', $df->context)) {
                                $newentry->approved = 0;
                            }
                            $newentry->id = $DB->insert_record('datalynx_entries', $newentry);
                            
                            foreach ($contents as $content) {
                                $newcontent = $content;
                                $newcontent->entryid = $newentry->id;
                                if (!$DB->insert_record('datalynx_contents', $newcontent)) {
                                    throw new moodle_exception('cannotinsertrecord', null, null, 
                                            $newentry->id);
                                }
                            }
                            $processed[$newentry->id] = $newentry;
                        }
                        
                        if ($processed) {
                            $eventdata = (object) array('items' => $processed);
                            $df->events_trigger("entryadded", $eventdata);
                        }
                        
                        $strnotify = 'entriesduplicated';
                        break;
                    
                    case 'approve':
                        $completiontype = COMPLETION_COMPLETE;
                        // approvable entries should be filtered above
                        $entryids = array_keys($entries);
                        $ids = implode(',', $entryids);
                        $DB->set_field_select('datalynx_entries', 'approved', 1,
                            " dataid = ? AND id IN ($ids) ", array($df->id()));
                        $processed = $entries;
                        
                        $processed += $this->create_approved_entries_for_team($entryids);
                        
                        if ($processed) {
                            $eventdata = (object) array('items' => $processed);
                            $df->events_trigger("entryapproved", $eventdata);
                        }
                        
                        $strnotify = 'entriesapproved';
                        break;
                    
                    case 'disapprove':
                        $completiontype = COMPLETION_COMPLETE;
                        // disapprovable entries should be filtered above
                        $entryids = array_keys($entries);
                        $ids = implode(',', $entryids);
                        $DB->set_field_select('datalynx_entries', 'approved', 0, 
                                " dataid = ? AND id IN ($ids) ", array($df->id()));
                        $processed = $entries;
                        if ($processed) {
                            $eventdata = (object) array('items' => $processed);
                            $df->events_trigger("entrydisapproved", $eventdata);
                        }
                        
                        $strnotify = 'entriesdisapproved';
                        break;
                    
                    case 'delete':
                        $completiontype = COMPLETION_INCOMPLETE;
                        // deletable entries should be filtered above
                        foreach ($entries as $entry) {
                            $fields = $df->get_fields();
                            foreach ($fields as $field) {
                                $field->delete_content($entry->id);
                            }
                            
                            $DB->delete_records('datalynx_entries', array('id' => $entry->id));
                            $processed[$entry->id] = $entry;
                        }
                        if ($processed) {
                            $eventdata = (object) array('items' => $processed);
                            $df->events_trigger("entrydeleted", $eventdata);
                        }
                        
                        $strnotify = 'entriesdeleted';
                        break;
                    
                    default:
                        break;
                }
                
                if ($processed) {
                    // Update completion state
                    $completion = new completion_info($df->course);
                    if ($completion->is_enabled($df->cm) &&
                             $df->cm->completion == COMPLETION_TRACKING_AUTOMATIC &&
                             $df->data->completionentries) {
                        foreach ($processed as $entry) {
                            $completion->update_state($df->cm, $completiontype, $entry->userid);
                        }
                    }
                    $strnotify = get_string($strnotify, 'datalynx', count($processed));
                } else {
                    $strnotify = get_string($strnotify, 'datalynx', get_string('no'));
                }
                
                return array($strnotify, array_keys($processed));
            }
        }
    }

    /**
     * Trigger events to notify the team members when new members were
     * added to the field "teammemeberselect" in a specific entry
     * @param object $entry
     * @param object $field
     * @param array $oldmembers
     * @param array $newmembers
     */
    public function notify_team_members($entry, $field, $oldmembers, $newmembers) {
        global $DB;
        
        $oldmembers = !empty($oldmembers) ? array_filter($oldmembers) : array();
        $newmembers = array_filter($newmembers);
        
        $addedmemberids = array_diff($newmembers, $oldmembers);
        $removedmemberids = array_diff($oldmembers, $newmembers);
        
        if (!empty($addedmemberids)) {
            list($insql, $params) = $DB->get_in_or_equal($addedmemberids);
            $addedmembers = $DB->get_records_sql("SELECT * FROM {user} WHERE id $insql", $params);
        } else {
            $addedmembers = array();
        }
        
        if (!empty($removedmemberids)) {
            list($insql, $params) = $DB->get_in_or_equal($removedmemberids);
            $removedmembers = $DB->get_records_sql("SELECT * FROM {user} WHERE id $insql", $params);
        } else {
            $removedmembers = array();
        }
        
        $other = ['dataid' => $this->datalynx->id(), 'fieldid' => $field->id, 
            'name' => $field->name, 'addedmembers' => json_encode($addedmembers), 
            'removedmembers' => json_encode($removedmembers)
        ];
        
        if (!empty($addedmembers)) {
            $event = \mod_datalynx\event\team_updated::create(
                    array('context' => $this->datalynx->context, 'objectid' => $entry->id, 
                        'other' => $other));
            $event->trigger();
        }
        
        if (!empty($removedmembers)) {
            $event = \mod_datalynx\event\team_updated::create(
                    array('context' => $this->datalynx->context, 'objectid' => $entry->id, 
                        'other' => $other));
            $event->trigger();
        }
    }

    /**
     * Creates copies of approved entries for each selected team member that doesn't have one.
     * If an entry exists the contents will be copied into it in order to maintain consistency.
     * 
     * @param array $entryids An array containing IDs of entries to create approved entries from
     * @return array An array of processed and/or added entries (id => entry)
     */
    public function create_approved_entries_for_team(array $entryids) {
        global $DB;
        
        $df = $this->datalynx;
        
        $fields = $df->get_fields();
        $teamfield = false;
        foreach ($fields as $field) {
            if ($field->type == 'teammemberselect' && $field->referencefieldid) {
                $teamfield = $field;
                break;
            }
        }
        
        $processed = array();
        
        if ($teamfield) {
            foreach ($entryids as $entryid) {
                $oldcontents = $contents = $DB->get_records('datalynx_contents', 
                        array('entryid' => $entryid));
                
                $teammemberids = json_decode(
                        $DB->get_field('datalynx_contents', 'content', 
                                array('entryid' => $entryid, 'fieldid' => $teamfield->id())), true);
                
                if ($teamfield->referencefieldid != -1) {
                    $sqllike = $DB->sql_like('dc.content', ':content', false);
                    $likecontent = '';
                    foreach ($contents as $content) {
                        if ($content->fieldid == $teamfield->referencefieldid) {
                            $likecontent = $content->content;
                            break;
                        }
                    }
                } else {
                    $sqllike = '1';
                    $likecontent = '';
                }
                
                $entry = $DB->get_record('datalynx_entries', array('id' => $entryid));
                $userid = $entry->userid;
                
                foreach ($teammemberids as $teammemberid) {
                    if (!$teammemberid) {
                        continue;
                    }
                    
                    $newteammemberids = array_diff($teammemberids, array($teammemberid));
                    $newteammemberids[] = $userid;
                    $newteammemberids = array_values($newteammemberids);
                    
                    if ($teamfield->referencefieldid != -1) {
                        $query = "SELECT DISTINCT de.id
                                FROM {datalynx_entries} de
                          INNER JOIN {datalynx_contents} dc ON de.id = dc.entryid
                               WHERE de.dataid = :dataid
                                 AND de.userid = :userid
                                 AND dc.fieldid = :fieldid
                                 AND $sqllike";
                        $existingentryid = $DB->get_field_sql($query, 
                                array('dataid' => $df->id(), 'userid' => $teammemberid, 
                                    'fieldid' => $teamfield->referencefieldid, 
                                    'content' => $likecontent
                                ));
                    } else {
                        $existingentryid = false;
                    }
                    
                    if ($existingentryid) {
                        $existingentry = $DB->get_record('datalynx_entries', 
                                array('id' => $existingentryid));
                        $existingentry->approved = 1;
                        foreach ($contents as $content) {
                            $newcontent = clone $content;
                            if ($content->fieldid == $teamfield->id()) {
                                $newcontent->content = json_encode($newteammemberids);
                            }
                            $DB->set_field('datalynx_contents', 'content', $newcontent->content, 
                                    array('entryid' => $existingentry->id, 
                                        'fieldid' => $newcontent->fieldid
                                    ));
                        }
                        $DB->update_record('datalynx_entries', $existingentry);
                        $processed[$existingentry->id] = $existingentry;
                    } else {
                        $newentry = clone $entry;
                        $newentry->userid = $teammemberid;
                        $newentry->dataid = $df->id();
                        $newentry->groupid = $df->currentgroup;
                        $newentry->timecreated = $newentry->timemodified = time();
                        $newentry->approved = 1;
                        $newentry->id = $DB->insert_record('datalynx_entries', $newentry);
                        
                        foreach ($contents as $content) {
                            $newcontent = clone $content;
                            if ($content->fieldid == $teamfield->id()) {
                                $newcontent->content = json_encode($newteammemberids);
                            }
                            
                            $newcontent->entryid = $newentry->id;
                            $DB->insert_record('datalynx_contents', $newcontent);
                        }
                        
                        $processed[$newentry->id] = $newentry;
                    }
                }
                
                $DB->update_record('datalynx_entries', $entry);
                
                foreach ($oldcontents as $content) {
                    $content->entryid = $entry->id;
                    $DB->update_record('datalynx_contents', $content);
                }
            }
        }
        
        return $processed;
    }

    /**
     * Update an entry
     * @param object $entry
     * @param array $data
     * @param boolean $updatetime
     * @return boolean|Ambigous <boolean, number>
     */
    public function update_entry($entry, $data = null, $updatetime = true) {
        global $CFG, $DB, $USER;
        
        $df = $this->datalynx;
        
        if ($data and has_capability('mod/datalynx:manageentries', $df->context)) {
            foreach ($data as $key => $value) {
                if ($key == 'name') {
                    $entry->userid = $value;
                } else {
                    $entry->{$key} = $value;
                }
                if ($key == 'timemodified') {
                    $updatetime = false;
                }
            }
        }
        
        // update existing entry (only authenticated users)
        if ($entry->id > 0) {
            if ($df->user_can_manage_entry($entry)) { // just in case the user opens two forms at
                                                      // the same time
                if (!has_capability('mod/datalynx:approve', $df->context)) {
                    $entry->approved = 0;
                }
                
                $oldapproved = $DB->get_field('datalynx_entries', 'approved', 
                        array('id' => $entry->id));
                $newapproved = isset($entry->approved) ? $entry->approved : 0;
                
                if ($updatetime) {
                    $entry->timemodified = time();
                }
                
                $entry->status = isset($data['status']) ? $data['status'] : $entry->status;
                
                if ($DB->update_record('datalynx_entries', $entry)) {
                    if (!$oldapproved && $newapproved) {
                        $this->create_approved_entries_for_team(array($entry->id));
                    }
                    return $entry->id;
                } else {
                    return false;
                }
            }
            
            // add new entry (authenticated or anonymous (if enabled))
        } else if ($df->user_can_manage_entry(null)) {
            // identify non-logged-in users (in anonymous entries) as guests
            $userid = empty($USER->id) ? $CFG->siteguest : $USER->id;
            $entry->dataid = $df->id();
            $entry->userid = !empty($entry->userid) ? $entry->userid : $userid;
            if (!isset($entry->groupid))
                $entry->groupid = $df->currentgroup;
            if (!isset($entry->timecreated))
                $entry->timecreated = time();
            if (!isset($entry->timemodified))
                $entry->timemodified = time();
            $entry->status = isset($data['status']) ? $data['status'] : 0;
            $entryid = $DB->insert_record('datalynx_entries', $entry);
            if (isset($entry->approved) && $entry->approved) {
                $this->create_approved_entries_for_team(array($entryid));
            }
            return $entryid;
        }
        
        return false;
    }

    /**
     * get sql params
     * @param unknown $params
     * @param array $param
     * @param string $value
     * @return string
     */
    private function sqlparams(&$params, $param, $value) {
        if (!array_key_exists($param, $params)) {
            $params[$param] = array();
        }
        
        $p = count($params[$param]);
        $params[$param][$param . $p] = $value;
        return $param . $p;
    }
}
