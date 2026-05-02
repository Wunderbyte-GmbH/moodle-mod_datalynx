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
 * This file is part of the Datalynx module for Moodle - http:// Moodle.org/.
 *
 *
 * @package datalynxview_tabular
 * @subpackage tabular
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxview_tabular;

use html_table;
use html_table_cell;
use html_table_row;
use html_writer;
use mod_datalynx\local\view\base;
use stdClass;

/**
 * A template for displaying datalynx entries in a tabular list
 * Parameters used:
 * param1 - activity grading
 * param2 - repeated entry section
 * param3 - table header
 */
class view extends base {
    /** @var string View type identifier. */
    protected string $type = 'tabular';

    /**
     * @var array List of editors
     */
    protected array $editors = ['section', 'param2'];

    /**
     * @var array List of view editors
     */
    protected array $vieweditors = ['section', 'param2'];

    /**
     * Generates the default view
     */
    public function generate_default_view() {
        // Get all the fields.
        $fields = $this->dl->get_fields();
        if (!$fields) {
            return; // You shouldn't get that far if there are no user fields.
        }

        // Remove fields that are used in fieldgroup.
        $fields = parent::remove_duplicates($fields);

        // Set views and filters menus and quick search.
        $table = new html_table();
        $table->attributes['cellpadding'] = '2';
        // First row: menus.
        $row1 = new html_table_row();
        $viewsmenu = new html_table_cell('##viewsmenu##');
        $seperator = new html_table_cell('     ');
        $filtersmenu = new html_table_cell('##filtersmenu##');
        $quicksearch = new html_table_cell('##quicksearch##');
        $quickperpage = new html_table_cell('##quickperpage##');
        $row1->cells = [$viewsmenu, $seperator, $filtersmenu, $quicksearch, $quickperpage];
        foreach ($row1->cells as $cell) {
            $cell->style = 'border:0 none;';
        }
        // Second row: add entries.
        $row2 = new html_table_row();
        $addentries = new html_table_cell('##addnewentry##');
        $addentries->colspan = 5;
        $row2->cells = [$addentries];
        foreach ($row2->cells as $cell) {
            $cell->style = 'border:0 none;';
        }
        // Third row: paging bar.
        $row3 = new html_table_row();
        $pagingbar = new html_table_cell('##pagingbar##');
        $pagingbar->colspan = 5;
        $row3->cells = [$pagingbar];
        foreach ($row3->cells as $cell) {
            $cell->style = 'border:0 none;';
        }
        // Construct the table.
        $table->data = [$row1, $row2, $row3];
        $sectiondefault = html_writer::table($table);
        $this->view->esection = html_writer::tag(
            'div',
            $sectiondefault,
            ['class' => 'mdl-align']
        ) . '<div class="mod-datalynx-tabular-entries" data-region="tabular-view-browser">##entries##</div>';

        // Set content table.
        $table = new html_table();
        $table->attributes['cellpadding'] = '2';
        $table->responsive = false; // Prevent wrapping in <div class="table-responsive">.
        $header = [];
        $entry = [];
        $align = [];
        // Author picture.
        $header[] = '';
        $entry[] = '##author:picture##';
        $align[] = 'center';
        // Author name.
        $header[] = '';
        $entry[] = '##author:name##';
        $align[] = 'left';

        // Fields.
        foreach ($fields as $field) {
            if (is_numeric($field->field->id) && $field->field->id > 0) {
                $header[] = $field->field->name . " %%{$field->field->name}:bulkedit%%";
                if ($field->type == "userinfo") {
                    $entry[] = "##author:{$field->field->name}##";
                } else {
                    $entry[] = '[[' . $field->field->name . ']]';
                }
                $align[] = 'left';
            }
        }
        // Multiedit.
        $header[] = '##multiedit:icon##';
        $entry[] = '##edit##';
        $align[] = 'center';
        // Multidelete.
        $header[] = '##multidelete:icon##';
        $entry[] = '##delete##';
        $align[] = 'center';
        // Multiapprove.
        $header[] = '##multiapprove:icon##';
        $entry[] = '##approve##';
        $align[] = 'center';
        // Selectallnone.
        $header[] = '##selectallnone##';
        $entry[] = '##select##';
        $align[] = 'center';

        // Construct the table.
        $table->head = $header;
        $table->align = $align;
        $table->data[] = $entry;
        $this->view->eparam2 = html_writer::table($table);
    }

    /**
     * Display the view
     *
     * @param array $options
     * @return string
     */
    public function display(array $options = []): string {
        global $PAGE;

        if (!$this->returntoentriesform && !$this->user_is_editing()) {
            $filteroptions = [
                'page' => (int) ($this->filter->page ?? 0),
            ];
            $activefilterid = optional_param('filter', 0, PARAM_INT);
            if (!empty($activefilterid)) {
                $filteroptions['filterid'] = $activefilterid;
            } else if (!empty($this->filter->id)) {
                $filteroptions['filterid'] = (int) $this->filter->id;
            }
            if (!empty($this->filter->perpage)) {
                $filteroptions['perpage'] = (int) $this->filter->perpage;
            }
            if (!empty($this->filter->eids)) {
                $filteroptions['eids'] = $this->filter->eids;
            }
            if (!empty($this->filter->customsort)) {
                $filteroptions['customsort'] = $this->filter->customsort;
            }
            if (!empty($this->filter->customsearch)) {
                $filteroptions['customsearch'] = unserialize($this->filter->customsearch);
            }
            if (!empty($this->filter->search)) {
                $filteroptions['search'] = (string) $this->filter->search;
            }
            if (!empty($this->filter->selection)) {
                $filteroptions['selection'] = (int) $this->filter->selection;
            }
            if (!empty($this->filter->groupby)) {
                $filteroptions['groupby'] = (string) $this->filter->groupby;
            }
            if (!empty($this->filter->users)) {
                $filteroptions['users'] = $this->filter->users;
            }
            if (!empty($this->filter->groups)) {
                $filteroptions['groups'] = $this->filter->groups;
            }

            $this->set_filter($filteroptions, $this->is_forcing_filter());
            $this->set_content();
        }

        $tohtml = $options['tohtml'] ?? false;
        $browsemode = !$this->returntoentriesform && !$this->user_is_editing() && !optional_param('new', 0, PARAM_INT) &&
            !$this->entriesprocessedsuccessfully;
        $useajaxbrowser = false;
        $output = parent::display(array_merge($options, ['tohtml' => true]));

        if ($useajaxbrowser) {
            $legacyentries = $this->entries->get_count() ? $this->display_entries($options) : $this->display_no_entries();
            $browserregion = html_writer::tag(
                'div',
                $legacyentries,
                ['class' => 'mod-datalynx-tabular-entries', 'data-region' => 'tabular-view-browser']
            );
            $position = strpos($output, $legacyentries);
            if ($position !== false) {
                $output = substr_replace($output, $browserregion, $position, strlen($legacyentries));
            }
        }

        if ($tohtml) {
            return $output;
        }

        echo $output;

        $PAGE->requires->js_call_amd('mod_datalynx/bulkactions', 'init');
        $PAGE->requires->js_call_amd('mod_datalynx/tabularbulkedit', 'init');
        if ($useajaxbrowser) {
            $selector = '[data-id="' . $this->dl->id() . '"][data-viewid="' . $this->id() .
                '"] [data-region="tabular-view-browser"]';
            $args = [
                'd' => (int) $this->dl->id(),
                'view' => (int) $this->id(),
                'filterid' => (int) ($this->filter->id ?? 0),
                'page' => (int) ($this->filter->page ?? 0),
            ];
            if (!empty($this->filter->perpage)) {
                $args['perpage'] = (int) $this->filter->perpage;
            }
            if (!empty($this->filter->eids)) {
                $args['eids'] = is_array($this->filter->eids) ? implode(',', $this->filter->eids) : (string) $this->filter->eids;
            }
            if (!empty($this->filter->customsort)) {
                $args['customsort'] = $this->filter->customsort;
            }
            if (!empty($this->filter->customsearch)) {
                $args['customsearch'] = $this->filter->customsearch;
            }
            if (!empty($this->filter->search)) {
                $args['search'] = (string) $this->filter->search;
            }
            if (!empty($this->filter->selection)) {
                $args['selection'] = (int) $this->filter->selection;
            }
            if (!empty($this->filter->groupby)) {
                $args['groupby'] = (string) $this->filter->groupby;
            }
            if (!empty($this->filter->users)) {
                $args['users'] = is_array($this->filter->users)
                    ? implode(',', $this->filter->users)
                    : (string) $this->filter->users;
            }
            if (!empty($this->filter->groups)) {
                $args['groups'] = is_array($this->filter->groups)
                    ? implode(',', $this->filter->groups)
                    : (string) $this->filter->groups;
            }

            $PAGE->requires->js_call_amd('mod_datalynx/viewbrowser', 'init', [$selector, [
                'methodname' => 'mod_datalynx_get_tabular_view_data',
                'template' => 'mod_datalynx/tabular_view_browser',
                'args' => $args,
            ]]);
        }

        return '';
    }

    /**
     * Apply entry group layout
     *
     * @param array $entriesset
     * @param string $name
     * @return array
     */
    protected function apply_entry_group_layout($entriesset, $name = '') {
        global $OUTPUT;

        $tablehtml = trim($this->view->eparam2);
        $opengroupdiv = html_writer::start_tag('div', ['class' => 'entriesview']);
        $closegroupdiv = html_writer::end_tag('div');
        if ($name) {
            $name = ($name == 'newentry' ? get_string('entrynew', 'datalynx') : $name);
        }
        $groupheading = $OUTPUT->heading($name, 3, 'main');

        $elements = [];

        // If there are no field definition just return everything as html.
        if (empty($entriesset)) {
            $elements[] = ['html', $opengroupdiv . $groupheading . $tablehtml . $closegroupdiv];
        } else {
            // Strip the table-responsive wrapper that html_writer::table() adds by default
            // in newer Moodle versions, so the template starts with <table>.
            $tablehtml = preg_replace('/^<div[^>]*\btable-responsive\b[^>]*>\s*/i', '', $tablehtml);
            $tablehtml = trim(preg_replace('/\s*<\/div>$/i', '', $tablehtml));
            $tablehtml = $this->annotate_bulkedit_controls($tablehtml);

            // Clean any prefix and get the open table tag.
            $tablepattern = '/^<table[^>]*>/i';
            preg_match($tablepattern, $tablehtml, $match); // Must be there.
            $tablehtml = trim(preg_replace($tablepattern, '', $tablehtml));
            $opentable = reset($match);
            // Clean any suffix and get the close table tag.
            $tablehtml = trim(preg_replace('/<\/table>$/i', '', $tablehtml));
            $closetable = '</table>';

            // Always extract the <thead> block so it never ends up in the entry template.
            $requireheaderrow = $this->view->param3;
            $headerrow = '';
            if (strpos($tablehtml, '<thead>') === 0) {
                $theadpattern = '/^<thead>[\s\S]*<\/thead>/i';
                preg_match($theadpattern, $tablehtml, $match);
                $tablehtml = trim(preg_replace($theadpattern, '', $tablehtml));
                if ($requireheaderrow) {
                    $headerrow = reset($match);
                }
            }
            // Remove tbody wrapper tags.
            if (strpos($tablehtml, '<tbody>') === 0) {
                $tablehtml = trim(preg_replace('/^<tbody>|<\/tbody>$/i', '', $tablehtml));
            }
            // Fallback: if header is required but no <thead> was found, use the first <tr>.
            if ($requireheaderrow && empty($headerrow)) {
                $trpattern = '/^<tr>[\s\S]*<\/tr>/i';
                preg_match($trpattern, $tablehtml, $match);
                $tablehtml = trim(preg_replace($trpattern, '', $tablehtml));
                $headerrow = '<thead>' . reset($match) . '</thead>';
            }
            // The reset of $tablehtml should be the entry template.
            $entrytemplate = $tablehtml;
            // Construct elements.
            // First everything before the entrytemplate as html.
            $elements[] = ['html', $opengroupdiv . $groupheading . $opentable . $headerrow . '<tbody>',
            ];

            // Do the entries.
            // Get tags from the first item in the entry set.
            $tagsitem = reset($entriesset);
            $tagsitem = reset($tagsitem);
            $tags = array_keys($tagsitem);

            foreach ($entriesset as $fielddefinitions) {
                $definitions = reset($fielddefinitions);
                $parts = $this->split_template_by_tags($tags, $entrytemplate);

                foreach ($parts as $part) {
                    if (in_array($part, $tags)) {
                        if ($def = $definitions[$part]) {
                            $elements[] = $def;
                        }
                    } else {
                        $elements[] = ['html', $part];
                    }
                }
            }

            // Finish the table.
            $elements[] = ['html', '</tbody>' . $closetable . $closegroupdiv];
        }

        return $elements;
    }

    /**
     * Add stable bulk-edit data attributes to rendered tabular controls.
     *
     * This lets the AMD module target inputs without parsing generated field names.
     *
     * @param string $html
     * @return string
     */
    private function annotate_bulkedit_controls(string $html): string {
        $html = preg_replace_callback(
            '/<(input|select|textarea)\b([^>]*\bname=(["\'])field_(\d+)_bulkedit\3[^>]*?)(\s*\/?)>/i',
            static function (array $matches): string {
                [$fullmatch, $tagname, $attributes, , $fieldid, $selfclosing] = $matches;

                if (strpos($attributes, 'data-datalynx-bulkedit-toggle') !== false) {
                    return $fullmatch;
                }

                $attributes = self::append_css_class($attributes, 'datalynx-tabular-bulkedit-toggle');
                $attributes .= ' data-datalynx-bulkedit-toggle="1"';
                $attributes .= ' data-datalynx-bulkedit-field="' . $fieldid . '"';

                return "<{$tagname}{$attributes}{$selfclosing}>";
            },
            $html
        );

        return preg_replace_callback(
            '/<(input|select|textarea)\b([^>]*\bname=(["\'])field_(\d+)_(\d+)(?:\[[^"\']*\])?\3[^>]*?)(\s*\/?)>/i',
            static function (array $matches): string {
                [$fullmatch, $tagname, $attributes, , $fieldid, $entryid, $selfclosing] = $matches;

                if (strpos($attributes, 'data-datalynx-bulkedit-input') !== false) {
                    return $fullmatch;
                }

                $attributes = self::append_css_class($attributes, 'datalynx-tabular-bulkedit-input');
                $attributes .= ' data-datalynx-bulkedit-input="1"';
                $attributes .= ' data-datalynx-bulkedit-field="' . $fieldid . '"';
                $attributes .= ' data-datalynx-entryid="' . $entryid . '"';

                return "<{$tagname}{$attributes}{$selfclosing}>";
            },
            $html
        );
    }

    /**
     * Append a CSS class to an HTML attribute fragment.
     *
     * @param string $attributes
     * @param string $classname
     * @return string
     */
    private static function append_css_class(string $attributes, string $classname): string {
        if (preg_match('/\bclass=(["\'])([^"\']*)\1/i', $attributes, $matches)) {
            $currentclasses = preg_split('/\s+/', trim($matches[2]));
            if (!in_array($classname, $currentclasses)) {
                $currentclasses[] = $classname;
            }
            $replacement = 'class=' . $matches[1] . trim(implode(' ', array_filter($currentclasses))) . $matches[1];
            return preg_replace('/\bclass=(["\'])([^"\']*)\1/i', $replacement, $attributes, 1);
        }

        return $attributes . ' class="' . $classname . '"';
    }

    /**
     * Entry definition
     *
     * @param array $fielddefinitions
     * @return array
     */
    protected function entry_definition($fielddefinitions) {
        $elements = [];
        // Just store the definitions.
        // And group_entries_definition will process them.
        $elements[] = $fielddefinitions;
        return $elements;
    }

    /**
     * New entry definition
     *
     * @param int $entryid
     * @return array
     */
    protected function new_entry_definition($entryid = -1) {
        $elements = [];

        // Get patterns definitions.
        $fields = $this->dl->get_fields();
        $fielddefinitions = [];
        $entry = new stdClass();
        foreach ($this->tags['field'] as $fieldid => $patterns) {
            $field = $fields[$fieldid];
            $entry->id = $entryid;
            $options = ['edit' => true, 'manage' => true];
            if ($definitions = $field->get_definitions($patterns, $entry, $options)) {
                $fielddefinitions = array_merge($fielddefinitions, $definitions);
            }
        }

        $elements[] = $fielddefinitions;
        return $elements;
    }
}
