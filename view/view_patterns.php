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
 * @package datalynxview
 * @copyright 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();


/**
 * Base class for view patterns
 */
class datalynxview_patterns {

    const PATTERN_SHOW_IN_MENU = 0;

    const PATTERN_CATEGORY = 1;

    /**
     *
     * @var datalynxview_base
     */
    protected $_view = null;

    /**
     * Constructor
     */
    public function __construct(&$view) {
        $this->_view = $view;
    }

    /**
     * Find pattern/tags and return them in an array
     * 
     * @param string $text
     * @return multitype:unknown
     */
    public function search($text) {
        $viewid = $this->_view->view->id;
        
        $found = array();
        // Fixed patterns
        $patterns = array_keys($this->patterns());
        foreach ($patterns as $pattern) {
            if (strpos($text, $pattern) !== false) {
                $found[] = $pattern;
            }
        }
        
        // Regexp patterns
        if ($patterns = array_keys($this->regexp_patterns())) {
            foreach ($patterns as $pattern) {
                if (preg_match_all("/$pattern/", $text, $matches)) {
                    foreach ($matches[0] as $match) {
                        $found[$match] = $match;
                    }
                }
            }
        }
        return $found;
    }

    /**
     * Returns tags menu to be rendered as dropdown
     *
     * @param string $showall
     * @return array
     */
    public final function get_menu($showall = false) {
        // the default menu category for views
        $patternsmenu = array();
        foreach ($this->patterns() as $tag => $pattern) {
            if ($showall or $pattern[self::PATTERN_SHOW_IN_MENU]) {
                // which category
                if (!empty($pattern[self::PATTERN_CATEGORY])) {
                    $cat = $pattern[self::PATTERN_CATEGORY];
                } else {
                    $cat = get_string('views', 'datalynx');
                }
                // prepare array
                if (!isset($patternsmenu[$cat])) {
                    $patternsmenu[$cat] = array($cat => array()
                    );
                }
                // add tag
                $patternsmenu[$cat][$cat][$tag] = $tag;
            }
        }
        return $patternsmenu;
    }

    /**
     * Get an array with the tags and the values to replace. 
     * 
     * @param array $tags array of tags with tagname as value and tagname or number as key 
     * @param null $entry
     * @param array $options options like pluginfileurl, entriescount, entriesfiltercount, hidenewentry, baseurl
     * @return array of strings key: tagname, value:the string that replaces the tag
     */
    public function get_replacements($tags = null, $entry = null, array $options = array()) {
        $view = $this->_view;
        
        // existing tag types
        $info = array_keys($this->info_patterns()); // number of entries
        $ref = array_keys($this->ref_patterns()); // viewrelated tags like ##viewurl## and ##filtersmenu##
        $userpref = array_keys($this->userpref_patterns()); // custom filter tags
        $actions = array_keys($this->action_patterns()); // action tags like ##addnewentry## and ##multiedit##
        $paging = array_keys($this->paging_patterns()); // paging tags
        
        $options['filter'] = $view->get_filter();
        $options['baseurl'] = new moodle_url($view->get_baseurl(), 
                array('sesskey' => sesskey()
                ));
        
        $replacements = array();
        foreach ($tags as $tag) {
            if($tag == '##entries##') {
                $replacements[$tag] = '##entries##';
            } else if (in_array($tag, $info)) {
                $replacements[$tag] = $this->get_info_replacements($tag, $entry, $options);
            } else if (in_array($tag, $ref)) {
                $replacements[$tag] = $this->get_ref_replacements($tag, $entry, $options);
            } else if (in_array($tag, $userpref)) {
                $replacements[$tag] = $this->get_userpref_replacements($tag, $entry, $options);
            } else if (in_array($tag, $actions)) {
                $replacements[$tag] = $this->get_action_replacements($tag, $entry, $options);
            } else if (in_array($tag, $paging)) {
                $replacements[$tag] = $this->get_paging_replacements($tag, $entry, $options);
            } else if ($this->is_regexp_pattern($tag)) {
                $replacements[$tag] = $this->get_regexp_replacements($tag, $entry, $options);
            } else {
                $replacements[$tag] = '';
            }
        }
        
        return $replacements;
    }

    /**
     * Get the values for tags that contain a viewname or a fieldname
     * 
     * @param unknown $tag
     * @param string $entry
     * @param array $options
     * @return string
     */
    protected function get_regexp_replacements($tag, $entry = null, array $options = null) {
        global $OUTPUT;
        
        $df = $this->_view->get_df();
        
        static $views = null;
        if ($views === null) {
            $views = $df->get_all_views();
            foreach ($views as $view) {
                $viewname = $view->name;
                
                $baseurlparams = array();
                $baseurlparams['d'] = $view->dataid;
                $baseurlparams['view'] = $view->id;
                
                $view->baseurl = new moodle_url(
                        "/mod/datalynx/{$this->_view->get_df()->pagefile()}.php", $baseurlparams);
            }
        }
        
        if ($views) {
            foreach ($views as $view) {
                $viewname = $view->name;
                if (strpos($tag, "#{{viewlink:$viewname;") === 0) {
                    list(, $linktext, $urlquery, ) = explode(';', $tag);
                    // Pix icon for text
                    if (strpos($linktext, '_pixicon:') === 0) {
                        list(, $icon, $titletext) = explode(':', $linktext);
                        $linktext = $OUTPUT->pix_icon($icon, $titletext);
                    }
                    // Replace pipes in urlquery with &
                    $urlquery = str_replace('|', '&', $urlquery);
                    return html_writer::link($view->baseurl->out(false) . "&$urlquery", $linktext);
                }
                if (strpos($tag, "#{{viewsesslink:$viewname;") === 0) {
                    list(, $linktext, $urlquery, ) = explode(';', $tag);
                    // Pix icon for text
                    if (strpos($linktext, '_pixicon:') === 0) {
                        list(, $icon, $titletext) = explode(':', $linktext);
                        $linktext = $OUTPUT->pix_icon($icon, $titletext);
                    }
                    $urlquery = str_replace('|', '&', $urlquery);
                    $linkparams = array('sesskey' => sesskey(), 'sourceview' => $this->_view->id()
                    );
                    $viewlink = new moodle_url($view->baseurl, $linkparams);
                    if (strpos($urlquery, 'new=1') === false || $this->user_can_add_new_entry()) {
                        return html_writer::link($viewlink->out(false) . "&$urlquery", $linktext);
                    } else {
                        return '';
                    }
                }
            }
        }
        
        static $fields = null;
        if ($fields === null) {
            $fields = $df->get_fields(null, true);
        }
        
        foreach ($fields as $id => $fieldname) {
            if (strpos($tag, "%%{$fieldname}:bulkedit%%") === 0) {
                if (true) { // if (isset($options['edit'])) {
                    return html_writer::checkbox("field_{$id}_bulkedit", 1, false, '');
                } else {
                    return '';
                }
            }
        }
        
        return '';
    }

    /**
     * Find out if user can add a new entry
     * 
     * @param number $userid
     * @return boolean true if user is allowed to add a new entry
     */
    private function user_can_add_new_entry($userid = 0) {
        global $USER, $DB;
        $userid = $userid ? $userid : $USER->id;
        $df = $this->_view->get_df();
        $maxentries = $df->data->maxentries;
        if ($maxentries == -1) {
            return true;
        }
        $params = array('userid' => $userid, 'dataid' => $df->id()
        );
        $sql = "SELECT COUNT(1)
                  FROM {datalynx_entries} de
                 WHERE de.userid = :userid
                   AND de.dataid = :dataid";
        $count = $DB->get_field_sql($sql, $params);
        
        return $count < $maxentries;
    }

    /**
     * Returns the values for number of total entries and number of entries displayed
     * 
     * @param string $tag
     * @param string $entry
     * @param array $options
     * @return Ambigous <string, number>
     */
    protected function get_info_replacements($tag, $entry = null, array $options = null) {
        $replacement = '';
        
        switch ($tag) {
            case '##numentriestotal##':
                $replacement = empty($options['entriesfiltercount']) ? 0 : $options['entriesfiltercount'];
                break;
            case '##numentriesdisplayed##':
                $replacement = empty($options['entriescount']) ? 0 : $options['entriescount'];
                break;
        }
        return $replacement;
    }

    /**
     * Return the value of a tag that references a view (viewurl, etc.) 
     * 
     * @param string $tag
     * @param string $entry
     * @param array $options
     * @return string
     */
    protected function get_ref_replacements($tag, $entry = null, array $options = null) {
        if ($tag == '##viewsmenu##') {
            return $this->print_views_menu($options, true);
        }
        
        if ($tag == '##filtersmenu##') {
            return $this->print_filters_menu($options, true);
        }
        
        // View url
        if ($tag == '##viewurl##') {
            return $this->get_viewurl_replacement();
        }
        
        if (strpos($tag, '##viewurl:') === 0) {
            list(, $viewname) = explode(':', trim($tag, '#'));
            return $this->get_viewurl_replacement($viewname);
        }
        
        // View content
        if (strpos($tag, '##viewcontent:') === 0) {
            list(, $viewname) = explode(':', trim($tag, '#'));
            return $this->get_viewcontent_replacement($viewname);
        }
        
        return '';
    }

    /**
     * Get the HTML code that replacee the user preferences tags like
     * ##quickper##page
     * 
     * @param string $tag
     * @param string $entry
     * @param array $options
     * @return string
     */
    protected function get_userpref_replacements($tag, $entry = null, array $options = null) {
        $view = $this->_view;
        $filter = $view->get_filter();
        
        if (!$view->is_forcing_filter() and (!$filter->id or !empty($options['entriescount']))) {
            switch ($tag) {
                case '##quickperpage##':
                    return $this->print_quick_perpage($filter, true);
                case '##advancedfilter##':
                    return $this->print_advanced_filter($filter, true);
            }
        }
        if ($tag == '##quicksearch##') {
            return $this->print_quick_search($filter, true);
        }
        return '';
    }

    /**
     * Get the HTML code that replaces action tags like ##edit##
     * 
     * @param string $tag
     * @param string $entry
     * @param array $options
     * @return string
     */
    protected function get_action_replacements($tag, $entry = null, array $options = null) {
        global $CFG, $OUTPUT;
        
        $replacement = '';
        
        $view = $this->_view;
        $df = $view->get_df();
        $filter = $view->get_filter();
        $baseurl = new moodle_url($view->get_baseurl());
        $baseurl->param('sesskey', sesskey());
        $baseurl->param('sourceview', $this->_view->id());
        
        $showentryactions = (!empty($options['showentryactions']) or
                 has_capability('mod/datalynx:manageentries', $df->context));
        // TODO: move to a view attribute so as to call only once
        // Can this user registered or anonymous add entries
        $usercanaddentries = $view->get_df()->user_can_manage_entry();
        
        switch ($tag) {
            case '##addnewentry##':
            case '##addnewentries##':
                if (!empty($options['hidenewentry']) or !$usercanaddentries) {
                    break;
                }
                
                if ($tag == '##addnewentry##') {
                    if (!empty($df->data->singleedit)) {
                        $baseurl->param('view', $df->data->singleedit);
                    }
                    $baseurl->param('new', 1);
                    $label = html_writer::tag('span', get_string('entryaddnew', 'datalynx'));
                    $replacement = html_writer::link($baseurl, $label, 
                            array('class' => 'addnewentry'
                            ));
                } else {
                    $range = range(1, 20);
                    $options = array_combine($range, $range);
                    $select = new single_select(new moodle_url($baseurl), 'new', $options, null, 
                            array(0 => get_string('dots', 'datalynx')
                            ), 'newentries_jump');
                    $select->set_label(get_string('entryaddmultinew', 'datalynx') . '&nbsp;');
                    $replacement = $OUTPUT->render($select);
                }
                
                break;
            
            case '##multiduplicate##':
                $replacement = html_writer::empty_tag('input', 
                        array('type' => 'button', 'name' => 'multiduplicate', 
                            'value' => get_string('multiduplicate', 'datalynx'), 
                            'onclick' => 'bulk_action(\'entry\'&#44; \'' . $baseurl->out(false) .
                                     '\'&#44; \'duplicate\')'
                        ));
                break;
            
            case '##multiduplicate:icon##':
                if ($showentryactions) {
                    $replacement = html_writer::tag('button', 
                            $OUTPUT->pix_icon('t/copy', get_string('multiduplicate', 'datalynx')), 
                            array('type' => 'button', 'name' => 'multiduplicate', 
                                'onclick' => 'bulk_action(\'entry\'&#44; \'' . $baseurl->out(false) .
                                         '\'&#44; \'duplicate\')'
                            ));
                }
                break;
            
            case '##multiedit##':
                if ($showentryactions) {
                    $replacement = html_writer::empty_tag('input', 
                            array('type' => 'button', 'name' => 'multiedit', 
                                'value' => get_string('multiedit', 'datalynx'), 
                                'onclick' => 'bulk_action(\'entry\'&#44; \'' . $baseurl->out(false) .
                                         '\'&#44; \'editentries\')'
                            ));
                }
                break;
            
            case '##multiedit:icon##':
                if ($showentryactions) {
                    $replacement = html_writer::tag('button', 
                            $OUTPUT->pix_icon('t/edit', get_string('multiedit', 'datalynx')), 
                            array('type' => 'button', 'name' => 'multiedit', 
                                'onclick' => 'bulk_action(\'entry\'&#44; \'' . $baseurl->out(false) .
                                         '\'&#44; \'editentries\')'
                            ));
                }
                break;
            
            case '##multidelete##':
                if ($showentryactions) {
                    $replacement = html_writer::empty_tag('input', 
                            array('type' => 'button', 'name' => 'multidelete', 
                                'value' => get_string('multidelete', 'datalynx'), 
                                'onclick' => 'bulk_action(\'entry\'&#44; \'' . $baseurl->out(false) .
                                         '\'&#44; \'delete\')'
                            ));
                }
                break;
            
            case '##multidelete:icon##':
                if ($showentryactions) {
                    $replacement = html_writer::tag('button', 
                            $OUTPUT->pix_icon('t/delete', get_string('multidelete', 'datalynx')), 
                            array('type' => 'button', 'name' => 'multidelete', 
                                'onclick' => 'bulk_action(\'entry\'&#44; \'' . $baseurl->out(false) .
                                         '\'&#44; \'delete\')'
                            ));
                }
                break;
            
            case '##multiapprove##':
            case '##multiapprove:icon##':
                if ($df->data->approval and has_capability('mod/datalynx:approve', $df->context)) {
                    if ($tag == '##multiapprove##') {
                        $replacement = html_writer::empty_tag('input', 
                                array('type' => 'button', 'name' => 'multiapprove', 
                                    'value' => get_string('multiapprove', 'datalynx'), 
                                    'onclick' => 'bulk_action(\'entry\'&#44; \'' .
                                             $baseurl->out(false) . '\'&#44; \'approve\')'
                                ));
                    } else {
                        $replacement = html_writer::tag('button', 
                                $OUTPUT->pix_icon('i/grade_correct', 
                                        get_string('multiapprove', 'datalynx')), 
                                array('type' => 'button', 'name' => 'multiapprove', 
                                    'onclick' => 'bulk_action(\'entry\'&#44; \'' .
                                             $baseurl->out(false) . '\'&#44; \'approve\')'
                                ));
                    }
                }
                break;
            
            case '##multiexport##':
                $buttonval = get_string('multiexport', 'datalynx');
            case '##multiexport:icon##':
                $buttonval = !isset($buttonval) ? $OUTPUT->pix_icon('t/portfolioadd', 
                        get_string('multiexport', 'datalynx')) : $buttonval;
                
                if (!empty($CFG->enableportfolios)) {
                    if (!empty($format)) {
                        $baseurl->param('format', $format);
                    }
                    // list(,$ext,) = explode(':', $tag);
                    $replacement = html_writer::tag('button', $buttonval, 
                            array('type' => 'button', 'name' => 'multiexport', 
                                'onclick' => 'bulk_action(\'entry\'&#44; \'' . $baseurl->out(false) .
                                         '\'&#44; \'export\'&#44;-1)'
                            ));
                }
                break;
            
            case '##selectallnone##':
                $replacement = html_writer::checkbox(null, null, false, null, 
                        array('onclick' => 'select_allnone(\'entry\'&#44;this.checked)'
                        ));
                
                break;
        }
        
        return $replacement;
    }

    /**
     * Get HTML that replaces the tag ##pagingbar##
     * 
     * @param string $tag
     * @param string $entry
     * @param array $options
     * @return Ambigous <string, paging_bar>
     */
    protected function get_paging_replacements($tag, $entry = null, array $options = null) {
        global $OUTPUT;
        
        $replacement = '';
        
        $view = $this->_view;
        $df = $view->get_df();
        $filter = $view->get_filter();
        $baseurl = $view->get_baseurl();
        
        // typical entry 'more' request. If not single view (1 per page) show return to list instead
        // of paging bar
        if (!empty($filter->eids)) {
            $url = new moodle_url($baseurl);
            // Add page
            if ($filter->page) {
                $url->param('page', $filter->page);
            }
            // Change view to caller
            if ($ret = optional_param('ret', 0, PARAM_INT)) {
                $url->param('view', $ret);
            }
            // Remove eids so that we return to list
            $url->remove_params('eids');
            // Make the link
            $pagingbar = html_writer::link($url->out(false), 
                    get_string('viewreturntolist', 'datalynx'));
            
            // typical groupby, one group per page case. show paging bar as per number of groups
        } else if (isset($filter->pagenum)) {
            $pagingbar = new paging_bar($filter->pagenum, $filter->page, 1, $baseurl . '&amp;', 
                    'page', '', true);
            // standard paging bar case
        } else if (!empty($filter->perpage) and !empty($options['entriescount']) and
                 !empty($options['entriesfiltercount']) and
                 $options['entriescount'] != $options['entriesfiltercount']) {
            
            $pagingbar = new paging_bar($options['entriesfiltercount'], $filter->page, 
                    $filter->perpage, $baseurl . '&amp;', 'page', '', true);
        } else {
            $pagingbar = '';
        }
        
        if ($pagingbar instanceof paging_bar) {
            $replacement = $OUTPUT->render($pagingbar);
        } else {
            $replacement = $pagingbar;
        }
        return $replacement;
    }

    /**
     * If viewname is not specified, return the URL of the current view
     * else return the URL of the $viewname
     *
     * @param string $viewname
     * @return string base URL of the view, empty string if view does not exist
     */
    protected function get_viewurl_replacement($viewname = null) {
        $view = $this->_view;
        
        // Return this view's url
        if ($viewname === null) {
            return $view->get_baseurl()->out(false);
        }
        
        $df = $this->_view->get_df();
        static $views = null;
        if ($views === null) {
            $views = array();
            if ($theviews = $df->get_views()) {
                foreach ($theviews as $theview) {
                    $views[$theview->name()] = $theview;
                }
            }
        }
        
        if (!empty($views[$viewname])) {
            return $views[$viewname]->get_baseurl()->out(false);
        }
        return '';
    }

    /**
     * return HTML of the content of a given view $viewname
     *
     * @param string $viewname
     * @return string
     */
    protected function get_viewcontent_replacement($viewname = null) {
        $df = $this->_view->get_df();
        static $views = null;
        if ($views === null) {
            $views = array();
            if ($theviews = $df->get_views()) {
                foreach ($theviews as $theview) {
                    $views[$theview->name()] = $theview;
                }
            }
        }
        
        if (!empty($views[$viewname])) {
            // Cannot display current view or else infinite loop
            if ($views[$viewname]->id() == $this->_view->id()) {
                return '';
            }
            
            $views[$viewname]->set_content();
            return $views[$viewname]->display(array('tohtml' => true
            ));
        }
        return '';
    }

    /**
     * Get all tags in a single array
     * 
     * @return array of tags
     */
    protected function patterns() {
        $patterns = array_merge($this->info_patterns(), $this->ref_patterns(), 
                $this->userpref_patterns(), $this->action_patterns(), $this->paging_patterns(), 
                $this->bulkedit_patterns());
        return $patterns;
    }

    /**
     * Get tags for total number of entries and entries per page
     * and the localised "entries" string
     * 
     * @return array of arrays 
     */
    protected function info_patterns() {
        $cat = get_string('entries', 'datalynx');
        $patterns = array('##numentriestotal##' => array(true, $cat
        ), '##numentriesdisplayed##' => array(true, $cat
        )
        );
        return $patterns;
    }

    /**
     * Get view references with localised string
     * Only views visible to current user are used
     * 
     * @return array associative array sith pattern string as value and key
     */
    protected function ref_patterns() {
        $cat = get_string('reference', 'datalynx');
        $patterns = array('##viewurl##' => array(true, $cat
        ), '##viewsmenu##' => array(true, $cat
        ), '##filtersmenu##' => array(true, $cat
        )
        );
        
        $df = $this->_view->get_df();
        
        static $views = null;
        if ($views === null) {
            $views = $df->get_views_menu();
        }
        
        if ($views) {
            foreach ($views as $viewname) {
                $patterns["##viewurl:$viewname##"] = array(false
                );
                $patterns["##viewcontent:$viewname##"] = array(false
                );
            }
        }
        return $patterns;
    }

    /**
     * Get filter and search tags with localised string
     * 
     * @return array multidimensional
     */
    protected function userpref_patterns() {
        $cat = get_string('userpref', 'datalynx');
        $patterns = array('##quicksearch##' => array(true, $cat
        ), '##quickperpage##' => array(true, $cat
        ), '##advancedfilter##' => array(true, $cat
        )
        );
        return $patterns;
    }

    /**
     * Get actions tags with localised string
     * 
     * @return array multidimensional
     */
    protected function action_patterns() {
        $cat = get_string('generalactions', 'datalynx');
        $patterns = array('##addnewentry##' => array(true, $cat
        ), '##addnewentries##' => array(true, $cat
        ), '##selectallnone##' => array(true, $cat
        ), '##multiduplicate##' => array(true, $cat
        ), '##multiduplicate:icon##' => array(true, $cat
        ), '##multiedit##' => array(true, $cat
        ), '##multiedit:icon##' => array(true, $cat
        ), '##multidelete##' => array(true, $cat
        ), '##multidelete:icon##' => array(true, $cat
        ), '##multiapprove##' => array(true, $cat
        ), '##multiapprove:icon##' => array(true, $cat
        ), '##multiexport##' => array(true, $cat
        ), '##multiexport:icon##' => array(true, $cat
        ), '##multiimport##' => array(true, $cat
        ), '##multiimporty:icon##' => array(true, $cat
        )
        );
        return $patterns;
    }

    /**
     * Get pagingbar tag
     * 
     * @return array multidimensional
     */
    protected function paging_patterns() {
        $cat = get_string('pagingbar', 'datalynx');
        $patterns = array('##pagingbar##' => array(true, $cat
        )
        );
        return $patterns;
    }

    /**
     * viewlink and viewsesslink tags with localised string
     * TODO Currently not included in the menu
     * 
     * @return multitype:multitype:boolean unknown  multitype:boolean string
     */
    protected function regexp_patterns() {
        $df = $this->_view->get_df();
        
        $patterns = array();
        // Get list of views
        if ($views = $df->get_views_menu()) {
            // View link
            $cat = get_string('reference', 'datalynx');
            foreach ($views as $viewname) {
                $viewname = preg_quote($viewname, '/');
                $patterns["#{{viewlink:$viewname;[^;]*;[^;]*;}}#"] = array(true, $cat
                );
                $patterns["#{{viewsesslink:$viewname;[^;]*;[^;]*;}}#"] = array(true, $cat
                );
            }
        }
        
        return $patterns;
    }

    /**
     * Get bulkedit fieldname specific bulkedit tags with localised string
     * 
     * @return array multitype:multitype:boolean string
     */
    protected function bulkedit_patterns() {
        $df = $this->_view->get_df();
        
        $patterns = array();
        
        $fields = $df->get_fields(null, true);
        $cat = get_string('reference', 'datalynx');
        foreach ($fields as $fieldname) {
            $fieldname = preg_quote($fieldname, '/');
            $patterns["%%{$fieldname}:bulkedit%%"] = array(true, $cat
            );
        }
        return $patterns;
    }

    /**
     * Find out if pattern contains a viewname or fieldname
     * 
     * @param string $pattern
     * @return boolean true if $pattern is a viewname or fieldame
     */
    public function is_regexp_pattern($pattern) {
        $df = $this->_view->get_df();
        
        static $views = null;
        if ($views === null) {
            $views = $df->get_views_menu();
        }
        
        if ($views) {
            foreach ($views as $viewname) {
                if (strpos($pattern, "#{{viewlink:$viewname;") === 0) {
                    return true;
                }
                if (strpos($pattern, "#{{viewsesslink:$viewname;") === 0) {
                    return true;
                }
            }
        }
        
        static $fields = null;
        if ($fields === null) {
            $fields = $df->get_fields(null, true);
        }
        
        foreach ($fields as $fieldname) {
            if (strpos($pattern, "%%{$fieldname}:bulkedit%%") === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Echo or return views menu HTML (Dropdown of available views for the user)
     * 
     * @param array $options
     * @param string $return
     * @return string
     */
    protected function print_views_menu($options, $return = false) {
        global $OUTPUT;
        
        $view = $this->_view;
        $df = $view->get_df();
        $filter = $view->get_filter();
        $baseurl = $view->get_baseurl();
        
        $viewjump = '';
        
        if ($menuviews = $df->get_views_menu() and count($menuviews) > 1) {
            
            // Display the view form jump list
            $baseurl = $baseurl->out_omit_querystring();
            $baseurlparams = array('d' => $df->id(), 'sesskey' => sesskey()
            );
            $viewselect = new single_select(new moodle_url($baseurl, $baseurlparams), 'view', 
                    $menuviews, $view->id(), array('' => 'choosedots'
                    ), 'viewbrowse_jump');
            $viewselect->set_label(get_string('viewcurrent', 'datalynx') . '&nbsp;');
            $viewjump = $OUTPUT->render($viewselect);
        }
        
        if ($return) {
            return $viewjump;
        } else {
            echo $viewjump;
        }
    }

    /**
     * Echo or return filter menu HTML (Dropdown of available filters for the user)
     * @param array $options
     * @param string $return
     * @return string
     */
    protected function print_filters_menu($options, $return = false) {
        global $OUTPUT;
        
        $view = $this->_view;
        $df = $view->get_df();
        $filter = $view->get_filter();
        $baseurl = $view->get_baseurl();
        
        $filterjump = '';
        
        if (!$view->is_forcing_filter() and ($filter->id or !empty($options['entriescount']))) {
            $fm = $df->get_filter_manager();
            if (!$menufilters = $fm->get_filters(null, true)) {
                $menufilters = array();
            }
            if ($userfilters = $fm->get_user_filters_menu($view->id())) {
                $menufilters[] = array(get_string('filtermy', 'datalynx') => $userfilters
                );
            }
            
            $baseurl = $baseurl->out_omit_querystring();
            $baseurlparams = array('d' => $df->id(), 'sesskey' => sesskey(), 'view' => $view->id()
            );
            // if ($filter->id) {
            // $menufilters[0] = get_string('filtercancel', 'datalynx');
            // }
            
            // Display the filter form jump list
            $filterselect = new single_select(new moodle_url($baseurl, $baseurlparams), 'filter', 
                    $menufilters, $filter->id, array('' => 'choosedots'
                    ), 'filterbrowse_jump');
            $filterselect->set_label(get_string('filtercurrent', 'datalynx') . '&nbsp;');
            $filterjump = $OUTPUT->render($filterselect);
        }
        
        if ($return) {
            return $filterjump;
        } else {
            echo $filterjump;
        }
    }

    /**
     * Echo or return quicksearch HTML (input field for text to search for)
     * 
     * @param array $options
     * @param string $return
     * @return string
     */
    protected function print_quick_search($options, $return = false) {
        $view = $this->_view;
        $df = $view->get_df();
        $filter = $view->get_filter();
        $baseurl = $view->get_baseurl();
        
        $quicksearchjump = '';
        
        $baseurl = $baseurl->out_omit_querystring();
        $baseurlparams = array('d' => $df->id(), 'sesskey' => sesskey(), 'view' => $view->id(), 
            'filter' => $filter->id
        ); // datalynx_filter_manager::USER_FILTER_SET);
        /*
         * if ($filter->id < 0 and $filter->search) {
         * $searchvalue = $filter->search;
         * } else {
         * $searchvalue = '';
         * }
         */
        $searchvalue = $filter->search;
        // Display the quick search form
        $label = html_writer::label(get_string('search'), "usersearch");
        $inputfield = html_writer::empty_tag('input', 
                array('type' => 'text', 'name' => 'usersearch', 'value' => $searchvalue, 
                    'size' => 20
                ));
        
        $button = '';
        // html_writer::empty_tag('input', array('type' => 'submit', 'value' =>
        // get_string('submit')));
        
        $formparams = '';
        foreach ($baseurlparams as $var => $val) {
            $formparams .= html_writer::empty_tag('input', 
                    array('type' => 'hidden', 'name' => $var, 'value' => $val
                    ));
        }
        
        $attributes = array('method' => 'post', 'action' => new moodle_url($baseurl)
        );
        
        $qsform = html_writer::tag('form', "$formparams&nbsp;$label&nbsp;$inputfield&nbsp;$button", 
                $attributes);
        
        // and finally one more wrapper with class
        $quicksearchjump = html_writer::tag('div', $qsform, 
                array('class' => 'singleselect'
                ));
        
        if ($return) {
            return $quicksearchjump;
        } else {
            echo $quicksearchjump;
        }
    }

    /**
     * Echo or return the HTML for entries per page dropdown menu
     * 
     * @param unknown $filter
     * @param string $return
     * @return string
     */
    protected function print_quick_perpage($filter, $return = false) {
        global $OUTPUT;
        
        $view = $this->_view;
        $df = $view->get_df();
        $filter = $view->get_filter();
        $baseurl = $view->get_baseurl();
        
        $perpagejump = '';
        
        $baseurl = $baseurl->out_omit_querystring();
        $baseurlparams = array('d' => $df->id(), 'sesskey' => sesskey(), 'view' => $view->id(), 
            'filter' => datalynx_filter_manager::USER_FILTER_SET
        );
        
        if ($filter->id < 0 and $filter->perpage) {
            $perpagevalue = $filter->perpage;
        } else {
            $perpagevalue = 0;
        }
        
        $perpage = array(1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 
            10 => 10, 15 => 15, 20 => 20, 30 => 30, 40 => 40, 50 => 50, 100 => 100, 200 => 200, 
            300 => 300, 400 => 400, 500 => 500, 1000 => 1000
        );
        
        // Display the view form jump list
        $select = new single_select(new moodle_url($baseurl, $baseurlparams), 'uperpage', $perpage, 
                $perpagevalue, array('' => 'choosedots'
                ), 'perpage_jump');
        $select->set_label(get_string('filterperpage', 'datalynx') . '&nbsp;');
        $perpagejump = $OUTPUT->render($select);
        
        if ($return) {
            return $perpagejump;
        } else {
            echo $perpagejump;
        }
    }

    /**
     * Echo or return advanced filter form HTML
     * 
     * @param unknown $filter
     * @param string $return
     * @return string
     */
    protected function print_advanced_filter($filter, $return = false) {
        global $OUTPUT;
        
        $view = $this->_view;
        $df = $view->get_df();
        $filter = $view->get_filter();
        $baseurl = $view->get_baseurl();
        
        $fm = $df->get_filter_manager();
        $filterform = $fm->get_advanced_filter_form($filter, $view);
        
        if ($return) {
            return html_writer::tag('div', $filterform->html(), 
                    array('class' => 'mdl-left'
                    ));
        } else {
            html_writer::start_tag('div', array('class' => 'mdl-left'
            ));
            $filterform->display();
            html_writer::end_tag('div');
        }
    }
}
