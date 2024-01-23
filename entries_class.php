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
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_user\fields;

defined('MOODLE_INTERNAL') || die();

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
    // Datalynx object.
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
     *
     * @param datalynx $datalynx
     * @param datalynx_filter|null $filter
     * @throws coding_exception
     */
    public function __construct(mod_datalynx\datalynx $datalynx, datalynx_filter $filter = null) {
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
     * Performs entries count in order to display number of entries.
     *
     * @param array $options
     * @throws coding_exception
     * @throws dml_exception
     */
    public function set_content(array $options = array()) {
        if (isset($options['entriesset'])) {
            $entriesset = $options['entriesset'];
        } else {
            if (!empty($options['user'])) {
                $entriesset = $this->get_entries(array('search' => array('userid' => $options['user'])));
            } else {
                if (!optional_param('new', 0, PARAM_INT)) {
                    $entriesset = $this->get_entries($options);
                }
            }
        }

        $this->_entries = !empty($entriesset->entries) ? $entriesset->entries : array();
        $this->_entriestotalcount = !empty($entriesset->max) ? $entriesset->max : count(
                $this->_entries);
        $this->_entriesfiltercount = !empty($entriesset->found) ? $entriesset->found : count(
                $this->_entries);
    }

    /**
     * retrieve all entries depending on the options passed and
     * the permissions of the user viewing the view and other conditions
     *
     * @param array $options array of strings
     * @return object retrieved entries
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_entries($options = null) {
        global $DB, $USER;

        $datalynx = &$this->datalynx;
        $fields = $datalynx->get_fields();

        // Get the filter.
        if (empty($options['filter'])) {
            $filter = $this->filter;
        } else {
            if ($filter = $options['filter']) {
                // When view is called by a datalynxview_field.
                if (is_array($filter->users)) {
                    $optionsfilterusers = $filter->users[0];
                }
                if (is_array($filter->groups)) {
                    $optionsfiltergroups = $filter->groups[0];
                }
                $optionseids = $filter->eids;
            }
        }

        // Filter sql.
        list($filtertables, $wheresearch, $sortorder, $whatcontent, $filterparams, $datalynxcontent) = $filter->get_sql($fields);

        // Named params array for the sql.
        $params = array();

        // USER filtering.
        $whereuser = '';
        if (isset($optionsfilterusers)) { // Datalynxview_field goes first.
            $whereuser = " AND e.userid = :{$this->sqlparams($params, 'userid', $optionsfilterusers)} ";
        } else {
            if (!$datalynx->user_can_view_all_entries()) {
                // Include only the user's entries.
                $whereuser = " AND e.userid = :{$this->sqlparams($params, 'userid', $USER->id)} ";
            } else {
                // Specific users requested.
                if (!empty($filter->users)) {
                    list($inusers, $userparams) = $DB->get_in_or_equal($filter->users, SQL_PARAMS_NAMED,
                            'users');
                    $whereuser .= " AND e.userid $inusers ";
                    $params = array_merge($params, array('users' => $userparams));
                }

                // Exclude guest/anonymous.
                if (!has_capability('mod/datalynx:viewanonymousentry', $datalynx->context)) {
                    $whereuser .= " AND e.userid <> :{$this->sqlparams($params, 'guestid', 1)} ";
                }
            }
        }

        // GROUP filtering.
        $wheregroup = '';

        if (isset($optionsfiltergroups)) { // Datalynxview_field goes first.
            $wheregroup = " AND e.groupid = :{$this->sqlparams($params, 'groupid', $optionsfiltergroups)} ";
        } else {
            if ($datalynx->currentgroup) {
                $wheregroup = " AND e.groupid = :{$this->sqlparams($params, 'groupid', $datalynx->currentgroup)} ";
            } else {
                // Specific groups requested.
                if (!empty($filter->groups)) {
                    list($ingroups, $groupparams) = $DB->get_in_or_equal($filter->groups,
                            SQL_PARAMS_NAMED, 'groups');
                    $whereuser .= " AND e.userid $ingroups ";
                    $params = array_merge($params, array('groups' => $groupparams));
                }
            }
        }

        // APPROVE filtering.
        $whereapprove = '';
        if ($datalynx->data->approval &&
                !has_capability('mod/datalynx:manageentries', $datalynx->context)
        ) {
            if (isloggedin()) {
                $whereapprove = " AND (e.approved = :{$this->sqlparams($params, 'approved', 1)}
                                        OR e.userid = :{$this->sqlparams($params, 'userid', $USER->id)}) ";
            } else {
                $whereapprove = " AND e.approved = :{$this->sqlparams($params, 'approved', 1)} ";
            }
        }

        // STATUS filtering (visibility).
        $wherestatus = '';
        if (!has_capability('mod/datalynx:viewdrafts', $datalynx->context)) {
            $wherestatus = " AND (e.status <> :{$this->sqlparams($params, 'status', datalynxfield__status::STATUS_DRAFT)}
                              OR  e.userid = :{$this->sqlparams($params, 'userid', $USER->id)}) ";
        }

        // Sql for fetching the entries.
        $userfields = fields::for_name()->including('idnumber', 'username', 'institution', 'email');
        $selectfields = $userfields->get_sql('u', false, '', 'uid')->selects;

        $what = ' DISTINCT ' .
                // Entry.
                ' e.id, e.approved, e.timecreated, e.timemodified, e.userid, e.groupid, e.status ' .
                // User.
                $selectfields . ', ' .
                // Group (TODO g.description AS groupdesc need to be varchar for MSSQL).
                'g.name AS groupname, g.picture AS grouppic ' .
                // Content (including ratings and comments if required).
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

        if (isset($optionseids)) { // Datalynxview_field's entry-ids as an additional filter.
            // (= all entries which match the searched value).
            $eids = explode(",", $optionseids);
            list($ineids, $eidparams) = $DB->get_in_or_equal($eids, SQL_PARAMS_NAMED, 'eid');
            $whereoptions = " AND e.id $ineids ";
            $params = array_merge($params, array('eid' => $eidparams));
        }

        $fromsql = " $tables $filtertables ";
        $wheresql = " $wheredfid $whereoptions $whereuser $wheregroup $whereapprove $wherestatus $wheresearch";
        $sqlselect = "SELECT $what FROM $fromsql WHERE $wheresql $sortorder";

        // Total number of entries the user is authorized to view (without additional filtering).
        $sqlmax = "SELECT $count FROM $tables WHERE $wheredfid $whereoptions $whereuser $wheregroup $whereapprove $wherestatus";
        // Number of entries in this particular view call (with filtering).
        $sqlcount = "SELECT $count FROM $fromsql WHERE $wheresql";
        // Base params + search params.
        $baseparams = array();
        foreach ($params as $paramset) {
            $baseparams = array_merge($paramset, $baseparams);
        }
        $allparams = array_merge($baseparams, $filterparams);

        // Count prospective entries.
        if (empty($wheresearch)) {
            $maxcount = $searchcount = $DB->count_records_sql($sqlmax, $baseparams);
        } else {
            if ($maxcount = $DB->count_records_sql($sqlmax, $baseparams)) {
                $searchcount = $DB->count_records_sql($sqlcount, $allparams);
            } else {
                $searchcount = 0;
            }
        }

        // Initialize returned object.
        $entries = new stdClass();
        $entries->max = $maxcount;
        $entries->found = $searchcount;
        $entries->entries = null;

        if ($searchcount) {
            // If specific entries requested (eids).
            if (!empty($filter->eids) && !isset($optionseids)) {
                $eids = explode(",", $filter->eids);
                list($ineids, $eidparams) = $DB->get_in_or_equal($eids, SQL_PARAMS_NAMED, 'eid');
                $andwhereeid = " AND e.id $ineids ";

                $sqlselect = "SELECT $what $whatcontent
                              FROM $fromsql
                              WHERE $wheresql $andwhereeid $sortorder";

                if ($entries->entries = $DB->get_records_sql($sqlselect, $allparams + $eidparams)) {
                    // If one entry was requested get its position.
                    if (!is_array($filter->eids) || count($filter->eids) == 1) {
                        $sqlselect = "$sqlcount AND e.id $ineids";
                        $eidposition = $DB->get_records_sql($sqlselect, $allparams + $eidparams);

                        $filter->page = key($eidposition) - 1;
                    }
                }

                // Get perpage subset.
            } else {
                $perpage = $filter->perpage;
                if (!$filter->groupby && $perpage) {

                    // A random set (filter->selection == 1).
                    if (!empty($filter->selection)) {
                        // Get ids of found entries.
                        $sqlselect = "SELECT DISTINCT e.id FROM $fromsql WHERE $wheresql";
                        $entryids = $DB->get_records_sql($sqlselect, $allparams);
                        // Get a random subset of ids.
                        $randids = array_rand($entryids, min($perpage, count($entryids)));
                        // Get the entries.
                        list($insql, $paramids) = $DB->get_in_or_equal($randids, SQL_PARAMS_NAMED,
                                'rand');
                        $andwhereids = " AND e.id $insql ";
                        $sqlselect = "SELECT $what FROM $fromsql WHERE $wheresql $andwhereids";
                        $entries->entries = $DB->get_records_sql($sqlselect, $allparams + $paramids);

                        // By page.
                    } else {
                        $page = isset($filter->page) ? $filter->page : 0;
                        $numpages = $searchcount > $perpage ? ceil($searchcount / $perpage) : 1;

                        if (isset($filter->onpage)) {
                            // First page.
                            if ($filter->onpage == self::SELECT_FIRST_PAGE) {
                                $page = 0;

                                // Last page.
                            } else {
                                if ($filter->onpage == self::SELECT_LAST_PAGE) {
                                    $page = $numpages - 1;

                                    // Next page.
                                } else {
                                    if ($filter->onpage == self::SELECT_NEXT_PAGE) {
                                        $page = $filter->page = ($page % $numpages);

                                        // Random page.
                                    } else {
                                        if ($filter->onpage == self::SELECT_RANDOM_PAGE) {
                                            $page = $numpages > 1 ? rand(0, ($numpages - 1)) : 0;
                                        }
                                    }
                                }
                            }
                        }
                        $entries->entries = $DB->get_records_sql($sqlselect, $allparams, $page * $perpage, $perpage);
                    }
                    // Get everything.
                } else {
                    $entries->entries = $DB->get_records_sql($sqlselect, $allparams);
                }
            }
            // Now get the contents if required and add it to the entry objects.
            if ($datalynxcontent && $entries->entries) {
                // Get the node content of the requested entries.
                list($fids, $fparams) = $DB->get_in_or_equal($datalynxcontent, SQL_PARAMS_NAMED);
                list($eids, $eparams) = $DB->get_in_or_equal(array_keys($entries->entries),
                        SQL_PARAMS_NAMED);
                $params = array_merge($eparams, $fparams);
                $contents = $DB->get_records_select('datalynx_contents',
                        "entryid {$eids} AND fieldid {$fids}", $params);

                // If we see multiple contents to one entry and field, build array with postfix _fieldgroup.
                foreach ($contents as $contentid => $content) {
                    $entry = $entries->entries[$content->entryid];

                    // Create the contentid part.
                    $fieldid = $content->fieldid;
                    $varcontentid = "c{$fieldid}_id";

                    // If this has multiples we see a fieldgroup. Set as array and append.
                    if (isset($entry->{$varcontentid})) {
                        $varcontentids = "c{$fieldid}_id_fieldgroup";
                        $varcontentlineids = "c{$fieldid}_lineid_fieldgroup";
                        if (!isset($entry->{$varcontentids})) {
                            $entry->{$varcontentids} = array($entry->{$varcontentid});
                            $entry->{$varcontentlineids} = array(0); // TODO: We start with line 0, this is up for debate.
                        }
                        $entry->{$varcontentids}[] = $contentid;
                        $entry->{$varcontentlineids}[] = $content->lineid;

                    } else {
                        $entry->{$varcontentid} = $contentid; // Normal case, only one content item.
                    }

                    // Create the content part(s) as one field can have multiple content values.
                    foreach ($fields[$fieldid]->get_content_parts() as $part) {
                        $varpart = "c{$fieldid}_$part";

                        // If this already exists we see a fieldgroup. Set as array and append.
                        if (isset($entry->{$varpart})) {
                            $varparts = "c{$fieldid}_{$part}_fieldgroup";
                            if (!isset($entry->{$varparts})) {
                                $entry->{$varparts} = array($entry->{$varpart});
                            }
                            $entry->{$varparts}[] = $content->{$part};
                        } else {
                            $entry->{$varpart} = $content->{$part}; // Normal case, only one content item.
                        }
                    }
                    $entries->entries[$content->entryid] = $entry;
                }
            }
        }

        return $entries;
    }

    /**
     * get all entries created by the user with $userid
     *
     * @param integer $userid the user id of the user who created the entry (or was assigned as author of the entry)
     * @return object
     * @throws coding_exception
     * @throws dml_exception
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
     *
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
     *
     * @return array:
     */
    public function entries() {
        return $this->_entries;
    }

    /**
     * Retrieves stored files which are embedded in the current content
     * set_content must have been called
     *
     * @param array $fids
     * @return array of stored files
     * @throws coding_exception
     */
    public function get_embedded_files(array $fids) {
        $files = array();

        if (!empty($fids) && !empty($this->_entries)) {
            $fs = get_file_storage();
            foreach ($this->_entries as $entry) {
                foreach ($fids as $fieldid) {
                    // Get the content id of the requested field.
                    $contentid = isset($entry->{"c{$fieldid}_id"}) ? $entry->{"c{$fieldid}_id"} : null;
                    // The field may not hold any content.
                    if ($contentid) {
                        // Retrieve the files (no dirs) from file area.
                        // TODO for Picture fields this does not distinguish between the images and their thumbs.
                        // But the view may not necessarily display both.
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
     * Returns an array of objects indexed by contentid.
     * entryid, fieldid, userid, firstname, lastname.
     *
     *
     * @param array $fids
     * @return array int[]
     */
    public function get_contentinfo(array $fids) {
        $contentinfo = array();

        if (!empty($fids) && !empty($this->_entries)) {
            foreach ($this->_entries as $entry) {
                foreach ($fids as $fieldid) {
                    $contentid = isset($entry->{"c{$fieldid}_id"}) ? $entry->{"c{$fieldid}_id"} : null;
                    if ($contentid) {
                        $contentobject = new stdClass();
                        $contentobject->entryid = $entry->id;
                        $contentobject->fid = $fieldid;
                        $contentobject->userid = $entry->uid;
                        $contentobject->lastname = $entry->lastname;
                        $contentobject->firstname = $entry->firstname;
                        $contentinfo[$contentid] = $contentobject;
                    }
                }
            }
        }
        return $contentinfo;
    }

    /**
     * Process entries when after editing content for saving into db
     *
     * @param string $action
     * @param string||array $eids
     * @param null $data
     * @param bool $confirmed
     * @return array notificationstrings, list of processed ids
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function process_entries(string $action, $eids, $data = null, bool $confirmed = false): array {
        global $DB, $USER, $OUTPUT, $PAGE;
        $dl = $this->datalynx;
        $errorstring = '';

        $entries = array();
        // Some entries may be specified for action.
        if ($eids) {
            $importentryids = array();
            // Adding or updating entries.
            if ($action == 'update') {
                if (!is_array($eids)) {
                    // Adding new entries.
                    if ($eids < 0) {
                        $eids = array_reverse(range($eids, -1));
                        // Editing existing entries.
                    } else {
                        $eids = explode(',', $eids);
                    }
                }

                // TODO Prepare counters for adding new entries.
                $addcount = 0;
                $addmax = $dl->data->maxentries;
                $perinterval = ($dl->data->intervalcount > 1);
                if ($addmax != -1 && has_capability('mod/datalynx:manageentries', $dl->context)) {
                    $addmax = -1;
                } else {
                    if ($addmax != -1) {
                        $addmax = max(0, $addmax - $dl->user_num_entries($perinterval));
                    }
                }

                // Prepare the entries to process.
                foreach ($eids as $eid) {
                    $entry = new stdClass();

                    // Existing entry from view.
                    if ($eid > 0 && isset($this->_entries[$eid])) {
                        $entries[$eid] = $this->_entries[$eid];

                        // TODO existing entry *not* from view (import).
                    } else {
                        if ($eid > 0) {
                            $importentryids[] = $eid;

                            // New entries $eid is the number of new entries.
                        } else {
                            if ($eid < 0) {
                                $addcount++;
                                if ($addmax == -1 || $addmax >= $addcount) {
                                    $entry->id = 0;
                                    $entry->groupid = $dl->currentgroup;
                                    $entry->userid = $USER->id;
                                    $entries[$eid] = $entry;
                                }
                            }
                        }
                    }
                }

                // All other types of processing must refer to specific entry ids.
            } else {
                $entries = $DB->get_records_select('datalynx_entries', "dataid = ? AND id IN ($eids)", array($dl->id()));
            }

            if (!empty($importentryids)) {
                $filterdata = array('dataid' => $dl->id(), 'eids' => $importentryids);
                $filter = new datalynx_filter((object) $filterdata);
                $entries += $this->get_entries(array('filter' => $filter))->entries;
            }

            if ($entries) {
                foreach ($entries as $eid => $entry) {
                    // Filter approvable entries.
                    if (($action == 'approve' || $action == 'disapprove') &&
                            !has_capability('mod/datalynx:approve', $dl->context)
                    ) {
                        unset($entries[$eid]);
                        $capname = get_string('datalynx:approve', 'mod_datalynx');
                        $errorstring .= get_string('missingrequiredcapability', 'webservice', $capname);
                        $errorstring .= get_string('affectedid', 'mod_datalynx', $eid) . '<br>';
                        // Filter managable entries.
                    } else {
                        if (!$dl->user_can_manage_entry($entry)) {
                            unset($entries[$eid]);
                            $capname = get_string('updateentry', 'mod_datalynx');
                            $errorstring .= get_string('missingrequiredcapability', 'webservice', $capname);
                            $errorstring .= get_string('affectedid', 'mod_datalynx', $eid) . '<br>';
                        }
                    }
                }
            }
        }

        if (empty($entries)) {
            return array(get_string("entrynoneforaction", 'datalynx') . '<br>' . $errorstring, '');
        } else {
            if (!$confirmed) {

                // Print a confirmation page.
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
                            $fields = $dl->get_fields();

                            // First parse the data to collate content in an array for each recognized field.
                            $contents = array_fill_keys(array_keys($entries),
                                    array('info' => array(), 'fields' => array()
                                    ));
                            $entryinfo = array(datalynxfield__entry::_ENTRY,
                                    datalynxfield__time::_TIMECREATED,
                                    datalynxfield__time::_TIMEMODIFIED,
                                    datalynxfield__approve::_APPROVED,
                                    datalynxfield_entryauthor::_USERID,
                                    datalynxfield_entryauthor::_USERNAME,
                                    datalynxfield_entrygroup::_GROUP,
                                    datalynxfield__status::_STATUS
                            );

                            $skipnotification = array();
                            $drafttofinal = array();

                            // Iterate the data and extract entry and fields content.
                            foreach ($data as $name => $value) {
                                // Assuming only field names contain field_.
                                if (strpos($name, 'field_') !== false) {
                                    // If we don't see the iterator we are backwards compatible and fill with null.
                                    list(, $fieldid, $entryid, $iterator, $other) = array_pad(explode('_', $name, 5), 5, null);

                                    // Important, url appends _url, so only iterator if number.
                                    // TODO: This should be fixed in url, use an array to store _url and _alt, normalise that.
                                    if (!is_numeric($iterator)) {
                                        $iterator = null;
                                    } else {
                                        $iterator = intval($iterator);
                                    }

                                    if (array_key_exists($fieldid, $fields)) {
                                        $field = $fields[$fieldid];
                                    } else {
                                        continue;
                                    }
                                    // Entry info.
                                    if (in_array($fieldid, $entryinfo)) {
                                        // TODO.
                                        if ($fieldid == datalynxfield_entryauthor::_USERID ||
                                                $fieldid == datalynxfield_entryauthor::_USERNAME
                                        ) {
                                            $entryvar = 'userid';
                                        } else {
                                            $entryvar = $field->get_internalname();
                                        }
                                        if ($fieldid == datalynxfield__status::_STATUS &&
                                                $value == datalynxfield__status::STATUS_DRAFT
                                        ) {
                                            $skipnotification[] = $entryid;
                                        }
                                        if ($fieldid == datalynxfield__status::_STATUS &&
                                                $value == datalynxfield__status::STATUS_FINAL_SUBMISSION &&
                                                isset($entry->status) &&
                                                $entry->status == datalynxfield__status::STATUS_DRAFT
                                        ) {
                                            $drafttofinal[] = $entryid;
                                        }

                                        $contents[$entryid]['info'][$entryvar] = $value;

                                        // Entry content.
                                    } else {
                                        if (!array_key_exists($fieldid,
                                                $contents[$entryid]['fields'])
                                        ) {
                                            $contents[$entryid]['fields'][$fieldid] = $field->get_content_from_data(
                                                    $entryid, $data);
                                        }
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
                                    foreach ($contents[$entryid]['fields'] as $fieldid => $value) {
                                        if (array_search($fieldid, $bulkeditfields) !== false) {
                                            $newfields[$fieldid] = $contents[$firstentryid]['fields'][$fieldid];
                                        } else {
                                            if (array_key_exists($fieldid, $oldcontent['fields'])) {
                                                // If no values are updated just copy old values.
                                                $newfields[$fieldid] = $oldcontent['fields'][$fieldid];
                                            }
                                        }
                                    }
                                    $newcontents[$entryid]['fields'] = $newfields;
                                } else {
                                    $newcontents[$entryid] = $oldcontent;
                                }
                            }
                            $contents = $newcontents;

                            global $DB;
                            // Now update entry and contents TODO: TEAM_CHANGED - check this!
                            $addorupdate = '';
                            foreach ($entries as $eid => $entry) {
                                if ($eid > 0) {
                                    if (isset($contents[$eid]['info']['status'])) {
                                        $entrystatus = $DB->get_field('datalynx_entries', 'status', array('id' => $eid), 'MUST_EXIST'); // Find current state of entry in db.
                                        require_once('field/_status/field_class.php');
                                        if ($entrystatus == datalynxfield__status::STATUS_FINAL_SUBMISSION
                                                && !has_capability('mod/datalynx:manageentries', $this->datalynx->context)) {
                                            continue; // Check user has capacity & status is final. If stop update.
                                        }
                                    }
                                }

                                if ($entry->id = $this->update_entry($entry, $contents[$eid]['info'])) {

                                    $emptycontent = array(); // Array with lines and deleted contentids.
                                    $countfgfields = 0; // Store how many fields exist per line.

                                    // Variable $eid should be different from $entryid only in new entries.
                                    // Iterate through all the fields part of an entry and a fieldgroup. Field by field.
                                    foreach ($contents[$eid]['fields'] as $fieldid => $content) {

                                        // If we see a fieldgroup we split and reset the content.
                                        $fieldgroup = array_search(true, $content);
                                        if (strpos($fieldgroup, "fieldgroup") === 0) {
                                            $countfgfields++;
                                            // TODO: Rewrite this for fieldgroup_id instead of fieldgroup. Use $fieldgroup.
                                            // How many lines were visible to the user, store only those.

                                            $fieldname = "field_{$fieldid}_{$eid}";
                                            // Split $content and generate temporary content.
                                            // Look for all content_names like _url or _alt.
                                            $tempcontent = array();

                                            foreach ($content as $key => $value) {

                                                // Only add keys that start with our expected pattern to tempcontent.
                                                // Pattern of submitted field content.
                                                if (strpos($key, 'fieldgroup_') === 0) {
                                                    continue;
                                                }

                                                // Skip _alt from url, this does corrupt updating.
                                                if (!substr_compare($key, "_alt", -4, 4)) {
                                                    continue;
                                                }

                                                $getlinenumber = explode("_", $key);
                                                // Line number is the 6th element of the array.
                                                $i = $getlinenumber[5];

                                                $fieldcontentpattern = "{$fieldname}_{$fieldgroup}_{$i}";
                                                if (0 === strpos($key, $fieldcontentpattern)) {
                                                    // If we found sth. relevant, split it up and rebuild key.
                                                    // Either it has content_name after the iterator or not.
                                                    $contentname = explode("{$fieldcontentpattern}_", $key);
                                                    if (isset($contentname[1])) {
                                                        $tempcontent[$contentname[1]] = $value; // No need for fieldname.
                                                    } else {
                                                        $tempcontent[$fieldname] = $value;
                                                    }
                                                }

                                                // We know this is a fieldgroup but there is only one line set.
                                                if (!isset($entry->{"c{$fieldid}_id_fieldgroup"})
                                                    && isset($entry->{"c{$fieldid}_id"})) {
                                                    $entry->{"c{$fieldid}_id_fieldgroup"}[0] = $entry->{"c{$fieldid}_id"};
                                                }

                                                // Split $entry and overwrite entry content.
                                                $entry->{"c{$fieldid}_id"} = $entry->{"c{$fieldid}_content"} = null;
                                                if (isset($entry->{"c{$fieldid}_id_fieldgroup"}[$i])) {
                                                    $entry->{"c{$fieldid}_id"} = $entry->{"c{$fieldid}_id_fieldgroup"}[$i];
                                                }
                                                if (isset($entry->{"c{$fieldid}_content_fieldgroup"}[$i])) {
                                                    $entry->{"c{$fieldid}_content"} =
                                                        $entry->{"c{$fieldid}_content_fieldgroup"}[$i];
                                                }

                                                /* Loop all fields like _content1 and _content2.
                                                   TODO: Test this. And rewrite in order to avoid loops. Define what content is
                                                    used in the field class. */
                                                for ($j = 1; $j <= 4; $j++) {
                                                    if (isset($entry->{"c{$fieldid}_content{$j}_fieldgroup"}[$i])) {
                                                        $entry->{"c{$fieldid}_content{$j}"} =
                                                            $entry->{"c{$fieldid}_content{$j}_fieldgroup"}[$i];
                                                    }
                                                }
                                                // Pass tempstuff to updatecontent.
                                                // TODO: This relies on the correctness of the field classes update content.
                                                $newcontentid = $fields[$fieldid]->update_content($entry, $tempcontent);

                                                // In case this field has no content mark and check deletion later.
                                                // TODO: Needs to be extended for all field classes in function.
                                                if ($fields[$fieldid]->is_fieldvalue_empty($value)) {

                                                    if (isset($entry->{"c{$fieldid}_id_fieldgroup"}[$i])) {
                                                        $emptycontent[$i][] = $entry->{"c{$fieldid}_id_fieldgroup"}[$i];
                                                    } else {
                                                        $emptycontent[$i][] = $newcontentid;
                                                    }
                                                }
                                            }
                                        } else {
                                            // Keep behaviour if no fieldgroup is detected.
                                            $fields[$fieldid]->update_content($entry, $content);
                                        }

                                    }

                                    // Remove contentids that we have collected.
                                    if ($emptycontent) {
                                        $deletedcontentids = array();
                                        foreach ($emptycontent as $line => $contentids) {
                                            // Check if every field is empty, only then remove line.
                                            if (count($contentids) != $countfgfields) {
                                                continue;
                                            }
                                            $deletedcontentids = array_merge($deletedcontentids, $contentids);
                                        }
                                        if ($deletedcontentids) {
                                            $in = implode(',', $deletedcontentids);
                                            $DB->delete_records_select('datalynx_contents', "id IN ($in)"); // TESTING.
                                        }
                                    }
                                    $processed[$entry->id] = $entry;

                                    if (!$addorupdate) {
                                        $addorupdate = $eid < 0 ? 'added' : 'updated';
                                    }
                                }
                            }
                            if ($processed) {
                                $eventdata = (object) array('items' => $processed);
                                $dl->events_trigger("entry$addorupdate", $eventdata);
                            }
                        }
                        break;

                    case 'duplicate':
                        $completiontype = COMPLETION_COMPLETE;
                        foreach ($entries as $entry) {
                            // Can user add anymore entries?
                            if (!$dl->user_can_manage_entry()) {
                                // TODO: notify something.
                                break;
                            }

                            // Get content of entry to duplicate.
                            $contents = $DB->get_records('datalynx_contents', array('entryid' => $entry->id));

                            // Add a duplicated entry and content.
                            $newentry = $entry;
                            $newentry->userid = $USER->id;
                            $newentry->dataid = $dl->id();
                            $newentry->groupid = $dl->currentgroup;
                            $newentry->timecreated = $newentry->timemodified = time();

                            if ($dl->data->approval &&
                                    !has_capability('mod/datalynx:approve', $dl->context)
                            ) {
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
                            $dl->events_trigger("entryadded", $eventdata);
                        }

                        $strnotify = 'entriesduplicated';
                        break;

                    case 'approve':
                        $completiontype = COMPLETION_COMPLETE;
                        // Approvable entries should be filtered above.
                        $entryids = array_keys($entries);
                        $ids = implode(',', $entryids);
                        $DB->set_field_select('datalynx_entries', 'approved', 1,
                                " dataid = ? AND id IN ($ids) ", array($dl->id()));
                        $processed = $entries;

                        $processed += $this->create_approved_entries_for_team($entryids);

                        if ($processed) {
                            $eventdata = (object) array('items' => $processed);
                            $dl->events_trigger("entryapproved", $eventdata);
                        }

                        $strnotify = 'entriesapproved';
                        break;

                    case 'disapprove':
                        $completiontype = COMPLETION_COMPLETE;
                        // Disapprovable entries should be filtered above.
                        $entryids = array_keys($entries);
                        $ids = implode(',', $entryids);
                        $DB->set_field_select('datalynx_entries', 'approved', 0,
                                " dataid = ? AND id IN ($ids) ", array($dl->id()));
                        $processed = $entries;
                        if ($processed) {
                            $eventdata = (object) array('items' => $processed);
                            $dl->events_trigger("entrydisapproved", $eventdata);
                        }

                        $strnotify = 'entriesdisapproved';
                        break;

                    case 'delete':
                        $completiontype = COMPLETION_INCOMPLETE;
                        // Deletable entries should be filtered above.
                        foreach ($entries as $entry) {
                            $fields = $dl->get_fields();
                            foreach ($fields as $field) {
                                $field->delete_content($entry->id);
                            }

                            $DB->delete_records('datalynx_entries', array('id' => $entry->id));
                            $processed[$entry->id] = $entry;
                        }
                        if ($processed) {
                            $eventdata = (object) array('items' => $processed);
                            $dl->events_trigger("entrydeleted", $eventdata);
                        }

                        $strnotify = 'entriesdeleted';
                        break;

                    default:
                        break;
                }

                if ($processed) {
                    // Update completion state.
                    $completion = new completion_info($dl->course);
                    if ($completion->is_enabled($dl->cm) &&
                            $dl->cm->completion == COMPLETION_TRACKING_AUTOMATIC &&
                            $dl->data->completionentries
                    ) {
                        foreach ($processed as $entry) {
                            $completion->update_state($dl->cm, $completiontype, $entry->userid);
                        }
                    }
                    $strnotify = get_string($strnotify, 'datalynx', count($processed));
                } else {
                    $strnotify = get_string($strnotify, 'datalynx', get_string('no'));
                }

                return array($strnotify . $errorstring, array_keys($processed));
            }
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
        $dl = $this->datalynx;

        $fields = $dl->get_fields();
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
                                array('dataid' => $dl->id(), 'userid' => $teammemberid,
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
                        $newentry->dataid = $dl->id();
                        $newentry->groupid = $dl->currentgroup;
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
     * Update an entry.
     *
     * @param object $entry
     * @param array $data
     * @param boolean $updatetime
     * @return boolean|integer <boolean, number>
     */
    public function update_entry($entry, $data = null, $updatetime = true) {
        global $CFG, $DB, $USER;

        $df = $this->datalynx;

        if ($data && has_capability('mod/datalynx:manageentries', $df->context)) {
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

        // Update existing entry (only authenticated users).
        if ($entry->id > 0) {
            if ($df->user_can_manage_entry($entry)) { // Just in case the user opens two forms at the same time.
                if (!has_capability('mod/datalynx:approve', $df->context)
                        && ($df->data->approval == mod_datalynx\datalynx::APPROVAL_ON_UPDATE)
                ) {
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

            // Add new entry (authenticated or anonymous (if enabled)).
        } else {
            if ($df->user_can_manage_entry(null)) {
                // Identify non-logged-in users (in anonymous entries) as guests.
                $userid = empty($USER->id) ? $CFG->siteguest : $USER->id;
                $entry->dataid = $df->id();
                $entry->userid = !empty($entry->userid) ? $entry->userid : $userid;
                if (!isset($entry->groupid)) {
                    $entry->groupid = $df->currentgroup;
                }
                if (!isset($entry->timecreated)) {
                    $entry->timecreated = time();
                }
                if (!isset($entry->timemodified)) {
                    $entry->timemodified = time();
                }
                $entry->status = isset($data['status']) ? $data['status'] : 0;
                $entryid = $DB->insert_record('datalynx_entries', $entry);
                if (isset($entry->approved) && $entry->approved) {
                    $this->create_approved_entries_for_team(array($entryid));
                }
                return $entryid;
            }
        }

        return false;
    }

    /**
     * Get sql params.
     *
     * @param array $params
     * @param string $param
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
