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
 *
 *          The Datalynx has been developed as an enhanced counterpart
 *          of Moodle's Database activity module (1.9.11+ (20110323)).
 *          To the extent that Datalynx code corresponds to Database code,
 *          certain copyrights on the Database module may obtain.
 */

/**
 * Datalynx class
 */
class datalynx {

    const COUNT_ALL = 0;

    const COUNT_APPROVED = 1;

    const COUNT_UNAPPROVED = 2;

    const COUNT_LEFT = 3;

    /** no approval required **/
    const APPROVAL_NONE = 0;
    /** approval for new entries and updates required **/
    const APPROVAL_ON_UPDATE = 1;
    /** approval only for new entries required **/
    const APPROVAL_ON_NEW = 2;

    /**
     *
     * @var stdClass course module
     */
    public $cm = NULL;

    /**
     *
     * @var fieldset object of the course
     */
    public $course = NULL;

    /**
     *
     * @var fieldset record of datalynx instance
     */
    public $data = NULL;
    public $context = NULL;
    public $groupmode = 0;
    public $currentgroup = 0;
    public $notifications = array('bad' => array(), 'good' => array());

    protected $pagefile = 'view';

    protected $fields = array();

    protected $views = array();

    protected $_filtermanager = null;

    protected $_rulemanager = null;

    protected $_presetmanager = null;

    protected $_currentview = null;

    // internal fields
    protected $internalfields = array();

    // internal group modes
    protected $internalgroupmodes = array('separateparticipants' => -1);

    /**
     * constructor
     */
    public function __construct($d = 0, $id = 0) {
        global $DB;

        // initialize from datalynx id or object
        if ($d) {
            if (is_object($d)) { // try object first
                $this->data = $d;
            } else if (!$this->data = $DB->get_record('datalynx', array('id' => $d))) {
                throw new moodle_exception('invaliddatalynx', 'datalynx', null, null,
                        "Datalynx id: $d");
            }
            if (!$this->course = $DB->get_record('course', array('id' => $this->data->course))) {
                throw new moodle_exception('invalidcourse', 'datalynx', null, null,
                        "Course id: {$this->data->course}");
            }
            if (!$this->cm = get_coursemodule_from_instance('datalynx', $this->id(),
                    $this->course->id)) {
                throw new moodle_exception('invalidcoursemodule', 'datalynx', null, null,
                        "Cm id: {$this->id()}");
            }
            // initialize from course module id
        } else if ($id) {
            if (!$this->cm = get_coursemodule_from_id('datalynx', $id)) {
                throw new moodle_exception('invalidcoursemodule ' . $id, 'datalynx', null, null,
                        "Cm id: $id");
            }
            if (!$this->course = $DB->get_record('course', array('id' => $this->cm->course))) {
                throw new moodle_exception('invalidcourse', 'datalynx', null, null,
                        "Course id: {$this->cm->course}");
            }
            if (!$this->data = $DB->get_record('datalynx', array('id' => $this->cm->instance))) {
                throw new moodle_exception('invaliddatalynx', 'datalynx', null, null,
                        "Datalynx id: {$this->cm->instance}");
            }
        }

        // get context
        $this->context = context_module::instance($this->cm->id);

        // set groups
        if ($this->cm->groupmode and in_array($this->cm->groupmode, $this->internalgroupmodes)) {
            $this->groupmode = $this->cm->groupmode;
        } else {
            $this->groupmode = groups_get_activity_groupmode($this->cm);
            $this->currentgroup = groups_get_activity_group($this->cm, true);
        }

        // set fields manager
        // $this->_fieldmanager = new datalynxfield_manager($this);

        // set views manager
        // $this->_viewmanager = new datalynxview_manager($this);
    }

    public static function get_datalynx_by_instance($instanceid) {
        $cm = get_coursemodule_from_instance('datalynx', $instanceid);
        return new datalynx($instanceid, $cm->id);
    }

    /**
     */
    public function id() {
        return $this->data->id;
    }

    /**
     */
    public function name() {
        return $this->data->name;
    }

    /**
     */
    public function pagefile() {
        return $this->pagefile;
    }

    /**
     */
    public function internal_group_modes() {
        return $this->internalgroupmodes;
    }

    /**
     */
    public function get_current_view() {
        return $this->_currentview;
    }

    /**
     */
    public function get_filter_manager() {
        // set filters manager
        if (!$this->_filtermanager) {
            require_once ('filter/filter_class.php');
            $this->_filtermanager = new datalynx_filter_manager($this);
        }
        return $this->_filtermanager;
    }

    /**
     */
    public function get_rule_manager() {
        // set rules manager
        if (!$this->_rulemanager) {
            require_once ('rule/rule_manager.php');
            $this->_rulemanager = new datalynx_rule_manager($this);
        }
        return $this->_rulemanager;
    }

    /**
     */
    public function get_preset_manager() {
        // set preset manager
        if (!$this->_presetmanager) {
            require_once ('preset/preset_manager.php');
            $this->_presetmanager = new datalynx_preset_manager($this);
        }
        return $this->_presetmanager;
    }

    /**
     */
    public function get_entriescount($type, $user = 0) {
        global $DB;

        switch ($type) {
            case self::COUNT_ALL:
                $count = $DB->count_records_sql(
                        'SELECT COUNT(e.id) FROM {datalynx_entries} e WHERE e.dataid = ?',
                        array($this->id()));
                break;

            case self::COUNT_APPROVED:
                $count = '---';
                break;

            case self::COUNT_UNAPPROVED:
                $count = '---';
                break;

            case self::COUNT_LEFT:
                $count = '---';
                break;

            default:
                $count = '---';
        }

        return $count;
    }

    /**
     */
    public function update($params, $notify = '') {
        global $DB;

        if ($params) {
            $updatedf = false;
            foreach ($params as $key => $value) {
                $oldvalue = !empty($this->data->{$key}) ? $this->data->{$key} : null;
                $newvalue = !empty($value) ? $value : null;
                if ($newvalue != $oldvalue) {
                    $this->data->{$key} = $value;
                    $updatedf = true;
                }
            }
            if ($updatedf) {
                if (!$DB->update_record('datalynx', $this->data)) {
                    if ($notify === true) {
                        $this->notifications['bad'][] = get_string('dfupdatefailed', 'datalynx');
                    } else if ($notify) {
                        $this->notifications['bad'][] = $notify;
                    }
                    return false;
                } else {
                    if ($notify === true) {
                        // $this->notifications['good'][] = get_string('dfupdatefailed',
                    // 'datalynx');
                    } else if ($notify) {
                        $this->notifications['good'][] = $notify;
                    }
                }
            }
        }
        return true;
    }

    /**
     * sets the datalynx page
     *
     * @param string $page current page
     * @param array $params
     */
    public function set_page($page = 'view', $params = null, $skiplogincheck = false) {
        global $CFG, $PAGE, $USER;

        $this->pagefile = $page;
        $thisid = $this->id();

        $params = (object) $params;
        $urlparams = array();
        if (!empty($params->urlparams)) {
            foreach ($params->urlparams as $param => $value) {
                if ($value != 0 and $value != '') {
                    $urlparams[$param] = $value;
                }
            }
        }

        if (!$skiplogincheck) {
            require_login($this->course->id, true, $this->cm);
        }

        // make sure there is at least datalynx id param
        $urlparams['d'] = $thisid;

        $manager = has_capability('mod/datalynx:managetemplates', $this->context);

        // if datalynx activity closed don't let students in
        if (!$manager) {
            $timenow = time();
            if (!empty($this->data->timeavailable) and $this->data->timeavailable > $timenow) {
                throw new moodle_exception('notopenyet', 'datalynx', '',
                        userdate($this->data->timeavailable));
            }
        }

        // RSS
        if (!empty($params->rss) and !empty($CFG->enablerssfeeds) and
                 !empty($CFG->datalynx_enablerssfeeds) and $this->data->rssarticles > 0) {
            require_once ("$CFG->libdir/rsslib.php");
            $rsstitle = format_string($this->course->shortname) . ': %fullname%';
            rss_add_http_header($this->context, 'mod_datalynx', $this->data, $rsstitle);
        }

        // COMMENTS
        if (!empty($params->comments)) {
            require_once ("$CFG->dirroot/comment/lib.php");
            comment::init();
        }

        $fs = get_file_storage();

        // ///////////////////////////////////
        // PAGE setup for activity pages only

        if ($page != 'external') {
            // Is user editing
            $urlparams['edit'] = optional_param('edit', 0, PARAM_BOOL);
            $PAGE->set_url("/mod/datalynx/$page.php", $urlparams);

            // editing button (omit in embedded datalynxs)
            if ($page != 'embed' and $PAGE->user_allowed_editing()) {
                // teacher editing mode
                if ($urlparams['edit'] != -1) {
                    $USER->editing = $urlparams['edit'];
                }

                $buttons = '<table><tr><td><form method="get" action="' . $PAGE->url . '"><div>' .
                         '<input type="hidden" name="d" value="' . $thisid . '" />' .
                         '<input type="hidden" name="edit" value="' . ($PAGE->user_is_editing() ? 0 : 1) .
                         '" />' . '<input type="submit" value="' .
                         get_string($PAGE->user_is_editing() ? 'blockseditoff' : 'blocksediton') .
                         '" /></div></form></td></tr></table>';
                $PAGE->set_button($buttons);
            }

            // auto refresh
            if (!empty($urlparams['refresh'])) {
                $PAGE->set_periodic_refresh_delay($urlparams['refresh']);
            }

            // page layout
            if (!empty($params->pagelayout)) {
                $PAGE->set_pagelayout($params->pagelayout);
            }

            $PAGE->requires->css(
                    new moodle_url(
                            $CFG->wwwroot . '/mod/datalynx/field/picture/shadowbox/shadowbox.css'));

            // Mark as viewed
            if (!empty($params->completion)) {
                require_once ($CFG->libdir . '/completionlib.php');
                $completion = new completion_info($this->course);
                $completion->set_module_viewed($this->cm);
            }

            $modulename = $this->name();
            $viewid = (!empty($urlparams['view']) ? $urlparams['view'] : (!empty(
                    $this->data->defaultview) ? $this->data->defaultview : 0));
            if ($page == 'view' && $viewid) {
                global $DB;
                $viewname = $DB->get_field('datalynx_views', 'name', array('id' => $viewid));
                $pagestring = get_string('page');
                $pageparam = optional_param('page', 0, PARAM_INT);
                $pagenum = !empty($pageparam) ? $pageparam + 1 : 1;
                $editmode = optional_param('editentries', 0, PARAM_SEQUENCE);
                $editstring = get_string('editmode', 'datalynx');
                $edit = $editmode ? " ({$editstring})" : "";
                $pagename = "{$modulename}: {$viewname}: {$pagestring} {$pagenum}{$edit}";
                $PAGE->set_title($pagename);
            } else {
                $manage = get_string('managemode', 'datalynx');
                $what = strpos($page, 'view') !== false ? get_string('views', 'datalynx') : '???';
                $what = strpos($page, 'field') !== false ? get_string('fields', 'datalynx') : $what;
                $what = strpos($page, 'filter') !== false ? get_string('filters', 'datalynx') : $what;
                $what = strpos($page, 'rule') !== false ? get_string('rules', 'datalynx') : $what;
                $what = strpos($page, 'tool') !== false ? get_string('tools', 'datalynx') : $what;
                $what = strpos($page, 'js') !== false ? get_string('jsinclude', 'datalynx') : $what;
                $what = strpos($page, 'css') !== false ? get_string('cssinclude', 'datalynx') : $what;
                $what = strpos($page, 'preset') !== false ? get_string('presets', 'datalynx') : $what;
                $what = strpos($page, 'import') !== false ? get_string('import', 'datalynx') : $what;
                $what = strpos($page, 'statistics') !== false ? get_string('statistics', 'datalynx') : $what;
                $what = strpos($page, 'behavior') !== false ? get_string('behaviors', 'datalynx') : $what;
                $what = strpos($page, 'renderer') !== false ? get_string('renderers', 'datalynx') : $what;
                $pagename = "{$modulename}: {$what} ({$manage})";
                $PAGE->set_title($pagename);
            }
            $PAGE->set_heading($this->course->fullname);

            // Include blocks dragdrop when editing
            if ($PAGE->user_is_editing()) {
                $params = array('courseid' => $this->course->id, 'cmid' => $this->cm->id,
                    'pagetype' => $PAGE->pagetype, 'pagelayout' => $PAGE->pagelayout,
                    'regions' => $PAGE->blocks->get_regions()
                );
                $PAGE->requires->yui_module('moodle-core-blocks', 'M.core_blocks.init_dragdrop',
                        array($params), null, true);
            }
        }

        // //////////////////////////////////
        // PAGE setup for datalynx content anywhere

        // Use this to return css if this df page is set after header
        $output = '';

        // CSS (cannot be required after head)
        $cssurls = array();
        if (!empty($params->css)) {
            // js includes from the js template
            if ($this->data->cssincludes) {
                foreach (explode("\n", $this->data->cssincludes) as $cssinclude) {
                    $cssinclude = trim($cssinclude);
                    if ($cssinclude) {
                        $cssurls[] = new moodle_url($cssinclude);
                    }
                }
            }
            // Uploaded css files
            if ($files = $fs->get_area_files($this->context->id, 'mod_datalynx', 'css', 0,
                    'sortorder', false)) {
                $path = "/{$this->context->id}/mod_datalynx/css/0";
                foreach ($files as $file) {
                    $filename = $file->get_filename();
                    $cssurls[] = moodle_url::make_file_url('/pluginfile.php', "$path/$filename");
                }
            }
            // css code from the css template
            if ($this->data->css) {
                $cssurls[] = new moodle_url('/mod/datalynx/css.php', array('d' => $thisid));
            }
        }
        if ($PAGE->state == moodle_page::STATE_BEFORE_HEADER) {
            foreach ($cssurls as $cssurl) {
                $PAGE->requires->css($cssurl);
            }
        } else {
            $attrs = array('rel' => 'stylesheet', 'type' => 'text/css');
            foreach ($cssurls as $cssurl) {
                $attrs['href'] = $cssurl;
                $output .= html_writer::empty_tag('link', $attrs) . "\n";
                unset($attrs['id']);
            }
        }

        // JS
        $jsurls = array();
        if (!empty($params->js)) {
            // js includes from the js template
            if ($this->data->jsincludes) {
                foreach (explode("\n", $this->data->jsincludes) as $jsinclude) {
                    $jsinclude = trim($jsinclude);
                    if ($jsinclude) {
                        $jsurls[] = new moodle_url($jsinclude);
                    }
                }
            }
            // Uploaded js files
            if ($files = $fs->get_area_files($this->context->id, 'mod_datalynx', 'js', 0,
                    'sortorder', false)) {
                $path = "/{$this->context->id}/mod_datalynx/js/0";
                foreach ($files as $file) {
                    $filename = $file->get_filename();
                    $jsurls[] = moodle_url::make_file_url('/pluginfile.php', "$path/$filename");
                }
            }
            // js code from the js template
            if ($this->data->js) {
                $jsurls[] = new moodle_url('/mod/datalynx/js.php', array('d' => $thisid));
            }
        }
        foreach ($jsurls as $jsurl) {
            $PAGE->requires->js($jsurl);
        }

        // MOD JS
        if (!empty($params->modjs)) {
            $PAGE->requires->js('/mod/datalynx/datalynx.js');
        }

        // TODO
        // if ($mode == 'asearch') {
        // $PAGE->navbar->add(get_string('search'));
        // }

        // set current view and view's page requirements
        $currentview = !empty($urlparams['view']) ? $urlparams['view'] : 0;
        $this->_currentview = $this->get_current_view_from_id($currentview);

        // if a new datalynx or incomplete design, direct manager to manage area
        if ($manager) {
            $viewrecords = $this->get_view_records();
            if (empty($viewrecords)) {
                if ($page == 'view' or $page == 'embed') {
                    $getstarted = new stdClass();
                    $getstarted->presets = html_writer::link(
                            new moodle_url('/mod/datalynx/preset/index.php', array('d' => $thisid)),
                                get_string('presets', 'datalynx'));
                    $getstarted->fields = html_writer::link(
                            new moodle_url('/mod/datalynx/field/index.php', array('d' => $thisid)),
                                get_string('fields', 'datalynx'));
                    $getstarted->views = html_writer::link(
                            new moodle_url('/mod/datalynx/view/index.php', array('d' => $thisid)),
                                get_string('views', 'datalynx'));

                    $this->notifications['bad']['getstarted'] = html_writer::tag('div',
                            get_string('getstarted', 'datalynx', $getstarted), array('class' => 'mdl-left'));
                }
            } else if (!$this->data->defaultview) {
                $linktoviews = html_writer::link(
                        new moodle_url('/mod/datalynx/view/index.php', array('d' => $thisid)),
                            get_string('views', 'datalynx'));
                $this->notifications['bad']['defaultview'] = get_string('viewnodefault', 'datalynx', $linktoviews);
            }
        }

        return $output;
    }

    /**
     * prints the header of the current datalynx page
     *
     * @param array $params
     */
    public function print_header($params = null) {
        global $OUTPUT;

        $params = (object) $params;

        echo $OUTPUT->header();

        // print intro
        if (!empty($params->heading)) {
            echo $OUTPUT->heading(format_string($this->name()));
        }

        // print intro
        if (!empty($params->intro) and $params->intro) {
            $this->print_intro();
        }

        // print the tabs
        if (!empty($params->tab)) {
            $currenttab = $params->tab;
            include ('tabs.php');
        }

        // print groups menu if needed
        if (!empty($params->groups)) {
            $this->print_groups_menu($params->urlparams->view, $params->urlparams->filter);
        }

        // TODO: explore letting view decide whether to print rsslink and intro
        // $df->print_rsslink();

        // print any notices
        if (empty($params->nonotifications)) {
            foreach ($this->notifications['good'] as $notification) {
                if (!empty($notification)) {
                    echo $OUTPUT->notification($notification, 'notifysuccess'); // good (usually
                                                                                // green)
                }
            }
            foreach ($this->notifications['bad'] as $notification) {
                if (!empty($notification)) {
                    echo $OUTPUT->notification($notification); // bad (usually red)
                }
            }
        }
    }

    /**
     * prints the footer of the current datalynx page
     *
     * @param array $params
     */
    public function print_footer($params = null) {
        global $OUTPUT;

        echo $OUTPUT->footer();
    }

    /**
     * TODO: consider moving into the view
     */
    public function print_groups_menu($view, $filter) {
        if ($this->groupmode and !in_array($this->groupmode, $this->internalgroupmodes)) {
            $returnurl = new moodle_url("/mod/datalynx/{$this->pagefile}.php",
                    array('d' => $this->id(), 'view' => $view, 'filter' => $filter));
            groups_print_activity_menu($this->cm, $returnurl . '&amp;');
        }
    }

    /**
     * TODO: consider moving into the view
     */
    public function print_rsslink() {
        global $USER, $CFG;
        // Link to the RSS feed
        if (!empty($CFG->enablerssfeeds) && !empty($CFG->datalynx_enablerssfeeds) &&
                 $this->data->rssarticles > 0) {
            echo '<div style="float:right;">';
            rss_print_link($this->course->id, $USER->id, 'datalynx', $this->id(), get_string('rsstype'));
            echo '</div>';
            echo '<div style="clear:both;"></div>';
        }
    }

    /**
     * TODO: consider moving into the view
     */
    public function print_intro() {
        global $OUTPUT;
        // TODO: make intro stickily closable
        // display the intro only when there are on pages: if ($this->data->intro and empty($page))
        // {
        if ($this->data->intro) {
            $options = new stdClass();
            $options->noclean = true;
            echo $OUTPUT->box(format_module_intro('datalynx', $this->data, $this->cm->id), 'generalbox', 'intro');
        }
    }

    /**
     */
    public function set_content() {
        if (!empty($this->_currentview)) {
            $this->_currentview->process_data();
            $this->_currentview->set_content();
        }
    }

    /**
     */
    public function display() {
        global $PAGE;
        if (!empty($this->_currentview)) {

            $event = \mod_datalynx\event\course_module_viewed::create(
                    array('objectid' => $PAGE->cm->instance, 'context' => $PAGE->context));
            $event->add_record_snapshot('course', $PAGE->course);
            $event->trigger();

            $this->_currentview->display();
        }
    }

    /**
     * Returns datalynx content for inline display.
     * Used in mod_datalynxcoursepage only.
     *
     * @param int $datalynxid The id of the datalynx whose content should be displayed
     * @param int $viewid The id of the datalynx's view whose content should be displayed
     * @return string
     */
    public static function get_content_inline($datalynxid, $viewid = 0, $eids = null) {
        global $CFG;
        require_once $CFG->dirroot . '/mod/datalynx/view/view_class.php';
        $urlparams = new stdClass();
        $datalynx = new datalynx($datalynxid, null);
        $urlparams->d = $datalynxid;
        $urlparams->view = $viewid;
        $urlparams->pagelayout = 'external';
        if($eids) {
            $urlparams->eids = $eids;
        }

        $pageparams = array('js' => true, 'css' => true, 'rss' => true, 'modjs' => true,
            'completion' => true, 'comments' => true, 'urlparams' => $urlparams);
        $datalynx->set_page('external', $pageparams);
        $type = $datalynx->views[$viewid]->type;
        require_once $CFG->dirroot . "/mod/datalynx/view/$type/view_class.php";
        $viewclass = "datalynxview_$type";
        $datalynx->_currentview = $datalynx->get_current_view_from_id($viewid);

        if ($view = new $viewclass($datalynxid, $viewid)) {
            $view->set_content();
            $view->get_df()->_currentview = $datalynx->_currentview;
            $viewcontent = $view->display(array('tohtml' => true));
            return "$viewcontent";
        }
        return null;
    }

    /**
     * ********************************************************************************
     * FIELDS
     * *******************************************************************************
     */

    /**
     * Initialize if needed and return the internal fields
     */
    public function get_internal_fields() {
        global $CFG;

        if (!$this->internalfields) {
            $fieldplugins = get_list_of_plugins('mod/datalynx/field/');
            foreach ($fieldplugins as $fieldname) {
                require_once ("$CFG->dirroot/mod/datalynx/field/$fieldname/field_class.php");
                $fieldclass = "datalynxfield_$fieldname";
                if (!$fieldclass::is_internal()) {
                    continue;
                }
                $internalfields = $fieldclass::get_field_objects($this->data->id);
                foreach ($internalfields as $fid => $field) {
                    $this->internalfields[$fid] = $this->get_field($field);
                }
            }
        }

        return $this->internalfields;
    }

    /**
     */
    public function get_user_defined_fields($forceget = false, $sort = '') {
        $this->get_fields(null, false, $forceget, $sort);
        return $this->fields;
    }

    /**
     * given a field id return the field object from get_fields
     * Initializes get_fields if necessary
     */
    public function get_field_from_id($fieldid, $forceget = false) {
        $fields = $this->get_fields(null, false, $forceget);

        if (empty($fields[$fieldid])) {
            return false;
        } else {
            return $fields[$fieldid];
        }
    }

    /**
     * given a field type returns the field object from get_fields
     * Initializes get_fields if necessary
     */
    public function get_fields_by_type($type, $menu = false) {
        $typefields = array();
        foreach ($this->get_fields() as $fieldid => $field) {
            if ($field->type() === $type) {
                if ($menu) {
                    $typefields[$fieldid] = $field->name();
                } else {
                    $typefields[$fieldid] = $field;
                }
            }
        }
        return $typefields;
    }

    /**
     * given a field name returns the field object from get_fields
     */
    public function get_field_by_name($name) {
        foreach ($this->get_fields() as $field) {
            if ($field->name() === $name) {
                return $field;
            }
        }
        return false;
    }

    /**
     * returns a subclass field object given a record of the field
     * used to invoke plugin methods
     * input: $param $field record from db, or field type
     */
    public function get_field($key) {
        global $CFG;

        if ($key) {
            if (is_object($key)) {
                $type = $key->type;
            } else {
                $type = $key;
                $key = 0;
            }
            require_once ('field/' . $type . '/field_class.php');
            $fieldclass = 'datalynxfield_' . $type;
            $field = new $fieldclass($this, $key);
            return $field;
        } else {
            return false;
        }
    }

    /**
     *
     * @param null $exclude
     * @param bool $menu
     * @param bool $forceget
     * @param string $sort
     * @return datalynxfield_base[]
     */
    public function get_fields($exclude = null, $menu = false, $forceget = false, $sort = '') {
        global $DB;

        if (!$this->fields or $forceget) {
            $this->fields = array();
            // collate user fields
            if ($fields = $DB->get_records('datalynx_fields', array('dataid' => $this->id()), $sort)) {
                foreach ($fields as $fieldid => $field) {
                    $this->fields[$fieldid] = $this->get_field($field);
                }
            }
        }

        // collate all fields
        $fields = $this->fields + $this->get_internal_fields();

        if (empty($exclude) and !$menu) {
            return $fields;
        } else {
            $retfields = array();
            foreach ($fields as $fieldid => $field) {
                if (!empty($exclude) and in_array($fieldid, $exclude)) {
                    continue;
                }
                if ($menu) {
                    $retfields[$fieldid] = $field->name();
                } else {
                    $retfields[$fieldid] = $field;
                }
            }
            return $retfields;
        }
    }

    private function find_filters_using_fields($fields) {
        global $DB;
        $filters = $DB->get_records('datalynx_filters', ['dataid' => $this->id()]);
        $usedfilters = [];
        $fieldids = array_keys($fields);
        foreach ($filters as $filter) {
            $custormsort = unserialize($filter->customsort);
            $custormsort = $custormsort ? array_keys($custormsort) : [];

            $customsearch = unserialize($filter->customsearch);
            $customsearch = $customsearch ? array_keys($customsearch) : [];

            if (array_intersect($fieldids, $customsearch) || array_intersect($fieldids, $custormsort)) {
                $usedfilters[] = $filter;
            }
        }
        return $usedfilters;
    }

    /**
     * Checks if string contains html tags
     *
     * @param string $string
     * @return boolean
     */
    private function is_html($string) {
        return preg_match("/<[^<]+>/", $string, $m) != 0;
    }

    /**
     * Process the action triggered by user for a specific field
     *
     * @param string $action
     * @param string $fids (comma separated numbers of field ids)
     * @param boolean $confirmed
     */
    public function process_fields($action, $fids, $confirmed = false) {
        global $OUTPUT, $DB;

        if (!has_capability('mod/datalynx:managetemplates', $this->context)) {
            // TODO throw exception
            return false;
        }

        $dffields = $this->get_fields();
        $fields = array();
        // collate the fields for processing
        if ($fieldids = explode(',', $fids)) {
            foreach ($fieldids as $fieldid) {
                if ($fieldid > 0 and isset($dffields[$fieldid])) {
                    $fields[$fieldid] = $dffields[$fieldid];
                }
            }
        }

        $processedfids = array();
        $strnotify = '';

        if (empty($fields) and $action != 'add') {
            $this->notifications['bad'][] = get_string("fieldnoneforaction", 'datalynx');
            return false;
        } else {
            if (!$confirmed) {
                // print header
                $this->print_header('fields');

                $msg = get_string("fieldsconfirm$action", 'datalynx', count($fields));
                if ($action === 'delete') {
                    $fieldlist = array_reduce($fields,
                            function ($list, $field) {
                                return $list . "<li>{$field->field->name}</li>";
                            }, '');
                    $fieldlist = "<ul>$fieldlist</ul>";
                    $filters = $this->find_filters_using_fields($fields);
                    $filterlist = array_reduce($filters,
                            function ($list, $filter) {
                                return $list . "<li>{$filter->name}</li>";
                            }, '');
                    $filterlist = "<ul>$filterlist</ul>";
                    if ($filters) {
                        echo "<div class=\"alert alert-warning\">" .
                                 get_string('deletefieldfilterwarning', 'datalynx',
                                        ['fieldlist' => $fieldlist, 'filterlist' => $filterlist
                                        ]) . "</div>";
                        echo $OUTPUT->continue_button(
                                new moodle_url('/mod/datalynx/field/index.php',
                                        array('d' => $this->id())
                                )
                        );

                        echo $OUTPUT->footer();
                        exit();
                    }
                }

                echo $OUTPUT->confirm($msg,
                        new moodle_url('/mod/datalynx/field/index.php',
                                array('d' => $this->id(),
                                    $action => implode(',', array_keys($fields)),
                                    'sesskey' => sesskey(), 'confirmed' => 1)
                        ),
                        new moodle_url('/mod/datalynx/field/index.php', array('d' => $this->id())));

                echo $OUTPUT->footer();
                exit();
            } else {
                // go ahead and perform the requested action
                switch ($action) {
                    case 'add': // TODO add new
                        if ($forminput = data_submitted()) {
                            // Check for arrays and convert to a comma-delimited string
                            $this->convert_arrays_to_strings($forminput);

                            // Create a field object to collect and store the data safely
                            $field = $this->get_field($forminput->type);
                            $field->insert_field($forminput);

                            $other = array('dataid' => $this->id());
                            $event = \mod_datalynx\event\field_created::create(
                                    array('context' => $this->context,
                                        'objectid' => $field->field->id, 'other' => $other
                                    )
                            );
                            $event->trigger();
                        }
                        $strnotify = 'fieldsadded';
                        break;

                    case 'update': // update existing
                        if ($forminput = data_submitted()) {
                            // Check for arrays and convert to a comma-delimited string
                            $this->convert_arrays_to_strings($forminput);

                            // Create a field object to collect and store the data safely
                            $field = reset($fields);
                            $oldfieldname = $field->field->name;
                            $field->update_field($forminput);

                            $other = array('dataid' => $this->id());
                            $event = \mod_datalynx\event\field_updated::create(
                                    array('context' => $this->context,
                                        'objectid' => $field->field->id, 'other' => $other
                                    )
                            );
                            $event->trigger();

                            // Update the views
                            if ($oldfieldname != $field->field->name) {
                                $this->replace_field_in_views($oldfieldname, $field->field->name);
                            }
                        }
                        $strnotify = 'fieldsupdated';
                        break;

                    case 'editable':
                        foreach ($fields as $fid => $field) {
                            // lock = 0; unlock = -1;
                            $editable = $field->field->edits ? 0 : -1;
                            $DB->set_field('datalynx_fields', 'edits', $editable,
                                    array('id' => $fid));
                            $processedfids[] = $fid;
                            $other = array('dataid' => $this->id());
                            $event = \mod_datalynx\event\field_updated::create(
                                    array('context' => $this->context, 'objectid' => $fid,
                                        'other' => $other
                                    )
                            );
                            $event->trigger();
                        }

                        $strnotify = '';
                        break;

                    case 'duplicate':
                        foreach ($fields as $field) {
                            // set new name
                            while ($this->name_exists('fields', $field->name())) {
                                $field->field->name .= '_1';
                            }
                            $fieldid = $DB->insert_record('datalynx_fields', $field->field);
                            $processedfids[] = $fieldid;

                            $other = array('dataid' => $this->id());
                            $event = \mod_datalynx\event\field_created::create(
                                    array('context' => $this->context, 'objectid' => $fieldid,
                                        'other' => $other
                                    )
                            );
                            $event->trigger();
                        }

                        $strnotify = 'fieldsadded';
                        break;

                    case 'delete':
                        foreach ($fields as $field) {
                            $field->delete_field();
                            $processedfids[] = $field->field->id;
                            // Update views
                            $this->replace_field_in_views($field->field->name, '');

                            $other = array('dataid' => $this->id());
                            $event = \mod_datalynx\event\field_deleted::create(
                                    array('context' => $this->context,
                                        'objectid' => $field->field->id, 'other' => $other
                                    )
                            );
                            $event->trigger();
                        }
                        $strnotify = 'fieldsdeleted';
                        break;

                    case 'convert':
                        foreach ($fields as $fid => $field) {
                            $processedfids[] = $field->field->id;

                            // Convert field content to HTML
                            $contents = $DB->get_records('datalynx_contents',
                                    array('fieldid' => $fid), null, 'id,content');
                            $htmlcontent = new stdClass();
                            if ($contents) {
                                foreach ($contents as $contentid => $content) {
                                    if (!$this->is_html($content->content)) {
                                        $htmlcontent->id = $contentid;
                                        $htmlcontent->content = format_text($content->content, FORMAT_PLAIN);
                                        $htmlcontent->content1 = FORMAT_HTML;
                                        $DB->update_record('datalynx_contents', $htmlcontent);
                                    }
                                }
                            }
                            // Convert field type to editor
                            $DB->set_field('datalynx_fields', 'type', 'editor', array('id' => $fid));

                            $other = array('dataid' => $this->id());
                            $event = \mod_datalynx\event\field_updated::create(
                                    array('context' => $this->context, 'objectid' => $fid, 'other' => $other));
                            $event->trigger();
                        }
                        $strnotify = 'fieldsupdated';
                        break;

                    default:
                        break;
                }

                if ($strnotify) {
                    $fieldsprocessed = $processedfids ? count($processedfids) : 'No';
                    $this->notifications['good'][] = get_string($strnotify, 'datalynx', $fieldsprocessed);
                }
                return $processedfids;
            }
        }
    }

    /**
     * ********************************************************************************
     * VIEWS
     * *******************************************************************************
     */

    /**
     * Retrieves the views associated with the current
     * datalynx instance visible to the current user
     * Updates $this->views
     *
     * @param boolean $forceget if true, the entries will be reread form the database
     * @param string $sort SQL ORDER BY clause
     * @return array an array of datalynx_views entry objects
     */
    public function get_view_records($forceget = false, $sort = '') {
        global $DB;

        if (empty($this->views) or $forceget) {
            $views = array();
            if (!$views = $DB->get_records('datalynx_views', array('dataid' => $this->id()), $sort)) {
                return false;
            }
            $this->views = array();
            foreach ($views as $viewid => $view) {
                if ($this->is_visible_to_user($view)) {
                    $this->views[$viewid] = $view;
                }
            }
        }
        return $this->views;
    }


    /**
     * Get all views of a datalynx instance
     * @return array of view objects indexed by view id, empty array if no views are found
     */
    public function get_all_views(){
        global $DB;
        $views = array();
        if (!$views = $DB->get_records('datalynx_views', array('dataid' => $this->id()))) {
            return array();
        } else {
            return $views;
        }
    }

    /**
     * Verifies whether the current user has the needed
     * permission to access a particular datalynx view.
     *
     * @param stdClass $view datalynx_views entry
     * @return boolean true if user can see the view, false otherwise
     */
    public function is_visible_to_user($view) {
        $isadmin = has_capability('mod/datalynx:viewprivilegeadmin', $this->context, null, true);
        $mask = (has_capability('mod/datalynx:viewprivilegemanager', $this->context, null, false) ? 1 : 0) |
                 (has_capability('mod/datalynx:viewprivilegeteacher', $this->context, null, false) ? 2 : 0) |
                 (has_capability('mod/datalynx:viewprivilegestudent', $this->context, null, false) ? 4 : 0) |
                 (has_capability('mod/datalynx:viewprivilegeguest', $this->context, null, false) ? 8 : 0);
        return $isadmin || ($view->visible & $mask);
    }

    /**
     * TODO there is no need to instantiate all views!!!
     * this function creates an instance of the particular subtemplate class *
     */
    public function get_current_view_from_id($viewid = 0) {
        if ($views = $this->get_view_records()) {
            if ($viewid and isset($views[$viewid])) {
                $view = $views[$viewid];

                // if can't find the requested, try the default
            } else if ($viewid = $this->data->defaultview and isset($views[$viewid])) {
                $view = $views[$viewid];
            } else {
                return false;
            }

            return $this->get_view($view, true);
        }

        return false;
    }

    /**
     * TODO there is no need to instantiate all viewds!!!
     * this function creates an instance of the particular subtemplate class *
     */
    public function get_view_from_id($viewid = 0) {
        if ($views = $this->get_view_records()) {
            if ($viewid and isset($views[$viewid])) {
                $view = $views[$viewid];

                // if can't find the requested, try the default
            } else if ($viewid = $this->data->defaultview and isset($views[$viewid])) {
                $view = $views[$viewid];
            } else {
                return false;
            }

            return $this->get_view($view);
        }

        return false;
    }

    /**
     * returns a view subclass object given a view record or view type
     * invoke plugin methods
     * input: $param $vt - mixed, view record or view type
     */
    public function get_view($viewortype, $active = false) {
        global $CFG;

        if ($viewortype) {
            if (is_object($viewortype)) {
                $type = $viewortype->type;
            } else {
                $type = $viewortype;
                $viewortype = 0;
            }
            require_once ($CFG->dirroot . '/mod/datalynx/view/' . $type . '/view_class.php');
            $viewclass = 'datalynxview_' . $type;
            return new $viewclass($this, $viewortype, $active);
        }
    }

    /**
     * given a view type returns the view object from $this->views
     * Initializes $this->views if necessary
     */
    public function get_views_by_type($type, $forceget = false) {
        if (!$views = $this->get_view_records($forceget)) {
            return false;
        }

        $typeviews = array();
        foreach ($views as $viewid => $view) {
            if ($view->type === $type) {
                $typeviews[$viewid] = $this->get_view($view);
            }
        }
        return $typeviews;
    }

    /**
     * Get the view objects visible to the user as an array indexed by the view id
     *
     * @param array $exclude array of viewids to exclude
     * @param boolean $forceget true to get from db directly
     * @param string $sort
     * @return array of view objects indexed by view id or false if no views are found
     */
    public function get_views($exclude = null, $forceget = false, $sort = '') {
        if (!$this->get_view_records($forceget, $sort)) {
            return false;
        }

        static $views = null;
        if ($views === null or $forceget) {
            $views = array();
            foreach ($this->views as $viewid => $view) {
                if (!empty($exclude) and in_array($viewid, $exclude)) {
                    continue;
                }
                $views[$viewid] = $this->get_view($view);
            }
        }
        return $views;
    }

    /**
     * Get all views visible to the user of a datalynx instance as an array indexed by viewid
     * @param string $exclude
     * @param string $forceget
     * @param string $sort
     * @return string[viewid]
     */
    public function get_views_menu($exclude = null, $forceget = false, $sort = '') {
        $views = array();

        if ($this->get_view_records($forceget, $sort)) {
            foreach ($this->views as $viewid => $view) {
                if (!empty($exclude) and in_array($viewid, $exclude)) {
                    continue;
                }
                $views[$viewid] = $view->name;
            }
        }
        return $views;
    }

    /**
     */
    public function set_default_view($viewid = 0) {
        global $DB;

        $rec = new stdClass();
        $rec->id = $this->id();
        $rec->defaultview = $viewid;
        if (!$DB->update_record('datalynx', $rec)) {
            throw new moodle_exception('Failed to update the database');
        }
        $this->data->defaultview = $viewid;
    }

    /**
     */
    public function set_default_filter($filterid = 0) {
        global $DB;

        $rec = new stdClass();
        $rec->id = $this->id();
        $rec->defaultfilter = $filterid;
        if (!$DB->update_record('datalynx', $rec)) {
            throw new moodle_exception('Failed to update the database');
        }
        $this->data->defaultfilter = $filterid;
    }

    /**
     */
    public function set_single_edit_view($viewid = 0) {
        global $DB;

        $rec = new stdClass();
        $rec->id = $this->id();
        $rec->singleedit = $viewid;
        if (!$DB->update_record('datalynx', $rec)) {
            throw new moodle_exception('Failed to update the database');
        }
        $this->data->singleedit = $viewid;
    }

    /**
     */
    public function set_single_more_view($viewid = 0) {
        global $DB;

        $rec = new stdClass();
        $rec->id = $this->id();
        $rec->singleview = $viewid;
        if (!$DB->update_record('datalynx', $rec)) {
            throw new moodle_exception('Failed to update the database');
        }
        $this->data->singleview = $viewid;
    }

    public function get_default_view_id() {
        return $this->data->defaultview;
    }

    /**
     * Search for a field name and replaces it with another one in all the *
     * form templates.
     * Set $newfieldname as '' if you want to delete the *
     * field from the form.
     */
    public function replace_field_in_views($searchfieldname, $newfieldname) {
        if ($views = $this->get_views()) {
            foreach ($views as $view) {
                $view->replace_field_in_view($searchfieldname, $newfieldname);
            }
        }
    }

    /**
     */
    public function process_views($action, $vids, $confirmed = false) {
        global $DB, $OUTPUT;

        if (!has_capability('mod/datalynx:managetemplates', $this->context)) {
            // TODO throw exception
            return false;
        }

        if ($vids) { // some views are specified for action
            $views = array();
            $viewobjs = $this->get_views(false, true);
            foreach (explode(',', $vids) as $vid) {
                if (!empty($viewobjs[$vid])) {
                    $views[$vid] = $viewobjs[$vid];
                }
            }
        }

        $processedvids = array();
        $strnotify = '';

        if (empty($views)) {
            $this->notifications['bad'][] = get_string("viewnoneforaction", 'datalynx');
            return false;
        } else {
            if (!$confirmed) {
                // print header
                $this->print_header('views');

                // Print a confirmation page
                echo $OUTPUT->confirm(get_string("viewsconfirm$action", 'datalynx', count($views)),
                        new moodle_url('/mod/datalynx/view/index.php',
                                array('d' => $this->id(),
                                    $action => implode(',', array_keys($views)),
                                    'sesskey' => sesskey(), 'confirmed' => 1)),
                        new moodle_url('/mod/datalynx/view/index.php', array('d' => $this->id())));

                echo $OUTPUT->footer();
                exit();
            } else {
                // go ahead and perform the requested action
                switch ($action) {
                    case 'visible':
                        $updateview = new stdClass();
                        foreach ($views as $vid => $view) {
                            if ($vid == $this->data->defaultview) {
                                // TODO: notify something
                                continue;
                            } else {
                                $updateview->id = $vid;
                                $DB->update_record('datalynx_views', $updateview);

                                $other = array('dataid' => $this->id());
                                $event = \mod_datalynx\event\view_updated::create(
                                        array('context' => $this->context, 'objectid' => $vid,
                                            'other' => $other));
                                $event->trigger();

                                $processedvids[] = $vid;
                            }
                        }

                        $strnotify = '';
                        break;

                    case 'filter':
                        $updateview = new stdClass();
                        $filterid = optional_param('fid', 0, PARAM_INT);
                        foreach ($views as $vid => $view) {
                            if ($filterid != $view->view->filter) {
                                $updateview->id = $vid;
                                if ($filterid == -1) {
                                    $updateview->filter = 0;
                                } else {
                                    $updateview->filter = $filterid;
                                }
                                $DB->update_record('datalynx_views', $updateview);

                                $other = array('dataid' => $this->id());
                                $event = \mod_datalynx\event\view_updated::create(
                                        array('context' => $this->context, 'objectid' => $vid,
                                            'other' => $other));
                                $event->trigger();

                                $processedvids[] = $vid;
                            }
                        }

                        $strnotify = 'viewsupdated';
                        break;

                    case 'reset':
                        foreach ($views as $vid => $view) {
                            // generate default view and update
                            $view->generate_default_view();

                            // update view
                            $view->update($view->view);

                            $other = array('dataid' => $this->id());
                            $event = \mod_datalynx\event\view_updated::create(
                                    array('context' => $this->context, 'objectid' => $vid,
                                        'other' => $other));
                            $event->trigger();

                            $processedvids[] = $vid;
                        }

                        $strnotify = 'viewsupdated';
                        break;

                    case 'duplicate':
                        foreach ($views as $vid => $view) {
                            // TODO: check for limit

                            // set name
                            if ($this->name_exists('views', $view->name())) {
                                $copyname = $view->view->name = 'Copy of ' . $view->name();
                            }
                            $i = 2;
                            while ($this->name_exists('views', $view->name())) {
                                $view->view->name = $copyname . " ($i)";
                                $i++;
                            }
                            // reset id
                            $oldviewid = $view->view->id;
                            $view->view->id = 0;
                            $viewid = $view->add($view->view);

                            $newviewid = $viewid;
                            $contextid = $this->context->id;
                            $component = 'mod_datalynx';
                            $fs = get_file_storage();
                            foreach (array('viewsection', 'viewparam2'
                            ) as $filearea) {
                                $files = $fs->get_area_files($contextid, $component, $filearea,
                                        $oldviewid);
                                foreach ($files as $file) {
                                    if ($file->is_directory() and $file->get_filepath() === '/') {
                                        continue;
                                    }
                                    $file_record = array('contextid' => $contextid,
                                        'component' => $component, 'filearea' => $filearea,
                                        'itemid' => $newviewid
                                    );
                                    $fs->create_file_from_storedfile($file_record, $file);
                                }
                            }

                            $other = array('dataid' => $this->id());
                            $event = \mod_datalynx\event\view_created::create(
                                    array('context' => $this->context, 'objectid' => $newviewid,
                                        'other' => $other));
                            $event->trigger();

                            $processedvids[] = $viewid;
                        }

                        $strnotify = 'viewsadded';
                        break;

                    case 'delete':
                        foreach ($views as $vid => $view) {
                            $view->delete();
                            $processedvids[] = $vid;

                            // reset default view if needed
                            if ($view->id() == $this->data->defaultview) {
                                $this->set_default_view();
                            }

                            $other = array('dataid' => $this->id());
                            $event = \mod_datalynx\event\view_deleted::create(
                                    array('context' => $this->context, 'objectid' => $vid,
                                        'other' => $other));
                            $event->trigger();
                        }

                        $strnotify = 'viewsdeleted';
                        break;

                    case 'default':
                        foreach ($views as $vid => $view) { // there should be only one
                            $this->set_default_view($vid);
                            $processedvids[] = $vid;
                            break;
                        }
                        $strnotify = '';
                        break;

                    default:
                        break;
                }

                if ($strnotify) {
                    $viewsprocessed = $processedvids ? count($processedvids) : 'No';
                    $this->notifications['good'][] = get_string($strnotify, 'datalynx', $viewsprocessed);
                }
                return $processedvids;
            }
        }
    }

    /**
     * ********************************************************************************
     * USER
     * *******************************************************************************
     */

    /**
     */
    public function get_gradebook_users(array $userids = null) {
        global $DB, $CFG;

        // get the list of users by gradebook roles
        if (!empty($CFG->gradebookroles)) {
            $gradebookroles = explode(",", $CFG->gradebookroles);
        } else {
            $gradebookroles = '';
        }

        if (!empty($CFG->enablegroupings) and $this->cm->groupmembersonly) {
            $groupingsusers = groups_get_grouping_members($this->cm->groupingid, 'u.id', 'u.id');
            $gusers = $groupingsusers ? array_keys($groupingsusers) : null;
        }

        if (!empty($userids)) {
            if (!empty($gusers)) {
                $gusers = array_intersect($userids, $gusers);
            } else {
                $gusers = $userids;
            }
        }

        if (isset($gusers)) {
            if (!empty($gusers)) {
                list($inuids, $params) = $DB->get_in_or_equal($gusers);
                return get_role_users($gradebookroles, $this->context, true,
                        user_picture::fields('u'), 'u.lastname ASC', true, $this->currentgroup, '',
                        '', "u.id $inuids", $params);
            } else {
                return null;
            }
        } else {
            return get_role_users($gradebookroles, $this->context, true,
                    'u.id, u.lastname, u.firstname', 'u.lastname ASC', true, $this->currentgroup);
        }
    }

    /**
     * has a user reached the max number of entries?
     * if interval is set then required entries, max entrie etc.
     * are relative to the current interval
     *
     * @return boolean
     */
    public function user_at_max_entries($perinterval = false) {
        if ($this->data->maxentries < 0 or
                 has_capability('mod/datalynx:manageentries', $this->context)) {
            return false;
        } else if ($this->data->maxentries == 0) {
            return true;
        } else {
            return ($this->user_num_entries($perinterval) >= $this->data->maxentries);
        }
    }

    /**
     * output bool
     */
    public function user_can_view_all_entries($options = null) {
        global $OUTPUT;
        if (has_capability('mod/datalynx:manageentries', $this->context)) {
            return true;
        } else {
            // Check the number of entries required against the number of entries already made
            $numentries = $this->user_num_entries();
            if ($this->data->entriesrequired and $numentries < $this->data->entriesrequired) {
                $entriesleft = $this->data->entriesrequired - $numentries;
                if (!empty($options['notify'])) {
                    echo $OUTPUT->notification(
                            get_string('entrieslefttoadd', 'datalynx', $entriesleft));
                }
            }

            // check separate participants group
            if ($this->groupmode == $this->internalgroupmodes['separateparticipants']) {
                return false;
            } else {
                // Check the number of entries required before to view other participant's entries
                // against the number of entries already made (doesn't apply to teachers)
                if ($this->data->entriestoview and $numentries < $this->data->entriestoview) {
                    $entrieslefttoview = $this->data->entriestoview - $numentries;
                    if (!empty($options['notify'])) {
                        echo $OUTPUT->notification(
                                get_string('entrieslefttoaddtoview', 'datalynx', $entrieslefttoview));
                    }
                    return false;
                } else {
                    return true;
                }
            }
        }
    }

    /**
     */
    public function user_can_export_entry($entry = null) {
        global $CFG, $USER;
        // we need portfolios for export
        if (!empty($CFG->enableportfolios)) {

            // can export all entries
            if (has_capability('mod/datalynx:exportallentries', $this->context)) {
                return true;
            }

            // for others, it depends on the entry
            if (isset($entry->id) and $entry->id > 0) {
                if (has_capability('mod/datalynx:exportownentry', $this->context)) {
                    if (!$this->data->grouped and $USER->id == $entry->userid) {
                        return true;
                    } else if ($this->data->grouped and groups_is_member($entry->groupid)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     */
    public function user_can_manage_entry($entry = null) {
        global $USER, $CFG;

        // teachers can always manage entries
        if (has_capability('mod/datalynx:manageentries', $this->context)) {
            return true;
        }

        // anonymous/guest can only add entries if enabled
        if ((!isloggedin() or isguestuser()) and empty($entry->id) and $CFG->datalynx_anonymous and
                 $this->data->anonymous) {
            return true;
        }

        // for others, it depends ...
        if (has_capability('mod/datalynx:writeentry', $this->context)) {
            $timeavailable = $this->data->timeavailable;
            $timedue = $this->data->timedue;
            $allowlate = $this->data->allowlate;
            $now = time();

            // activity time frame
            if ($timeavailable and !($now >= $timeavailable) or
                     ($timedue and !($now < $timedue) and !$allowlate)) {
                return false;
            }

            // group access
            if ($this->groupmode and !in_array($this->groupmode, $this->internalgroupmodes) and
                     !has_capability('moodle/site:accessallgroups', $this->context) and (($this->currentgroup and
                     !groups_is_member($this->currentgroup)) or
                     (!$this->currentgroup and $this->groupmode == VISIBLEGROUPS))) {
                return false; // for members only
            }

            // managing a certain entry
            if (!empty($entry->id)) {
                // entry owner
                // TODO groups_is_member queries DB for each entry!
                if (empty($USER->id) or (!$this->data->grouped and $USER->id != $entry->userid) or
                         ($this->data->grouped and !groups_is_member($entry->groupid))) {
                    return false; // who are you anyway???
                }

                require_once ('field/_status/field_class.php');
                if (!($entry->status == datalynxfield__status::STATUS_DRAFT ||
                         $entry->status == datalynxfield__status::STATUS_NOT_SET)) {
                    return false;
                }
                // ok owner, what's the time (limit)?
                if ($this->data->timelimit != -1) {
                    $timelimitsec = ($this->data->timelimit * 60);
                    $elapsed = $now - $entry->timecreated;
                    if ($elapsed > $timelimitsec) {
                        return false; // too late ...
                    }
                }

                // phew, within time limit, but wait, are we still in the same interval?
                if ($timeinterval = $this->data->timeinterval) {
                    $elapsed = $now - $timeavailable;
                    $currentintervalstarted = (floor($elapsed / $timeinterval) * $timeinterval) +
                             $timeavailable;
                    if ($entry->timecreated < $currentintervalstarted) {
                        return false; // nop ...
                    }
                }

                // trying to add an entry
            } else if ($this->user_at_max_entries(true)) {
                return false; // no more entries for you (come back next interval or so)
            }

            // if you got this far you probably deserve to do something ... go ahead
            return true;
        }

        return false;
    }

    /**
     * returns the number of entries already made by this user; defaults to all entries
     *
     * @param global $CFG, $USER
     * @param boolean $perinterval output int
     */
    public function user_num_entries($perinterval = false) {
        global $USER, $CFG, $DB;

        static $numentries = null;
        static $numentries_intervaled = null;

        if (!$perinterval and !is_null($numentries)) {
            return $numentries;
        }

        if ($perinterval and !is_null($numentries_intervaled)) {
            return $numentries_intervaled;
        }

        $params = array();
        $params['dataid'] = $this->id();

        $and_whereuserorgroup = '';
        $and_whereinterval = '';

        // go by user
        if (!$this->data->grouped) {
            $and_whereuserorgroup = " AND userid = :userid ";
            $params['userid'] = $USER->id;
            // go by group
        } else {
            $and_whereuserorgroup = " AND groupid = :groupid ";
            // if user is trying add an entry and got this far
            // the user should belong to the current group
            $params['groupid'] = $this->currentgroup;
        }

        // time interval
        if ($timeinterval = $this->data->timeinterval and $perinterval) {
            $timeavailable = $this->data->timeavailable;
            $elapsed = time() - $timeavailable;
            $intervalstarttime = (floor($elapsed / $timeinterval) * $timeinterval) + $timeavailable;
            $intervalendtime = $intervalstarttime + $timeinterval;
            $and_whereinterval = " AND timecreated >= :starttime AND timecreated < :endtime ";
            $params['starttime'] = $intervalstarttime;
            $params['endtime'] = $intervalendtime;
        }

        $sql = "SELECT COUNT(*)
                FROM {datalynx_entries}
                WHERE dataid = :dataid $and_whereuserorgroup $and_whereinterval";
        $entriescount = $DB->count_records_sql($sql, $params);

        if (!$perinterval) {
            $numentries = $entriescount;
        } else {
            $numentries_intervaled = $entriescount;
        }

        return $entriescount;
    }

    /**
     * ********************************************************************************
     * UTILITY
     * *******************************************************************************
     */
    const PERMISSION_MANAGER = 1;

    const PERMISSION_TEACHER = 2;

    const PERMISSION_STUDENT = 4;

    const PERMISSION_GUEST = 8;

    const PERMISSION_AUTHOR = 16;

    const PERMISSION_MENTOR = 32;

    const PERMISSION_ADMIN = 64;

    public function get_datalynx_permission_names($absoluteonly = false, $includeadmin = false) {
        $permissions = [];

        if ($includeadmin) {
            $permissions[self::PERMISSION_ADMIN] = get_string('admin', 'datalynx');
        }

        $permissions[self::PERMISSION_MANAGER] = get_string('visible_1', 'datalynx');
        $permissions[self::PERMISSION_TEACHER] = get_string('visible_2', 'datalynx');
        $permissions[self::PERMISSION_STUDENT] = get_string('visible_4', 'datalynx');
        $permissions[self::PERMISSION_GUEST] = get_string('visible_8', 'datalynx');

        if (!$absoluteonly) {
            $permissions[self::PERMISSION_AUTHOR] = get_string('author', 'datalynx');
            $permissions[self::PERMISSION_MENTOR] = get_string('mentor', 'datalynx');
        }

        return $permissions;
    }

    /**
     * Note: this function doesn't return contextual permissions (entry author/mentor).
     *
     * @param int $userid ID of the user. If not given the current user is presumed.
     * @param string $type 'any', 'both', 'view', or 'edit'. Defaults to 'any'.
     * @return int[] Array of IDs of permissions the user has in this instance.
     * @throws coding_exception
     */
    public function get_user_datalynx_permissions($userid = 0, $type = 'any') {
        global $USER, $DB;

        if (!$userid) {
            $user = $USER;
        } else {
            $user = $DB->get_record('user', array('id' => $userid));
        }

        $edit = $type === 'edit' || $type === 'both';
        $view = $type === 'view' || $type === 'both';

        $permissions = [];

        if ($type === 'any') {
            if (has_capability('mod/datalynx:viewprivilegeadmin', $this->context, $user, true) ||
                     has_capability('mod/datalynx:editprivilegeadmin', $this->context, $user, true)) {
                $permissions[] = self::PERMISSION_ADMIN;
            }
            if (has_capability('mod/datalynx:viewprivilegemanager', $this->context, $user, false) ||
                     has_capability('mod/datalynx:editprivilegemanager', $this->context, $user,
                            false)) {
                $permissions[] = self::PERMISSION_MANAGER;
            }
            if (has_capability('mod/datalynx:viewprivilegeteacher', $this->context, $user, false) ||
                     has_capability('mod/datalynx:editprivilegeteacher', $this->context, $user,
                            false)) {
                $permissions[] = self::PERMISSION_TEACHER;
            }
            if (has_capability('mod/datalynx:viewprivilegestudent', $this->context, $user, false) ||
                     has_capability('mod/datalynx:editprivilegestudent', $this->context, $user,
                            false)) {
                $permissions[] = self::PERMISSION_STUDENT;
            }
            if (has_capability('mod/datalynx:viewprivilegeguest', $this->context, $user, false) ||
                     has_capability('mod/datalynx:editprivilegeguest', $this->context, $user, false)) {
                $permissions[] = self::PERMISSION_GUEST;
            }
        } else if ($edit || $view) {
            if ((!$view ||
                     has_capability('mod/datalynx:viewprivilegeadmin', $this->context, $user, true)) &&
                     (!$edit ||
                     has_capability('mod/datalynx:editprivilegeadmin', $this->context, $user, true))) {
                $permissions[] = self::PERMISSION_ADMIN;
            }
            if ((!$view ||
                     has_capability('mod/datalynx:viewprivilegemanager', $this->context, $user,
                            false)) &&
                     (!$edit ||
                     has_capability('mod/datalynx:editprivilegemanager', $this->context, $user,
                            false))) {
                $permissions[] = self::PERMISSION_MANAGER;
            }
            if ((!$view ||
                     has_capability('mod/datalynx:viewprivilegeteacher', $this->context, $user,
                            false)) &&
                     (!$edit ||
                     has_capability('mod/datalynx:editprivilegeteacher', $this->context, $user,
                            false))) {
                $permissions[] = self::PERMISSION_TEACHER;
            }
            if ((!$view ||
                     has_capability('mod/datalynx:viewprivilegestudent', $this->context, $user,
                            false)) &&
                     (!$edit ||
                     has_capability('mod/datalynx:editprivilegestudent', $this->context, $user,
                            false))) {
                $permissions[] = self::PERMISSION_STUDENT;
            }
            if ((!$view ||
                     has_capability('mod/datalynx:viewprivilegeguest', $this->context, $user, false)) &&
                     (!$edit ||
                     has_capability('mod/datalynx:editprivilegeguest', $this->context, $user, false))) {
                $permissions[] = self::PERMISSION_GUEST;
            }
        } else {
            debug("Invalid \$type parameter: $type");
        }

        return $permissions;
    }

    /**
     */
    public function name_exists($table, $name, $id = 0) {
        global $DB;

        $params = array($this->id(), $name, $id);

        $where = " dataid = ? AND name = ? AND id <> ? ";
        return $DB->record_exists_select("datalynx_{$table}", $where, $params);
    }

    /**
     * // TODO
     */
    public function settings_navigation() {
    }

    /**
     */
    public function convert_arrays_to_strings(&$fieldinput) {
        foreach ($fieldinput as $key => $val) {
            if (is_array($val)) {
                $str = '';
                foreach ($val as $inner) {
                    $str .= $inner . ',';
                }
                $str = substr($str, 0, -1);
                $fieldinput->$key = $str;
            }
        }
    }

    /**
     * Method triggering entry-based events
     *
     * @param $event
     * @param $data
     */
    public function events_trigger($event, $data) {
        $data->df = $this;
        $data->coursename = $this->course->shortname;
        $data->datalynxname = $this->name();
        if (isset($data->view)) {
            $data->datalynxbaselink = html_writer::link($data->view->get_df()->get_baseurl(),
                    $data->datalynxname);
            $data->datalynxlink = html_writer::link($data->view->get_baseurl(), $data->datalynxname);
        }
        $data->context = $this->context->id;
        $data->event = $event;
        $data->notification = 1;
        $data->notificationformat = 1;

        $other = array('dataid' => $this->id());

        foreach ($data->items as $id => $item) {
            switch ($event) {
                case 'entryadded':
                    $event = \mod_datalynx\event\entry_created::create(
                            array('context' => $this->context, 'objectid' => $id, 'other' => $other));
                    $event->trigger();
                    break;
                case 'entryupdated':
                    $event = \mod_datalynx\event\entry_updated::create(
                            array('context' => $this->context, 'objectid' => $id, 'other' => $other));
                    $event->trigger();
                    break;
                case 'entrydeleted':
                    $event = \mod_datalynx\event\entry_deleted::create(
                            array('context' => $this->context, 'objectid' => $id, 'other' => $other));
                    $event->trigger();
                    break;
                case 'entryapproved':
                    $event = \mod_datalynx\event\entry_approved::create(
                            array('context' => $this->context, 'objectid' => $id, 'other' => $other));
                    $event->trigger();
                    break;
                case 'entrydisapproved':
                    $event = \mod_datalynx\event\entry_disapproved::create(
                            array('context' => $this->context, 'objectid' => $id, 'other' => $other));
                    $event->trigger();
                    break;
                default:
                    break;
            }
        }
    }

    public function get_baseurl() {
        // base url params
        $baseurlparams = array();
        $baseurlparams['d'] = $this->id();
        return new moodle_url("/mod/datalynx/{$this->pagefile()}.php", $baseurlparams);
    }

    /**
     * Given the ID of a textfield for this instance, it returns all used values as array
     *
     * @param int $id datalynx-instance-ID
     * @return string[] array with used text-fields-values!
     */
    public function get_distinct_textfieldvalues_by_id($id) {
        global $DB;

        $sql = "SELECT DISTINCT c.content FROM {datalynx_contents} as c WHERE c.fieldid = :fieldid ORDER BY c.content";
        $sqlparams['fieldid'] = $id;

        $textfieldvalues = $DB->get_fieldset_sql($sql, $sqlparams);

        return array_combine($textfieldvalues, $textfieldvalues);
    }
}

