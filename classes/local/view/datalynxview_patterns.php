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

namespace mod_datalynx\local\view;

use html_writer;
use mod_datalynx\local\filter\datalynx_filter_manager;
use moodle_url;
use paging_bar;
use single_select;

/**
 * Base class for view patterns
 */
class datalynxview_patterns {
    /** @var int Pattern show in menu constant */
    const PATTERN_SHOW_IN_MENU = 0;

    /** @var int Pattern category constant */
    const PATTERN_CATEGORY = 1;

    /**
     *
     * @var ?base
     */
    protected ?base $view = null;

    /**
     * Constructor
     *
     * @param base $view View instance.
     */
    public function __construct(&$view) {
        $this->view = $view;
    }

    /**
     * Search for patterns in text
     *
     * @param string $text Text to search in.
     * @param bool $checkvisibility Whether to check pattern visibility.
     * @return array
     */
    public function search($text, $checkvisibility = true) {
        $found = [];
        // Fixed patterns.
        $patterns = array_keys($this->patterns($checkvisibility));
        foreach ($patterns as $pattern) {
            if (strpos($text, $pattern) !== false) {
                $found[] = $pattern;
            }
        }

        // Regexp patterns.
        if ($patterns = array_keys($this->regexp_patterns($checkvisibility))) {
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
     * @param bool $showall
     * @param bool $checkvisibility if true only views visible to user are considered
     * @return array
     */
    final public function get_menu($showall = false, $checkvisibility = true) {
        // The default menu category for views.
        $patternsmenu = [];
        foreach ($this->patterns($checkvisibility) as $tag => $pattern) {
            if ($showall || $pattern[self::PATTERN_SHOW_IN_MENU]) {
                // Which category.
                if (!empty($pattern[self::PATTERN_CATEGORY])) {
                    $cat = $pattern[self::PATTERN_CATEGORY];
                } else {
                    $cat = get_string('views', 'datalynx');
                }
                // Prepare array.
                if (!isset($patternsmenu[$cat])) {
                    $patternsmenu[$cat] = [$cat => []];
                }
                // Add tag.
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
    public function get_replacements($tags = null, $entry = null, array $options = []) {
        $view = $this->view;

        // Existing tag types.
        $info = array_keys($this->info_patterns()); // Number of entries.
        $ref = array_keys($this->ref_patterns()); // Viewrelated tags like ##viewurl## and ##filtersmenu##.
        $userpref = array_keys($this->userpref_patterns()); // Custom filter tags.
        $actions = array_keys($this->action_patterns()); // Action tags like ##addnewentry## and ##multiedit##.
        $paging = array_keys($this->paging_patterns()); // Paging tags.

        $options['filter'] = $view->get_filter();
        $options['baseurl'] = new moodle_url($view->get_baseurl(), ['sesskey' => sesskey()]);

        $replacements = [];
        foreach ($tags as $tag) {
            if ($tag == '##entries##') {
                $replacements[$tag] = '##entries##';
            } else {
                if (in_array($tag, $info)) {
                    $replacements[$tag] = $this->get_info_replacements($tag, $entry, $options);
                } else {
                    if (in_array($tag, $ref)) {
                        $replacements[$tag] = $this->get_ref_replacements($tag, $entry, $options);
                    } else {
                        if (in_array($tag, $userpref)) {
                            $replacements[$tag] = $this->get_userpref_replacements($tag, $options);
                        } else {
                            if (in_array($tag, $actions)) {
                                $replacements[$tag] = $this->get_action_replacements($tag, $entry, $options);
                            } else {
                                if (in_array($tag, $paging)) {
                                    $replacements[$tag] = $this->get_paging_replacements($options);
                                } else {
                                    if ($this->is_regexp_pattern($tag)) {
                                        $replacements[$tag] = $this->get_regexp_replacements($tag, $entry, $options);
                                    } else {
                                        $replacements[$tag] = '';
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $replacements;
    }

    /**
     * Get the values for tags that contain a viewname or a fieldname
     *
     * @param string $tag
     * @param mixed $entry
     * @param ?array $options
     * @return string
     */
    protected function get_regexp_replacements($tag, $entry = null, ?array $options = null) {
        global $OUTPUT;

        $df = $this->view->get_dl();
        $currentview = $df->get_current_view();

        $views = $df->get_all_views();
        foreach ($views as $view) {
            $baseurlparams = [];
            $baseurlparams['d'] = $view->dataid;
            $baseurlparams['view'] = $view->id;

            $view->baseurl = new moodle_url(
                "/mod/datalynx/{$this->view->get_dl()->pagefile_for_urls()}.php",
                $baseurlparams
            );
        }
        if ($views) {
            foreach ($views as $view) {
                $viewname = $view->name;
                $viewlink = strpos($tag, "##viewlink:$viewname;");
                $sesslink = strpos($tag, "##viewsesslink:$viewname;");
                if ($viewlink === 0 || $sesslink === 0) {
                    // Already editing the entry so do not show link for editing entry.
                    if (
                        $sesslink === 0 && $currentview && $currentview->user_is_editing() &&
                        strpos($tag, 'editentries') !== false
                    ) {
                        return '';
                    }
                    // Strip leading and trailing ## delimiters.
                    $tagdefinition = substr($tag, 2, -2);
                    [, $linktext, $urlquery, $class] = array_pad(explode(';', $tagdefinition, 4), 4, '');
                    // Pix icon for text.
                    if (strpos($linktext, '_pixicon:') === 0) {
                        [, $icon, $titletext] = explode(':', $linktext);
                        $linktext = $OUTPUT->pix_icon($icon, $titletext);
                    }
                    // Replace pipes in urlquery with &.
                    $urlquery = str_replace('|', '&', $urlquery);
                    $urlquery = $this->resolve_nested_entry_tags($urlquery, $entry, $options ?? []);
                    $linkparams = [];
                    // If it is a link with session (viewsesslink).
                    if ($sesslink === 0) {
                        $linkparams['sesskey'] = sesskey();
                    }
                    $viewurl = new moodle_url($view->baseurl, $linkparams);
                    if ($sesslink === 0) {
                        if (!((strpos($urlquery, 'new=1') === false || $this->user_can_add_new_entry()))) {
                            return '';
                        }
                    }
                    $linkurl = $viewurl->out(false);
                    if ($urlquery !== '') {
                        $linkurl .= "&$urlquery";
                    }
                    return html_writer::link($linkurl, $linktext, ['class' => $class]);
                }
            }
        }

        $fields = $df->get_fields(null, true);

        foreach ($fields as $id => $fieldname) {
            if (strpos($tag, "%%{$fieldname}:bulkedit%%") === 0) {
                return html_writer::checkbox("field_{$id}_bulkedit", 1, false, '');
            }
        }

        return '';
    }

    /**
     * Find out if user can add a new entry
     *
     * @param number $userid
     * @return bool true if user is allowed to add a new entry
     */
    private function user_can_add_new_entry($userid = 0) {
        global $USER, $DB;
        $userid = $userid ? $userid : $USER->id;
        $df = $this->view->get_dl();
        $maxentries = $df->data->maxentries;
        $writeentry = has_capability('mod/datalynx:writeentry', $df->context);
        if ($writeentry) {
            if ($maxentries == -1) {
                return true;
            }
            $params = ['userid' => $userid, 'dataid' => $df->id()];
            $sql = "SELECT COUNT(1)
                      FROM {datalynx_entries} de
                     WHERE de.userid = :userid
                       AND de.dataid = :dataid";
            $count = $DB->get_field_sql($sql, $params);

            return $count < $maxentries;
        } else {
            return false;
        }
    }

    /**
     * Resolve nested entry field tags inside a view-link URL query string.
     *
     * @param string $text
     * @param mixed $entry
     * @param array $options
     * @return string
     */
    protected function resolve_nested_entry_tags(string $text, $entry = null, array $options = []): string {
        if (!$entry || strpos($text, '##') === false) {
            return $text;
        }

        preg_match_all('/##[^#]+##/', $text, $matches);
        $tags = array_unique($matches[0] ?? []);
        if (!$tags) {
            return $text;
        }

        $replacements = [];
        foreach ($this->view->get_dl()->get_fields() as $field) {
            $fieldtags = $field->renderer()->search($text);
            if (!$fieldtags) {
                continue;
            }

            $definitions = $field->get_definitions($fieldtags, $entry, $options);
            foreach ($definitions as $tag => $definition) {
                if (is_array($definition) && array_key_exists(1, $definition)) {
                    $replacements[$tag] = (string) $definition[1];
                } else if (is_scalar($definition)) {
                    $replacements[$tag] = (string) $definition;
                }
            }
        }

        if (!$replacements) {
            return $text;
        }

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Returns the values for number of total entries and number of entries displayed
     *
     * @param string $tag
     * @param mixed $entry
     * @param ?array $options
     * @return string
     */
    protected function get_info_replacements($tag, $entry = null, ?array $options = null) {
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
     * @param mixed $entry
     * @param ?array $options
     * @return string
     */
    protected function get_ref_replacements($tag, $entry = null, ?array $options = null) {
        if ($tag == '##viewsmenu##') {
            return $this->print_views_menu($options, true);
        }

        if ($tag == '##filtersmenu##') {
            return $this->print_filters_menu($options, true);
        }

        // View url.
        if ($tag == '##viewurl##') {
            return $this->get_viewurl_replacement();
        }

        if (strpos($tag, '##viewurl:') === 0) {
            [, $viewname] = explode(':', trim($tag, '#'));
            return $this->get_viewurl_replacement($viewname);
        }

        // View content.
        if (strpos($tag, '##viewcontent:') === 0) {
            [, $viewname] = explode(':', trim($tag, '#'));
            return $this->get_viewcontent_replacement($viewname);
        }

        return '';
    }

    /**
     * Get the HTML code that replaces the user preferences tags like
     * ##quickperpage##
     *
     * @param string $tag
     * @param ?array $options
     * @return string
     */
    protected function get_userpref_replacements($tag, ?array $options = null): string {
        $view = $this->view;
        $filter = $view->get_filter();
        // Do not display quickperpage and similar tags in edit view.
        if ($view->user_is_editing()) {
            return '';
        }
        if (!$view->is_forcing_filter() && (!$filter->id || $filter->customsearch || !empty($options['entriescount']))) {
            switch ($tag) {
                case '##quickperpage##':
                    return $this->print_quick_perpage(true);
                case '##advancedfilter##':
                    return $this->print_advanced_filter($filter, true);
            }
            // When we just updated an entry, only a continue button is shown, so do not display the form.
            if ($this->view->entriesprocessedsuccessfully) {
                return '';
            }
            if (strpos($tag, '##customfilter') !== false && !$view->user_is_editing()) {
                return $this->print_custom_filter($tag, true);
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
     * @param mixed $entry
     * @param ?array $options
     * @return string
     */
    protected function get_action_replacements($tag, $entry = null, ?array $options = null): string {
        global $CFG, $OUTPUT;
        $replacement = '';

        $view = $this->view;
        $df = $view->get_dl();
        $baseurl = new moodle_url($view->get_baseurl());
        $baseurl->param('sesskey', sesskey());
        // When user is editing then do not render these tags.
        if ($view->user_is_editing()) {
            return $replacement;
        }

        $showentryactions = (!empty($options['showentryactions']) ||
                has_capability('mod/datalynx:manageentries', $df->context));
        // TODO MDL-000000: move to a view attribute so as to call only once.
        // Can this user add entries?
        switch ($tag) {
            case '##addnewentry##':
            case '##addnewentries##':
                if (!empty($options['hidenewentry']) || !($this->user_can_add_new_entry())) {
                    break;
                }

                if ($tag == '##addnewentry##') {
                    if (!empty($df->data->singleedit)) {
                        $baseurl->param('view', $df->data->singleedit);
                    }
                    $baseurl->param('new', 1);
                    $label = html_writer::tag('span', get_string('entryaddnew', 'datalynx'));
                    $replacement = html_writer::link(
                        $baseurl,
                        $label,
                        ['class' => 'addnewentry']
                    );
                } else {
                    $range = range(1, 20);
                    $options = array_combine($range, $range);
                    $select = new single_select(
                        new moodle_url($baseurl),
                        'new',
                        $options,
                        null,
                        [0 => get_string('dots', 'datalynx')],
                        'newentries_jump'
                    );
                    $select->set_label(get_string('entryaddmultinew', 'datalynx') . '&nbsp;');
                    $replacement = $OUTPUT->render($select);
                }
                break;

            case '##multiduplicate##':
                $replacement = html_writer::empty_tag(
                    'input',
                    ['type' => 'button', 'name' => 'multiduplicate',
                                'value' => get_string('multiduplicate', 'datalynx'),
                                'onclick' => 'bulk_action(\'entry\'&#44; \'' . $baseurl->out(false) .
                                        '\'&#44; \'duplicate\')',
                        ]
                );
                break;

            case '##multiduplicate:icon##':
                if ($showentryactions) {
                    $replacement = html_writer::tag(
                        'button',
                        $OUTPUT->pix_icon('t/copy', get_string('multiduplicate', 'datalynx')),
                        ['type' => 'button', 'name' => 'multiduplicate',
                                    'onclick' => 'bulk_action(\'entry\'&#44; \'' . $baseurl->out(false) .
                                            '\'&#44; \'duplicate\')',
                            ]
                    );
                }
                break;

            case '##multiedit##':
                if ($showentryactions) {
                    $replacement = html_writer::empty_tag(
                        'input',
                        ['type' => 'button', 'name' => 'multiedit',
                                    'value' => get_string('multiedit', 'datalynx'),
                                    'onclick' => 'bulk_action(\'entry\'&#44; \'' . $baseurl->out(false) .
                                            '\'&#44; \'editentries\')',
                            ]
                    );
                }
                break;

            case '##multiedit:icon##':
                if ($showentryactions) {
                    $replacement = html_writer::tag(
                        'button',
                        $OUTPUT->pix_icon('t/edit', get_string('multiedit', 'datalynx')),
                        ['type' => 'button', 'name' => 'multiedit',
                                    'onclick' => 'bulk_action(\'entry\'&#44; \'' . $baseurl->out(false) .
                                            '\'&#44; \'editentries\')',
                            ]
                    );
                }
                break;

            case '##multidelete##':
                if ($showentryactions) {
                    $replacement = html_writer::empty_tag(
                        'input',
                        ['type' => 'button', 'name' => 'multidelete',
                                    'value' => get_string('multidelete', 'datalynx'),
                                    'onclick' => 'bulk_action(\'entry\'&#44; \'' . $baseurl->out(false) .
                                            '\'&#44; \'delete\')',
                            ]
                    );
                }
                break;

            case '##multidelete:icon##':
                if ($showentryactions) {
                    $replacement = html_writer::tag(
                        'button',
                        $OUTPUT->pix_icon('t/delete', get_string('multidelete', 'datalynx')),
                        ['type' => 'button', 'name' => 'multidelete',
                                    'onclick' => 'bulk_action(\'entry\'&#44; \'' . $baseurl->out(false) .
                                            '\'&#44; \'delete\')',
                            ]
                    );
                }
                break;

            case '##multiapprove##':
            case '##multiapprove:icon##':
                if ($df->data->approval && has_capability('mod/datalynx:approve', $df->context)) {
                    if ($tag == '##multiapprove##') {
                        $replacement = html_writer::empty_tag(
                            'input',
                            ['type' => 'button', 'name' => 'multiapprove',
                                        'value' => get_string('multiapprove', 'datalynx'),
                                        'onclick' => 'bulk_action(\'entry\'&#44; \'' .
                                                $baseurl->out(false) . '\'&#44; \'approve\')',
                                ]
                        );
                    } else {
                        $replacement = html_writer::tag(
                            'button',
                            $OUTPUT->pix_icon(
                                'i/grade_correct',
                                get_string('multiapprove', 'datalynx')
                            ),
                            ['type' => 'button', 'name' => 'multiapprove',
                                        'title' => trim(get_string('multiapprove', 'datalynx')),
                                        'onclick' => 'bulk_action(\'entry\'&#44; \'' .
                                                $baseurl->out(false) . '\'&#44; \'approve\')',
                                ]
                        );
                    }
                }
                break;

            case '##multiexport##':
                $buttonval = get_string('multiexport', 'datalynx');
                // Fall through.
            case '##multiexport:icon##':
                $buttonval = !isset($buttonval) ? $OUTPUT->pix_icon(
                    't/portfolioadd',
                    get_string('multiexport', 'datalynx')
                ) : $buttonval;

                if (!empty($CFG->enableportfolios)) {
                    if (!empty($format)) {
                        $baseurl->param('format', $format);
                    }
                    $replacement = html_writer::tag(
                        'button',
                        $buttonval,
                        ['type' => 'button', 'name' => 'multiexport',
                                    'onclick' => 'bulk_action(\'entry\'&#44; \'' . $baseurl->out(false) .
                                            '\'&#44; \'export\'&#44;-1)',
                            ]
                    );
                }
                break;

            case '##selectallnone##':
                $replacement = html_writer::checkbox(
                    null,
                    null,
                    false,
                    null,
                    ['onclick' => 'select_allnone(\'entry\'&#44;this.checked)',
                                'title' => get_string('multiselect', 'datalynx'),
                                'id' => 'datalynx-selectallnone',
                        ]
                );

                break;
        }

        return $replacement;
    }

    /**
     * Get HTML that replaces the tag ##pagingbar##
     *
     * @param ?array $options
     * @return string rendered paging bar
     */
    protected function get_paging_replacements(?array $options = null): string {
        global $OUTPUT;

        $view = $this->view;
        $filter = $view->get_filter();
        $baseurl = $view->get_baseurl();

        $pagingbar = null;

        // Typical entry 'more' request. If not single view (1 per page) show nothing instead of paging bar.
        if (!empty($filter->eids) || $view->user_is_editing()) {
            return '';
            // Typical groupby, one group per page case. show paging bar as per number of groups.
        } else {
            if (isset($filter->pagenum)) {
                $pagingbar = new paging_bar(
                    $filter->pagenum,
                    $filter->page,
                    1,
                    $baseurl . '&amp;',
                    'page',
                    '',
                    true
                );
                // Standard paging bar case.
            } else {
                if (
                        !empty($filter->perpage) && !empty($options['entriescount']) &&
                        !empty($options['entriesfiltercount']) &&
                        $options['entriescount'] != $options['entriesfiltercount']
                ) {
                    $pagingbar = new paging_bar(
                        $options['entriesfiltercount'],
                        $filter->page,
                        $filter->perpage,
                        $baseurl,
                        'page'
                    );
                } else { // No paging bar case at all:.
                    return '';
                }
            }
        }

        if ($pagingbar) {
            $replacement = $OUTPUT->render($pagingbar);
        } else {
            $replacement = "";
        }
        return $replacement;
    }

    /**
     * Convert array content fields to string
     *
     * @param array $contentfields
     * @return string
     */
    protected function contentfield_convert_array_to_string($contentfields) {

        $contentfieldstr = "";
        foreach ($contentfields as $key => $val) {
            if (!is_array($val)) {
                $contentfieldstr .= "&contentfields[" . $key . "]=" . $val;
            }
        }
        return $contentfieldstr;
    }

    /**
     * If viewname is not specified, return the URL of the current view
     * else return the URL of the $viewname
     *
     * @param ?string $viewname
     * @return string base URL of the view, empty string if view does not exist
     */
    protected function get_viewurl_replacement(?string $viewname = null) {
        $view = $this->view;

        // Return this view's url.
        if ($viewname === null) {
            return $view->get_baseurl()->out(false);
        }

        $df = $this->view->get_dl();
        $views = [];
        $theviews = $df->get_views();
        if (!empty($theviews)) {
            foreach ($theviews as $theview) {
                $views[$theview->name()] = $theview;
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
     * @param ?string $viewname
     * @return string
     */
    protected function get_viewcontent_replacement(?string $viewname = null): string {
        $df = $this->view->get_dl();
        $views = [];
        $theviews = $df->get_views();
        if (!empty($theviews)) {
            foreach ($theviews as $theview) {
                $views[$theview->name()] = $theview;
            }
        }

        if (!empty($views[$viewname])) {
            // Cannot display current view or else infinite loop.
            if ($views[$viewname]->id() == $this->view->id()) {
                return '';
            }

            $views[$viewname]->set_content();
            return $views[$viewname]->display(['tohtml' => true]);
        }
        return '';
    }

    /**
     * Get all tags in a single array
     *
     * @param boolean $checkvisibility if true only views visible to user are considered
     * @return array of tags/patterns
     */
    protected function patterns($checkvisibility = true): array {
        $patterns = array_merge(
            $this->info_patterns(),
            $this->ref_patterns($checkvisibility),
            $this->userpref_patterns(),
            $this->action_patterns(),
            $this->paging_patterns(),
            $this->viewlink_patterns($checkvisibility),
            $this->bulkedit_patterns()
        );
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
        $patterns = ['##numentriestotal##' => [true, $cat],
                '##numentriesdisplayed##' => [true, $cat]];
        return $patterns;
    }

    /**
     * Get view references with localised string
     * Only views visible to current user are used
     *
     * @param boolean $checkvisibility true if only views visible to the user should be considered
     * @return array associative array sith pattern string as value and key
     */
    protected function ref_patterns($checkvisibility = true) {
        $cat = get_string('reference', 'datalynx');
        $patterns = ['##viewurl##' => [true, $cat],
                '##viewsmenu##' => [true, $cat],
                '##filtersmenu##' => [true, $cat]];

        $df = $this->view->get_dl();

        $views = [];
        if ($checkvisibility) {
            $views = $df->get_views_menu();
        } else {
            $viewojects = $df->get_all_views();
            if (!empty($viewojects)) {
                foreach ($viewojects as $viewid => $view) {
                    $views[$viewid] = $view->name;
                }
            }
        }

        if ($views) {
            foreach ($views as $viewname) {
                $patterns["##viewurl:$viewname##"] = [false];
                $patterns["##viewcontent:$viewname##"] = [false];
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
        global $DB;

        $cat = get_string('userpref', 'datalynx');
        $patterns = ['##quicksearch##' => [true, $cat],
                '##quickperpage##' => [true, $cat],
                '##advancedfilter##' => [true, $cat]];
        $dataid = $this->view->view->dataid;
        $where = ['dataid' => $dataid, 'visible' => '1'];
        $rs = $DB->get_records('datalynx_customfilters', $where, 'name', 'id,name');
        foreach ($rs as $customfilter) {
            $patterns['##customfilter:' . $customfilter->name . '##'] = [true, $cat];
        }
        return $patterns;
    }

    /**
     * Get actions tags with localised string
     *
     * @return array multidimensional
     */
    protected function action_patterns() {
        $cat = get_string('generalactions', 'datalynx');
        $patterns = ['##addnewentry##' => [true, $cat], '##addnewentries##' => [true, $cat],
                '##selectallnone##' => [true, $cat], '##multiduplicate##' => [true, $cat],
                '##multiduplicate:icon##' => [true, $cat], '##multiedit##' => [true, $cat],
                '##multiedit:icon##' => [true, $cat], '##multidelete##' => [true, $cat],
                '##multidelete:icon##' => [true, $cat], '##multiapprove##' => [true, $cat],
                '##multiapprove:icon##' => [true, $cat], '##multiexport##' => [true, $cat],
                '##multiexport:icon##' => [true, $cat], '##multiimport##' => [true, $cat],
                '##multiimporty:icon##' => [true, $cat]];
        return $patterns;
    }

    /**
     * Get pagingbar tag
     *
     * @return array multidimensional
     */
    protected function paging_patterns() {
        $cat = get_string('pagingbar', 'datalynx');
        $patterns = ['##pagingbar##' => [true, $cat]];
        return $patterns;
    }

    /**
     * Get viewlink and viewsesslink stub menu patterns for each visible view.
     *
     * Each pattern is a concrete (non-regex) stub that can be selected from the dropdown
     * and inserted into the view template. The JS patterndialogue then handles them
     * as view-link tags (matched by VIEW_LINK_TAG_RE).
     *
     * @param bool $checkvisibility true if only views visible to the user should be considered
     * @return array multidimensional with pattern as key and array with showinmenu and category as value
     */
    protected function viewlink_patterns($checkvisibility = true) {
        $df = $this->view->get_dl();

        $views = [];
        $patterns = [];
        if ($checkvisibility) {
            $views = $df->get_views_menu();
        } else {
            $viewobjects = $df->get_all_views();
            if (!empty($viewobjects)) {
                foreach ($viewobjects as $viewid => $view) {
                    $views[$viewid] = $view->name;
                }
            }
        }

        if ($views) {
            $cat = get_string('reference', 'datalynx');
            foreach ($views as $viewname) {
                $patterns["##viewlink:$viewname;;;##"] = [true, $cat];
                $patterns["##viewsesslink:$viewname;;;##"] = [true, $cat];
            }
        }

        return $patterns;
    }

    /**
     * viewlink and viewsesslink tags with localised string
     *
     * @param boolean $checkvisibility true if only views visible to the user should be considered
     * @return array multidimensional with pattern as key and array with showinmenu and category as value
     */
    protected function regexp_patterns($checkvisibility = true) {
        $df = $this->view->get_dl();

        $views = [];
        $patterns = [];
        if ($checkvisibility) {
            $views = $df->get_views_menu();
        } else {
            $viewojects = $df->get_all_views();
            if (!empty($viewojects)) {
                foreach ($viewojects as $viewid => $view) {
                    $views[$viewid] = $view->name;
                }
            }
        }
        // Get list of views.
        if ($views) {
            // View link.
            $cat = get_string('reference', 'datalynx');
            foreach ($views as $viewname) {
                $viewname = preg_quote($viewname, '/');
                $patterns["##viewlink:$viewname;[^;]*;[^;]*;[a-z\d\-_\s]*##"] = [true, $cat];
                $patterns["##viewsesslink:$viewname;[^;]*;[^;]*;[a-z\d\-_\s]*##"] = [true, $cat];
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
        $df = $this->view->get_dl();

        $patterns = [];

        $fieldnames = $df->get_fieldnames();
        $cat = get_string('reference', 'datalynx');
        foreach ($fieldnames as $fieldname) {
            $fieldname = preg_quote($fieldname, '/');
            $patterns["%%{$fieldname}:bulkedit%%"] = [true, $cat];
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
        $df = $this->view->get_dl();

        $views = $df->get_views_menu();

        if ($views) {
            foreach ($views as $viewname) {
                if (strpos($pattern, "##viewlink:$viewname;") === 0) {
                    return true;
                }
                if (strpos($pattern, "##viewsesslink:$viewname;") === 0) {
                    return true;
                }
            }
        }

        $fields = $df->get_fields(null, true);

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
     * @param ?array $options
     * @param bool $return
     * @return string
     */
    protected function print_views_menu(?array $options = null, $return = false) {
        global $OUTPUT;
        $view = $this->view;
        $dl = $view->get_dl();
        $baseurl = $view->get_baseurl();
        $viewjump = '';
        $menuviews = $dl->get_views_menu();

        if (!empty($menuviews) && (count($menuviews) > 1)) {
            // Display the view form jump list.
            $baseurl = $baseurl->out_omit_querystring();
            $baseurlparams = ['d' => $dl->id(), 'sesskey' => sesskey()];
            $viewselect = new single_select(
                new moodle_url($baseurl, $baseurlparams),
                'view',
                $menuviews,
                $view->id(),
                ['' => 'choosedots'],
                'viewbrowse_jump'
            );
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
     *
     * @param ?array $options
     * @param bool $return
     * @return string
     */
    protected function print_filters_menu(?array $options = null, $return = false) {
        global $OUTPUT;

        $view = $this->view;

        // If in edit view filters should never be displayed.
        if ($view->user_is_editing()) {
            return '';
        }

        $df = $view->get_dl();
        $filter = $view->get_filter();
        $baseurl = $view->get_baseurl();

        $filterjump = '';

        if (!$view->is_forcing_filter() && ($filter->id || !empty($options['entriescount']))) {
            $fm = $df->get_filter_manager();
            if (!$menufilters = $fm->get_filters(null, true)) {
                $menufilters = [];
            }
            if ($userfilters = $fm->get_user_filters_menu($view->id())) {
                $menufilters[] = [get_string('filtermy', 'datalynx') => $userfilters];
            }

            $baseurl = $baseurl->out_omit_querystring();
            $baseurlparams = ['d' => $df->id(), 'sesskey' => sesskey(), 'view' => $view->id()];

            // Display the filter form jump list.
            $filterselect = new single_select(
                new moodle_url($baseurl, $baseurlparams),
                'filter',
                $menufilters,
                $filter->id,
                ['' => 'choosedots'],
                'filterbrowse_jump'
            );
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
     * @param ?mixed $options
     * @param bool $return
     * @return string
     */
    protected function print_quick_search($options, $return = false) {
        $view = $this->view;
        $df = $view->get_dl();
        $filter = $view->get_filter();
        $baseurl = $view->get_baseurl();

        $baseurl = $baseurl->out_omit_querystring();
        $baseurlparams = ['d' => $df->id(), 'sesskey' => sesskey(), 'view' => $view->id(),
                'filter' => $filter->id];
        $searchvalue = $filter->search;
        // Display the quick search form.
        $label = html_writer::label(get_string('search'), "usersearch");
        $inputfield = html_writer::empty_tag(
            'input',
            ['type' => 'text', 'name' => 'usersearch', 'id' => 'usersearch', 'value' => $searchvalue, 'size' => 20]
        );

        $button = '';

        $formparams = '';
        foreach ($baseurlparams as $var => $val) {
            $formparams .= html_writer::empty_tag(
                'input',
                ['type' => 'hidden', 'name' => $var, 'value' => $val]
            );
        }

        $attributes = ['method' => 'post', 'action' => new moodle_url($baseurl)];

        $qsform = html_writer::tag('form', "$formparams&nbsp;$label&nbsp;$inputfield&nbsp;$button", $attributes);

        // And finally one more wrapper with class.
        $quicksearchjump = html_writer::tag('div', $qsform, ['class' => 'singleselect']);

        if ($return) {
            return $quicksearchjump;
        } else {
            echo $quicksearchjump;
        }
    }

    /**
     * Echo or return the HTML for entries per page dropdown menu
     *
     * @param bool $return
     * @return string
     */
    protected function print_quick_perpage($return = false) {
        global $OUTPUT;

        $view = $this->view;
        $df = $view->get_dl();
        $filter = $view->get_filter();
        $baseurl = $view->get_baseurl();

        $baseurl = $baseurl->out_omit_querystring();
        $baseurlparams = ['d' => $df->id(), 'sesskey' => sesskey(), 'view' => $view->id(),
                'filter' => datalynx_filter_manager::USER_FILTER_SET];

        if ($filter->id < 0 && $filter->perpage) {
            $perpagevalue = $filter->perpage;
        } else {
            $perpagevalue = 0;
        }

        $perpage = [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9,
                10 => 10, 15 => 15, 20 => 20, 30 => 30, 40 => 40, 50 => 50, 100 => 100, 200 => 200,
                300 => 300, 400 => 400, 500 => 500, 1000 => 1000];

        // Display the view form jump list.
        $select = new single_select(
            new moodle_url($baseurl, $baseurlparams),
            'uperpage',
            $perpage,
            $perpagevalue,
            ['' => 'choosedots'],
            'perpage_jump'
        );
        $select->set_label(get_string('filterperpage', 'datalynx') . '&nbsp;');
        $perpagejump = $OUTPUT->render($select);

        if ($return) {
            return $perpagejump;
        } else {
            echo $perpagejump;
        }
    }

    /**
     * Retrieve and print advanced filter
     *
     * @param ?mixed $options
     * @param bool $return
     * @return string
     */
    protected function print_advanced_filter($options, $return = false) {

        $view = $this->view;
        $df = $view->get_dl();
        $filter = $view->get_filter();

        $fm = $df->get_filter_manager();
        $filterform = $fm->get_advanced_filter_form($filter, $view);

        if ($return) {
            return html_writer::tag(
                'div',
                $filterform->html(),
                ['class' => 'mdl-left']
            );
        } else {
            html_writer::start_tag('div', ['class' => 'mdl-left']);
            $filterform->display();
            html_writer::end_tag('div');
        }
    }

    /**
     * Get custom filter and print it
     *
     * @param string $tag Custom filter tag string.
     * @param bool $return Whether to return HTML instead of printing.
     * @return string
     */
    protected function print_custom_filter($tag, $return = false) {
        global $DB;

        $view = $this->view;
        $filter = $view->get_filter();
        $dl = $view->get_dl();
        $customfiltername = str_replace('##', '', str_replace('##customfilter:', '', $tag));
        $where = ['name' => $customfiltername, 'dataid' => $dl->id()];
        $customfilter = $DB->get_record('datalynx_customfilters', $where);
        $fm = $dl->get_filter_manager();
        $filterform = $fm->get_customfilter_frontend_form($filter, $view, $customfilter);

        if ($return) {
            return html_writer::tag('div', $filterform->html(), ['class' => 'mdl-left']);
        } else {
            html_writer::start_tag('div', ['class' => 'mdl-left']);
            $filterform->display();
            html_writer::end_tag('div');
        }
    }
}
