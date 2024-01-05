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
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/view/view_form.php");

class datalynxview_grid_form extends datalynxview_base_form {

    /**
     */
    public function view_definition_after_gps() {
        $view = $this->_view;
        $editoroptions = $view->editors();
        $editorattr = array('cols' => 40, 'rows' => 12);

        $mform = &$this->_form;

        // Grid layout (param3).
        $mform->addElement('header', 'gridsettingshdr',
                get_string('gridsettings', 'datalynxview_grid'));

        // Cols.
        $range = range(2, 50);
        $options = array('' => get_string('choosedots')) + array_combine($range, $range);
        $mform->addElement('select', 'cols', get_string('cols', 'datalynxview_grid'), $options);

        // Rows.
        $mform->addElement('selectyesno', 'rows', get_string('rows', 'datalynxview_grid'));
        $mform->disabledIf('rows', 'cols', 'eq', '');

        // Repeated entry (param2).
        $mform->addElement('header', 'entrytemplatehdr', get_string('entrytemplate', 'datalynx'));

        $mform->addElement('editor', 'eparam2_editor', '', $editorattr, $editoroptions['param2']);
        $mform->setDefault("eparam2_editor[format]", FORMAT_HTML);
        $this->add_tags_selector('eparam2_editor', 'field');
        $this->add_tags_selector('eparam2_editor', 'character');
    }

    /**
     */
    public function data_preprocessing(&$data) {
        parent::data_preprocessing($data);
        // Grid layout.
        if (!empty($data->param3)) {
            list($data->cols, $data->rows, ) = explode(' ', $data->param3);
        }
    }

    /**
     */
    public function set_data($data) {
        $this->data_preprocessing($data);
        parent::set_data($data);
    }

    /**
     */
    public function get_data($slashed = true) {
        if ($data = parent::get_data($slashed)) {
            // Grid layout.
            if (!empty($data->cols)) {
                $data->param3 = $data->cols . ' ' . (int) !empty($data->rows);
            } else {
                $data->param3 = '';
            }
        }
        return $data;
    }
}
