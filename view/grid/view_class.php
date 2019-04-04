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
 * @package datalynxview
 * @subpackage grid
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/view/view_class.php");

class datalynxview_grid extends datalynxview_base {

    protected $type = 'grid';

    protected $_editors = array('section', 'param2');

    /**
     * Returns a fieldset of view options
     */
    public function generate_default_view() {
        // Get all the fields.
        if (!$fields = $this->_df->get_fields()) {
            return; // You shouldn't get that far if there are no user fields.
        }

        // Remove fields that are used in fieldgroup.
        $fields = parent::remove_duplicates($fields);

        // Set views and filters menus and quick search.
        $table = new html_table();
        $table->attributes['align'] = 'center';
        $table->attributes['cellpadding'] = '2';
        // First row: menus.
        $row1 = new html_table_row();
        $viewsmenu = new html_table_cell('##viewsmenu##');
        $seperator = new html_table_cell('     ');
        $filtersmenu = new html_table_cell('##filtersmenu##');
        $quicksearch = new html_table_cell('##quicksearch##');
        $quickperpage = new html_table_cell('##quickperpage##');
        $row1->cells = array($viewsmenu, $seperator, $filtersmenu, $quicksearch, $quickperpage);
        foreach ($row1->cells as $cell) {
            $cell->style = 'border:0 none;';
        }
        // Second row: add entries.
        $row2 = new html_table_row();
        $addentries = new html_table_cell('##addnewentry##');
        $addentries->colspan = 5;
        $row2->cells = array($addentries);
        foreach ($row2->cells as $cell) {
            $cell->style = 'border:0 none;';
        }
        // Third row: paging bar.
        $row3 = new html_table_row();
        $pagingbar = new html_table_cell('##pagingbar##');
        $pagingbar->colspan = 5;
        $row3->cells = array($pagingbar);
        foreach ($row3->cells as $cell) {
            $cell->style = 'border:0 none;';
        }
        // Construct the table.
        $table->data = array($row1, $row2, $row3);
        $sectiondefault = html_writer::table($table);
        $this->view->esection = html_writer::tag('div', $sectiondefault,
                        array('class' => 'mdl-align')) . "<div>##entries##</div>";

        // Set content.
        $table = new html_table();
        $table->attributes['align'] = 'center';
        $table->attributes['cellpadding'] = '2';

        // Fields.
        foreach ($fields as $field) {

            if ($field->field->id > 0) {
                $name = new html_table_cell($field->name() . ':');
                $name->style = 'text-align:right;';
                if ($field->type == "userinfo") {
                    $content = new html_table_cell("##author:{$field->name()}##");
                } else {
                    $content = new html_table_cell("[[{$field->name()}]]");
                }
                $row = new html_table_row();
                $row->cells = array($name, $content);
                $table->data[] = $row;
            }
        }
        // Actions.
        $row = new html_table_row();
        $actions = new html_table_cell('##edit##  ##delete##');
        $actions->colspan = 2;
        $row->cells = array($actions);
        $table->data[] = $row;
        // Construct the table.
        $entrydefault = html_writer::table($table);
        $this->view->eparam2 = html_writer::tag('div', $entrydefault, array('class' => 'entry'));
    }

    /**
     */
    protected function apply_entry_group_layout($entriesset, $name = '') {
        global $OUTPUT;

        $elements = array();

        // Prepare grid table if needed.
        if ($name != 'newentry' and !empty($this->view->param3)) {
            $entriescount = count($entriesset);
            list($cols, $rows) = explode(' ', $this->view->param3);
            if ($entriescount < $cols) {
                $cols = $entriescount;
                $rows = 1;
            } else {
                if ($rows) {
                    $rows = ceil($entriescount / $cols);
                } else {
                    $rows = 1;
                    $percol = ceil($entriescount / $cols) > 1 ? ceil($entriescount / $cols) : null;
                }
            }

            $table = $this->make_table($cols, $rows);
            $grouphtml = html_writer::table($table);
            // Now split $tablehtml to cells by ##begintablecell##.
            $cells = explode('##begintablecell##', $grouphtml);
            // The first part is everything before first cell.
            $elements[] = array('html', array_shift($cells));
        }

        // Flatten the set to a list of elements.
        $count = 0;
        foreach ($entriesset as $entrydefinitions) {
            $elements = array_merge($elements, $entrydefinitions);
            if (!empty($cells)) {
                if (empty($percol) or $count >= $percol - 1) {
                    $count = 0;
                    $elements[] = array('html', array_shift($cells));
                } else {
                    $count++;
                }
            }
        }

        // Add remaining cells.
        if (!empty($cells)) {
            foreach ($cells as $cell) {
                $elements[] = array('html', $cell);
            }
        }

        // Add group heading.
        $name = ($name == 'newentry') ? get_string('entrynew', 'datalynx') : $name;
        if ($name) {
            array_unshift($elements, array('html', $OUTPUT->heading($name, 3, 'main')));
        }

        return $elements;
    }

    /**
     */
    protected function new_entry_definition($entryid = -1) {
        $elements = array();

        // Get patterns definitions.
        $fields = $this->_df->get_fields();
        $tags = array();
        $patterndefinitions = array();
        $entry = new stdClass();
        foreach ($this->_tags['field'] as $fieldid => $patterns) {
            if (isset($fields[$fieldid])) {
                $field = $fields[$fieldid];
                $entry->id = $entryid;
                $options = array('edit' => true, 'manage' => true);
                if ($fielddefinitions = $field->get_definitions($patterns, $entry, $options)) {
                    $patterndefinitions = array_merge($patterndefinitions, $fielddefinitions);
                }
                $tags = array_merge($tags, $patterns);
            }
        }

        // Split the entry template to tags and html.
        $parts = $this->split_template_by_tags($tags, $this->view->eparam2);

        foreach ($parts as $part) {
            if (in_array($part, $tags)) {
                if ($def = $patterndefinitions[$part]) {
                    $elements[] = $def;
                }
            } else {
                $elements[] = array('html', $part);
            }
        }

        return $elements;
    }

    /**
     */
    protected function make_table($cols, $rows) {
        $table = new html_table();
        $table->align = array_fill(0, $cols, 'center');
        $table->attributes['align'] = 'center';
        for ($r = 0; $r < $rows; $r++) {
            $row = new html_table_row();
            for ($c = 0; $c < $cols; $c++) {
                $cell = new html_table_cell();
                $cell->text = '##begintablecell##';
                $row->cells[] = $cell;
            }
            $table->data[] = $row;
        }

        return $table;
    }
}
