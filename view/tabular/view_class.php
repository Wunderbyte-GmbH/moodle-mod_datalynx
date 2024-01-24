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
 * @package datalynxview
 * @subpackage tabular
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/view/view_class.php");

/**
 * A template for displaying datalynx entries in a tabular list
 * Parameters used:
 * param1 - activity grading
 * param2 - repeated entry section
 * param3 - table header
 */
class datalynxview_tabular extends datalynxview_base {

    protected $type = 'tabular';

    protected $_editors = array('section', 'param2');

    protected $_vieweditors = array('section', 'param2');

    /**
     */
    public function generate_default_view() {
        // Get all the fields.
        $fields = $this->_df->get_fields();
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

        // Set content table.
        $table = new html_table();
        $table->attributes['cellpadding'] = '2';
        $header = array();
        $entry = array();
        $align = array();
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

    public function display(array $options = array()) {
        parent::display($options);
        global $PAGE;
        $PAGE->requires->js_init_call('M.datalynxview_tabular.init', array(), false,
                $this->get_js_module());
    }

    private function get_js_module() {
        $jsmodule = array('name' => 'datalynxview_tabular',
                'fullpath' => '/mod/datalynx/view/tabular/tabular.js',
                'requires' => array('node', 'event', 'node-event-delegate'
                ));
        return $jsmodule;
    }

    /**
     */
    protected function apply_entry_group_layout($entriesset, $name = '') {
        global $OUTPUT;

        $tablehtml = trim($this->view->eparam2);
        $opengroupdiv = html_writer::start_tag('div', array('class' => 'entriesview'));
        $closegroupdiv = html_writer::end_tag('div');
        if ($name) {
            $name = ($name == 'newentry' ? get_string('entrynew', 'datalynx') : $name);
        }
        $groupheading = $OUTPUT->heading($name, 3, 'main');

        $elements = array();

        // If there are no field definition just return everything as html.
        if (empty($entriesset)) {
            $elements[] = array('html', $opengroupdiv . $groupheading . $tablehtml . $closegroupdiv);
        } else {

            // Clean any prefix and get the open table tag.
            $tablepattern = '/^<table[^>]*>/i';
            preg_match($tablepattern, $tablehtml, $match); // Must be there.
            $tablehtml = trim(preg_replace($tablepattern, '', $tablehtml));
            $opentable = reset($match);
            // Clean any suffix and get the close table tag.
            $tablehtml = trim(preg_replace('/<\/table>$/i', '', $tablehtml));
            $closetable = '</table>';

            // Get the header row if required.
            $headerrow = '';
            if ($requireheaderrow = $this->view->param3) {
                if (strpos($tablehtml, '<thead>') === 0) {
                    // Get the header row and remove from subject.
                    $theadpattern = '/^<thead>[\s\S]*<\/thead>/i';
                    preg_match($theadpattern, $tablehtml, $match);
                    $tablehtml = trim(preg_replace($theadpattern, '', $tablehtml));
                    $headerrow = reset($match);
                }
            }
            // We may still need to get the header row.
            // But first remove tbody tags.
            if (strpos($tablehtml, '<tbody>') === 0) {
                $tablehtml = trim(preg_replace('/^<tbody>|<\/tbody>$/i', '', $tablehtml));
            }
            // Assuming a simple two rows structure for now.
            // If no theader the first row should be the header.
            if ($requireheaderrow && empty($headerrow)) {
                // Assuming header row does not contain nested tables.
                $trpattern = '/^<tr>[\s\S]*<\/tr>/i';
                preg_match($trpattern, $tablehtml, $match);
                $tablehtml = trim(preg_replace($trpattern, '', $tablehtml));
                $headerrow = '<thead>' . reset($match) . '</thead>';
            }
            // The reset of $tablehtml should be the entry template.
            $entrytemplate = $tablehtml;
            // Construct elements.
            // First everything before the entrytemplate as html.
            $elements[] = array('html', $opengroupdiv . $groupheading . $opentable . $headerrow . '<tbody>'
            );

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
                        $elements[] = array('html', $part);
                    }
                }
            }

            // Finish the table.
            $elements[] = array('html', '</tbody>' . $closetable . $closegroupdiv);
        }

        return $elements;
    }

    /**
     */
    protected function entry_definition($fielddefinitions) {
        $elements = array();
        // Just store the definitions.
        // And group_entries_definition will process them.
        $elements[] = $fielddefinitions;
        return $elements;
    }

    /**
     */
    protected function new_entry_definition($entryid = -1) {
        $elements = array();

        // Get patterns definitions.
        $fields = $this->_df->get_fields();
        $fielddefinitions = array();
        $entry = new stdClass();
        foreach ($this->_tags['field'] as $fieldid => $patterns) {
            $field = $fields[$fieldid];
            $entry->id = $entryid;
            $options = array('edit' => true, 'manage' => true);
            if ($definitions = $field->get_definitions($patterns, $entry, $options)) {
                $fielddefinitions = array_merge($fielddefinitions, $definitions);
            }
        }

        $elements[] = $fielddefinitions;
        return $elements;
    }
}
