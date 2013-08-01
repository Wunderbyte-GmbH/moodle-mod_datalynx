<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.
 
/**
 * This file is part of the Dataform module for Moodle - http://moodle.org/. 
 *
 * @package dataformview
 * @subpackage tabular
 * @copyright 2012 Itamar Tzadok 
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->dirroot/mod/dataform/view/view_form.php");

class dataformview_tabular_form extends dataformview_base_form {

    /**
     *
     */
    function view_definition_after_gps() {

        $view = $this->_customdata['view'];
        $editoroptions = $view->editors();
        $editorattr = array('cols' => 40, 'rows' => 12);

        $mform =& $this->_form;

        // content
        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'entriessectionhdr', get_string('viewlistbody', 'dataform'));

        $mform->addElement('selectyesno', 'param3', get_string('headerrow', 'dataformview_tabular'));
        $mform->setDefault('param3', 1);
        
        $mform->addElement('editor', 'eparam2_editor', get_string('table', 'dataformview_tabular'), $editorattr, $editoroptions['param2']);
        $this->add_tags_selector('eparam2_editor', 'general');
        $this->add_tags_selector('eparam2_editor', 'field');        

    }

}
