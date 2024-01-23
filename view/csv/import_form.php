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
 * @subpackage csv
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");
require_once("$CFG->libdir/csvlib.class.php");

/**
 */
class datalynxview_csv_import_form extends moodleform {

    protected $_view;

    public function __construct($view, $action = null, $customdata = null, $method = 'post', $target = '',
            $attributes = null, $editable = true) {
        $this->_view = $view;

        parent::__construct($action, $customdata, $method, $target, $attributes, $editable);
    }

    /**
     */
    public function html() {
        return $this->_form->toHtml();
    }

    public function definition() {
        $view = $this->_view;
        $fieldsettings = empty($this->_customdata['hidefieldsettings']) ? true : false;

        $mform = &$this->_form;

        // Action buttons.
        $this->add_action_buttons(true, get_string('import', 'datalynx'));

        // Field settings.
        $this->field_settings();

        // Csv settings.
        $this->csv_settings();

        // Action buttons.
        $this->add_action_buttons(true, get_string('import', 'datalynx'));
    }

    /**
     */
    protected function field_settings() {
        $view = $this->_view;
        $df = $view->get_dl();
        $mform = &$this->_form;

        $mform->addElement('header', 'fieldsettingshdr',
                get_string('fieldsimportsettings', 'mod_datalynx'));
        $columns = $view->get_columns();
        // Generate the headings and settings for values to import.
        foreach ($columns as $column) {
            list($pattern, $header, ) = $column;
            $patternname = trim($pattern, '[#]');
            $header = $header ? $header : $patternname;
            $fieldid = $view->get_pattern_fieldid($pattern);
            if (!$fieldid) {
                continue;
            }
            $field = $df->get_field_from_id($fieldid);
            if (!$field) {
                continue;
            }

            $name = "f_{$fieldid}_$patternname";

            $grp = array();
            $grp[] = &$mform->createElement('text', "{$name}_name", null, array('size' => '16'));

            $mform->addGroup($grp, "grp$patternname", $patternname, array(), false);

            $mform->setType("{$name}_name", PARAM_NOTAGS);
            $mform->setDefault("{$name}_name", $header);
        }
    }

    /**
     */
    protected function csv_settings() {
        $view = $this->_view;
        $mform = &$this->_form;

        $mform->addElement('header', 'csvsettingshdr', get_string('csvsettings', 'datalynx'));

        // Delimiter.
        $delimiters = csv_import_reader::get_delimiter_list();
        $mform->addElement('select', 'delimiter', get_string('csvdelimiter', 'datalynx'), $delimiters);
        $mform->setDefault('delimiter', $view->get_delimiter());

        // Enclosure.
        $mform->addElement('text', 'enclosure', get_string('csvenclosure', 'datalynx'), array('size' => '10'));
        $mform->setType('enclosure', PARAM_NOTAGS);
        $mform->setDefault('enclosure', $view->get_enclosure());

        // Encoding.
        $choices = core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('encoding', 'grades'), $choices);
        $mform->setDefault('encoding', $view->get_encoding());

        // Upload file.
        $mform->addElement('filepicker', 'importfile', get_string('uploadfile', 'mod_datalynx'));

        // Upload text.
        $mform->addElement('textarea', 'csvtext', get_string('uploadtext', 'mod_datalynx'),
                array('wrap' => 'soft', 'rows' => '5', 'style' => 'width:100%;'));

        // Update existing entries.
        $mform->addElement('selectyesno', 'updateexisting', get_string('updateexisting', 'mod_datalynx'));

    }
}
