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

require_once("$CFG->dirroot/mod/datalynx/view/view_form.php");

class datalynxview_tabular_form extends datalynxview_base_form {

    /**
     */
    public function view_definition_after_gps() {
        $view = $this->_view;
        $editoroptions = $view->editors();
        $editorattr = array('cols' => 40, 'rows' => 12);

        $mform = &$this->_form;

        // Content.
        $mform->addElement('header', 'entrytemplatehdr', get_string('entrytemplate', 'datalynx'));

        $mform->addElement('selectyesno', 'param3', get_string('headerrow', 'datalynxview_tabular'));
        $mform->setDefault('param3', 1);

        $mform->addElement('editor', 'eparam2_editor', get_string('table', 'datalynxview_tabular'),
                $editorattr, $editoroptions['param2']);
        $this->add_tags_selector('eparam2_editor', 'general');
        $this->add_tags_selector('eparam2_editor', 'field');
    }
}
