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
 * @package datalynxview
 * @copyright 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * A base class for datalynx views
 * (see view/<view type>/view_class.php)
 */
abstract class datalynxview_base {

    const ADD_NEW_ENTRY = -1;

    protected $type = 'unknown';      // Subclasses must override the type with their name

    public $view = NULL;            // The view object itself, if we know it

    protected $_df = NULL;           // The datalynx object that this view belongs to
    /**
     * @var datalynx_filter
     */
    protected $_filter = null;
    protected $_patterns = null;

    protected $_editors = array('section');
    protected $_vieweditors = array('section');
    protected $_entries = null;

    protected $_tags = array();
    protected $_baseurl = '';

    protected $_notifications = array('good' => array(), 'bad' => array());

    protected $_editentries = 0;
    protected $_entriesform = null;
    protected $_display_definition = array();
    protected $_returntoentriesform = null;
    protected $_redirect = 0;

    /**
     * Constructor
     * View or datalynx or both, each can be id or object
     */
    public function __construct($df = 0, $view = 0, $filteroptions = true) {
        global $DB, $CFG;

        if (empty($df)) {
            throw new coding_exception('Datalynx id or object must be passed to field constructor.');
        // datalynx object
        } else if ($df instanceof datalynx) {
            $this->_df = $df;
        // datalynx id
        } else {
            $this->_df = new datalynx($df);
        }

        // set existing view
        if (!empty($view)) {
            if (is_object($view)) {
                $this->view = $view;  // Programmer knows what they are doing, we hope
            } else if (!$this->view = $DB->get_record('datalynx_views', array('id' => $view))) {
                throw new moodle_exception('invalidview', 'datalynx', null, null, $view);
            }
        // set defaults for new view
        } else {
            $this->view = new stdClass();
            $this->view->id = 0;
            $this->view->type = $this->type;
            $this->view->dataid = $this->_df->id();
            $this->view->name = get_string('pluginname', "datalynxview_{$this->type}");
            $this->view->description = '';
            $this->view->visible = 7;
            $this->view->filter = 0;
            $this->view->perpage = 0;
            $this->view->groupby = '';
            $this->view->param10 = 0;
        }

        $this->_redirect = $this->view->param10;

        // set editors and patterns
        $this->set__editors();
        $this->set__patterns();

        $this->set_filter($filteroptions);

        // base url params
        $baseurlparams = array();
        $baseurlparams['d'] = $this->_df->id();
        $baseurlparams['view'] = $this->id();
        $baseurlparams['filter'] = $this->_filter->id;
        if (!empty($eids)) {
            $baseurlparams['eids'] = $eids;
        }
        if ($this->_filter->page) {
            $baseurlparams['page'] = $this->_filter->page;
        }
        if ($this->_df->currentgroup) {
            $baseurlparams['currentgroup'] = $this->_df->currentgroup;
        }

        $this->_baseurl = new moodle_url("/mod/datalynx/{$this->_df->pagefile()}.php", $baseurlparams);

        $this->set_groupby_per_page();

        require_once("$CFG->dirroot/mod/datalynx/entries_class.php");
        $this->_entries = new datalynx_entries($this->_df, $this->_filter);
    }

    /**
     * Set view
     */
    protected function set_view($data) {
        $this->view->name = $data->name;
        $this->view->description = !empty($data->description) ? $data->description : '';

        $this->view->visible = !empty($data->visible) ? $data->visible : 7;
        $this->view->perpage = !empty($data->perpage) ? $data->perpage : 0;
        $this->view->groupby = !empty($data->groupby) ? $data->groupby : '';
        $this->view->filter = !empty($data->filter) ? $data->filter : 0;

        for ($i = 1; $i <= 10; $i++) {
            if (isset($data->{"param$i"})) {
                $this->view->{"param$i"} = $data->{"param$i"};
            }
        }

        $this->set__editors($data);
        $this->set__patterns($data);
    }

    public function get_default_view_settings() {
        return (object) array(
            'description' => '',
            'visible' => 7,
            'perpage' => 0,
            'groupby' => '',
            'filter' => 0,
        );
    }

    /**
     *
     */
    protected function set__editors($data = null) {
        $text = '';
        foreach ($this->_editors as $editor) { // new view or from DB so add editor fields
            if (is_null($data)) {
                if (!empty($this->view->$editor)) {
                    $editordata = $this->view->$editor;
                    if (strpos($editordata, 'ft:') === 0) { // legacy support
                        $text = substr($editordata, 11);
                    } else {
                        $text = $editordata;
                    }
                }
                $this->view->{"e{$editor}"} = $text;
            } else { // view from form or editor areas updated
                $this->view->$editor = null;
                if (isset($data->{"e{$editor}"})) {
                    $text = isset($data->{"e{$editor}"}) ? $data->{"e{$editor}"} : '';
                } else if ($currenteditor = $data->{"e{$editor}_editor"}) {
                    $text = !empty($currenteditor['text']) ? $currenteditor['text'] : '';
                }
                $this->view->$editor = $text;
            }
        }
    }

    /**
     *
     */
    protected function set__patterns() {
        $patterncache = cache::make('mod_datalynx', 'patterns');
        $cachedpatterns = $patterncache->get($this->view->id);

        if (!$cachedpatterns) {
            $cachedpatterns = array('view' => array(), 'field' => array());
            $text = '';
            foreach ($this->_editors as $editor) {
                $text .= isset($this->view->$editor) ? $this->view->$editor : '';
            }

            if (trim($text)) {
                // This view patterns
                $cachedpatterns['view'] = $this->patterns()->search($text);

                // Field patterns
                if ($fields = $this->_df->get_fields()) {
                    foreach ($fields as $fieldid => $field) {
                        $cachedpatterns['field'][$fieldid] = $field->renderer()->search($text);
                    }
                }

                $patterncache->set($this->view->id, $cachedpatterns);
            }
        }

        $this->_tags = $cachedpatterns;
    }

    /**
     * Sets up filter options based on parameters from URL, filter assigned to this particular view, and default settings.
     * @param bool $filteroptions
     * @param bool $ignoreurl true, if URL filter options should be ignored
     */
    public function set_filter($filteroptions = true, $ignoreurl = false) {
        $fm = $this->_df->get_filter_manager($this);

        if (!$ignoreurl) {
            $urloptions = $filteroptions ? $fm::get_filter_options_from_url() : array();
        } else {
            $urloptions = array();
        }

        if (is_array($filteroptions)) {
            $urloptions = array_merge($urloptions, $filteroptions);
        }

        $fid = !empty($urloptions['filterid']) ? $urloptions['filterid'] : 0;
        $afilter = !empty($urloptions['afilter']) ? $urloptions['afilter'] : 0;
        $eids = !empty($urloptions['eids']) ? $urloptions['eids'] : null;
        $users = !empty($urloptions['users']) ? $urloptions['users'] : null;
        $groups = !empty($urloptions['groups']) ? $urloptions['groups'] : null;
        $page = !empty($urloptions['page']) ? $urloptions['page'] : 0;

        $perpage = !empty($urloptions['perpage']) ? $urloptions['perpage'] : 0;
        $groupby = !empty($urloptions['groupby']) ? $urloptions['groupby'] : 0;

        $csort = !empty($urloptions['customsort']) ? $urloptions['customsort'] : null;
        $csearch = !empty($urloptions['customsearch']) ? $urloptions['customsearch'] : null;

        $usersearch = !empty($urloptions['usersearch']) ? $urloptions['usersearch'] : '';

        $filterid = $fid ? $fid : ($this->view->filter ? $this->view->filter : 0);

        $this->_filter = $fm->get_filter_from_id($filterid, array('view' => $this, 'advanced' => $afilter));

        // set specific entry id
        $this->_filter->eids = $eids;
        // set specific user id
        if ($users) {
            $this->_filter->users = is_array($users) ? $users : explode(',', $users);
        }
        // set specific entry id, if requested
        if ($groups) {
            $this->_filter->groups = is_array($groups) ? $groups : explode(',', $groups);
        }

        $this->_filter->perpage = $perpage ? $perpage : ($this->_filter->perpage ? $this->_filter->perpage : 50);

        $this->_filter->groupby = $groupby ? $groupby : $this->_filter->groupby;

        $this->_filter->search = $usersearch ? $usersearch : $this->_filter->search;


        // add page
        $this->_filter->page = $page ? $page : 0;
        // content fields
        $this->_filter->contentfields = array_keys($this->get__patterns('field'));

        // Append custom sort options
        if ($csort) {
            $this->_filter->append_sort_options($csort);
        }
        // Append custom search options
        if ($csearch) {
            $this->_filter->append_search_options($csearch);
        }
    }

    ////////////////////////////////////
    // VIEW TYPE
    ////////////////////////////////////

    /**
     * Insert a new view into the database
     * $this->view is assumed set
     */
    public function add($data) {
        global $DB, $OUTPUT;

        $this->set_view($data);

        if (!$this->view->id = $DB->insert_record('datalynx_views', $this->view)) {
            echo $OUTPUT->notification('Insertion of new view failed!');
            return false;
        }

        return $this->view->id;
    }

    /**
     * Update a view in the database
     * $this->view is assumed set
     */
    public function update($data = null) {
        global $DB, $OUTPUT;

        if ($data) {
            $patterncache = cache::make('mod_datalynx', 'patterns');
            $patterncache->delete($this->view->id, true);
            $data = $this->from_form($data);
            $this->set_view($data);
        }

        if (!$DB->update_record('datalynx_views', $this->view)) {
            echo $OUTPUT->notification('updating view failed!');
            return false;
        }

        return true;
    }

    /**
     * Delete a view from the database
     */
    public function delete() {
        global $DB;

        if (!empty($this->view->id)) {
            $fs = get_file_storage();
            foreach ($this->_editors as $key => $editorname) {
                $fs->delete_area_files($this->_df->context->id, 'mod_datalynx', "view$editorname", $this->id(). $key);
            }
            return $DB->delete_records('datalynx_views', array('id' => $this->view->id));
        }
        return true;
    }

    /**
     *
     */
    public function get_form() {
        global $CFG;

        $formclass = 'datalynxview_'. $this->type. '_form';
        $formparams = array(
            'd' => $this->_df->id(),
            'vedit' => $this->id(),
            'type' => $this->type
        );
        $actionurl = new moodle_url('/mod/datalynx/view/view_edit.php', $formparams);

        require_once($CFG->dirroot. '/mod/datalynx/view/'. $this->type. '/view_form.php');
        return new $formclass($this, $actionurl);
    }

    /**
     * prepare view data for form
     */
    public function to_form($data = null) {
        $data = $data ? $data : $this->view;
        $data = $this->prepare_view_editors($data);
        return $data;
    }


    /**
     * prepare view data for form
     */
    public function from_form($data) {
        $data = $this->update_view_editors($data);
        return $data;
    }

    /**
     * Prepare view editors for form
     */
    public function prepare_view_editors($data) {
        $editors = $this->editors();

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data->{$key} = str_replace('##moreurl##', '$$moreurl$$', $value);
            }
        }
        foreach ($editors as $editorname => $options) {
            $data->{"e{$editorname}format"} = FORMAT_HTML;
            $data->{"e{$editorname}trust"} = 1;
            $data = file_prepare_standard_editor($data, "e$editorname", $options, $this->_df->context, 'mod_datalynx', "view$editorname", $this->view->id);
        }

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data->{$key} = str_replace('$$moreurl$$', '##moreurl##', $value);
            } else if (is_array($value)) {
                foreach ($value as $subkey => $subvalue) {
                    if (is_string($subvalue)) {
                        $data->{$key}[$subkey] = str_replace('$$moreurl$$', '##moreurl##', $subvalue);
                    }
                }
            }
        }
        return $data;
    }

    /**
     * Update view editors from form
     */
    public function update_view_editors($data) {
        $format = FORMAT_HTML;
        $trust = 1;
        $editors = $this->editors();
        if ($editors && $this->view->id) {
            foreach ($editors as $editorname => $options) {
                if (!(isset($data->{"e{$editorname}_editor"}) && is_array($data->{"e{$editorname}_editor"}))) {
                    $text = isset($data->{"e{$editorname}"}) ? $data->{"e{$editorname}"} : '';
                    $data->{"e{$editorname}_editor"} = array('format' => $format, 'trust' => $trust, 'text' => $text);
                }

                $data = file_postupdate_standard_editor($data, "e$editorname", $options, $this->_df->context, 'mod_datalynx', "view$editorname", $this->view->id);
            }
        }
        return $data;
    }

    /**
     * Subclass may need to override
     */
    public function replace_field_in_view($searchfieldname, $newfieldname) {
        $patterns = array('[['.$searchfieldname.']]','[['.$searchfieldname.'#id]]');
        if (!$newfieldname) {
            $replacements = '';
        } else {
            $replacements = array('[['.$newfieldname.']]','[['.$newfieldname.'#id]]');
        }

        foreach ($this->_editors as $editor) {
            $this->view->{"e$editor"} = str_ireplace($patterns, $replacements, $this->view->{"e$editor"});
        }
        $this->update($this->view);
    }

    /**
     * Returns the name/type of the view
     */
    public function name_exists($name, $viewid) {
        return $this->_df->name_exists('views', $name, $viewid);
    }

    /**
     * process any view specific actions
     */
    public function process_data() {

        // Process entries data
        $processed = $this->process_entries_data();
        if (is_array($processed)) {
            list($strnotify, $processedeids) = $processed;
        } else {
            list($strnotify, $processedeids) = array('', '');
        }
        
        if ($processedeids) {
           $this->_notifications['good']['entries'] = $strnotify;
        } else if ($strnotify) {
           $this->_notifications['bad']['entries'] = $strnotify;
        }

        // With one entry per page show the saved entry
        if ($processedeids and $this->_editentries and !$this->_returntoentriesform) {
            if ($this->_filter->perpage == 1) {
                $this->_filter->eids = $this->_editentries;
            }
            $this->_editentries = '';
        }

        return $processed;
    }

    /**
     *
     */
    public function set_content() {
        if ($this->_returntoentriesform) {
            return;
        }
        if ($this->_editentries >= 0 or $this->view->perpage != 1) {
            $this->_entries->set_content();
        }
    }

    /**
     *
     */
    public function display(array $options = array()) {
        global $OUTPUT;

        // set display options
        $new = optional_param('new', 0, PARAM_INT);
        $displaycontrols = isset($options['controls']) ? $options['controls'] : true;
        $showentryactions = isset($options['entryactions']) ? $options['entryactions'] : true;
        $notify = isset($options['notify']) ? $options['notify'] : true;
        $tohtml = isset($options['tohtml']) ? $options['tohtml'] : false;
        $pluginfileurl = isset($options['pluginfileurl']) ? $options['pluginfileurl'] : null;

        // build entries display definition
        $requiresmanageentries = $this->set__display_definition($options);

        // set view specific tags
        $viewoptions = array(
            'pluginfileurl' => $pluginfileurl,
            'entriescount' => $this->_entries->get_count(),
            'entriesfiltercount' => $this->_entries->get_count(true),
            'hidenewentry' => $this->user_is_editing() ? 1 : 0,
            'showentryactions' => $requiresmanageentries && $showentryactions,
        );

        $this->set_view_tags($viewoptions);

        $notifications = $notify ? $this->print_notifications() : '';

        if ($this->_returntoentriesform !== false) {
            if ($displaycontrols) {
                $output = $notifications . $this->process_calculations($this->view->esection);
            } else {
                $output = '##entries##';
            }
            $entryhtml = $this->display_entries($options);
            if ($entryhtml && ($this->_entries->get_count() || $new)) {
                $output = str_replace('##entries##', $entryhtml, $output);
            } else {
                $output = str_replace('##entries##', $this->display_no_entries(), $output);
            }
        } else {
            $redirectid = $this->_redirect ? $this->_redirect : $this->id();
            $url = new moodle_url($this->_baseurl, array('view' => $redirectid));
            $output = $notifications . $OUTPUT->continue_button($url);
        }

        $viewname = 'datalynxview-'. str_replace(' ', '_', $this->name());
        $output = html_writer::tag('div', $output, array('class' => $viewname));

        if ($tohtml) {
            return $output;
        } else {
            echo $output;
        }

        return '';
    }

    protected function print_notifications() {
        global $OUTPUT;
        $notifications = '';
        foreach ($this->_notifications['good'] as $notification) {
            $notifications = $OUTPUT->notification($notification, 'notifysuccess');
        }
        foreach ($this->_notifications['bad'] as $notification) {
            $notifications = $OUTPUT->notification($notification);
        }
        return $notifications;
    }

    protected function display_no_entries() {
        global $OUTPUT, $DB;

        if ($this->view->filter > 0) { // this view has a forced filter set
            $output = $OUTPUT->notification(get_string('noentries', 'datalynx'));
        } else if ($this->_filter->id) { // this view has a user filter set
            $output = $OUTPUT->notification(get_string('nomatchingentries', 'datalynx'));
            $url = new moodle_url($this->_baseurl, array('filter' => 0));
            $output .= str_replace(get_string('continue'),
                                   get_string('resetsettings', 'datalynx'),
                                   $OUTPUT->continue_button($url));
        } else if ($this->_filter->eids) { // this view displays only entries with chosen ids
            list($insql, $params) = $DB->get_in_or_equal($this->_filter->eids, SQL_PARAMS_NAMED);
            if (!$DB->record_exists_select('datalynx_entries', "id $insql", $params)) {
                $output = $OUTPUT->notification(get_string('nosuchentries', 'datalynx')) .
                          $OUTPUT->continue_button($this->_df->get_baseurl());
            } else {
                $output = $OUTPUT->notification(get_string('nopermission', 'datalynx')) .
                          $OUTPUT->continue_button($this->_df->get_baseurl());
            }

        } else { // there are no entries in this datalynx
            $output = $OUTPUT->notification(get_string('noentries', 'datalynx'));
        }
        return $output;
    }

    /**
     *
     */
    public function set_view_tags($options) {
        // rewrite plugin urls
        $pluginfileurl = !empty($options['pluginfileurl']) ? $options['pluginfileurl'] : null;
        foreach ($this->_editors as $editorname) {
            $editor = "e$editorname";

            // export with files should provide the file path
            if ($pluginfileurl) {
                $this->view->$editor = str_replace('@@PLUGINFILE@@/', $pluginfileurl, $this->view->$editor);
            } else {
                $this->view->$editor = file_rewrite_pluginfile_urls($this->view->$editor,
                    'pluginfile.php',
                    $this->_df->context->id,
                    'mod_datalynx',
                    "view$editorname",
                    $this->id());
            }
        }

        $tags = $this->_tags['view'];
        $replacements = $this->patterns()->get_replacements($tags, null, $options);
        foreach ($this->_vieweditors as $editor) {
            $text = $this->view->{"e$editor"};
            list($text, $map) = $this->mask_tags($text);
            $text = format_text($text, FORMAT_HTML, array('trusted' => 1, 'filter' => true));
            $this->view->{"e$editor"}  = $this->unmask_tags($text, $map);
            $this->view->{"e$editor"} = str_replace($tags, $replacements, $this->view->{"e$editor"});
        }
    }

    /**
     * Masks view and field tags so that they do not get auto-linked
     * @param string $text a string with masked tags
     * @return array an array containing data for unmasking
     */
    public function mask_tags($text) {
        $matches = array();
        $find = array();
        $replace = array();
        preg_match_all('/(?:(\[\[[^\]]+\]\])|(##[^#]+##)|(%%[^%]+%%)|(#\{\{[^\}#]+\}\}#))/', $text, $matches, PREG_PATTERN_ORDER);
        $map = $matches[0];
        foreach ($map as $index => $match) {
            $find[$index] = "/" . preg_quote($match, '/') . "/";
            $replace[$index] = "!!!!!{$index}!!!!!";
        }
        $text = preg_replace($find, $replace, $text);
        return array($text, $map);
    }

    /**
     * Reverses the tag masking
     * @param string  $text a string with masked tags
     * @param array   $map an array containing data for unmasking, obtained through mask_tags
     * @return string input string with tags restored
     */
    public function unmask_tags($text, $map) {
        $find = array();
        foreach (array_keys($map) as $index) {
            $find[$index] = "/!!!!!{$index}!!!!!/";
        }
        return preg_replace($find, $map, $text);
    }

    ////////////////////////////////////
    // HELPERS
    ////////////////////////////////////
    public function get_view_fields() {
        $viewfields = array();

        if (!empty($this->_tags['field'])) {
            $fields = $this->_df->get_fields();
            foreach (array_keys($this->_tags['field']) as $fieldid) {
                if (array_key_exists($fieldid, $fields)) {
                    $viewfields[$fieldid] = $fields[$fieldid];
                }
            }
        }

        return $viewfields;
    }

    /**
     *
     */
    public function field_tags() {
        $patterns = array();
        if ($fields = $this->_df->get_fields()) {
            foreach ($fields as $field) {
                if ($fieldpatterns = $field->renderer()->get_menu()) {
                    $patterns = array_merge_recursive($patterns, $fieldpatterns);
                }
            }
        }

        return $patterns;
    }

    /**
     *
     */
    public function character_tags() {
        $patterns = array('---' => array('---' => array()));
        $patterns['9'] = 'tab';
        $patterns['10'] = 'new line';

        return $patterns;
    }

    /**
     *
     */
    public abstract function generate_default_view();

    /**
     *
     */
    public function editors() {
        $editors = array();

        $options = array('trusttext' => true,
                         'noclean' => true,
                         'subdirs' => false,
                         'changeformat' => true,
                         'collapsed' => true,
                         'rows' => 20,
                         'style' => 'width:100%',
                         'maxfiles' => EDITOR_UNLIMITED_FILES,
                         'maxbytes' => $this->_df->course->maxbytes,
                         'context'=> $this->_df->context);

        foreach ($this->_editors as $editor) {
            $editors[$editor] = $options;
        }

        return $editors;
    }

    /**
     * @return datalynxview_patterns
     */
    public function patterns() {
        global $CFG;
        
        if (!$this->_patterns) {
            $viewtype = $this->type;
            
            if (file_exists("$CFG->dirroot/mod/datalynx/view/$viewtype/view_patterns.php")) {
                require_once("$CFG->dirroot/mod/datalynx/view/$viewtype/view_patterns.php");
                $patternsclass = "datalynxview_{$viewtype}_patterns";
            } else {
                require_once("$CFG->dirroot/mod/datalynx/view/view_patterns.php");
                $patternsclass = "datalynxview_patterns";
            }
            $this->_patterns = new $patternsclass($this);
        }
        return $this->_patterns;
    }

    /**
     *
     */
    public function get__patterns($set = null) {
        if (is_null($set)) {
            return $this->_tags;
        } else if ($set == 'view' or $set == 'field') {
            return $this->_tags[$set];
        } else {
            return false;
        }
    }

    /**
     *
     */
    public function get_pattern_fieldid($pattern) {
        if (!empty($this->_tags['field'])) {
            foreach ($this->_tags['field'] as $fieldid => $patterns) {
                if (in_array($pattern, $patterns)) {
                    return $fieldid;
                }
            }
        }
        return null;
    }

    /**
     *
     */
    public function get_embedded_files($set = null) {
        $files = array();
        $fs = get_file_storage();

        // view files
        if (empty($set) or $set == 'view') {
            foreach ($this->_editors as $key => $editorname) {
                // $editor = "e$editorname";
                $files = array_merge($files, $fs->get_area_files($this->_df->context->id,
                                                                'mod_datalynx',
                                                                'view',
                                                                $this->id(). $key,
                                                                'sortorder, itemid, filepath, filename',
                                                                false));
            }
        }

        // field files
        if (empty($set) or $set == 'field') {
            // find which fields actually display files/images in the view
            $fids = array();
            if (!empty($this->_tags['field'])) {
                $fields = $this->_df->get_fields();
                foreach ($this->_tags['field'] as $fieldid => $tags) {
                    if (array_intersect($tags, $fields[$fieldid]->renderer()->pluginfile_patterns())) {
                        $fids[] = $fieldid;
                    }
                }
            }
            // get the files from the entries
            if ($this->_entries and !empty($fids)) {  // set_content must have been called
                $files = array_merge($files, $this->_entries->get_embedded_files($fids));
            }
        }

        return $files;
    }

    /**
     *
     */
    protected abstract function apply_entry_group_layout($entriesset, $name = '');

    /**
     *
     */
    protected abstract function new_entry_definition($entryid = -1);

    /**
     * @param $fielddefinitions
     * @return array
     */
    protected function entry_definition($fielddefinitions) {
        $elements = array();

        // split the entry template to tags and html
        $tags = array_keys($fielddefinitions);
        $parts = $this->split_template_by_tags($tags, $this->view->eparam2);

        foreach ($parts as $part) {
            if (in_array($part, $tags)) {
                if ($def = $fielddefinitions[$part]) {
                    $elements[] = $def;
                }
            } else {
                $elements[] = array('html', $part);
            }
        }

        return $elements;
    }

    /**
     * @param array $patterns array of arrays of pattern replacement pairs
     */
    protected function split_template_by_tags($patterns, $subject) {
        foreach ($patterns as $id => $pattern) {
            $patterns[$id] = preg_quote($pattern, '/');
        }
        $delims = implode('|', $patterns);
        $elements = preg_split("/($delims)/", $subject, null, PREG_SPLIT_DELIM_CAPTURE);

        return $elements;
    }

    /**
     * FIXME: there was an error here at get_definitions call!
     */
    protected function get_groupby_value($entry) {
        $fields = $this->_df->get_fields();
        $fieldid = $this->_filter->groupby;
        $groupbyvalue = '';

        if (array_key_exists($fieldid, $this->_tags['field'])) {
            // first pattern
            $pattern = reset($this->_tags['field'][$fieldid]);
            if ($pattern !== false) {
                $field = $fields[$fieldid];

                if ($definition = $field->get_definitions(array($pattern), $entry, array())) {
                    $groupbyvalue = $definition[$pattern][1];
                }
            }
        }

        return $groupbyvalue;
    }

    /**
     * TODO: this needs to be moved to the filter itself!!!
     * Set sort and search criteria for grouping by
     */
    protected function set_groupby_per_page() {
        global $CFG;

        // Get the group by fieldid
        if (empty($this->_filter->groupby)) {
            return;
        }
        
        $fieldid = $this->_filter->groupby;
        // set sorting to begin with this field
        $insort = false;
        // TODO: asc order is arbitrary here and should be determined differently
        $sortdir = 0;
        $sortfields = array();
        if ($this->_filter->customsort) {
            $sortfields = unserialize($this->_filter->customsort);
            if ($insort = in_array($fieldid, array_keys($sortfields))) {
                $sortdir = $sortfields[$fieldid];
                unset($sortfields[$fieldid]);
            }
        }
        $sortfields = array($fieldid => $sortdir) + $sortfields;
        $this->_filter->customsort = serialize($sortfields);

        // Get the distinct content for the group by field
        $field = $this->_df->get_field_from_id($fieldid);
        if (!$groupbyvalues = $field->get_distinct_content($sortdir)) {
            return;
        }

        // Get the displayed subset according to page         
        $numvals = count($groupbyvalues);
        // Calc number of pages
        if ($this->_filter->perpage and $this->_filter->perpage < $numvals) {
            $this->_filter->pagenum = ceil($numvals / $this->_filter->perpage);
            $this->_filter->page = $this->_filter->page % $this->_filter->pagenum;
        } else {
            $this->_filter->perpage = 0;
            $this->_filter->pagenum = 0;
            $this->_filter->page = 0;
        }
        
        if ($this->_filter->perpage) {
            $offset = $this->_filter->page * $this->_filter->perpage;
            $vals = array_slice($groupbyvalues, $offset, $this->_filter->perpage);
        } else {
            $vals = $groupbyvalues;
        }

        $searchfields = array();
        if ($this->_filter->customsearch) {
            $searchfields = unserialize($this->_filter->customsearch);
        }

        $this->_filter->customsearch = serialize($searchfields);
    }

    /**
     *
     */
    protected function is_rating() {
        global $USER, $CFG;

        require_once("$CFG->dirroot/mod/datalynx/field/_rating/field_class.php");
        
        if (!$this->_df->data->rating or empty($this->_tags['field'][datalynxfield__rating::_RATING])) {
            return null;
        }
        
        $ratingfield = $this->_df->get_field_from_id(datalynxfield__rating::_RATING);
        $ratingoptions = new stdClass();
        $ratingoptions->context = $this->_df->context;
        $ratingoptions->component = 'mod_datalynx';
        $ratingoptions->ratingarea = 'entry';
        $ratingoptions->aggregate = $ratingfield->renderer()->get_aggregations($this->_tags['field'][datalynxfield__rating::_RATING]);
        $ratingoptions->scaleid = $this->get_scaleid('entry');
        $ratingoptions->userid = $USER->id;

        return $ratingoptions;
    }

    public function get_scaleid($area) {
        if ($area == 'entry' and $this->_df->data->rating) {
            return $this->_df->data->rating;
        } else if ($area == 'activity' and $this->_df->data->grade) {
            return $this->_df->data->grade;
        }
        return 0;
    }
        
    /**
     *
     */
    protected function is_grading() {
        if (!$this->_df->data->grade) {
            // grading is disabled in this datalynx
            return false;
        }

        if (empty($this->view->param1)) {
            // grading is not activated in this view
            return false;
        }
        
        return true;
    }
        
    /**
     *
     */
    protected function get_grading_options() {
        global $USER;

        if (!$this->_df->data->grade) {
            return null;
        }

        $gradingoptions = new object;
        $gradingoptions->context = $this->_df->context;
        $gradingoptions->component = 'mod_datalynx';
        $gradingoptions->ratingarea = 'activity';
        $gradingoptions->aggregate = array(RATING_AGGREGATE_MAXIMUM);
        $gradingoptions->scaleid = $this->_df->data->grade;
        $gradingoptions->userid = $USER->id;

        return $gradingoptions;
    }

    ////////////////////////////////////
    // VIEW ENTRIES
    ////////////////////////////////////
    /**
     *
     */
    public function display_entries(array $options = null) {
        if (!$this->user_is_editing()) {
            $html = $this->definition_to_html();
            if (isset($options['pluginfileurl'])) {
                $html = $this->replace_pluginfile_urls($html, $options['pluginfileurl']);
            }
        } else {
            // prepare options for form
            $entriesform = $this->get_entries_form();
            $html = $entriesform->html();
        }

        // Process calculations if any
        $html = $this->process_calculations($html);

        return $html;
    }

    /**
     *
     */
    public function definition_to_html() {
        $html = '';
        $elements = $this->get_entries_definition();
        foreach ($elements as $element) {
            list(, $content) = $element;
            $html .= $content;
        }

        return $html;
    }

    public function definition_to_form(HTML_QuickForm &$mform) {
        $elements = $this->get_entries_definition();
        foreach ($elements as $element) {
            if (!empty($element)) {
                list($type, $content) = $element;
                if ($type === 'html') {
                    $mform->addElement('html', $content);
                } else {
                    list($func, $params) = $content;
                    call_user_func_array($func, array_merge(array($mform),$params));
                }
            }
        }
    }

    /**
     * @return datalynxview_entries_form
     */
    protected function get_entries_form() {
        static $entriesform = null;

        if ($entriesform == null) {
            global $CFG, $DB;
            // prepare params for for content management
            $actionparams = array(
                'd' => $this->_df->id(),
                'view' => $this->id(),
                'page' => $this->_filter->page,
                'eids' => $this->_filter->eids,
                'update' => $this->_editentries,
                'sourceview' => optional_param('sourceview', null, PARAM_INT),
            );
            $actionurl = new moodle_url("/mod/datalynx/{$this->_df->pagefile()}.php", $actionparams);
            $custom_data = array(
                'view' => $this,
                'update' => $this->_editentries
            );

            $formclass = 'datalynxview_entries_form';
            require_once("$CFG->dirroot/mod/datalynx/view/view_entries_form.php");
            $entriesform = new $formclass($actionurl, $custom_data);
        }

        return $entriesform;
    }


    /**
     * @param array $entriesset entryid => array(entry, edit, editable)
     */
    // TODO THIS IS CRITICAL!!!
    public function get_entries_definition() {

        $display_definition = $this->_display_definition;
        $groupedelements = array();
        foreach ($display_definition as $name => $entriesset) {
            $definitions = array();
            if ($name == 'newentry') {
                foreach ($entriesset as $entryid => $unused) {
                    $definitions[$entryid] = $this->new_entry_definition($entryid);
                }
            } else {
                foreach ($entriesset as $entryid => $entryparams) {
                    list($entry, $editthisone, $managethisone) = $entryparams;
                    $options = array('edit' => $editthisone, 'manage' => $managethisone);
                    $fielddefinitions = $this->get_entry_tag_replacements($entry, $options); // <======================= HERE
                    $definitions[$entryid] = $this->entry_definition($fielddefinitions);
                }
            }
            $groupedelements[$name] = $this->apply_entry_group_layout($definitions, $name);
        }
        // Flatten the elements
        $elements = array();
        foreach ($groupedelements as $group) {
            $elements = array_merge($elements, $group);
        }

        return $elements;
    }

    /**
     *
     */
    protected function get_entry_tag_replacements($entry, $options) {
        $fields = $this->_df->get_fields();
        $entry->baseurl = $this->_baseurl;

        $definitions = array();
        foreach ($this->_tags['field'] as $fieldid => $patterns) {
            if (isset($fields[$fieldid])) {
                $field = $fields[$fieldid];
                if ($fielddefinitions = $field->get_definitions($patterns, $entry, $options)) { // <==================== HERE
                    $definitions = array_merge($definitions, $fielddefinitions);
                }
            }
        }
        $fielddefinitions = $definitions;

        // enables view tag replacement within the entry template
        if ($patterns = $this->patterns()->get_replacements($this->_tags['view'], null, $options)) {
            $viewdefinitions = array();
            foreach ($patterns as $tag => $pattern) {
                if ((strpos($tag, 'viewlink') !== 0 || strpos($tag, 'viewsesslink') !== 0) && !$options['edit']) {
                    foreach ($fielddefinitions as $fieldtag => $definition) {
                        $pattern = str_replace($fieldtag, isset($definitions[$fieldtag][1]) ? $definitions[$fieldtag][1] : '', $pattern);
                    }
                }
                $viewdefinitions[$tag] = array('html', $pattern);
            }
            $definitions = array_merge($definitions, $viewdefinitions);
        }

        return $definitions;
    }

    /**
     *
     */
    protected function set__display_definition(array $options = null) {

        $this->_display_definition = array();
        // Indicate if there are managable entries in the display for the current user
        // in which case edit/delete action
        $requiresmanageentries = false;

        $editentries = null;

        // Display a new entry to add in its own group
        if ($this->_editentries < 0) {
            if ($this->_df->user_can_manage_entry()) {
                $this->_display_definition['newentry'] = array();
                for ($i = -1; $i >= $this->_editentries; $i--) {
                    $this->_display_definition['newentry'][$i] = null;
                }
            }
        } else if ($this->_editentries) {
            $editentries = explode(',', $this->_editentries);
        }

        // compile entries if any
        if ($entries = $this->_entries->entries()) {
            $groupname = '';
            $groupdefinition = array();

            // If action buttons should be hidden entries should unmanageable
            $displayactions = isset($options['entryactions']) ? $options['entryactions'] : true;
            foreach ($entries as $entryid => $entry) {
               // Is this entry edited
               $editthisone = $editentries ? in_array($entryid, $editentries) : false;
               // Set a flag if we are editing any entries
               $requiresmanageentries = $editthisone ? true : $requiresmanageentries;
               // Calculate manageability for this entry only if action buttons can be displayed and we're not already editing it
               $manageable = false;
               if ($displayactions and !$editthisone) {
                   $manageable = $this->_df->user_can_manage_entry($entry);
                }

                // Are we grouping?
                if ($this->_filter->groupby) {
                    // assuming here that the groupbyed field returns only one pattern
                    $groupbyvalue = $this->get_groupby_value($entry);
                    if ($groupbyvalue != $groupname) {
                        // Compile current group definitions
                        if ($groupname) {
                            // Add the group entries definitions
                            $this->_display_definition[$groupname] = $groupdefinition;
                            $groupdefinition = array();
                        }
                        // Reset group name
                        $groupname = $groupbyvalue;
                    }
                }

                // add to the current entries group
                $groupdefinition[$entryid] = array($entry, $editthisone, $manageable);

            }
            // collect remaining definitions (all of it if no groupby)
            $this->_display_definition[$groupname] = $groupdefinition;
        }
        return $requiresmanageentries;
    }

    /**
     *
     */
    protected function process_calculations($text) {
        global $CFG;

        if (preg_match_all("/%%F\d*:=[^%]+%%/", $text, $matches)) {
            require_once("$CFG->libdir/mathslib.php");
            sort($matches[0]);
            $replacements = array();
            $formulas = array();
            foreach ($matches[0] as $pattern) {
                $cleanpattern = trim($pattern, '%');
                list($fid, $formula) = explode(':=', $cleanpattern, 2);
                // Process group formulas (e.g. _F1_)
                if (preg_match_all("/_F\d*_/", $formula, $frefs)) {
                    foreach ($frefs[0] as $fref) {
                        $fref = trim($fref, '_');
                        if (isset($formulas[$fref])) {
                            $formula = str_replace("_{$fref}_", implode(',', $formulas[$fref]), $formula);
                        }
                    }
                }
                isset($formulas[$fid]) or $formulas[$fid] = array();
                // Enclose formula in brackets to preserve precedence
                $formulas[$fid][] = "($formula)";
                $replacements[$pattern] = $formula;
            }

            foreach ($replacements as $pattern => $formula) {
                // Number of decimals can be set as ;n at the end of the formula
                $decimals = null;
                if (strpos($formula, ';')) {
                    list($formula, $decimals) = explode(';', $formula);
                }

                $calc = new calc_formula("=$formula");
                $result = $calc->evaluate();
                // false as result indicates some problem
                if ($result === false) {
                    // add more error hints
                    $replacements[$pattern] = html_writer::tag('span', $formula, array('style' => 'color:red;')); //get_string('errorcalculationunknown', 'grades');
                } else {
                    // Set decimals
                    if (is_numeric($decimals)) {
                        $result = sprintf("%4.{$decimals}f", $result);
                    }
                    $replacements[$pattern] = $result;
                }
            }
            $text = str_replace(array_keys($replacements), $replacements, $text);
        }
        return $text;
    }

    ////////////////////////////////////
    // GETTERS
    ////////////////////////////////////

    /**
     * Returns the type of the view
     */
    public function id() {
        return $this->view->id;
    }

    /**
     * Returns the type of the view
     */
    public function type() {
        return $this->type;
    }

    /**
     * Returns the type name of the view
     */
    public function typename() {
        return get_string('pluginname', "datalynxview_{$this->type}");
    }

    /**
     * Returns the name/type of the view
     */
    public function name() {
        return $this->view->name;
    }

    /**
     * Returns the parent datalynx
     */
    public function get_df() {
        return $this->_df;
    }

    /**
     *
     */
    public function get_filter() {
        return $this->_filter;
    }

    /**
     *
     */
    public function get_baseurl() {
        return $this->_baseurl;
    }

    /**
     *
     */
    public function is_active() {
        return (optional_param('view', 0, PARAM_INT) == $this->id());
    }

    /**
     *
     */
    public function is_caching() {
        return false;
    }

    /**
     *
     */
    public function is_forcing_filter() {
        return $this->view->filter;
    }

    /**
     *
     */
    public function user_is_editing() {
        return $this->_editentries;
    }

    /**
     *
     */
    public function process_entries_data() {
        $illegalaction = false;

        // Check first if returning from form
        $update = optional_param('update', '', PARAM_RAW);
        if ($update) {
            $action = ($update != self::ADD_NEW_ENTRY) ? "edit" : "addnewentry";
            if (confirm_sesskey() && $this->confirm_view_action($action)) {
                // get entries only if updating existing entries
                if ($update != self::ADD_NEW_ENTRY) {
                    // fetch entries
                    $this->_entries->set_content();
                }

                // set the display definition for the form
                $this->_editentries = $update;
                $this->set__display_definition();

                $entriesform = $this->get_entries_form();

                // Process the form if not cancelled
                if (!$entriesform->is_cancelled()) {
                    if ($data = $entriesform->get_data()) {
                        // validated successfully so process request
                        $processed = $this->_entries->process_entries('update', $update, $data, true);
                        if (!$processed) {
                            $this->_returntoentriesform = true;
                            return false;
                        }

                        if (!empty($data->submitreturnbutton)) {
                            // If we have just added new entries refresh the content
                            // This is far from ideal because this new entries may be
                            // spread out in the form when we return to edit them
                            if ($this->_editentries < 0) {
                                $this->_entries->set_content();
                            }

                            // so that return after adding new entry will return the added entry
                            $this->_editentries = is_array($processed[1]) ? implode(',', $processed[1]) : $processed[1];
                            $this->_returntoentriesform = true;
                            return true;
                        } else {
                            // So that we can show the new entries if we so wish
                            if ($this->_editentries < 0) {
                                $this->_editentries = is_array($processed[1]) ? implode(',', $processed[1]) : $processed[1];
                            } else {
                                $this->_editentries = '';
                            }
                            $this->_returntoentriesform = false;
                            return $processed;
                        }
                    } else {
                        // form validation failed so return to form
                        $this->_returntoentriesform = true;
                        return false;
                    }
                } else {
                    $redirectid = $this->_redirect ? $this->_redirect : $this->id();
                    $url = new moodle_url($this->_baseurl, array('view' => $redirectid));
                    redirect($url);
                }
            } else {
                $illegalaction = true;
            }
        }

        // direct url params; not from form
        $new = optional_param('new', 0, PARAM_INT);               // open new entry form
        $editentries = optional_param('editentries', 0, PARAM_SEQUENCE);        // edit entries (all) or by record ids (comma delimited eids)
        $duplicate = optional_param('duplicate', '', PARAM_SEQUENCE);    // duplicate entries (all) or by record ids (comma delimited eids)
        $delete = optional_param('delete', '', PARAM_SEQUENCE);    // delete entries (all) or by record ids (comma delimited eids)
        $approve = optional_param('approve', '', PARAM_SEQUENCE);  // approve entries (all) or by record ids (comma delimited eids)
        $disapprove = optional_param('disapprove', '', PARAM_SEQUENCE);  // disapprove entries (all) or by record ids (comma delimited eids)
        $status = optional_param('status', '', PARAM_SEQUENCE);  // set status of entries (all) or by record ids (comma delimited eids)

        $confirmed = optional_param('confirmed', 0, PARAM_BOOL);

        $this->_editentries = $editentries;

        if ($new) {
            if (confirm_sesskey() && ($this->confirm_view_action("addnewentry") || $this->confirm_view_action("addnewentries"))) {
                return $this->_editentries = -$new;
            } else {
                $illegalaction = true;
            }
        } else if ($duplicate) {
            if (confirm_sesskey() && $this->confirm_view_action("duplicate")) {
                return $this->_entries->process_entries('duplicate', $duplicate, null, $confirmed);
            } else {
                $illegalaction = true;
            }
        } else if ($delete) {
            if (confirm_sesskey() && $this->confirm_view_action("delete")) {
                return $this->_entries->process_entries('delete', $delete, null, $confirmed);
            } else {
                $illegalaction = true;
            }
        } else if ($approve) {
            if (confirm_sesskey() && $this->confirm_view_action("approve")) {
                return $this->_entries->process_entries('approve', $approve, null, true);
            } else {
                $illegalaction = true;
            }
        } else if ($disapprove) {
            if (confirm_sesskey() && $this->confirm_view_action("approve")) {
                return $this->_entries->process_entries('disapprove', $disapprove, null, true);
            } else {
                $illegalaction = true;
            }
        } else if ($status) {
            if (confirm_sesskey() && $this->confirm_view_action("status")) {
                return $this->_entries->process_entries('status', $status, null, true);
            } else {
                $illegalaction = true;
            }
        }

        if ($illegalaction) {
            $sourceview = optional_param('sourceview', $this->id(), PARAM_INT);
            $url = new moodle_url('view.php', array('d' => $this->_df->id(), 'view' => $sourceview));
            redirect($url);
        }

        return false;
    }

    /**
     * Verifies if the given action is available to the user in the view. The TARGET view must be visible to the user
     * AND contain the necessary action tag in the entry template in order for the action to be allowed. This function
     * prevents users to circumvent action restrictions via URL queries.
     * @param $action String of the action
     * @return bool true, if the action is allowed; false otherwise.
     */
    private function confirm_view_action($action) {
        global $DB;
        $targetview = optional_param('view', 0, PARAM_INT);
        $view = $DB->get_record('datalynx_views', array('id' => $targetview));
        return $view && $this->_df->is_visible_to_user($this->view) &&
                ((strpos($view->param2, "##$action##") !== false) || (strpos($view->section, "##$action##") !== false));
    }

    private function replace_pluginfile_urls($html, $pluginfileurl) {
        $pluginfilepath = moodle_url::make_file_url("/pluginfile.php", "/{$this->_df->context->id}/mod_datalynx/content");
        $pattern = str_replace('/', '\/', $pluginfilepath);
        $pattern = "/$pattern\/\d+\//";
        return preg_replace($pattern, $pluginfileurl, $html);
    }
}
