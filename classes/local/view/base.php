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
 * @copyright based on the work by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx\local\view;

use coding_exception;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\filter\datalynx_filter;
use mod_datalynx\local\view\datalynxview_patterns;
use datalynxfield__rating;
use datalynxfield__status;
use HTML_QuickForm;
use html_writer;
use calc_formula;
use mod_datalynx\datalynx;
use moodle_exception;
use moodle_url;
use moodleform;
use stdClass;

require_once("$CFG->libdir/formslib.php");

/**
 * A base class for datalynx views
 * (see view/<view type>/base.php)
 */
abstract class base {
    /**
     * Constant value used to request adding a new entry.
     */
    const ADD_NEW_ENTRY = -1;

    /**
     *
     * @var string view type Subclasses must override the type with their name
     */
    protected string $type = 'unknown';

    /**
     *
     * @var ?stdClass get_record object of datalynx_views
     */
    public ?stdClass $view = null;

    /**
     *
     * @var ?datalynx object that this view belongs to
     */
    protected ?datalynx $dl = null;

    /**
     *
     * @var ?datalynx_filter
     */
    protected ?datalynx_filter $filter = null;

    /**
     * @var ?datalynxview_patterns
     */
    protected ?datalynxview_patterns $patternclass = null;

    /**
     * View section editors.
     *
     * @var array
     */
    protected array $editors = ['section', 'param2'];

    /**
     * Editors used in view form processing.
     *
     * @var array
     */
    protected array $vieweditors = ['section', 'param2'];

    /**
     * Cached entries handler.
     *
     * @var ?datalynx_entries
     */
    protected ?datalynx_entries $entries = null;

    /**
     * Cached pattern tags.
     *
     * @var array
     */
    protected array $tags = [];

    /**
     * Base URL for the current view.
     *
     * @var moodle_url
     */
    protected moodle_url $baseurl;

    /**
     * Notifications grouped by type.
     *
     * @var array
     */
    protected array $notifications = ['good' => [], 'bad' => []];

    /**
     * Empty array: Not editing entries.
     * One element: Editing one entry
     * More elements with positive numbers: Editing entries with these ids.
     * One element  with -1: New entry
     * Not sure: One element with -3: Three new entries?
     * TODO: MDL-00000 Array of strings should be converted to array of int.
     * @var array
     */
    protected array $editentries = [];

    /**
     * Grouped entries prepared for rendering.
     *
     * @var array
     */
    protected array $displaydefinition = [];

    /**
     * Indicates whether to return to entries form after processing.
     *
     * @var bool
     */
    protected bool $returntoentriesform = false;

    /**
     * View id used for post-action redirect.
     *
     * @var int
     */
    protected int $redirect = 0;

    /**
     * Stores info if entries just have been processed. In order to have a different behavior after submitting data than
     * when just displaying the view.
     * @var bool
     */
    public bool $entriesprocessedsuccessfully = false;

    /**
     * Constructor
     * View or datalynx or both, each can be id or object
     */
    public function __construct($df = 0, $view = 0, $filteroptions = true) {
        global $DB, $CFG;

        if (empty($df)) {
            throw new coding_exception('Datalynx id or object must be passed to field constructor.');
            // Datalynx object.
        } else {
            if ($df instanceof datalynx) {
                $this->dl = $df;
                // Datalynx id.
            } else {
                $this->dl = new datalynx($df);
            }
        }

        // Set existing view.
        if (!empty($view)) {
            if (is_object($view)) {
                $this->view = $view; // Programmer knows what they are doing, we hope.
            } else {
                $this->view = $DB->get_record('datalynx_views', ['id' => $view]);
                if (!$this->view) {
                    throw new moodle_exception('invalidview', 'datalynx', null, null, $view);
                }
            }
            // Set defaults for new view.
        } else {
            $this->view = new stdClass();
            $this->view->id = 0;
            $this->view->patterns = null;
            $this->view->type = $this->type;
            $this->view->dataid = $this->dl->id();
            $this->view->name = get_string('pluginname', "datalynxview_{$this->type}");
            $this->view->description = '';
            $this->view->visible = 7;
            $this->view->filter = 0;
            $this->view->perpage = 0;
            $this->view->groupby = '';
            $this->view->param10 = 0;
            $this->view->param5 = 0; // Overridefilter.
        }

        $this->redirect = $this->view->param10;

        // Set editors and patterns.
        $this->set__editors();
        $this->set__patterns();

        // Base url params.
        $baseurlparams = [];
        $baseurlparams['d'] = $this->dl->id();
        $baseurlparams['view'] = $this->id();
        if (!empty($eids)) {
            $baseurlparams['eids'] = $eids;
        }

        if ($this->dl->currentgroup) {
            $baseurlparams['currentgroup'] = $this->dl->currentgroup;
        }
        $usersearch = optional_param('usersearch', '', PARAM_TEXT);
        $uperpage = optional_param('uperpage', '', PARAM_INT);
        if (!empty($usersearch)) {
            $baseurlparams['usersearch'] = $usersearch;
        }
        if (!empty($uperpage)) {
            $baseurlparams['uperpage'] = $uperpage;
        }

        $this->baseurl = new moodle_url("/mod/datalynx/{$this->dl->pagefile()}.php", $baseurlparams);
        $this->set_filter($filteroptions, $this->is_forcing_filter()); // If filter is forced ignore URL parameters.
        $this->baseurl->param('filter', $this->filter->id);
        if ($this->filter->page) {
            $this->baseurl->param('page', $this->filter->page);
        }
        $this->set_groupby_per_page();

        $this->entries = new datalynx_entries($this->dl, $this->filter);
    }

    /**
     * Updates the view with data submitted from from after editing the view settings
     *
     * @param stdClass $data form data
     */
    protected function set_view($data) {
        $this->view->name = $data->name;
        $this->view->description = !empty($data->description) ? $data->description : '';
        $this->view->patterns = !empty($data->patterns) ? $data->patterns : null;
        $this->view->visible = !empty($data->visible) ? $data->visible : 0;
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

    /**
     * Return default settings for a newly created view.
     *
     * @return object
     */
    public function get_default_view_settings() {
        return (object) ['description' => '', 'visible' => 7, 'perpage' => 3, 'groupby' => '', 'filter' => 0];
    }

    /**
     * Synchronize editor values between form payload and view record.
     *
     * @param stdClass|null $data Submitted form data.
     */
    protected function set__editors($data = null) {
        $text = '';
        foreach ($this->editors as $editor) { // New view or from DB so add editor fields.
            if (is_null($data)) {
                if (!empty($this->view->$editor)) {
                    $editordata = $this->view->$editor;
                    if (strpos($editordata, 'ft:') === 0) { // Legacy support.
                        $text = substr($editordata, 11);
                    } else {
                        $text = $editordata;
                    }
                }
                $this->view->{"e{$editor}"} = $text;
            } else { // View from form or editor areas updated.
                $this->view->$editor = null;
                if (isset($data->{"e{$editor}"})) {
                    $text = isset($data->{"e{$editor}"}) ? $data->{"e{$editor}"} : '';
                } else {
                    if ($currenteditor = $data->{"e{$editor}_editor"}) {
                        $text = !empty($currenteditor['text']) ? $currenteditor['text'] : '';
                    }
                }
                $this->view->$editor = $text;
            }
        }
    }

    /**
     * Checks if patterns are cached. If yes patterns are retrieved from cache set in $this->tags
     * If no cached patterns are found, they are retrieved from the HTML provided in the
     * view settings section field (definition)
     */
    protected function set__patterns() {
        global $DB;

        $patternarray = [];
        $text = '';
        foreach ($this->editors as $editor) {
            $text .= isset($this->view->$editor) ? $this->view->$editor : '';
        }

        if (trim($text)) {
            // This view patterns.
            $patternarray['view'] = $this->patternclass()->search($text, false);

            // Field patterns.
            if ($fields = $this->dl->get_fields(null, false, true)) {
                foreach ($fields as $fieldid => $field) {
                    $patternarray['field'][$fieldid] = $field->renderer()->search($text);
                }
            }
            $serializedpatterns = serialize($patternarray);
            $DB->set_field('datalynx_views', 'patterns', $serializedpatterns, ['id' => $this->view->id]);
        }
        $this->tags = $patternarray;
    }

    /**
     * Sets up filter options based on parameters from URL, filter assigned to this particular view,
     * and default settings.
     *
     * @param bool $filteroptions
     * @param bool $ignoreurl true, if URL filter options should be ignored
     */
    public function set_filter($filteroptions = true, $ignoreurl = false) {
        $fm = $this->dl->get_filter_manager($this);
        $urlparams = $fm::get_filter_options_from_url();
        $urloptions = [];

        // Keep page to allow for pagination.
        if (isset($urlparams['page'])) {
            $urloptions['page'] = $urlparams['page'];
        }

        // Add all url parameters.
        if (!$ignoreurl && $filteroptions) {
            $urloptions = $urlparams;
        }

        if (is_array($filteroptions)) {
            $urloptions = array_merge($urloptions, $filteroptions);
        }

        $fid = !empty($urloptions['filterid']) ? $urloptions['filterid'] : 0;
        $afilter = !empty($urloptions['afilter']) ? $urloptions['afilter'] : 0;
        $cfilter = !empty($urloptions['cfilter']) ? $urloptions['cfilter'] : 0;
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

        $this->filter = $fm->get_filter_from_id($filterid, ['view' => $this, 'advanced' => $afilter,
                'customfilter' => $cfilter]);

        // Set specific entry id.
        $this->filter->eids = $eids;
        // Set specific user id.
        if ($users) {
            $this->filter->users = is_array($users) ? $users : explode(',', $users);
        }
        // Set specific entry id, if requested.
        if ($groups) {
            $this->filter->groups = is_array($groups) ? $groups : explode(',', $groups);
        }

        $this->filter->perpage = $perpage ? $perpage : ($this->filter->perpage ? $this->filter->perpage : 50);

        $this->filter->groupby = $groupby ? $groupby : $this->filter->groupby;

        $this->filter->search = $usersearch ? $usersearch : $this->filter->search;

        // Add page.
        $this->filter->page = $page ? $page : 0;
        // Content fields.
        $this->filter->contentfields = array_keys($this->get__patterns('field'));

        // Append custom sort options.
        if ($csort) {
            $this->filter->append_sort_options($csort);
        }
        // Append custom search options.
        if ($csearch) {
            $this->filter->append_search_options($csearch);
        }
    }

    // VIEW TYPE.

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
        // Invalidate patterns in the view.
        $DB->set_field('datalynx_views', 'patterns', null, ['id' => $this->view->id]);
        if ($data) {
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
     *
     * @return true
     */
    public function delete() {
        global $DB;

        if (!empty($this->view->id)) {
            $fs = get_file_storage();
            foreach ($this->editors as $key => $editorname) {
                $fs->delete_area_files(
                    $this->dl->context->id,
                    'mod_datalynx',
                    "view$editorname",
                    $this->id() . $key
                );
            }
            return $DB->delete_records('datalynx_views', ['id' => $this->view->id]);
        }
        return true;
    }

    /**
     * Get the form object of the specific view type (grid, etc)
     *
     * @return moodleform instance of the form for editing the view settings
     */
    public function get_form() {
        global $CFG;

        $formclass = 'datalynxview_' . $this->type . '_form';
        $formparams = ['d' => $this->dl->id(), 'vedit' => $this->id(), 'type' => $this->type];
        $actionurl = new moodle_url('/mod/datalynx/view/view_edit.php', $formparams);

        require_once($CFG->dirroot . '/mod/datalynx/view/' . $this->type . '/view_form.php');
        return new $formclass($this, $actionurl);
    }

    /**
     * prepare view data for form
     */
    public function to_form($data = null) {
        $data = $data ?: $this->view;
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
            $data = file_prepare_standard_editor(
                $data,
                "e$editorname",
                $options,
                $this->dl->context,
                'mod_datalynx',
                "view$editorname",
                $this->view->id
            );
        }

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data->{$key} = str_replace('$$moreurl$$', '##moreurl##', $value);
            } else {
                if (is_array($value)) {
                    foreach ($value as $subkey => $subvalue) {
                        if (is_string($subvalue)) {
                            $data->{$key}[$subkey] = str_replace('$$moreurl$$', '##moreurl##', $subvalue);
                        }
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
                if (
                    !(isset($data->{"e{$editorname}_editor"}) &&
                        is_array($data->{"e{$editorname}_editor"}))
                ) {
                    $text = isset($data->{"e{$editorname}"}) ? $data->{"e{$editorname}"} : '';
                    $data->{"e{$editorname}_editor"} = ['format' => $format, 'trust' => $trust,
                            'text' => $text,
                    ];
                }

                $data = file_postupdate_standard_editor(
                    $data,
                    "e$editorname",
                    $options,
                    $this->dl->context,
                    'mod_datalynx',
                    "view$editorname",
                    $this->view->id
                );
            }
        }
        return $data;
    }

    /**
     * Subclass may need to override
     */
    public function replace_field_in_view($searchfieldname, $newfieldname) {
        $patterns = ['[[' . $searchfieldname . ']]', '[[' . $searchfieldname . '#id]]'];
        if (!$newfieldname) {
            $replacements = '';
        } else {
            $replacements = ['[[' . $newfieldname . ']]', '[[' . $newfieldname . '#id]]'];
        }

        foreach ($this->editors as $editor) {
            $this->view->{"e$editor"} = str_ireplace(
                $patterns,
                $replacements,
                $this->view->{"e$editor"}
            );
        }
        $this->update($this->view);
    }

    /**
     * Returns the name/type of the view
     */
    public function name_exists($name, $viewid) {
        return $this->dl->name_exists('views', $name, $viewid);
    }

    /**
     * Process submitted data.
     *
     * @return void
     */
    public function process_data(): void {

        // Process entries data.
        $processed = $this->process_entries_data();
        if (is_array($processed)) {
            if ($processed[0] === -1) {
                return;
            }
            [$strnotify, $successfullyprocessedeids] = $processed;
        } else {
            [$strnotify, $successfullyprocessedeids] = ['', []];
        }

        if (!empty($successfullyprocessedeids)) {
            $this->entriesprocessedsuccessfully = true;
            $this->notifications['good']['entries'] = $strnotify;
        } else {
            if (!empty($strnotify)) {
                $this->notifications['bad']['entries'] = $strnotify;
            }
        }

        // TODO: MDL-00000 Revise this. Does not seem to make sense. Old description: With one entry per page show the saved entry.
        if ($successfullyprocessedeids && $this->user_is_editing() && !$this->returntoentriesform) {
            if ($this->filter->perpage == 1) {
                $this->filter->eids = implode(',', $this->editentries);
            }
            $this->editentries = [];
        }
    }

    /**
     * Retrieve the content for the fields which is saved in the table datalynx_contents
     *
     * @param array $options
     * @return void
     */
    public function set_content(array $options = []) {
        // Options: added for datalynxview_field calling external view.
        // Possible options values: filter, users, groups, eids.
        if ($this->returntoentriesform) {
            return;
        }
        if ($this->user_is_editing() && $this->editentries[0] >= 0 || $this->view->perpage != 1) {
            $this->entries->set_content($options);
        }
    }

    /**
     * Display or echo the content of a datalynx view
     *
     * @param array $options (tohtml = true means output is returned instead of echoed)
     * @return string (empty string of tohtml = false, html when tohtml is true)
     */
    public function display(array $options = []): string {
        global $OUTPUT;
        // Set display options.
        $new = optional_param('new', 0, PARAM_INT);
        $displaycontrols = $options['controls'] ?? true;
        $showentryactions = $options['entryactions'] ?? true;
        $notify = $options['notify'] ?? true;
        $tohtml = $options['tohtml'] ?? false;
        $pluginfileurl = $options['pluginfileurl'] ?? null;

        // Build entries display definition.
        $requiresmanageentries = $this->set_display_definition($options);

        // Set view specific tags.
        $viewoptions = ['pluginfileurl' => $pluginfileurl,
                'entriescount' => $this->entries->get_count(),
                'entriesfiltercount' => $this->entries->get_count(true),
                'hidenewentry' => ($this->user_is_editing() || $new) ? 1 : 0,
                'showentryactions' => $requiresmanageentries && $showentryactions];

        $this->set_view_tags($viewoptions);

        $notifications = $notify ? $this->print_notifications() : '';

        if ($this->returntoentriesform === false) {
            if ($displaycontrols) {
                $output = $notifications . $this->process_calculations($this->view->esection);
            } else {
                $output = '##entries##';
            }
            if ($new || $this->user_is_editing()) {
                $renderedentries = $this->display_entries($options);
                $output = str_replace('##entries##', $renderedentries, $output);
            } else if ($this->entries->get_count()) {
                // Entries have been updated or added. This is an intermediate page displaying the success of the operation.
                // It would be nice to replace that in the future with a modal or similar.
                if ($this->entriesprocessedsuccessfully) {
                    $redirectid = $this->redirect ?: $this->id();
                    $url = new moodle_url($this->baseurl, ['view' => $redirectid]);
                    $output = $notifications . $OUTPUT->continue_button($url);
                } else {
                    $renderedentries = $this->display_entries($options);
                    $output = str_replace('##entries##', $renderedentries, $output);
                }
            } else {
                $output = str_replace('##entries##', $this->display_no_entries(), $output);
            }
        } else {
            $entriesform = $this->get_entries_form();
            $output = $notifications . $entriesform->html();
        }

        $viewname = 'datalynxview-' . preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $this->name()));
        $output = html_writer::tag('div', $output, ['class' => $viewname, 'data-viewname' => $this->name(),
                'data-id' => $this->dl->id(), 'data-viewid' => $this->view->id]);

        if ($tohtml) {
            return $output;
        } else {
            echo $output;
        }
        return '';
    }

    /**
     * Get notifications
     *
     * @return string notifications
     */
    protected function print_notifications() {
        global $OUTPUT;
        $notifications = '';
        foreach ($this->notifications['good'] as $notification) {
            $notifications .= $OUTPUT->notification($notification, 'notifysuccess');
        }
        foreach ($this->notifications['bad'] as $notification) {
            $notifications .= $OUTPUT->notification($notification);
        }
        return $notifications;
    }

    /**
     * Get message why no entries are shown in this view
     *
     * @return string message why no entries are shown in this view
     */
    protected function display_no_entries() {
        global $OUTPUT, $DB;

        if ($this->view->filter > 0) { // This view has a forced filter set.
            $output = $OUTPUT->notification(get_string('noentries', 'datalynx'));
        } else {
            if ($this->filter->id || $this->filter->search) { // This view has a user filter set.
                $output = $OUTPUT->notification(get_string('nomatchingentries', 'datalynx'));
                $url = new moodle_url($this->baseurl, ['filter' => 0, 'usersearch' => 0]);
                $output .= str_replace(
                    get_string('continue'),
                    get_string('resetsettings', 'datalynx'),
                    $OUTPUT->continue_button($url)
                );
            } else {
                if ($this->filter->eids) { // This view displays only entries with chosen ids.
                    [$insql, $params] = $DB->get_in_or_equal($this->filter->eids, SQL_PARAMS_NAMED);
                    if (!$DB->record_exists_select('datalynx_entries', "id $insql", $params)) {
                        $output = $OUTPUT->notification(get_string('nosuchentries', 'datalynx')) .
                            $OUTPUT->continue_button($this->dl->get_baseurl());
                    } else {
                        $output = $OUTPUT->notification(get_string('nopermission', 'datalynx')) .
                            $OUTPUT->continue_button($this->dl->get_baseurl());
                    }
                } else { // There are no entries in this datalynx.
                    $output = $OUTPUT->notification(get_string('noentries', 'datalynx'));
                }
            }
        }
        return $output;
    }

    /**
     * Replace the tags in the view template section of a view with the appropriate values
     *
     * @param array $options
     */
    public function set_view_tags(array $options): void {
        // Rewrite plugin urls.
        $pluginfileurl = !empty($options['pluginfileurl']) ? $options['pluginfileurl'] : null;
        foreach ($this->editors as $editorname) {
            $editor = "e$editorname";

            // Export with files should provide the file path.
            if ($pluginfileurl) {
                $this->view->$editor = str_replace(
                    '@@PLUGINFILE@@/',
                    $pluginfileurl,
                    $this->view->$editor
                );
            } else {
                $this->view->$editor = file_rewrite_pluginfile_urls(
                    $this->view->$editor,
                    'pluginfile.php',
                    $this->dl->context->id,
                    'mod_datalynx',
                    "view$editorname",
                    $this->id()
                );
            }
        }

        $tags = $this->tags['view'];
        $replacements = $this->patternclass()->get_replacements($tags, null, $options);
        foreach ($this->vieweditors as $editor) {
            // Catch potential data mismatch.
            if (!isset($this->view->{"e$editor"})) {
                $this->view->{"e$editor"} = null;
                continue;
            }

            $text = $this->view->{"e$editor"};
            $text = $this->mask_tags($text);
            $text = format_text($text, FORMAT_HTML, ['trusted' => 1, 'filter' => true]);
            $text = $this->unmask_tags($text);
            $this->view->{"e$editor"} = str_replace($tags, $replacements, $text);
        }
        // Remove customfilter tags after we have displayed them.
        foreach ($tags as $key => $value) {
            if (strpos($value, '##customfilter') !== false) {
                unset($this->tags['view'][$key]);
            }
        }
    }

    /**
     * Masks view and field tags so that they do not get auto-linked
     *
     * @param string $text a string with tags to mask
     * @return $text HTML with masked tags
     */
    public function mask_tags($text) {
        $matches = [];
        $find = [];
        $replace = [];
        // Regex to mask all known tag patterns. Patterns followed by @ are not masked.
        preg_match_all(
            '/(?:(\[\[[^\]]+\]\])(?!@)|(##[^#]+##)|(%%[^%]+%%)|(#\{\{[^\}#]+\}\}#))/',
            $text,
            $matches,
            PREG_PATTERN_ORDER
        );
        $map = array_unique($matches[0]);
        foreach ($map as $index => $match) {
            if ($match != '##entries##') {
                $find[$index] = "/" . preg_quote($match, '/') . "(?!@)/";
                $replace[$index] = '<span class="nolink" title="donotreplaceme">' . $match . '</span>';
            }
        }
        $text = preg_replace($find, $replace, $text);
        return $text;
    }

    /**
     * Unmasks view and field tags
     *
     * @param string $text a string with masked tags
     * @return $text HTML with unmasked tags
     */
    public function unmask_tags($text) {
        $find = '/<span class="nolink" title="donotreplaceme">(.+?)<\/span>/is';
        $replace = '$1';
        $text = preg_replace($find, $replace, $text);
        return $text;
    }

    // HELPERS.
    /**
     * Get fields of a view as an array
     *
     * @return array of field ids
     */
    public function get_view_fields() {
        $viewfields = [];

        if (!empty($this->tags['field'])) {
            $fields = $this->dl->get_fields();
            foreach (array_keys($this->tags['field']) as $fieldid) {
                if (array_key_exists($fieldid, $fields)) {
                    $viewfields[$fieldid] = $fields[$fieldid];
                }
            }
        }
        return $viewfields;
    }

    /**
     * Renders fields as patterns
     *
     * @return array of strings (field pattern used in the view)
     */
    public function field_tags(): array {
        $patterns = [];
        if ($fields = $this->dl->get_fields()) {
            foreach ($fields as $field) {
                if ($fieldpatterns = $field->renderer()->get_menu()) {
                    $patterns = array_merge_recursive($patterns, $fieldpatterns);
                }
            }
        }
        return $patterns;
    }

    /**
     * Return predefined character replacement tags.
     *
     * @return array[]
     */
    public function character_tags(): array {
        $patterns = ['---' => ['---' => []]];
        $patterns['9'] = 'tab';
        $patterns['10'] = 'new line';

        return $patterns;
    }

    /**
     * Generate a default template definition for this view type.
     */
    abstract public function generate_default_view();

    /**
     * Return editor configuration options.
     *
     * @return array
     */
    public function editors() {
        $editors = [];

        $options = ['trusttext' => true, 'noclean' => true, 'subdirs' => false,
                'changeformat' => true, 'collapsed' => true, 'rows' => 20, 'style' => 'width:100%',
                'maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $this->dl->course->maxbytes,
                'context' => $this->dl->context];

        foreach ($this->editors as $editor) {
            $editors[$editor] = $options;
        }

        return $editors;
    }

    /**
     * Return the fully-qualified class name of the patterns class for this view type.
     * Subclasses with a custom patterns class should override this method.
     *
     * @return string
     */
    protected function patternclassname(): string {
        return datalynxview_patterns::class;
    }

    /**
     * Get the class for view patterns (tag processing)
     *
     * @return datalynxview_patterns
     */
    public function patternclass(): datalynxview_patterns {
        if (!$this->patternclass) {
            $class = $this->patternclassname();
            $this->patternclass = new $class($this);
        }
        return $this->patternclass;
    }

    /**
     * Get either all tags ($set = null) or field tags ($set = field) as an array.
     *
     * @param string|null $set Current: field or view.
     * @return array
     */
    public function get__patterns($set = null) {
        if (is_null($set)) {
            return $this->tags;
        }

        if (empty($this->tags)) {
            return [];
        }

        if ($set == 'view' || $set == 'field') {
            return $this->tags[$set];
        }

        return [];
    }

    /**
     * Resolve the field id associated with a pattern.
     *
     * @param string $pattern Pattern token.
     * @return int|null
     */
    public function get_pattern_fieldid($pattern) {
        if (!empty($this->tags['field'])) {
            foreach ($this->tags['field'] as $fieldid => $patterns) {
                if (in_array($pattern, $patterns)) {
                    return $fieldid;
                }
            }
        }
        return null;
    }

    /**
     * Collect embedded files used by this view and optionally by fields.
     *
     * @param string|null $set Restrict to view or field set.
     * @return array
     */
    public function get_embedded_files($set = null) {
        $files = [];
        $fs = get_file_storage();

        // View files.
        if (empty($set) || $set == 'view') {
            foreach ($this->editors as $key => $editorname) {
                // Build editor item id from the editor position key.
                $files = array_merge(
                    $files,
                    $fs->get_area_files(
                        $this->dl->context->id,
                        'mod_datalynx',
                        'view',
                        $this->id() . $key,
                        'sortorder, itemid, filepath, filename',
                        false
                    )
                );
            }
        }

        // Field files.
        if (empty($set) || $set == 'field') {
            // Find which fields actually display files/images in the view.
            $fids = [];
            if (!empty($this->tags['field'])) {
                $fields = $this->dl->get_fields();
                foreach ($this->tags['field'] as $fieldid => $tags) {
                    if (array_intersect($tags, $fields[$fieldid]->renderer()->pluginfile_patterns())) {
                        $fids[] = $fieldid;
                    }
                }
            }
            // Get the files from the entries.
            if ($this->entries && !empty($fids)) { // Set_content must have been called.
                $files = array_merge($files, $this->entries->get_embedded_files($fids));
            }
        }

        return $files;
    }

    /**
     * Apply view-specific layout to a grouped set of entry definitions.
     *
     * @param array $entriesset Entry definition groups.
     * @param string $name Group name.
     * @return mixed
     */
    abstract protected function apply_entry_group_layout($entriesset, $name = '');

    /**
     * Build a definition for a newly created entry row.
     *
     * @param int $entryid Temporary entry id.
     * @return mixed
     */
    abstract protected function new_entry_definition($entryid = -1);

    /**
     *
     * @param $fielddefinitions
     * @return array
     */
    protected function entry_definition($fielddefinitions) {
        $elements = [];

        // Split the entry template to tags and html.
        $tags = array_keys($fielddefinitions);
        $parts = $this->split_template_by_tags($tags, $this->view->eparam2);

        foreach ($parts as $part) {
            if (in_array($part, $tags)) {
                if ($def = $fielddefinitions[$part]) {
                    $elements[] = $def;
                }
            } else {
                $elements[] = ['html', $part];
            }
        }

        return $elements;
    }

    /**
     *
     * @param array $patterns array of arrays of pattern replacement pairs
     */
    protected function split_template_by_tags($patterns, $subject) {
        foreach ($patterns as $id => $pattern) {
            $patterns[$id] = preg_quote($pattern, '/');
        }
        $delims = implode('|', $patterns);
        $elements = preg_split("/($delims)/", $subject, 0, PREG_SPLIT_DELIM_CAPTURE);

        return $elements;
    }

    /**
     * FIXME: there was an error here at get_definitions call!
     */
    protected function get_groupby_value($entry) {
        $fields = $this->dl->get_fields();
        $fieldid = $this->filter->groupby;
        $groupbyvalue = '';

        if (array_key_exists($fieldid, $this->tags['field'])) {
            // First pattern.
            $pattern = reset($this->tags['field'][$fieldid]);
            if ($pattern !== false) {
                $field = $fields[$fieldid];

                if (
                    $definition = $field->get_definitions([$pattern,
                    ], $entry, [])
                ) {
                    $groupbyvalue = $definition[$pattern][1];
                }
            }
        }

        return $groupbyvalue;
    }

    /**
     * TODO: MDL-00000 this needs to be moved to the filter itself!!!
     * Set sort and search criteria for grouping by
     */
    protected function set_groupby_per_page() {

        // Get the group by fieldid.
        if (empty($this->filter->groupby)) {
            return;
        }

        $fieldid = $this->filter->groupby;
        // Set sorting to begin with this field.
        $insort = false;
        // TODO: MDL-00000 asc order is arbitrary here and should be determined differently.
        $sortdir = 0;
        $sortfields = [];
        if ($this->filter->customsort) {
            $sortfields = unserialize($this->filter->customsort);
            if ($insort = in_array($fieldid, array_keys($sortfields))) {
                $sortdir = $sortfields[$fieldid];
                unset($sortfields[$fieldid]);
            }
        }
        $sortfields = [$fieldid => $sortdir] + $sortfields;
        $this->filter->customsort = serialize($sortfields);

        // Get the distinct content for the group by field.
        $field = $this->dl->get_field_from_id($fieldid);
        if (!$groupbyvalues = $field->get_distinct_content($sortdir)) {
            return;
        }

        // Get the displayed subset according to page.
        $numvals = count($groupbyvalues);
        // Calc number of pages.
        if ($this->filter->perpage && $this->filter->perpage < $numvals) {
            $this->filter->pagenum = ceil($numvals / $this->filter->perpage);
            $this->filter->page = $this->filter->page % $this->filter->pagenum;
        } else {
            $this->filter->perpage = 0;
            $this->filter->pagenum = 0;
            $this->filter->page = 0;
        }

        if ($this->filter->perpage) {
            $offset = $this->filter->page * $this->filter->perpage;
            $vals = array_slice($groupbyvalues, $offset, $this->filter->perpage);
        } else {
            $vals = $groupbyvalues;
        }

        $searchfields = [];
        if ($this->filter->customsearch) {
            $searchfields = unserialize($this->filter->customsearch);
        }

        $this->filter->customsearch = serialize($searchfields);
    }

    /**
     * Build rating options when rating patterns are present.
     *
     * @return stdClass|null
     */
    protected function is_rating() {
        global $USER, $CFG;

        require_once("$CFG->dirroot/mod/datalynx/field/_rating/field_class.php");

        if (
            !$this->dl->data->rating || empty(
                $this->tags['field'][datalynxfield__rating::_RATING]
            )
        ) {
            return null;
        }

        $ratingfield = $this->dl->get_field_from_id(datalynxfield__rating::_RATING);
        $ratingoptions = new stdClass();
        $ratingoptions->context = $this->dl->context;
        $ratingoptions->component = 'mod_datalynx';
        $ratingoptions->ratingarea = 'entry';
        $ratingoptions->aggregate = $ratingfield->renderer()->get_aggregations(
            $this->tags['field'][datalynxfield__rating::_RATING]
        );
        $ratingoptions->scaleid = $this->get_scaleid('entry');
        $ratingoptions->userid = $USER->id;

        return $ratingoptions;
    }

    /**
     * Return configured scale id for a grading area.
     *
     * @param string $area Either entry or activity.
     * @return int
     */
    public function get_scaleid($area) {
        if ($area == 'entry' && $this->dl->data->rating) {
            return $this->dl->data->rating;
        } else {
            if ($area == 'activity' && $this->dl->data->grade) {
                return $this->dl->data->grade;
            }
        }
        return 0;
    }

    /**
     * Check whether grading is enabled for the current view.
     *
     * @return bool
     */
    protected function is_grading() {
        if (!$this->dl->data->grade) {
            // Grading is disabled in this datalynx.
            return false;
        }

        if (empty($this->view->param1)) {
            // Grading is not activated in this view.
            return false;
        }

        return true;
    }

    /**
     * Build grading options for the activity grade area.
     *
     * @return stdClass|null
     */
    protected function get_grading_options() {
        global $USER;

        if (!$this->dl->data->grade) {
            return null;
        }

        $gradingoptions = new stdClass();
        $gradingoptions->context = $this->dl->context;
        $gradingoptions->component = 'mod_datalynx';
        $gradingoptions->ratingarea = 'activity';
        $gradingoptions->aggregate = [RATING_AGGREGATE_MAXIMUM];
        $gradingoptions->scaleid = $this->dl->data->grade;
        $gradingoptions->userid = $USER->id;

        return $gradingoptions;
    }

    /**
     * Render entries of the datalynx view.
     * @param ?array $options
     * @return string
     */
    public function display_entries(array $options = null): string {
        global $DB, $OUTPUT, $CFG;

        if (!$this->user_is_editing()) {
            $html = $this->definition_to_html();
            if (isset($options['pluginfileurl'])) {
                $html = $this->replace_pluginfile_urls($html, $options['pluginfileurl']);
            }
        } else {
            $editallowed = false;
            // TODO: MDL-00000 is this compatible for editing multiple entries? Check change needed? Check if isset is necessary.
            if ($editallowed = $this->get_dl()->user_can_manage_entry()) {
                if (isset($this->editentries[0]) && count($this->editentries) == 1) {
                    $entrystatus = $DB->get_field(
                        'datalynx_entries',
                        'status',
                        ['id' => $this->editentries[0]]
                    );
                    require_once($CFG->dirroot . '/mod/datalynx/field/_status/field_class.php');
                    if (
                        !has_capability('mod/datalynx:manageentries', $this->dl->context) &&
                             $entrystatus == datalynxfield__status::STATUS_FINAL_SUBMISSION
                    ) {
                        $editallowed = false;
                    }
                }
            }
            if ($editallowed) {
                // Prepare options for form.
                $entriesform = $this->get_entries_form();
                $html = $entriesform->html();
            } else {
                // Show message that editing is not allowed.
                $redirectid = $this->redirect ? $this->redirect : $this->id();
                $url = new moodle_url($this->baseurl, ['view' => $redirectid]);
                $html = $OUTPUT->notification(get_string('notallowedtoeditentry', 'datalynx')) .
                    $OUTPUT->continue_button($url);
            }
        }
        // Process calculations if any.
        return $this->process_calculations($html);
    }

    /**
     * Assemble the replaced tags and field values to valid html
     *
     * @return string html
     */
    public function definition_to_html() {
        $html = '';
        $elements = $this->get_entries_definition();
        foreach ($elements as $element) {
            [, $content] = $element;
            $html .= $content;
        }

        return $html;
    }

    /**
     * Build and append all entry form elements to a Moodle form instance.
     *
     * @param HTML_QuickForm $mform Target form.
     * @return void
     */
    public function definition_to_form(HTML_QuickForm &$mform) {
        $elements = $this->get_entries_definition();
        foreach ($elements as $element) {
            if (!empty($element)) {
                [$type, $content] = $element;
                if ($type === 'html') {
                    $mform->addElement('html', $content);
                } else {
                    $params = [];
                    $func = $content[0];
                    $entry = $content[1][0];
                    if (isset($content[1][1])) {
                        $params = $content[1][1];
                    }
                    call_user_func_array($func, [&$mform, $entry, $params]);
                }
            }
        }
    }

    /**
     * Get view specific form for data input via Moodle form.
     *
     * @return datalynxview_entries_form
     */
    protected function get_entries_form(): ?datalynxview_entries_form {
        static $entriesform = null;

        if ($entriesform == null) {
            global $CFG;
            // Prepare params for for content management.
            $actionparams = [
                'd' => $this->dl->id(),
                'view' => $this->id(),
                'page' => $this->filter->page,
                'eids' => $this->filter->eids,
                'update' => implode(',', $this->editentries),
                'sourceview' => optional_param('sourceview', null, PARAM_INT),
            ];
            $actionurl = new moodle_url("/mod/datalynx/{$this->dl->pagefile()}.php", $actionparams);
            $customdata = ['view' => $this, 'update' => implode(',', $this->editentries)];

            $formclass = 'mod_datalynx\local\view\datalynxview_entries_form';
            $entriesform = new $formclass($actionurl, $customdata);
        }

        return $entriesform;
    }

    /**
     * Build the full entries definition for display or editing.
     * TODO: MDL-00000 THIS IS CRITICAL!!!
     *
     * @return array
     */
    public function get_entries_definition() {
        $displaydefinition = $this->displaydefinition;
        $groupedelements = [];
        foreach ($displaydefinition as $name => $entriesset) {
            $definitions = [];
            if ($name == 'newentry') {
                foreach ($entriesset as $entryid => $unused) {
                    $definitions[$entryid] = $this->new_entry_definition($entryid);
                }
            } else {
                foreach ($entriesset as $entryid => $entryparams) {
                    [$entry, $editthisone, $managethisone] = $entryparams;
                    $options = ['edit' => $editthisone, 'manage' => $managethisone];
                    $fielddefinitions = $this->get_entry_tag_replacements($entry, $options);
                    $definitions[$entryid] = $this->entry_definition($fielddefinitions);
                }
            }
            $groupedelements[$name] = $this->apply_entry_group_layout($definitions, $name);
        }
        // Flatten the elements.
        $elements = [];
        foreach ($groupedelements as $group) {
            $elements = array_merge($elements, $group);
        }

        return $elements;
    }

    /**
     * Build field and view tag replacements for a single entry.
     *
     * @param stdClass $entry Entry record.
     * @param array $options Rendering options.
     * @return array
     */
    protected function get_entry_tag_replacements($entry, $options) {
        $fields = $this->dl->get_fields();
        $entry->baseurl = $this->baseurl;

        $definitions = [];
        foreach ($this->tags['field'] as $fieldid => $patterns) {
            if (isset($fields[$fieldid])) {
                $field = $fields[$fieldid];
                if ($fielddefinitions = $field->get_definitions($patterns, $entry, $options)) {
                    $definitions = array_merge($definitions, $fielddefinitions);
                }
            }
        }
        $fielddefinitions = $definitions;

        // Enables view tag replacement within the entry template.
        if ($patterns = $this->patternclass()->get_replacements($this->tags['view'], null, $options)) {
            $viewdefinitions = [];
            foreach ($patterns as $tag => $pattern) {
                if (
                    (strpos($tag, 'viewlink') !== 0 || strpos($tag, 'viewsesslink') !== 0) &&
                        (!array_key_exists('edit', $options) || !$options['edit'])
                ) {
                    foreach ($fielddefinitions as $fieldtag => $definition) {
                        $pattern = str_replace(
                            $fieldtag,
                            isset($definitions[$fieldtag][1]) ? $definitions[$fieldtag][1] : '',
                            $pattern
                        );
                    }
                }
                $viewdefinitions[$tag] = ['html', $pattern];
            }
            $definitions = array_merge($definitions, $viewdefinitions);
        }

        return $definitions;
    }

    /**
     * Set display definition
     *
     * @param array|null $options
     * @return bool
     * @throws coding_exception
     */
    protected function set_display_definition(array $options = null) {
        $this->displaydefinition = [];
        // Indicate if there are managable entries in the display for the current user.
        // In which case edit/delete action.
        $requiresmanageentries = false;

        $editentries = [];
        // Display a new entry to add in its own group.
        if (count($this->editentries) == 1 && $this->editentries[0] < 0) {
            if ($this->dl->user_can_manage_entry()) {
                $this->displaydefinition['newentry'] = [];
                for ($i = -1; $i >= $this->editentries[0]; $i--) {
                    $this->displaydefinition['newentry'][$i] = null;
                }
            }
        } else {
            $editentries = $this->editentries;
        }

        // Compile entries if any.
        if ($entries = $this->entries->entries()) {
            $groupname = '';
            $groupdefinition = [];

            // If action buttons should be hidden entries should be unmanageable.
            $displayactions = isset($options['entryactions']) ? $options['entryactions'] : true;
            foreach ($entries as $entryid => $entry) {
                // Is this entry edited.
                $editthisone = $editentries ? in_array($entryid, $editentries) : false;
                // Set a flag if we are editing any entries.
                $requiresmanageentries = $editthisone ? true : $requiresmanageentries;
                // Calculate manageability for this entry only if action buttons can be displayed.
                // And we're not already editing it.
                $manageable = false;
                if ($displayactions && !$editthisone) {
                    $manageable = $this->dl->user_can_manage_entry($entry);
                }

                // Are we grouping?
                if ($this->filter->groupby) {
                    // Assuming here that the groupbyed field returns only one pattern.
                    $groupbyvalue = $this->get_groupby_value($entry);
                    if ($groupbyvalue != $groupname) {
                        // Compile current group definitions.
                        if ($groupname) {
                            // Add the group entries definitions.
                            $this->displaydefinition[$groupname] = $groupdefinition;
                            $groupdefinition = [];
                        }
                        // Reset group name.
                        $groupname = $groupbyvalue;
                    }
                }

                // Add to the current entries group.
                $groupdefinition[$entryid] = [$entry, $editthisone, $manageable];
            }
            // Collect remaining definitions (all of it if no groupby).
            $this->displaydefinition[$groupname] = $groupdefinition;
        }
        return $requiresmanageentries;
    }

    /**
     * Evaluate inline formula patterns in rendered text.
     *
     * @param string $text
     * @return string
     */
    protected function process_calculations(string $text): string {
        global $CFG;

        if (preg_match_all("/%%F\d*:=[^%]+%%/", $text, $matches)) {
            require_once("$CFG->libdir/mathslib.php");
            sort($matches[0]);
            $replacements = [];
            $formulas = [];
            foreach ($matches[0] as $pattern) {
                $cleanpattern = trim($pattern, '%');
                [$fid, $formula] = explode(':=', $cleanpattern, 2);
                // Process group formulas (e.g. _F1_).
                if (preg_match_all("/_F\d*_/", $formula, $frefs)) {
                    foreach ($frefs[0] as $fref) {
                        $fref = trim($fref, '_');
                        if (isset($formulas[$fref])) {
                            $formula = str_replace(
                                "_{$fref}_",
                                implode(',', $formulas[$fref]),
                                $formula
                            );
                        }
                    }
                }
                isset($formulas[$fid]) || $formulas[$fid] = [];
                // Enclose formula in brackets to preserve precedence.
                $formulas[$fid][] = "($formula)";
                $replacements[$pattern] = $formula;
            }

            foreach ($replacements as $pattern => $formula) {
                // Number of decimals can be set as ;n at the end of the formula.
                $decimals = null;
                if (strpos($formula, ';')) {
                    [$formula, $decimals] = explode(';', $formula);
                }

                $calc = new calc_formula("=$formula");
                $result = $calc->evaluate();
                // False as result indicates some problem.
                if ($result === false) {
                    // Add more error hints.
                    $replacements[$pattern] = html_writer::tag(
                        'span',
                        $formula,
                        ['style' => 'color:red;',
                        ]
                    );
                } else {
                    // Set decimals.
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

    // GETTERS.

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
    public function get_dl() {
        return $this->dl;
    }

    /**
     * Return current filter instance.
     *
     * @return datalynx_filter|null
     */
    public function get_filter() {
        return $this->filter;
    }

    /**
     * Return base URL for this view.
     *
     * @return moodle_url
     */
    public function get_baseurl(): moodle_url {
        return $this->baseurl;
    }

    /**
     * Check whether this view is currently selected in the request.
     *
     * @return bool
     */
    public function is_active() {
        return (optional_param('view', 0, PARAM_INT) == $this->id());
    }

    /**
     * Indicate whether this view supports caching.
     *
     * @return bool
     */
    public function is_caching() {
        return false;
    }

    /**
     * Indicate whether this view forces a predefined filter.
     *
     * @return bool|int
     */
    public function is_forcing_filter() {

        // If overridefilter is selected we don't force filters.
        if ($this->view->param5) {
            return false;
        }
        return $this->view->filter;
    }

    /**
     * When the array is empty then user is not editing.
     * @return bool true when edit mode false when display mode
     */
    public function user_is_editing(): bool {
        if (!empty($this->editentries)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Process the submitted data of entries
     *
     * @return array|bool
     */
    public function process_entries_data() {
        $illegalaction = false;

        // Check first if returning from form.
        $update = optional_param('update', '', PARAM_RAW);
        // Direct url params; not from form.
        $new = optional_param('new', 0, PARAM_INT); // Open new entry form.
        // Edit entries(all) or by record ids (comma delimited eids).
        $editentries = optional_param('editentries', [], PARAM_SEQUENCE);
        if (!is_array($editentries)) {
            $editentries = explode(',', $editentries);
        }
        // Duplicate entries (all) or by record ids (comma delimited eids).
        $duplicate = optional_param('duplicate', '', PARAM_SEQUENCE);
        // Delete entries (all) or by record Ids (comma delimited eids).
        $delete = optional_param('delete', '', PARAM_SEQUENCE);
        // Approve entries (all) or by record ids (comma delimited eids).
        $approve = optional_param('approve', '', PARAM_SEQUENCE);
        // Disapprove entries (all)or by record ids (comma delimited eids).
        $disapprove = optional_param('disapprove', '', PARAM_SEQUENCE);
        // Set status of entries (all) or by record ids (comma delimited eids).
        $status = optional_param('status', '', PARAM_SEQUENCE);
        // Confirm submission of data.
        $confirmed = optional_param('confirmed', 0, PARAM_BOOL);

        if ($update) {
            $action = ($update != self::ADD_NEW_ENTRY) ? "edit" : "addnewentry";
            if (confirm_sesskey() && $this->confirm_view_action($action)) {
                // Get entries only if updating existing entries.
                if ($update != self::ADD_NEW_ENTRY) {
                    // Fetch entries.
                    $this->entries->set_content();
                }

                // Set the display definition for the form. Cast $update to string because PHP has a problem exploding -1 integer.
                $this->editentries = explode(',', (string) $update);
                $this->set_display_definition();

                $entriesform = $this->get_entries_form();

                // Process the form if not cancelled.
                if (!$entriesform->is_cancelled()) {
                    if ($data = $entriesform->get_data()) {
                        // Validated successfully so process request.
                        $processed = $this->entries->process_entries(
                            'update',
                            $update,
                            $data,
                            true
                        );

                        if (!$processed) {
                            $this->returntoentriesform = true;
                            return false;
                        }
                        // So that we can show the new entries if we so wish.
                        if (isset($this->editentries[0]) && $this->editentries[0] < 0) {
                            $this->editentries = is_array($processed[1]) ? $processed[1] : [$processed[1]];
                        } else {
                            $this->editentries = [];
                        }
                        // TODO: MDL-00000 Replace with more standard way to tell datalynx there is no new entry anymore to edit.
                        $_POST['new'] = 0;
                        $this->entries->set_content();
                        return $processed;
                    } else {
                        // Form validation failed so return to form.
                        $this->returntoentriesform = true;
                        $formdata = (array) $entriesform->get_submitted_data();
                        $errors = $entriesform->validation($formdata, []);
                        return [implode('<br>', $errors), []];
                    }
                } else {
                    $redirectid = $this->redirect ?: $this->id();
                    $url = new moodle_url($this->baseurl, ['view' => $redirectid,
                    ]);
                    redirect($url);
                }
            } else {
                $illegalaction = true;
            }
        }

        // TODO: MDL-00000 Check if this is the right place to assign the var.
        $this->editentries = $editentries;

        if ($new) {
            if (
                confirm_sesskey() &&
                    ($this->confirm_view_action("addnewentry") ||
                            $this->confirm_view_action("addnewentries"))
            ) {
                return $this->editentries = [-$new];
            } else {
                $illegalaction = true;
            }
        } else {
            if ($duplicate) {
                if (confirm_sesskey() && $this->confirm_view_action("duplicate")) {
                    return $this->entries->process_entries('duplicate', $duplicate, null, $confirmed);
                } else {
                    $illegalaction = true;
                }
            } else {
                if ($delete) {
                    if (confirm_sesskey() && $this->confirm_view_action("delete")) {
                        return $this->entries->process_entries('delete', $delete, null, $confirmed);
                    } else {
                        $illegalaction = true;
                    }
                } else {
                    if ($approve) {
                        if (confirm_sesskey() && $this->confirm_view_action("approve")) {
                            return $this->entries->process_entries('approve', $approve, null, true);
                        } else {
                            $illegalaction = true;
                        }
                    } else {
                        if ($disapprove) {
                            if (confirm_sesskey() && $this->confirm_view_action("approve")) {
                                return $this->entries->process_entries('disapprove', $disapprove, null, true);
                            } else {
                                $illegalaction = true;
                            }
                        } else {
                            if ($status) {
                                if (confirm_sesskey() && $this->confirm_view_action("status")) {
                                    return $this->entries->process_entries('status', $status, null, true);
                                } else {
                                    $illegalaction = true;
                                }
                            }
                        }
                    }
                }
            }
        }

        if ($illegalaction) {
            $sourceview = optional_param('sourceview', $this->id(), PARAM_INT);
            $url = new moodle_url('view.php', ['d' => $this->dl->id(), 'view' => $sourceview]);
            redirect($url);
        }

        return false;
    }

    /**
     * Verifies if the given action is available to the user in the view.
     * The TARGET view must be visible to the user
     * AND contain the necessary action tag in the entry template in order for the action to be
     * allowed. This function
     * prevents users to circumvent action restrictions via URL queries.
     *
     * @param $action String of the action
     * @return bool true, if the action is allowed; false otherwise.
     */
    private function confirm_view_action($action): bool {
        global $DB;
        $targetview = optional_param('view', 0, PARAM_INT);
        $view = $DB->get_record('datalynx_views', ['id' => $targetview]);
        return $view && $this->dl->is_visible_to_user($this->view) &&
        ((strpos($view->param2, "##$action##") !== false) ||
                (strpos($view->section, "##$action##") !== false));
    }

    /**
     * Replace entry content pluginfile URLs with an export target URL.
     *
     * @param string $html Rendered HTML.
     * @param string $pluginfileurl Replacement URL.
     * @return string
     */
    private function replace_pluginfile_urls($html, $pluginfileurl) {
        $pluginfilepath = moodle_url::make_file_url(
            "/pluginfile.php",
            "/{$this->dl->context->id}/mod_datalynx/content"
        );
        $pattern = str_replace('/', '\/', $pluginfilepath);
        $pattern = "/$pattern\/\d+\//";
        return preg_replace($pattern, $pluginfileurl, $html);
    }

    /**
     * Find all fields that occur within a fieldgroup and remove duplicates.
     */
    public function remove_duplicates($fields) {
        foreach ($fields as $field) {
            if ($field->type == 'fieldgroup') {
                foreach ($field->fieldids as $fieldwithin) {
                    unset($fields[$fieldwithin]);
                }
            }
        }
        return $fields;
    }
}
