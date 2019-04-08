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
 * @package datalynxfield
 * @subpackage duration
 * @copyright 2014 onwards by edulabs.org and associated programmers
 * @copyright based on the work by 2013 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/field_form.php");

class datalynxfield_duration_form extends datalynxfield_form {

    /**
     */
    public function field_definition() {
        $mform = &$this->_form;

        $mform->addElement('header', 'fieldattributeshdr',
                get_string('fieldattributes', 'datalynx'));

        // Field width.
        $fieldwidthgrp = array();
        $fieldwidthgrp[] = &$mform->createElement('text', 'param2', null, array('size' => '8'));
        $fieldwidthgrp[] = &$mform->createElement('select', 'param3', null,
                array('px' => 'px', 'em' => 'em', '%' => '%'));
        $mform->addGroup($fieldwidthgrp, 'fieldwidthgrp', get_string('fieldwidth', 'datalynx'),
                array(' '), false);
        $mform->setType('param2', PARAM_INT);
        $mform->addGroupRule('fieldwidthgrp',
                array('param2' => array(array(null, 'numeric', null, 'client'))));
        $mform->disabledIf('param3', 'param2', 'eq', '');
        $mform->setDefault('param2', '');
        $mform->setDefault('param3', 'px');
    }
}
