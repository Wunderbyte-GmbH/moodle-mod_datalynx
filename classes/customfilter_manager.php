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
 * Contains class mod_datalynx_customfilter_manager
 *
 * @package mod
 * @subpackage datalynx
 * @copyright 2016 Thomas Niedermaier
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Customfilter manager class
 */
class mod_datalynx_customfilter_manager {

    const USER_FILTER_MAX_NUM = 5;

    const BLANK_FILTER = -1;

    const USER_FILTER_SET = -2;

    const USER_FILTER_ID_START = -10;

    protected $_df;

    protected $_customfilters;

    /**
     * constructor
     */
    public function __construct($df) {
        $this->_df = $df;
        $this->_customfilters = array();
    }

    /**
     */
    public function get_filter_from_id($filterid = 0, array $options = null) {

        $df = $this->_df;
        $dfid = $df->id();

        if ($filterid == self::BLANK_FILTER) {
            $filter = new stdClass();
            $filter->dataid = $df->id();
            $filter->name = get_string('filternew', 'datalynx');

            return new mod_datalynx_customfilter($filter);
        }

        if ($filterid < 0) {
            $view = !empty($options['view']) ? $options['view'] : null;
            $viewid = $view ? $view->id() : 0;

            if ($filterid == self::USER_FILTER_SET and $view and $view->is_active()) {
                $filter = $this->set_user_filter($filterid, $view);
                return new mod_datalynx_customfilter($filter);
            }

            if ($filterid != self::USER_FILTER_SET and
                $filter = get_user_preferences("datalynxcustomfilter-$dfid-$viewid-$filterid", null)) {
                $filter = unserialize($filter);
                $filter->dataid = $dfid;
                return new mod_datalynx_customfilter($filter);
            }

            $filterid = 0;
        }

        if ($filterid == 0) {
            if (!$df->data->defaultfilter) {
                $filter = new stdClass();
                $filter->dataid = $df->id();

                return new mod_datalynx_customfilter($filter);

            } else {
                $filterid = $df->data->defaultfilter;
            }
        }

        if ($this->get_filters() and isset($this->_customfilters[$filterid])) {
            return clone ($this->_customfilters[$filterid]);
        } else {
            $filter = new stdClass();
            $filter->dataid = $df->id();

            return new mod_datalynx_customfilter($filter);
        }
    }

    /**
     */
    public function get_filter_from_url($url, $raw = false) {

        $df = $this->_df;
        $dfid = $df->id();

        if ($options = self::get_filter_options_from_url($url)) {
            $options['dataid'] = $dfid;
            $filter = new mod_datalynx_customfilter((object) $options);

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
        if (!$this->_customfilters or $forceget) {
            $this->_filters = array();
            if ($filters = $DB->get_records('datalynx_customfilters', array('dataid' => $this->_df->id()))) {
                foreach ($filters as $filterid => $filterdata) {
                    $this->_customfilters[$filterid] = new mod_datalynx_customfilter($filterdata);
                }
            }
        }

        if ($this->_customfilters) {
            if (empty($exclude) and !$menu) {
                return $this->_customfilters;
            } else {
                $filters = array();
                foreach ($this->_customfilters as $filterid => $filter) {
                    if (!empty($exclude) and in_array($filterid, $exclude)) {
                        continue;
                    }
                    if ($menu) {
                        if ($filter->visible or
                            has_capability('mod/datalynx:managetemplates', $this->_df->context)) {
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
        if (has_capability('mod/datalynx:managetemplates', $df->context)) {
            if ($fids) {
                $filters = $DB->get_records_select('datalynx_customfilters', "id IN ($fids)");
            } else if ($action == 'update') {
                $filters[] = $this->get_filter_from_id(self::BLANK_FILTER);
            }
        }
        $processedfids = array();
        $strnotify = '';

        // TODO update should be roled
        if (empty($filters)) {
            $df->notifications['bad'][] = get_string("filternoneforaction", 'datalynx');
            return false;
        } else {
            if (!$confirmed) {
                $df->print_header('customfilters');

                echo $OUTPUT->confirm(
                    get_string("filtersconfirm$action", 'datalynx', count($filters)),
                    new moodle_url('/mod/datalynx/customfilter/index.php',
                        array('d' => $df->id(),
                            $action => implode(',', array_keys($filters)),
                            'sesskey' => sesskey(), 'confirmed' => 1)),
                    new moodle_url('/mod/datalynx/customfilter/index.php', array('d' => $df->id())));

                echo $OUTPUT->footer();
                exit();
            } else {
                switch ($action) {
                    case 'update':
                        $filter = reset($filters);
                        $mform = $this->get_customfilter_backend_form($filter);

                        if ($mform->is_cancelled()) {
                            break;
                        }

                        $formdata = $mform->get_submitted_data();
                        $filter = $this->get_filter_from_form($filter, $formdata);
                        $filterform = $this->get_customfilter_backend_form($filter);

                        if ($filterform->no_submit_button_pressed()) {
                            $this->display_filter_form($filterform, $filter);

                        } else if ($formdata = $filterform->get_data()) {
                            $filter = $this->get_filter_from_form($filter, $formdata);

                            if ($filter->id) {
                                $DB->update_record('datalynx_customfilters', $filter);
                                $processedfids[] = $filter->id;
                                $strnotify = 'filtersupdated';

                                $other = array('dataid' => $this->_df->id());
                                $event = \mod_datalynx\event\field_updated::create(
                                    array('context' => $this->_df->context,
                                        'objectid' => $filter->id, 'other' => $other));
                                $event->trigger();
                            } else {
                                $filter->id = $DB->insert_record('datalynx_customfilters', $filter, true);
                                $processedfids[] = $filter->id;
                                $strnotify = 'filtersadded';

                                $other = array('dataid' => $this->_df->id());
                                $event = \mod_datalynx\event\field_created::create(
                                    array('context' => $this->_df->context,
                                        'objectid' => $filter->id, 'other' => $other
                                    ));
                                $event->trigger();
                            }
                            $this->_filters[$filter->id] = $filter;
                        }

                        break;

                    case 'duplicate':
                        if (!empty($filters)) {
                            foreach ($filters as $filter) {
                                // TODO: check for limit
                                while ($df->name_exists('customfilters', $filter->name)) {
                                    $filter->name = 'Copy of ' . $filter->name;
                                }
                                $filterid = $DB->insert_record('datalynx_customfilters', $filter);

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
                            $DB->update_record('datalynx_customfilters', $updatefilter);
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
                            $DB->delete_records('datalynx_customfilters', array('id' => $filter->id));

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
    public function get_customfilter_backend_form($filter) {

        $formurl = new moodle_url('/mod/datalynx/customfilter/index.php',
            array('d' => $this->_df->id(), 'fid' => $filter->id, 'update' => 1));
        $mform = new mod_datalynx_customfilter_backend_form($this->_df, $filter, $formurl);
        return $mform;
    }


    /**
     */
    public function display_filter_form($mform, $filter, $urlparams = null) {
        $stredittitle = $filter->id ? get_string('filteredit', 'datalynx', $filter->name) :
            get_string('customfilternew', 'datalynx');
        $heading = html_writer::tag('h2', format_string($stredittitle), array('class' => 'mdl-align'));

        $this->_df->print_header(array('tab' => 'customfilters', 'urlparams' => $urlparams));
        echo $heading;
        $mform->display();
        $this->_df->print_footer();

        exit();
    }

    /**
     */
    public function get_filter_from_form($filter, $formdata) {
        $filter->name = $formdata->name;
        $filter->description = !empty($formdata->description) ? $formdata->description : '';
        $filter->visible = !isset($formdata->visible) ? 0 : $formdata->visible;
        $filter->fulltextsearch = !isset($formdata->fulltextsearch) ? 0 : $formdata->fulltextsearch;
        $filter->timecreated = empty($formdata->timecreated) ? 0 : $formdata->timecreated;
        $filter->timemodified = empty($formdata->timemodified) ? 0 : $formdata->timemodified;
        $filter->approve = empty($formdata->approve) ? 0 : $formdata->approve;
        $filter->status = empty($formdata->status) ? 0 : $formdata->status;
        $filter->fieldlist = empty($formdata->fieldlist) ? 0 : $formdata->fieldlist;

        return $filter;
    }

    /**
     */
    public function print_filter_list() {
        global $OUTPUT;

        $df = $this->_df;

        $filterbaseurl = '/mod/datalynx/customfilter/index.php';
        $linkparams = array('d' => $df->id(), 'sesskey' => sesskey());

        $strfilters = get_string('name');
        $strdescription = get_string('description');
        $strvisible = get_string('visible');
        $strfulltextsearch = get_string('fulltextsearch', 'datalynx');
        $strfieldlist = get_string('fieldlist', 'datalynx');
        $strhide = get_string('hide');
        $strshow = get_string('show');
        $stredit = get_string('edit');
        $strdelete = get_string('delete');
        $strduplicate = get_string('duplicate');

        $table = new html_table();
        $table->head = array($strfilters, $strdescription, $strfulltextsearch, $strfieldlist,
            $strvisible, $stredit, $strduplicate, $strdelete);
        $table->align = array('left', 'left', 'center', 'left', 'center', 'center', 'center', 'center');
        $table->wrap = array(false, false, false, false, false, false, false, false);
        $table->attributes['align'] = 'center';

        $yesstr = get_string("yes");
        $nostr = "---";
        foreach ($this->_customfilters as $filterid => $filter) {
            $filtername = html_writer::link(
                new moodle_url($filterbaseurl,
                    $linkparams + array('fedit' => $filterid, 'fid' => $filterid)), $filter->name);
            $filterdescription = shorten_text($filter->description, 30);
            $fulltextsearch = $filter->fulltextsearch ? $yesstr : $nostr;
            if ($filter->fieldlist) {
                $fieldlist = "";
                $connector = "";
                $fieldlistdecode = json_decode($filter->fieldlist);
                foreach($fieldlistdecode as $fname => $fid) {
                    $fieldlist .= $connector . $fname;
                    $connector = ", ";
                }
            } else {
                $fieldlist = $nostr;
            }

            if ($filter->visible) {
                $visibleicon = $OUTPUT->pix_icon('t/hide', $strhide);
            } else {
                $visibleicon = $OUTPUT->pix_icon('t/show', $strshow);
            }
            $visibleurl = html_writer::link(
                new moodle_url($filterbaseurl, $linkparams + array('visible' => $filterid)), $visibleicon);
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
            $table->data[] = array($filtername, $filterdescription, $fulltextsearch, $fieldlist,
                $visibleurl, $filteredit, $filterduplicate, $filterdelete
            );
        }

        echo html_writer::table($table);
    }

    /**
     */
    public function print_add_filter() {
        echo html_writer::empty_tag('br');
        echo html_writer::start_tag('div', array('class' => 'fieldadd mdl-align'));
        echo html_writer::link(
            new moodle_url('/mod/datalynx/customfilter/index.php',
                array('d' => $this->_df->id(), 'sesskey' => sesskey(), 'new' => 1)),
            get_string('customfilteradd', 'datalynx'));
        echo html_writer::end_tag('div');
        echo html_writer::empty_tag('br');
    }


    /**
     */
    public function get_user_filters_menu($viewid) {
        $filters = array();

        $df = $this->_df;
        $dfid = $df->id();
        if ($filternames = get_user_preferences("datalynxcustomfilter-$dfid-$viewid-userfilters", '')) {
            foreach (explode(';', $filternames) as $filteridname) {
                list($filterid, $name) = explode(' ', $filteridname, 2);
                $filters[$filterid] = $name;
            }
        }
        return $filters;
    }

    /**
     */
    public function set_user_filter($filterid, $view) {
        $df = $this->_df;
        $dfid = $df->id();
        $viewid = $view->id();

        if($filterid >= $this->USER_FILTER_ID_START ) {
            $filter = $this->get_filter_from_userpreferences($filterid);
        } else {
            $filter = $this->get_filter_from_url(null, true);
        }
        if(!$filter) {
            return null;
        }

        if ($userfilters = $this->get_user_filters_menu($viewid)) {
            if (empty($userfilters[$filterid])) {
                $filterid = key($userfilters) - 1;
            }
        } else {
            $filterid = self::USER_FILTER_ID_START;
        }

        if (count($userfilters) >= self::USER_FILTER_MAX_NUM) {
            $fids = array_keys($userfilters);
            while (count($fids) >= self::USER_FILTER_MAX_NUM) {
                $fid = array_pop($fids);
                unset($userfilters[$fid]);
                unset_user_preference("datalynxfilter-$dfid-$viewid-$fid");
            }
        }

        $filter->id = $filterid;
        $filter->dataid = $dfid;
        if (empty($filter->name)) {
            $filter->name = get_string('filtermy', 'datalynx') . ' ' . abs($filterid);
        }
        set_user_preference("datalynxfilter-$dfid-$viewid-$filterid", serialize($filter));

        $userfilters = array($filterid => $filter->name) + $userfilters;
        foreach ($userfilters as $filterid => $name) {
            $userfilters[$filterid] = "$filterid $name";
        }
        set_user_preference("datalynxfilter-$dfid-$viewid-userfilters", implode(';', $userfilters));

        return $filter;
    }

    // HELPERS.

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
            'eids' => array('eids', 0, PARAM_INT),
            'users' => array('users', '', PARAM_SEQUENCE),
            'groups' => array('groups', '', PARAM_SEQUENCE),
            'afilter' => array('afilter', 0, PARAM_INT),
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
                        } else if ($option == 'customsearch') {
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

        // Optional params.
        foreach ($filteroptions as $option => $args) {
            list($name, $default, $type) = $args;
            if ($val = optional_param($name, $default, $type)) {
                if ($option == 'customsort') {
                    $options[$option] = self::get_sort_options_from_query($val);
                } else if ($option == 'customsearch') {
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

        return $options;
    }

    public static function get_filter_options_from_userpreferences()
    {
        $filteroptions = array(   // Left: urlparam-names, right: userpreferences-names.
            'perpage' =>        'uperpage',
            'selection' =>      'uselection',
            'groupby' =>        'ugroupby',
            'customsort' =>     'usort',
            'customsearch' =>   'usearch',
            'page' =>           'page',
            'eids' =>           'eids',
            'users' =>          'users',
            'groups' =>         'groups',
            'afilter' =>        'afilter',
            'usersearch' =>     'usersearch'
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
                    } else if ($option == 'customsearch') {
                        $searchoptions = self::get_search_options_from_query($val);
                        if (is_array($searchoptions)) {
                            $options['customsearch'] = $searchoptions;
                        } else {
                            $options['search'] = $searchoptions;
                        }
                    } else if ($option == 'usersearch') {
                        $options['search'] = $val;
                    } else {
                        $options[$option] = $val;
                    }
                }
            }
        }

        return $options;

    }
}
