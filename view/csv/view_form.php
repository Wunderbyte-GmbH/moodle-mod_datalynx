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
 * CSV view configuration form.
 *
 * @package    datalynxview_csv
 * @copyright  2013 onwards edulabs.org and associated programmers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/view/view_form.php");
require_once("$CFG->libdir/csvlib.class.php");

class datalynxview_csv_form extends datalynxview_base_form {
    /**
     * Add view specific elements to the form.
     */
    public function view_definition_after_gps() {
        $mform = &$this->_form;

        $mform->addElement('header', 'settingshdr', get_string('settings'));

        // Export type.
        $options = ['csv' => get_string('csv', 'datalynxview_csv'),
                'ods' => get_string('ods', 'datalynxview_csv'),
                'xls' => get_string('xls', 'datalynxview_csv')];
        $mform->addElement('select', 'param3', get_string('outputtype', 'datalynxview_csv'), $options);

        // Delimiter.
        $delimiters = \csv_import_reader::get_delimiter_list();
        $mform->addElement('select', 'delimiter', get_string('csvdelimiter', 'datalynx'), $delimiters);
        $mform->setDefault('delimiter', 'comma');

        // Enclosure.
        $mform->addElement('text', 'enclosure', get_string('csvenclosure', 'datalynx'), ['size' => '10']);
        $mform->setType('enclosure', PARAM_NOTAGS);
        $mform->setDefault('enclosure', '"');

        // Encoding.
        $choices = \core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('encoding', 'grades'), $choices);
        $mform->setDefault('encoding', 'UTF-8');

        // Fields to import.
        $attributes = ['wrap' => 'soft', 'rows' => 10, 'cols' => 50];
        $mform->addElement('textarea', 'param2', get_string('exportfields', 'datalynxview_csv'), $attributes);
        $mform->addHelpButton('param2', 'exportfields', 'datalynxview_csv');
        $mform->setDefault('param2', FORMAT_PLAIN);

        // Show a list of fields in this view.
        $view = $this->view;
        $tags = $view->field_tags();
        if (isset($tags['Fields']['Fields']) && !empty($tags['Fields']['Fields'])) {
            $tags = implode("<br>", $tags['Fields']['Fields']);
            $mform->addElement('static', 'availablefields', get_string('fields', 'datalynx'), $tags);
        }
    }

    /**
     * Preprocess data before setting it to the form.
     *
     * @param stdClass $data
     */
    public function data_preprocessing(&$data) {
        parent::data_preprocessing($data);
        // CSV settings.
        if (!empty($data->param1)) {
            [$data->delimiter, $data->enclosure, $data->encoding] = explode(',', $data->param1);
        }
    }

    /**
     * Set data to the form.
     *
     * @param stdClass $data
     */
    public function set_data($data) {
        $this->data_preprocessing($data);
        parent::set_data($data);
    }

    /**
     * Get data from the form.
     *
     * @param bool $slashed
     * @return stdClass
     */
    public function get_data($slashed = true) {
        if ($data = parent::get_data($slashed)) {
            $data->param1 = "$data->delimiter,$data->enclosure,$data->encoding";
        }
        return $data;
    }
}
