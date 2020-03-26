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
 * @subpackage csv
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/view/view_class.php");

class datalynxview_csv extends datalynxview_base {

    const EXPORT_ALL = 'all';

    const EXPORT_PAGE = 'page';

    protected $type = 'csv';

    protected $_output = 'csv';

    protected $_delimiter = 'comma';

    protected $_enclosure = '';

    protected $_encoding = 'UTF-8';

    protected $_editors = array('section');

    protected $_columns = null;

    protected $_showimportform = false;

    /**
     */
    public function __construct($df = 0, $view = 0) {
        parent::__construct($df, $view);
        if (!empty($this->view->param3)) {
            $this->_output = $this->view->param3;
        }
        if (!empty($this->view->param1)) {
            list($this->_delimiter, $this->_enclosure, $this->_encoding) = explode(',',
                    $this->view->param1);
        }
    }

    /**
     */
    protected function apply_entry_group_layout($entriesset, $name = '') {
        global $OUTPUT;

        $elements = array();

        // Generate the header row.
        $tableheader = '';
        if ($this->has_headers()) {
            $columns = $this->get_columns();
            foreach ($columns as $column) {
                list(, $header, $class) = $column;
                $tableheader .= html_writer::tag('th', $header, array('class' => $class));
            }
            $tableheader = html_writer::tag('thead', html_writer::tag('tr', $tableheader));

            // Set view tags in header row.
            $tags = $this->_tags['view'];
            $replacements = $this->patternclass()->get_replacements($tags);
            $tableheader = str_replace($tags, $replacements, $tableheader);
        }
        // Open table and wrap header with thead.
        $elements[] = array('html',
                html_writer::start_tag('table', array('class' => 'generaltable')) . $tableheader);

        // Flatten the set to a list of elements, wrap with tbody and close table.
        $elements[] = array('html', html_writer::start_tag('tbody'));
        foreach ($entriesset as $entryid => $entrydefinitions) {
            $elements = array_merge($elements, $entrydefinitions);
        }
        $elements[] = array('html', html_writer::end_tag('tbody') . html_writer::end_tag('table'));

        // Add group heading.
        $name = ($name == 'newentry') ? get_string('entrynew', 'datalynx') : $name;
        if ($name) {
            array_unshift($elements, array('html', $OUTPUT->heading($name, 3, 'main')));
        }
        // Wrap with entriesview.
        array_unshift($elements, array('html', html_writer::start_tag('div', array('class' => 'entriesview'))));
        array_push($elements, array('html', html_writer::end_tag('div')));

        return $elements;
    }

    /**
     * process any view specific actions
     */
    public function process_data() {
        global $CFG;

        // Proces csv export request.
        if ($exportcsv = optional_param('exportcsv', '', PARAM_ALPHA)) {
            $this->process_export($exportcsv);
        }

        // Proces csv import request.
        if ($importcsv = optional_param('importcsv', 0, PARAM_INT)) {
            $this->process_import();
        }
    }

    /**
     */
    protected function entry_definition($fielddefinitions) {
        $elements = array();
        // Get the columns definition from the view template.
        $columns = $this->get_columns();

        // Generate entry table row.
        $elements[] = array('html', html_writer::start_tag('tr'));
        foreach ($columns as $column) {
            list($tag, , $class) = array_map('trim', $column);
            if (!empty($fielddefinitions[$tag])) {
                $fielddefinition = $fielddefinitions[$tag];
                if ($fielddefinition[0] == 'html') {
                    $elements[] = array('html',
                            html_writer::tag('td', $fielddefinition[1], array('class' => $class))
                    );
                } else {
                    $elements[] = array('html',
                            html_writer::start_tag('td', array('class' => $class))
                    );
                    $elements[] = $fielddefinition;
                    $elements[] = array('html', html_writer::end_tag('td'));
                }
            } else {
                $elements[] = array('html', html_writer::tag('td', '', array('class' => $class)));
            }
        }
        $elements[] = array('html', html_writer::end_tag('tr'));

        return $elements;
    }

    /**
     */
    protected function new_entry_definition($entryid = -1) {
        $elements = array();

        // Get the columns definition from the view template.
        $columns = $this->get_columns();

        // Get field definitions for new entry.
        $fields = $this->_df->get_fields();
        $entry = (object) array('id' => $entryid);
        $fielddefinitions = array();
        foreach ($this->_tags['field'] as $fieldid => $patterns) {
            $field = $fields[$fieldid];
            $options = array('edit' => true, 'manage' => true);
            if ($definitions = $field->get_definitions($patterns, $entry, $options)) {
                $fielddefinitions = array_merge($fielddefinitions, $definitions);
            }
        }

        // Generate entry table row.
        $elements[] = array('html', html_writer::start_tag('tr'));
        foreach ($columns as $column) {
            list($tag, , $class) = array_map('trim', $column);
            if (!empty($fielddefinitions[$tag])) {
                $fielddefinition = $fielddefinitions[$tag];
                if ($fielddefinition[0] == 'html') {
                    $elements[] = array('html',
                            html_writer::tag('td', $fielddefinition[1], array('class' => $class))
                    );
                } else {
                    $elements[] = array('html',
                            html_writer::start_tag('td', array('class' => $class))
                    );
                    $elements[] = $fielddefinition;
                    $elements[] = array('html', html_writer::end_tag('td'));
                }
            } else {
                $elements[] = array('html', html_writer::tag('td', '', array('class' => $class)));
            }
        }
        $elements[] = array('html', html_writer::end_tag('tr'));

        return $elements;
    }

    /**
     * Add the patterns we store in param2 to all the other used patterns.
     */
    protected function set__patterns() {
        parent::set__patterns();

        // Get patterns from param2.
        $text = !empty($this->view->param2) ? $this->view->param2 : '';
        if (trim($text)) {
            // This view patterns.
            if ($patterns = $this->patternclass()->search($text)) {
                $this->_tags['view'] = array_merge($this->_tags['view'], $patterns);
            }
            // Field patterns.
            if ($fields = $this->_df->get_fields()) {
                foreach ($fields as $fieldid => $field) {
                    if ($patterns = $field->renderer()->search($text)) {
                        $this->_tags['field'][$fieldid] = $patterns;
                    }
                }
            }
        }
        $this->view->patterns = serialize($this->_tags);
    }

    /**
     * Overridden to show import form without entries
     */
    public function display(array $params = array()) {
        if ($this->_showimportform) {

            $mform = $this->get_import_form();

            $tohtml = isset($params['tohtml']) ? $params['tohtml'] : false;
            // Print view.
            $viewname = 'datalynxview-' . preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $this->name()));
            if ($tohtml) {
                return html_writer::tag('div', $mform->html(), array('class' => $viewname));
            } else {
                echo html_writer::start_tag('div', array('class' => $viewname));
                $mform->display();
                echo html_writer::end_tag('div');
            }
        } else {
            return parent::display($params);
        }
    }

    /**
     */
    public function process_export($range = self::EXPORT_PAGE) {
        global $CFG;

        require_once($CFG->libdir . '/csvlib.class.php');

        if (!$csvcontent = $this->get_csv_content($range)) {
            return;
        }
        $datalynxname = $this->_df->name();
        $delimiter = csv_import_reader::get_delimiter($this->_delimiter);
        $filename = clean_filename("{$datalynxname}-export");
        $filename .= clean_filename('-' . gmdate("Ymd_Hi"));
        $filename .= clean_filename("-{$this->_delimiter}_separated");
        $filename .= '.' . $this->_output;

        $patterns = array("\n");
        $adjustments = array('');
        if ($this->_enclosure) {
            $patterns[] = $this->_enclosure;
            $adjustments[] = '&#' . ord($this->_enclosure) . ';';
        } else {
            $patterns[] = $delimiter;
            $adjustments[] = '&#' . ord($delimiter) . ';';
        }
        $returnstr = '';
        foreach ($csvcontent as $row) {
            foreach ($row as $key => $column) {
                $value = str_replace($patterns, $adjustments, $column);
                $row[$key] = $this->_enclosure . $value . $this->_enclosure;
            }
            $returnstr .= implode($delimiter, $row) . "\n";
        }

        // Convert encoding.
        $returnstr = mb_convert_encoding($returnstr, $this->_encoding, 'UTF-8');

        header("Content-Type: application/download\n");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header('Expires: 0');
        header('Cache-Control: must-revalidate,post-check=0,pre-check=0');
        header('Pragma: public');

        echo $returnstr;
        exit();
    }

    /**
     */
    public function get_csv_content($range = self::EXPORT_PAGE) {
        // Set content.
        if ($range == self::EXPORT_ALL) {
            $entries = new datalynx_entries($this->_df, $this->_filter);
            $options = array();
            // Set a filter to take it all.
            $filter = $this->get_filter();
            $filter->perpage = 0;
            $options['filter'] = $filter;
            // Do we need ratings?
            if ($ratingoptions = $this->is_rating()) {
                $options['ratings'] = $ratingoptions;
            }
            // Do we need comments?

            // Get the entries.
            $entries->set_content($options);
            $exportentries = $entries->entries();
        } else {
            $this->set_content();
            $exportentries = $this->_entries->entries();
        }

        // Compile entries if any.
        if (!$exportentries) {
            return null;
        }

        // Get the field definitions.
        $entryvalues = array();
        foreach ($exportentries as $entryid => $entry) {
            $patternvalues = array();
            $definitions = $this->get_entry_tag_replacements($entry, array());
            foreach ($definitions as $pattern => $definition) {
                if (is_array($definition)) {
                    list(, $value) = $definition;
                    $patternvalues[$pattern] = $value;
                }
            }
            $entryvalues[$entryid] = $patternvalues;
        }

        // Get csv headers from view columns.
        $columnpatterns = array();
        $csvheader = array();
        $columns = $this->get_columns();
        foreach ($columns as $column) {
            list($pattern, $header, ) = $column;
            $columnpatterns[] = $pattern;
            $csvheader[] = $header ? $header : trim($pattern, '[#]');
        }

        $csvcontent = array();
        $csvcontent[] = $csvheader;

        // Get the field definitions.
        foreach ($entryvalues as $entryid => $patternvalues) {
            $row = array();
            foreach ($columnpatterns as $pattern) {
                if (isset($patternvalues[$pattern])) {
                    $row[] = $patternvalues[$pattern];
                } else {
                    $row[] = '';
                }
            }
            $csvcontent[] = $row;
        }

        return $csvcontent;
    }

    /**
     */
    public function process_import() {
        global $CFG;

        $mform = $this->get_import_form();

        if ($mform->is_cancelled()) {
            return null;
        } else {
            if ($formdata = $mform->get_data()) {

                $data = new stdClass();
                $data->eids = array();

                $fieldsettings = array();

                // Collect field import settings from formdata by field, tag and element.
                foreach ($formdata as $name => $value) {
                    if (strpos($name, 'f_') !== false) { // Assuming only field settings start with f_.
                        list(, $fieldid, $tag, $elem) = explode('_', $name);
                        if (!array_key_exists($fieldid, $fieldsettings)) {
                            $fieldsettings[$fieldid] = array();
                        } else {
                            if (!array_key_exists($tag, $fieldsettings[$fieldid])) {
                                $fieldsettings[$fieldid][$tag] = array();
                            }
                        }
                        $fieldsettings[$fieldid][$tag][$elem] = $value;
                    }
                }

                // Process csv if any.
                if ($this->view->param2) {

                    if (!empty($formdata->csvtext)) { // Upload from text.
                        $csvcontent = $formdata->csvtext;
                    } else { // Upload from file.
                        $csvcontent = $mform->get_file_content('importfile');
                    }

                    $options = array('delimiter' => $formdata->delimiter,
                            'enclosure' => ($formdata->enclosure ? $formdata->enclosure : ''),
                            'encoding' => $formdata->encoding, 'updateexisting' => $formdata->updateexisting,
                            'settings' => $fieldsettings
                    );

                    if (!empty($csvcontent)) {
                        $data = $this->process_csv($data, $csvcontent, $options);
                        if (!empty($data->error)) {
                            $this->_showimportform = true;
                        }
                    }
                }

                // Process fields' non-csv import.
                foreach ($fieldsettings as $fieldid => $importsettings) {
                    $field = $this->_df->get_field_from_id($fieldid);
                    $field->prepare_import_content($data, $importsettings);
                }

                return $this->execute_import($data);
            } else {
                // Set import flag to display the form.
                $this->_showimportform = true;
            }
        }
    }

    /**
     * @param $data
     * @return bool|null
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function execute_import($data) {
        if ($data->eids) {
            $this->_entries->process_entries('update', $data->eids, $data, true);
            return true;
        } else {
            return null;
        }
    }

    /**
     * Process data from csv file.
     *
     * @param $data
     * @param $csvcontent
     * @param null|array $options associative delimiter,enclosure,encoding,updateexisting,settings
     * @return mixed
     */
    public function process_csv(&$data, $csvcontent, $options = null) {
        global $CFG;

        require_once("$CFG->libdir/csvlib.class.php");

        @set_time_limit(0);
        raise_memory_limit(MEMORY_EXTRA);

        $iid = csv_import_reader::get_new_iid('moddatalynx');
        $cir = new csv_import_reader($iid, 'moddatalynx');

        $delimiter = !empty($options['delimiter']) ? $options['delimiter'] : $this->_delimiter;
        $enclosure = !empty($options['enclosure']) ? $options['enclosure'] : $this->_enclosure;
        $encoding = !empty($options['encoding']) ? $options['encoding'] : $this->_encoding;
        $updateexisting = !empty($options['updateexisting']) ? $options['updateexisting'] : false;
        $fieldsettings = !empty($options['settings']) ? $options['settings'] : array();

        $readcount = $cir->load_csv_content($csvcontent, $encoding, $delimiter);

        if (empty($readcount)) {
            $data->error = $cir->get_error();
            return $data;
        }

        // Csv column headers.
        if (!$fieldnames = $cir->get_columns()) {
            $data->error = $cir->get_error();
            return $data;
        }

        // Process each csv record.
        $updateexisting = $updateexisting and !empty($csvfieldnames['Entry']);
        $i = 0;
        $cir->init();
        while ($csvrecord = $cir->next()) {
            $csvrecord = array_combine($fieldnames, $csvrecord);
            // Set the entry id.
            if ($updateexisting and $csvrecord['Entry'] > 0) {
                $data->eids[$csvrecord['Entry']] = $entryid = $csvrecord['Entry'];
            } else {
                $i--;
                $data->eids[$i] = $entryid = $i;
            }
            // Iterate the fields and add their content.

            foreach ($fieldsettings as $fieldid => $importsettings) {
                $field = $this->_df->get_field_from_id($fieldid);
                $field->prepare_import_content($data, $importsettings, $csvrecord, $entryid);
            }
        }
        $cir->cleanup(true);
        $cir->close();

        return $data;
    }

    /**
     * @return datalynxview_csv_import_form
     * @throws moodle_exception
     */
    public function get_import_form() {
        global $CFG;
        require_once("$CFG->dirroot/mod/datalynx/view/csv/import_form.php");

        $actionurl = new moodle_url($this->_baseurl, array('importcsv' => 1));
        return new datalynxview_csv_import_form($this, $actionurl);
    }

    /**
     * Generates the view with default settings.
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
        // Don't show addnewentries, it has no functionality..
        $row2 = new html_table_row();
        $addentries = new html_table_cell('');
        $addentries->colspan = 5;
        $row2->cells = array($addentries);
        foreach ($row2->cells as $cell) {
            $cell->style = 'border:0 none;';
        }
        // Next row: export import.
        $row2a = new html_table_row();
        $addentries = new html_table_cell('##export:all## | ##export:page## | ##import##');
        $addentries->colspan = 5;
        $row2a->cells = array($addentries);
        foreach ($row2a->cells as $cell) {
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
        $table->data = array($row1, $row2, $row2a, $row3);
        $sectiondefault = html_writer::table($table);
        $this->view->esection = html_writer::tag('div', $sectiondefault,
                        array('class' => 'mdl-align')) . "<div>##entries##</div>";

        // Set content.
        $this->view->param2 = '';
        foreach ($fields as $field) {

            if ($field->field->id > 0) {
                $fieldname = $field->name();
                if ($field->type == "userinfo") {
                    $this->view->param2 .= "##author:{$fieldname}##\n";
                } else {
                    $this->view->param2 .= "[[$fieldname]]\n";
                }
            }
        }
    }

    /**
     * Overridden to add default headers from patterns
     */
    public function get_columns() {
        if (empty($this->_columns)) {
            $this->_columns = array();
            $columns = explode("\n", $this->view->param2);
            foreach ($columns as $column) {
                $column = trim($column);
                if (empty($column)) {
                    continue;
                }
                $arr = explode("|", $column);
                $tag = $arr[0]; // Must exist.
                $header = !empty($arr[1]) ? $arr[1] : trim($tag, '[]#');
                $class = !empty($arr[2]) ? $arr[2] : '';

                $definition = array($tag, $header, $class);
                $this->_columns[] = $definition;
            }
        }
        return $this->_columns;
    }

    /**
     */
    protected function has_headers() {
        foreach ($this->get_columns() as $column) {
            if (!empty($column[1])) {
                return true;
            }
        }
        return false;
    }

    // GETTERS.
    /**
     */
    public function get_output_type() {
        return $this->_output;
    }

    /**
     */
    public function get_delimiter() {
        return $this->_delimiter;
    }

    /**
     */
    public function get_enclosure() {
        return $this->_enclosure;
    }

    /**
     */
    public function get_encoding() {
        return $this->_encoding;
    }
}
