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
 * @copyright based on the work by 2012 Itamar Tzadok
 * @copyright 2015 onwards edulabs.org
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;
use completion_info;
use context_module;
use datalynx_filter_manager;
use datalynx_preset_manager;
use datalynx_rule_manager;
use html_writer;
use moodle_page;
use moodle_url;
use stdClass;
use moodle_exception;
use comment;
defined('MOODLE_INTERNAL') or die();

/**
 * Class datalynx
 *
 * @package mod_datalynx
 */
class datalynx {

    const COUNT_ALL = 0;

    const COUNT_APPROVED = 1;

    const COUNT_UNAPPROVED = 2;

    const COUNT_LEFT = 3;

    // No approval required.
    const APPROVAL_NONE = 0;

    // Approval for new entries and updates required.
    const APPROVAL_ON_UPDATE = 1;

    // Approval only for new entries required.
    const APPROVAL_ON_NEW = 2;

    /**
     * @var stdClass course module
     */
    public $cm = null;

    /**
     * @var stdClass fieldset object of the course
     */
    public $course = null;

    /**
     * @var stdClass fieldset record of datalynx instance
     */
    public $data = null;

    /**
     * @var context_module|null
     */
    public $context = null;

    /**
     * @var int
     */
    public $groupmode = 0;

    /**
     * @var int|mixed
     */
    public $currentgroup = 0;

    /**
     * @var array
     */
    public $notifications = array('bad' => array(), 'good' => array());

    protected $pagefile = 'view';

    protected $fields = array();

    protected $views = array();

    protected $_filtermanager = null;

    protected $_customfiltermanager = null;

    protected $_rulemanager = null;

    protected $_presetmanager = null;

    protected $_currentview = null;

    protected $internalfields = array();

    protected $customfilterfields = array();

    protected $internalgroupmodes = array('separateparticipants' => -1);

    /**
     * datalynx constructor.
     *
     * @param int $d (id of datalynx instance fetchec from db table)
     * @param int $id (course module id)
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws moodle_exception
     */
    public function __construct($d = 0, $id = 0) {
        global $DB;

        // Initialize from datalynx id or object.
        if ($d) {
            if (is_object($d)) { // Try object first.
                $this->data = $d;
            } else {
                if (!$this->data = $DB->get_record('datalynx', array('id' => $d))) {
                    throw new moodle_exception('invaliddatalynx', 'datalynx', null, null,
                            "Datalynx id: $d");
                }
            }
            if (!$this->course = $DB->get_record('course', array('id' => $this->data->course))) {
                throw new moodle_exception('invalidcourse', 'datalynx', null, null,
                        "Course id: {$this->data->course}");
            }
            if (!$this->cm = get_coursemodule_from_instance('datalynx', $this->id(),
                    $this->course->id)
            ) {
                throw new moodle_exception('invalidcoursemodule', 'datalynx', null, null,
                        "Cm id: {$this->id()}");
            }
            // Initialize from course module id.
        } else {
            if ($id) {
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
        }

        // Get context.
        $this->context = context_module::instance($this->cm->id);

        // Set groups.
        if ($this->cm->groupmode and in_array($this->cm->groupmode, $this->internalgroupmodes)) {
            $this->groupmode = $this->cm->groupmode;
        } else {
            $this->groupmode = groups_get_activity_groupmode($this->cm);
            $this->currentgroup = groups_get_activity_group($this->cm, true);
        }

    }

    /**
     * Get datalynx object by instanceid (id of datalynx table)
     *
     * @param $instanceid
     * @return mod_datalynx\datalynx
     * @throws \coding_exception
     */
    public static function get_datalynx_by_instance($instanceid) {
        $cm = get_coursemodule_from_instance('datalynx', $instanceid);
        return new mod_datalynx\datalynx($instanceid, $cm->id);
    }

    /**
     * Get datalynx id.
     *
     * @return mixed
     */
    public function id() {
        return $this->data->id;
    }

    /**
     * Get name of datalynx instance.
     *
     * @return mixed
     */
    public function name() {
        return $this->data->name;
    }

    /**
     * @return string
     */
    public function pagefile() {
        return $this->pagefile;
    }

    /**
     * Get internal group modes.
     *
     * @return array
     */
    public function internal_group_modes() {
        return $this->internalgroupmodes;
    }

    /**
     * Get current view.
     *
     * @return null|object view object
     */
    public function get_current_view() {
        return $this->_currentview;
    }

    /**
     * Get filter manager.
     *
     * @return datalynx_filter_manager|null
     */
    public function get_filter_manager() {
        global $CFG;
        // Set filters manager.
        if (!$this->_filtermanager) {
            require_once($CFG->dirroot . '/mod/datalynx/filter/filter_class.php');
            $this->_filtermanager = new datalynx_filter_manager($this);
        }
        return $this->_filtermanager;
    }

    /**
     * Get custom filter manager.
     *
     * @return customfilter\manager|null
     */
    public function get_customfilter_manager() {
        if (!$this->_customfiltermanager) {
            $this->_customfiltermanager = new customfilter\manager($this);
        }
        return $this->_customfiltermanager;
    }

    /**
     * Get rule manager.
     *
     * @return datalynx_rule_manager|null
     */
    public function get_rule_manager() {
        global $CFG;
        // Set rules manager.
        if (!$this->_rulemanager) {
            require_once($CFG->dirroot . '/mod/datalynx/rule/rule_manager.php');
            $this->_rulemanager = new datalynx_rule_manager($this);
        }
        return $this->_rulemanager;
    }

    /**
     * Get preset manager.
     *
     * @return datalynx_preset_manager|null
     */
    public function get_preset_manager() {
        global $CFG;
        // Set preset manager.
        if (!$this->_presetmanager) {
            require_once($CFG->dirroot . '/mod/datalynx/preset/preset_manager.php');
            $this->_presetmanager = new datalynx_preset_manager($this);
        }
        return $this->_presetmanager;
    }

    /**
     * Get number of entries.
     *
     * @param string $type
     * @param int $user
     * @return int|string
     * @throws \dml_exception
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
     * Update datalynx settings
     *
     * @param $params
     * @param string $notify
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
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
                    } else {
                        if ($notify) {
                            $this->notifications['bad'][] = $notify;
                        }
                    }
                    return false;
                } else {
                    if (!$notify === true) {
                        if ($notify) {
                            $this->notifications['good'][] = $notify;
                        }
                    }
                }
            }
        }
        return true;
    }

    /**
     * Sets the datalynx page
     *
     * @param string $page current page
     * @param array $params
     * @throws moodle_exception
     * @return string output
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

        // Make sure there is at least datalynx id param.
        $urlparams['d'] = $thisid;

        $manager = has_capability('mod/datalynx:managetemplates', $this->context);

        // If datalynx activity closed don't let students in.
        if (!$manager) {
            $timenow = time();
            if (!empty($this->data->timeavailable) and $this->data->timeavailable > $timenow) {
                throw new moodle_exception('notopenyet', 'datalynx', '',
                        userdate($this->data->timeavailable));
            }
        }

        // RSS.
        if (!empty($params->rss) and !empty($CFG->enablerssfeeds) and
                !empty($CFG->datalynx_enablerssfeeds) and $this->data->rssarticles > 0
        ) {
            require_once("$CFG->libdir/rsslib.php");
            $rsstitle = format_string($this->course->shortname) . ': %fullname%';
            rss_add_http_header($this->context, 'mod_datalynx', $this->data, $rsstitle);
        }

        // COMMENTS.
        if (!empty($params->comments)) {
            require_once("$CFG->dirroot/comment/lib.php");
            comment::init();
        }

        $fs = get_file_storage();

        // PAGE setup for activity pages only.

        if ($page != 'external') {
            // Is user editing.
            $urlparams['edit'] = optional_param('edit', 0, PARAM_BOOL);
            $PAGE->set_url("/mod/datalynx/$page.php", $urlparams);

            // Blocks editing button (omit in embedded datalynxs).
            if ($page != 'embed' and $PAGE->user_allowed_editing()) {
                // Teacher editing mode.
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

            // Auto refresh.
            if (!empty($urlparams['refresh'])) {
                $PAGE->set_periodic_refresh_delay($urlparams['refresh']);
            }

            // Page layout.
            if (!empty($params->pagelayout)) {
                $PAGE->set_pagelayout($params->pagelayout);
            }

            $PAGE->requires->css(
                    new moodle_url(
                            $CFG->wwwroot . '/mod/datalynx/field/picture/zoomable/zoomable.css'));

            // If completion is on: Mark activity as viewed.
            if (!empty($params->completion)) {
                require_once($CFG->libdir . '/completionlib.php');
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
                $editbodyclass = $editmode ? 'datalynx-editentry' : 'datalynx-displayentry';
                $pagename = "{$modulename}: {$viewname}: {$pagestring} {$pagenum}{$edit}";
                $PAGE->set_title($pagename);
                $PAGE->add_body_classes([$editbodyclass]);
            } else {
                $manage = get_string('managemode', 'datalynx');
                $what = strpos($page, 'view') !== false ? get_string('views', 'datalynx') : '???';
                $what = strpos($page, 'field') !== false ? get_string('fields', 'datalynx') : $what;
                $what = strpos($page, 'filter') !== false ? get_string('filters', 'datalynx') : $what;
                $what = strpos($page, 'customfilter') !== false ? get_string('customfilters', 'datalynx') : $what;
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

            // Include blocks dragdrop when blocks/moodle editing.
            if ($PAGE->user_is_editing()) {
                $params = array('courseid' => $this->course->id, 'cmid' => $this->cm->id,
                        'pagetype' => $PAGE->pagetype, 'pagelayout' => $PAGE->pagelayout,
                        'regions' => $PAGE->blocks->get_regions()
                );
                $PAGE->requires->yui_module('moodle-core-blocks', 'M.core_blocks.init_dragdrop',
                        array($params), null, true);
            }
        }

        // PAGE setup for datalynx content anywhere.

        // Use this to return css if this df page is set after header.
        $output = '';

        // CSS (cannot be required after head).
        $cssurls = array();
        if (!empty($params->css)) {
            // Js includes from the js template.
            if ($this->data->cssincludes) {
                foreach (explode("\n", $this->data->cssincludes) as $cssinclude) {
                    $cssinclude = trim($cssinclude);
                    if ($cssinclude) {
                        $cssurls[] = new moodle_url($cssinclude);
                    }
                }
            }
            // Uploaded css files.
            if ($files = $fs->get_area_files($this->context->id, 'mod_datalynx', 'css', 0,
                    'sortorder', false)
            ) {
                $path = "/{$this->context->id}/mod_datalynx/css/0";
                foreach ($files as $file) {
                    $filename = $file->get_filename();
                    $cssurls[] = moodle_url::make_file_url('/pluginfile.php', "$path/$filename");
                }
            }
            // Css code from the css template.
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

        // JS.
        $jsurls = array();
        if (!empty($params->js)) {
            // Js includes from the js template.
            if ($this->data->jsincludes) {
                foreach (explode("\n", $this->data->jsincludes) as $jsinclude) {
                    $jsinclude = trim($jsinclude);
                    if ($jsinclude) {
                        $jsurls[] = new moodle_url($jsinclude);
                    }
                }
            }
            // Uploaded js files.
            if ($files = $fs->get_area_files($this->context->id, 'mod_datalynx', 'js', 0,
                    'sortorder', false)
            ) {
                $path = "/{$this->context->id}/mod_datalynx/js/0";
                foreach ($files as $file) {
                    $filename = $file->get_filename();
                    $jsurls[] = moodle_url::make_file_url('/pluginfile.php', "$path/$filename");
                }
            }
            // Js code from the js template.
            if ($this->data->js) {
                $jsurls[] = new moodle_url('/mod/datalynx/js.php', array('d' => $thisid));
            }
        }
        foreach ($jsurls as $jsurl) {
            $PAGE->requires->js($jsurl);
        }

        // MOD JS.
        if (!empty($params->modjs)) {
            $PAGE->requires->js('/mod/datalynx/datalynx.js');
        }

        // Set current view and view's page requirements.
        $currentview = !empty($urlparams['view']) ? $urlparams['view'] : 0;
        $this->_currentview = $this->get_current_view_from_id($currentview);

        // If a new datalynx or incomplete design, direct manager to manage area.
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
            } else {
                if (!$this->data->defaultview) {
                    $linktoviews = html_writer::link(
                            new moodle_url('/mod/datalynx/view/index.php', array('d' => $thisid)),
                            get_string('views', 'datalynx'));
                    $this->notifications['bad']['defaultview'] = get_string('viewnodefault', 'datalynx', $linktoviews);
                }
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
        global $OUTPUT, $CFG;

        $params = (object) $params;

        echo $OUTPUT->header();

        // Print intro.
        if (!empty($params->heading)) {
            echo $OUTPUT->heading(format_string($this->name()));
        }

        // Print intro.
        if (!empty($params->intro) and $params->intro) {
            $this->print_intro();
        }

        // Print the tabs.
        if (!empty($params->tab)) {
            $currenttab = $params->tab;
            include($CFG->dirroot . '/mod/datalynx/tabs.php');
        }

        // Print groups menu if needed.
        if (!empty($params->groups)) {
            $this->print_groups_menu($params->urlparams->view, $params->urlparams->filter);
        }

        // TODO: explore letting view decide whether to print rsslink and intro.

        // Print any notices.
        if (empty($params->nonotifications)) {
            foreach ($this->notifications['good'] as $notification) {
                if (!empty($notification)) {
                    echo $OUTPUT->notification($notification, 'notifysuccess'); // Good (usually.
                    // Green).
                }
            }
            foreach ($this->notifications['bad'] as $notification) {
                if (!empty($notification)) {
                    echo $OUTPUT->notification($notification); // Bad (usually red).
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
        // Link to the RSS feed.
        if (!empty($CFG->enablerssfeeds) && !empty($CFG->datalynx_enablerssfeeds) &&
                $this->data->rssarticles > 0
        ) {
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
        // TODO: make intro stickily closable.
        // Display the intro only when there are on pages: if ($this->data->intro and empty($page)).
        if ($this->data->intro) {
            $options = new stdClass();
            $options->noclean = true;
            echo $OUTPUT->box(format_module_intro('datalynx', $this->data, $this->cm->id), 'generalbox', 'intro');
        }
    }

    /**
     * Set view content.
     */
    public function set_content() {
        if (!empty($this->_currentview)) {
            $this->_currentview->process_data();
            $this->_currentview->set_content();
        }
    }

    /**
     * Output the view
     *
     * @throws \coding_exception
     */
    public function display() {
        global $PAGE;
        if (!empty($this->_currentview)) {

            $event = event\course_module_viewed::create(
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
        require_once($CFG->dirroot . '/mod/datalynx/view/view_class.php');
        $urlparams = new stdClass();
        $datalynx = new mod_datalynx\datalynx($datalynxid, null);
        $urlparams->d = $datalynxid;
        $urlparams->view = $viewid;
        $urlparams->pagelayout = 'external';
        if ($eids) {
            $urlparams->eids = $eids;
        }

        $pageparams = array('js' => true, 'css' => true, 'rss' => true, 'modjs' => true,
                'completion' => true, 'comments' => true, 'urlparams' => $urlparams);
        $datalynx->set_page('external', $pageparams);
        $type = $datalynx->views[$viewid]->type;
        require_once($CFG->dirroot . "/mod/datalynx/view/$type/view_class.php");
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
     *
     * @return array
     */
    public function get_internal_fields() {
        global $CFG;

        if (!$this->internalfields) {
            $fieldplugins = get_list_of_plugins('mod/datalynx/field/');
            foreach ($fieldplugins as $fieldname) {
                require_once("$CFG->dirroot/mod/datalynx/field/$fieldname/field_class.php");
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
     * Return the names of the internal fields
     * @return array
     */
    public function get_internal_fields_names() {
        global $CFG;

        $fieldplugins = get_list_of_plugins('mod/datalynx/field/');
        $internalfieldsnames = array();
        foreach ($fieldplugins as $fieldname) {
            require_once("$CFG->dirroot/mod/datalynx/field/$fieldname/field_class.php");
            $fieldclass = "datalynxfield_$fieldname";
            if (!$fieldclass::is_internal()) {
                continue;
            }
            $internalfields = $fieldclass::get_field_objects($this->data->id);
            foreach ($internalfields as $fid => $field) {
                $internalfieldsnames[$fid] = $field->name;
            }
        }

        return $internalfieldsnames;
    }

    /**
     * Returns an array of fields, suitable for use in customfilter form.
     *
     * @return array of strings
     */
    public function get_customfilterfieldtypes() {
        global $CFG;

        if (!$this->customfilterfields) {
            $this->customfilterfields = array();
            // Collate customfilter fields.
            $fieldplugins = get_list_of_plugins('mod/datalynx/field/');
            foreach ($fieldplugins as $fieldname) {
                require_once("$CFG->dirroot/mod/datalynx/field/$fieldname/field_class.php");
                $fieldclass = "datalynxfield_$fieldname";
                if ($fieldclass::is_internal()) {
                    continue;
                }
                if ($fieldclass::is_customfilterfield()) {
                    $this->customfilterfields[] = $fieldname;
                }
            }
        }

        return $this->customfilterfields;
    }

    /**
     * Given a field id return the field object from get_fields
     * Initializes get_fields if necessary
     *
     * @param number $fieldid
     * @param boolean $forceget
     * @return boolean|datalynxfield_base
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
     * Given a field type returns the field object from get_fields
     * Initializes get_fields if necessary
     *
     * @param string $type
     * @param string $menu
     * @return NULL[]|datalynxfield_base[]
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
     * Given a field name returns the field object from get_fields
     *
     * @param string $name
     * @return datalynxfield_base|boolean
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
     *
     * @param int|object $key
     * @return bool
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
            require_once($CFG->dirroot . '/mod/datalynx/field/' . $type . '/field_class.php');
            $fieldclass = 'datalynxfield_' . $type;
            $field = new $fieldclass($this, $key);
            return $field;
        } else {
            return false;
        }
    }

    /**
     * Returns a subclass field object given a record of the field
     * used to invoke plugin methods
     *
     * @param string $key
     * @return stdClass|boolean
     */
    public function get_fieldname($key) {
        global $CFG;
        if ($key) {
            if (is_object($key)) {
                $type = $key->type;
            } else {
                $type = $key;
                $key = 0;
            }
            require_once($CFG->dirroot . '/mod/datalynx/field/' . $type . '/field_class.php');
            $fieldclass = 'datalynxfield_' . $type;
            $field = new $fieldclass($this, $key);
            return $field;
        } else {
            return false;
        }
    }

    /**
     * Get fields of datalynx instance
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
            // Collate user fields.
            if ($fields = $DB->get_records('datalynx_fields', array('dataid' => $this->id()), $sort)) {
                foreach ($fields as $fieldid => $field) {
                    $this->fields[$fieldid] = $this->get_field($field);
                }
            }
        }

        // Collate all fields.
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

    /**
     * @param string $sort
     * @return array
     * @throws \dml_exception
     */
    public function get_fieldnames($sort = '') {
        global $DB;

        $fieldnames = array();
        // Collate user fields.
        if ($fields = $DB->get_records('datalynx_fields', array('dataid' => $this->id()), $sort)) {
            foreach ($fields as $fieldid => $field) {
                $fieldnames[$fieldid] = $field->name;
            }
        }

        // Collate all fields.
        $retfieldnames = $fieldnames + $this->get_internal_fields_names();

        return $retfieldnames;
    }

    /**
     * Find filters via fields
     *
     * @param array $fields
     * @return array
     * @throws \dml_exception
     */
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
     * @return boolean
     */
    public function process_fields($action, $fids, $confirmed = false) {
        global $OUTPUT, $DB;

        if (!has_capability('mod/datalynx:managetemplates', $this->context)) {
            // TODO throw exception.
            return false;
        }

        $dffields = $this->get_fields();
        $fields = array();
        // Collate the fields for processing.
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
                // Print header.
                $this->print_header('fields');

                $msg = get_string("fieldsconfirm$action", 'datalynx', count($fields));
                if ($action === 'delete') {
                    $fieldlist = array_reduce($fields,
                            function($list, $field) {
                                return $list . "<li>{$field->field->name}</li>";
                            }, '');
                    $fieldlist = "<ul>$fieldlist</ul>";
                    $filters = $this->find_filters_using_fields($fields);
                    $filterlist = array_reduce($filters,
                            function($list, $filter) {
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
                // Go ahead and perform the requested action.
                switch ($action) {
                    case 'add': // TODO add new.
                        if ($forminput = data_submitted()) {
                            // Check for arrays and convert to a comma-delimited string.
                            $this->convert_arrays_to_strings($forminput);

                            // Create a field object to collect and store the data safely.
                            $field = $this->get_field($forminput->type);
                            $field->insert_field($forminput);

                            $other = array('dataid' => $this->id());
                            $event = event\field_created::create(
                                    array('context' => $this->context,
                                            'objectid' => $field->field->id, 'other' => $other
                                    )
                            );
                            $event->trigger();
                        }
                        $strnotify = 'fieldsadded';
                        break;

                    case 'update': // Update existing.
                        if ($forminput = data_submitted()) {
                            // Check for arrays and convert to a comma-delimited string.
                            $this->convert_arrays_to_strings($forminput);

                            // Create a field object to collect and store the data safely.
                            $field = reset($fields);
                            $oldfieldname = $field->field->name;
                            $field->update_field($forminput);

                            $other = array('dataid' => $this->id());
                            $event = event\field_updated::create(
                                    array('context' => $this->context,
                                            'objectid' => $field->field->id, 'other' => $other
                                    )
                            );
                            $event->trigger();

                            // Update the views.
                            if ($oldfieldname != $field->field->name) {
                                $this->replace_field_in_views($oldfieldname, $field->field->name);
                            }
                        }
                        $strnotify = 'fieldsupdated';
                        break;

                    case 'editable':
                        foreach ($fields as $fid => $field) {
                            // Lock = 0; unlock = -1;.
                            $editable = $field->field->edits ? 0 : -1;
                            $DB->set_field('datalynx_fields', 'edits', $editable,
                                    array('id' => $fid));
                            $processedfids[] = $fid;
                            $other = array('dataid' => $this->id());
                            $event = event\field_updated::create(
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
                            // Set new name.
                            while ($this->name_exists('fields', $field->name())) {
                                $field->field->name .= '_1';
                            }
                            $fieldid = $DB->insert_record('datalynx_fields', $field->field);
                            $processedfids[] = $fieldid;

                            $other = array('dataid' => $this->id());
                            $event = event\field_created::create(
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
                            // Update views.
                            $this->replace_field_in_views($field->field->name, '');

                            $other = array('dataid' => $this->id());
                            $event = event\field_deleted::create(
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

                            // Convert field content to HTML.
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
                            // Convert field type to editor.
                            $DB->set_field('datalynx_fields', 'type', 'editor', array('id' => $fid));

                            $other = array('dataid' => $this->id());
                            $event = event\field_updated::create(
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
     *
     * @return array of view objects indexed by view id, empty array if no views are found
     */
    public function get_all_views() {
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
     * @throws \coding_exception
     */
    public function is_visible_to_user($view) {
        $isadmin = has_capability('mod/datalynx:viewprivilegeadmin', $this->context, null, true);
        $mask = has_capability('mod/datalynx:viewprivilegemanager', $this->context, null, false) ? 1 : 0;
        $mask |= has_capability('mod/datalynx:viewprivilegeteacher', $this->context, null, false) ? 2 : 0;
        $mask |= has_capability('mod/datalynx:viewprivilegestudent', $this->context, null, false) ? 4 : 0;
        $mask |= has_capability('mod/datalynx:viewprivilegeguest', $this->context, null, false) ? 8 : 0;
        return $isadmin || ($view->visible & $mask);
    }

    /**
     * TODO there is no need to instantiate all views!!!
     * this function creates an instance of the particular subtemplate class
     *
     * @param int $viewid
     * @return bool|mixed
     */
    public function get_current_view_from_id($viewid = 0) {
        if ($views = $this->get_view_records()) {
            if ($viewid and isset($views[$viewid])) {
                $view = $views[$viewid];
                // If can't find the requested, try the default.
            } else {
                if ($viewid = $this->data->defaultview and isset($views[$viewid])) {
                    $view = $views[$viewid];
                } else {
                    return false;
                }
            }
            return $this->get_view($view, true);
        }
        return false;
    }

    /**
     * TODO there is no need to instantiate all views!!!
     * this function creates an instance of the particular subtemplate class *
     *
     * @param int $viewid
     * @return bool|mixed
     */
    public function get_view_from_id($viewid = 0) {
        if ($views = $this->get_view_records()) {
            if ($viewid and isset($views[$viewid])) {
                $view = $views[$viewid];
                // If can't find the requested, try the default.
            } else {
                if ($viewid = $this->data->defaultview and isset($views[$viewid])) {
                    $view = $views[$viewid];
                } else {
                    return false;
                }
            }
            return $this->get_view($view);
        }
        return false;
    }

    /**
     * returns a view subclass object given a view record or view type
     * invoke plugin methods
     * input: $param $vt - mixed, view record or view type
     *
     * @param $viewortype
     * @param bool $active
     * @return mixed
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
            require_once($CFG->dirroot . '/mod/datalynx/view/' . $type . '/view_class.php');
            $viewclass = 'datalynxview_' . $type;
            return new $viewclass($this, $viewortype, $active);
        }
    }

    /**
     * given a view type returns the view object from $this->views
     * Initializes $this->views if necessary
     *
     * @param string $type
     * @param bool $forceget
     * @return array|bool
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
     *
     * @param string $exclude
     * @param boolean $forceget
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
     * Set default view
     *
     * @param int $viewid
     * @throws \dml_exception
     * @throws moodle_exception
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
     * Set default filter
     *
     * @param int $filterid
     * @throws \dml_exception
     * @throws moodle_exception
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
     * Set the default edit view
     *
     * @param int $viewid
     * @throws \dml_exception
     * @throws moodle_exception
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
     * Set view that is linked with the more-view patterns
     *
     * @param int $viewid
     * @throws \dml_exception
     * @throws moodle_exception
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
     * Search for a field name and replaces it with another one in all the
     * form templates.
     * Set $newfieldname as '' if you want to delete the field from the form.
     *
     * @param string $searchfieldname
     * @param string $newfieldname
     */
    public function replace_field_in_views($searchfieldname, $newfieldname) {
        if ($views = $this->get_views()) {
            foreach ($views as $view) {
                $view->replace_field_in_view($searchfieldname, $newfieldname);
            }
        }
    }

    /**
     * Apply actions to a view
     *
     * @param string $action
     * @param string $vids viewids comma separated
     * @param bool $confirmed
     * @return array|bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \stored_file_creation_exception
     * @throws moodle_exception
     */
    public function process_views($action, $vids, $confirmed = false) {
        global $DB, $OUTPUT;

        if (!has_capability('mod/datalynx:managetemplates', $this->context)) {
            // TODO throw exception.
            return false;
        }

        if ($vids) { // Some views are specified for action.
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
                // Print header.
                $this->print_header('views');

                // Print a confirmation page.
                echo $OUTPUT->confirm(get_string("viewsconfirm$action", 'datalynx', count($views)),
                        new moodle_url('/mod/datalynx/view/index.php',
                                array('d' => $this->id(),
                                        $action => implode(',', array_keys($views)),
                                        'sesskey' => sesskey(), 'confirmed' => 1)),
                        new moodle_url('/mod/datalynx/view/index.php', array('d' => $this->id())));

                echo $OUTPUT->footer();
                exit();
            } else {
                // Go ahead and perform the requested action.
                switch ($action) {
                    case 'visible':
                        $updateview = new stdClass();
                        foreach ($views as $vid => $view) {
                            if ($vid == $this->data->defaultview) {
                                // TODO: notify something.
                                continue;
                            } else {
                                $updateview->id = $vid;
                                $DB->update_record('datalynx_views', $updateview);

                                $other = array('dataid' => $this->id());
                                $event = event\view_updated::create(
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
                                $event = event\view_updated::create(
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
                            // Generate default view and update.
                            $view->generate_default_view();

                            // Update view.
                            $view->update($view->view);

                            $other = array('dataid' => $this->id());
                            $event = event\view_updated::create(
                                    array('context' => $this->context, 'objectid' => $vid,
                                            'other' => $other));
                            $event->trigger();

                            $processedvids[] = $vid;
                        }

                        $strnotify = 'viewsupdated';
                        break;

                    case 'duplicate':
                        foreach ($views as $vid => $view) {
                            // TODO: check for limit.

                            // Set name.
                            if ($this->name_exists('views', $view->name())) {
                                $copyname = $view->view->name = 'Copy of ' . $view->name();
                            }
                            $i = 2;
                            while ($this->name_exists('views', $view->name())) {
                                $view->view->name = $copyname . " ($i)";
                                $i++;
                            }
                            // Reset id.
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
                                    $filerecord = array('contextid' => $contextid,
                                            'component' => $component, 'filearea' => $filearea,
                                            'itemid' => $newviewid
                                    );
                                    $fs->create_file_from_storedfile($filerecord, $file);
                                }
                            }

                            $other = array('dataid' => $this->id());
                            $event = event\view_created::create(
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

                            // Reset default view if needed.
                            if ($view->id() == $this->data->defaultview) {
                                $this->set_default_view();
                            }

                            $other = array('dataid' => $this->id());
                            $event = event\view_deleted::create(
                                    array('context' => $this->context, 'objectid' => $vid,
                                            'other' => $other));
                            $event->trigger();
                        }

                        $strnotify = 'viewsdeleted';
                        break;

                    case 'default':
                        foreach ($views as $vid => $view) { // There should be only one.
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
     * @param array|null $userids
     * @return array|null
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_gradebook_users(array $userids = null) {
        global $DB, $CFG;

        // Get the list of users by gradebook roles.
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
     * Has a user reached the max number of entries?
     * If interval is set then required entries, max entries etc. are relative to the current interval
     *
     * @param bool $perinterval
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function user_at_max_entries($perinterval = false) {
        if ($this->data->maxentries < 0 or
                has_capability('mod/datalynx:manageentries', $this->context)
        ) {
            return false;
        } else {
            if ($this->data->maxentries == 0) {
                return true;
            } else {
                return ($this->user_num_entries($perinterval) >= $this->data->maxentries);
            }
        }
    }

    /**
     * Check if user has permission to view all entries.
     *
     * @param array $options
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function user_can_view_all_entries(array $options = null) {
        global $OUTPUT;
        if (has_capability('mod/datalynx:manageentries', $this->context)) {
            return true;
        } else {
            // Check the number of entries required against the number of entries already made.
            $numentries = $this->user_num_entries();
            if ($this->data->entriesrequired and $numentries < $this->data->entriesrequired) {
                $entriesleft = $this->data->entriesrequired - $numentries;
                if (!empty($options['notify'])) {
                    echo $OUTPUT->notification(
                            get_string('entrieslefttoadd', 'datalynx', $entriesleft));
                }
            }

            // Check separate participants group.
            if ($this->groupmode == $this->internalgroupmodes['separateparticipants']) {
                return false;
            } else {
                // Check the number of entries required before to view other participant's entries.
                // Against the number of entries already made (doesn't apply to teachers).
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
     * Check if user is allowed to export an entry.
     *
     * @param stdClass $entry
     * @return bool
     * @throws \coding_exception
     */
    public function user_can_export_entry($entry = null) {
        global $CFG, $USER;
        // We need portfolios for export.
        if (!empty($CFG->enableportfolios)) {

            // Can export all entries.
            if (has_capability('mod/datalynx:exportallentries', $this->context)) {
                return true;
            }

            // For others, it depends on the entry.
            if (isset($entry->id) and $entry->id > 0) {
                if (has_capability('mod/datalynx:exportownentry', $this->context)) {
                    if (!$this->data->grouped and $USER->id == $entry->userid) {
                        return true;
                    } else {
                        if ($this->data->grouped and groups_is_member($entry->groupid)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Has the actual user the right to edit any entries or the optional single entry parameter?
     *
     * @param $entry
     * @return boolean
     * @throws \coding_exception
     */
    public function user_can_manage_entry($entry = null) {
        global $USER, $CFG;

        // Teachers can always manage entries.
        if (has_capability('mod/datalynx:manageentries', $this->context)) {
            return true;
        }

        // Anonymous/guest can only add entries if enabled.
        if ((!isloggedin() or isguestuser()) and empty($entry->id) and $CFG->datalynx_anonymous and
                $this->data->anonymous
        ) {
            return true;
        }

        // For others, it depends ...
        if (has_capability('mod/datalynx:writeentry', $this->context)) {
            $timeavailable = $this->data->timeavailable;
            $timedue = $this->data->timedue;
            $allowlate = $this->data->allowlate;
            $now = time();

            // If there is an activity timeframe, we must be inside the timeframe right now.
            if ($timeavailable and !($now >= $timeavailable) or
                    ($timedue and !($now < $timedue) and !$allowlate)
            ) {
                return false;
            }

            // If group mode is enabled user has to be in the right group.
            if ($this->groupmode and !in_array($this->groupmode, $this->internalgroupmodes) and
                    !has_capability('moodle/site:accessallgroups', $this->context) and (($this->currentgroup and
                                    !groups_is_member($this->currentgroup)) or
                            (!$this->currentgroup and $this->groupmode == VISIBLEGROUPS))
            ) {
                return false; // For members only.
            }

            // Managing a certain entry.
            if (!empty($entry->id)) {
                // Entry owner.
                // TODO groups_is_member queries DB for each entry!
                if (empty($USER->id) or (!$this->data->grouped and $USER->id != $entry->userid) or
                        ($this->data->grouped and !groups_is_member($entry->groupid))
                ) {
                    return false; // Who are you anyway???
                }

                // If nor status 'draft' neither status 'not set' user is not allowed to manage this entry.
                require_once($CFG->dirroot . '/mod/datalynx/field/_status/field_class.php');
                if (!($entry->status == \datalynxfield__status::STATUS_DRAFT ||
                        $entry->status == \datalynxfield__status::STATUS_NOT_SET)
                ) {
                    return false;
                }
                // Ok owner, what's the time (limit)?
                if ($this->data->timelimit != -1) {
                    $timelimitsec = ($this->data->timelimit * 60);
                    $elapsed = $now - $entry->timecreated;
                    if ($elapsed > $timelimitsec) {
                        return false; // Too late ...
                    }
                }

                // Phew, within time limit, but wait, are we still in the same interval?
                if ($timeinterval = $this->data->timeinterval) {
                    $elapsed = $now - $timeavailable;
                    $currentintervalstarted = (floor($elapsed / $timeinterval) * $timeinterval) + $timeavailable;
                    if ($entry->timecreated < $currentintervalstarted) {
                        return false; // Nop ...
                    }
                }
            }// If you got this far you probably deserve to do something ... go ahead.
            return true;
        }

        return false;
    }

    /**
     * returns the number of entries already made by this user; defaults to all entries
     *
     * @param boolean $perinterval output int
     * @return integer
     * @throws \dml_exception
     */
    public function user_num_entries($perinterval = false) {
        global $USER, $CFG, $DB;

        static $numentries = null;
        static $numentriesintervaled = null;

        if (!$perinterval and !is_null($numentries)) {
            return $numentries;
        }

        if ($perinterval and !is_null($numentriesintervaled)) {
            return $numentriesintervaled;
        }

        $params = array();
        $params['dataid'] = $this->id();

        $andwhereuserorgroup = '';
        $andwhereinterval = '';

        // Go by user.
        if (!$this->data->grouped) {
            $andwhereuserorgroup = " AND userid = :userid ";
            $params['userid'] = $USER->id;
            // Go by group.
        } else {
            $andwhereuserorgroup = " AND groupid = :groupid ";
            // If user is trying add an entry and got this far.
            // The user should belong to the current group.
            $params['groupid'] = $this->currentgroup;
        }

        // Time interval.
        if ($timeinterval = $this->data->timeinterval and $perinterval) {
            $timeavailable = $this->data->timeavailable;
            $elapsed = time() - $timeavailable;
            $intervalstarttime = (floor($elapsed / $timeinterval) * $timeinterval) + $timeavailable;
            $intervalendtime = $intervalstarttime + $timeinterval;
            $andwhereinterval = " AND timecreated >= :starttime AND timecreated < :endtime ";
            $params['starttime'] = $intervalstarttime;
            $params['endtime'] = $intervalendtime;
        }

        $sql = "SELECT COUNT(*)
                FROM {datalynx_entries}
                WHERE dataid = :dataid $andwhereuserorgroup $andwhereinterval";
        $entriescount = $DB->count_records_sql($sql, $params);

        if (!$perinterval) {
            $numentries = $entriescount;
        } else {
            $numentriesintervaled = $entriescount;
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

    /**
     * Get permission localised permission names
     * TODO: Rename mentor, this is team member?
     *
     * @param bool $absoluteonly
     * @param bool $includeadmin
     * @return array
     * @throws \coding_exception
     */
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
                    has_capability('mod/datalynx:editprivilegeadmin', $this->context, $user, true)
            ) {
                $permissions[] = self::PERMISSION_ADMIN;
            }
            if (has_capability('mod/datalynx:viewprivilegemanager', $this->context, $user, false) ||
                    has_capability('mod/datalynx:editprivilegemanager', $this->context, $user,
                            false)
            ) {
                $permissions[] = self::PERMISSION_MANAGER;
            }
            if (has_capability('mod/datalynx:viewprivilegeteacher', $this->context, $user, false) ||
                    has_capability('mod/datalynx:editprivilegeteacher', $this->context, $user,
                            false)
            ) {
                $permissions[] = self::PERMISSION_TEACHER;
            }
            if (has_capability('mod/datalynx:viewprivilegestudent', $this->context, $user, false) ||
                    has_capability('mod/datalynx:editprivilegestudent', $this->context, $user,
                            false)
            ) {
                $permissions[] = self::PERMISSION_STUDENT;
            }
            if (has_capability('mod/datalynx:viewprivilegeguest', $this->context, $user, false) ||
                    has_capability('mod/datalynx:editprivilegeguest', $this->context, $user, false)
            ) {
                $permissions[] = self::PERMISSION_GUEST;
            }
        } else {
            if ($edit || $view) {
                if ((!$view ||
                                has_capability('mod/datalynx:viewprivilegeadmin', $this->context, $user, true)) &&
                        (!$edit ||
                                has_capability('mod/datalynx:editprivilegeadmin', $this->context, $user, true))
                ) {
                    $permissions[] = self::PERMISSION_ADMIN;
                    $permissions[] = self::PERMISSION_MANAGER; // Bug#876 Admin has manager permission by default.
                }
                if ((!$view ||
                                has_capability('mod/datalynx:viewprivilegemanager', $this->context, $user,
                                        false)) &&
                        (!$edit ||
                                has_capability('mod/datalynx:editprivilegemanager', $this->context, $user,
                                        false))
                ) {
                    $permissions[] = self::PERMISSION_MANAGER;
                }
                if ((!$view ||
                                has_capability('mod/datalynx:viewprivilegeteacher', $this->context, $user,
                                        false)) &&
                        (!$edit ||
                                has_capability('mod/datalynx:editprivilegeteacher', $this->context, $user,
                                        false))
                ) {
                    $permissions[] = self::PERMISSION_TEACHER;
                }
                if ((!$view ||
                                has_capability('mod/datalynx:viewprivilegestudent', $this->context, $user,
                                        false)) &&
                        (!$edit ||
                                has_capability('mod/datalynx:editprivilegestudent', $this->context, $user,
                                        false))
                ) {
                    $permissions[] = self::PERMISSION_STUDENT;
                }
                if ((!$view ||
                                has_capability('mod/datalynx:viewprivilegeguest', $this->context, $user, false)) &&
                        (!$edit ||
                                has_capability('mod/datalynx:editprivilegeguest', $this->context, $user, false))
                ) {
                    $permissions[] = self::PERMISSION_GUEST;
                }
            } else {
                debug("Invalid \$type parameter: $type");
            }
        }

        return $permissions;
    }

    /**
     * Checks if a name exists in the given table.
     * Use $id to exclude known entry when editing.
     */
    public function name_exists($table, $name, $id = 0) {
        global $DB;

        $params = array($this->id(), $name, $id);

        $where = " dataid = ? AND name = ? AND id <> ? ";
        return $DB->record_exists_select("datalynx_{$table}", $where, $params);
    }

    /**
     * // TODO.
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
                    $event = event\entry_created::create(
                            array('context' => $this->context, 'objectid' => $id, 'other' => $other));
                    $event->trigger();
                    break;
                case 'entryupdated':
                    $event = event\entry_updated::create(
                            array('context' => $this->context, 'objectid' => $id, 'other' => $other));
                    $event->trigger();
                    break;
                case 'entrydeleted':
                    $event = event\entry_deleted::create(
                            array('context' => $this->context, 'objectid' => $id, 'other' => $other));
                    $event->trigger();
                    break;
                case 'entryapproved':
                    $event = event\entry_approved::create(
                            array('context' => $this->context, 'objectid' => $id, 'other' => $other));
                    $event->trigger();
                    break;
                case 'entrydisapproved':
                    $event = event\entry_disapproved::create(
                            array('context' => $this->context, 'objectid' => $id, 'other' => $other));
                    $event->trigger();
                    break;
                default:
                    break;
            }
        }
    }

    public function get_baseurl() {
        // Base url params.
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

        $sql = "SELECT DISTINCT c.content FROM {datalynx_contents} c WHERE c.fieldid = :fieldid ORDER BY c.content";
        $sqlparams['fieldid'] = $id;

        $textfieldvalues = $DB->get_fieldset_sql($sql, $sqlparams);

        return array_combine($textfieldvalues, $textfieldvalues);
    }
}
