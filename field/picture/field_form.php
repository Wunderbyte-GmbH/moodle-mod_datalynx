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
 * @subpackage picture
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/file/field_form.php");

class datalynxfield_picture_form extends datalynxfield_file_form {

    /**
     */
    public function field_definition() {
        global $CFG;

        $mform = &$this->_form;

        // Pic display dimensions.
        $dispdimgrp = array();
        $dispdimgrp[] = &$mform->createElement('text', 'param4', null, array('size' => '8'));
        $dispdimgrp[] = &$mform->createElement('text', 'param5', null, array('size' => '8'));
        $dispdimgrp[] = &$mform->createElement('select', 'param6', null,
                array('px' => 'px', 'em' => 'em', '%' => '%'));
        $mform->addGroup($dispdimgrp, 'dispdim',
                get_string('displaydimensions', 'datalynxfield_picture'), array('x', ''), false);
        $mform->setType('param4', PARAM_INT);
        $mform->setType('param5', PARAM_INT);
        $mform->addGroupRule('dispdim',
                array('param4' => array(array(null, 'numeric', null, 'client'))));
        $mform->addGroupRule('dispdim',
                array('param5' => array(array(null, 'numeric', null, 'client'))));

        // Max pic dimensions (crop if needed).
        $maxpicdimgrp = array();
        $maxpicdimgrp[] = &$mform->createElement('text', 'param7', null, array('size' => '8'));
        $maxpicdimgrp[] = &$mform->createElement('text', 'param8', null, array('size' => '8'));
        $mform->addGroup($maxpicdimgrp, 'maxpicdim',
                get_string('maxdimensions', 'datalynxfield_picture'), 'x', false);
        $mform->setType('param7', PARAM_INT);
        $mform->setType('param8', PARAM_INT);
        $mform->addGroupRule('maxpicdim',
                array('param7' => array(array(null, 'numeric', null, 'client'))));
        $mform->addGroupRule('maxpicdim',
                array('param8' => array(array(null, 'numeric', null, 'client'))));
        $mform->setDefault('param7', '');
        $mform->setDefault('param8', '');

        // Thumbnail dimensions (crop if needed).
        $thumbnailgrp = array();
        $thumbnailgrp[] = &$mform->createElement('text', 'param9', null, array('size' => '8'));
        $thumbnailgrp[] = &$mform->createElement('text', 'param10', null, array('size' => '8'));
        $mform->addGroup($thumbnailgrp, 'thumbnaildim',
                get_string('thumbdimensions', 'datalynxfield_picture'), 'x', false);
        $mform->setType('param9', PARAM_INT);
        $mform->setType('param10', PARAM_INT);
        $mform->addGroupRule('thumbnaildim',
                array('param9' => array(array(null, 'numeric', null, 'client'))));
        $mform->addGroupRule('thumbnaildim',
                array('param10' => array(array(null, 'numeric', null, 'client'))));
        $mform->setDefault('param9', '');
        $mform->setDefault('param10', '');

        parent::field_definition();
    }

    /**
     */
    public function filetypes_definition() {
        $mform = &$this->_form;

        // Accetped types.
        $options = array();
        $options['image'] = get_string('filetypeimage', 'datalynx');
        $options['.jpg'] = get_string('filetypejpg', 'datalynx');
        $options['.gif'] = get_string('filetypegif', 'datalynx');
        $options['.png'] = get_string('filetypepng', 'datalynx');
        $mform->addElement('select', 'param3', get_string('filetypes', 'datalynx'), $options);
    }
}
