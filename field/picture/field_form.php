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
 * @package datalynxfield_picture
 * @subpackage picture
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/file/field_form.php");

/**
 * Field form for the picture field type.
 *
 * @package datalynxfield_picture
 */
class datalynxfield_picture_form extends datalynxfield_file_form {
    /**
     * Define the field attributes.
     */
    public function field_definition() {
        global $CFG;

        $mform = &$this->_form;

        // Accepted types.
        $this->filetypes_definition();

        $mform->addElement(
            'header',
            'fieldattributeshdr',
            get_string('fieldattributes', 'datalynx')
        );

        // Max bytes (param1).
        $options = get_max_upload_sizes($CFG->maxbytes, $this->_df->course->maxbytes);
        $mform->addElement('select', 'param1', get_string('filemaxsize', 'datalynx'), $options);

        // Max files (param2).
        $range = range(1, 100);
        $options = array_combine($range, $range);
        $options[-1] = get_string('unlimited');
        $mform->addElement('select', 'param2', get_string('filesmax', 'datalynx'), $options);
        $mform->setDefault('param2', -1);

        // Pic display dimensions.
        $dispdimgrp = [];
        $dispdimgrp[] = &$mform->createElement('text', 'param4', null, ['size' => '8']);
        $dispdimgrp[] = &$mform->createElement('text', 'param5', null, ['size' => '8']);
        $dispdimgrp[] = &$mform->createElement(
            'select',
            'param6',
            null,
            ['px' => 'px', 'em' => 'em', '%' => '%']
        );
        $mform->addGroup(
            $dispdimgrp,
            'dispdim',
            get_string('displaydimensions', 'datalynxfield_picture'),
            ['x', ''],
            false
        );
        $mform->setType('param4', PARAM_INT);
        $mform->setType('param5', PARAM_INT);
        $mform->addGroupRule(
            'dispdim',
            ['param4' => [[null, 'numeric', null, 'client']]]
        );
        $mform->addGroupRule(
            'dispdim',
            ['param5' => [[null, 'numeric', null, 'client']]]
        );

        // Max pic dimensions (crop if needed).
        $maxpicdimgrp = [];
        $maxpicdimgrp[] = &$mform->createElement('text', 'param7', null, ['size' => '8']);
        $maxpicdimgrp[] = &$mform->createElement('text', 'param8', null, ['size' => '8']);
        $mform->addGroup(
            $maxpicdimgrp,
            'maxpicdim',
            get_string('maxdimensions', 'datalynxfield_picture'),
            'x',
            false
        );
        $mform->setType('param7', PARAM_INT);
        $mform->setType('param8', PARAM_INT);
        $mform->addGroupRule(
            'maxpicdim',
            ['param7' => [[null, 'numeric', null, 'client']]]
        );
        $mform->addGroupRule(
            'maxpicdim',
            ['param8' => [[null, 'numeric', null, 'client']]]
        );
        $mform->setDefault('param7', '');
        $mform->setDefault('param8', '');

        // Thumbnail dimensions (crop if needed).
        $thumbnailgrp = [];
        $thumbnailgrp[] = &$mform->createElement('text', 'param9', null, ['size' => '8']);
        $thumbnailgrp[] = &$mform->createElement('text', 'param10', null, ['size' => '8']);
        $mform->addGroup(
            $thumbnailgrp,
            'thumbnaildim',
            get_string('thumbdimensions', 'datalynxfield_picture'),
            'x',
            false
        );
        $mform->setType('param9', PARAM_INT);
        $mform->setType('param10', PARAM_INT);
        $mform->addGroupRule(
            'thumbnaildim',
            ['param9' => [[null, 'numeric', null, 'client']]]
        );
        $mform->addGroupRule(
            'thumbnaildim',
            ['param10' => [[null, 'numeric', null, 'client']]]
        );
        $mform->setDefault('param9', '');
        $mform->setDefault('param10', '');

        // Clean up the user interface.
        $mform->hideIf('dispdim', 'param3', 'eq', 'audio');
        $mform->hideIf('maxpicdim', 'param3', 'eq', 'audio');
        $mform->hideIf('thumbnaildim', 'param3', 'eq', 'audio');
        $mform->hideIf('thumbnaildim', 'param3', 'eq', 'video');
    }

    /**
     * Define the file types attributes.
     */
    public function filetypes_definition() {
        $mform = &$this->_form;

        // Accetped types.
        $options = [];
        $options['image'] = get_string('filetypeimage', 'datalynx');
        $options['video'] = get_string('filetypevideo', 'datalynx');
        $options['audio'] = get_string('filetypeaudio', 'datalynx');
        $options['.jpg'] = get_string('filetypejpg', 'datalynx');
        $options['.gif'] = get_string('filetypegif', 'datalynx');
        $options['.png'] = get_string('filetypepng', 'datalynx');
        $mform->addElement('select', 'param3', get_string('filetypes', 'datalynx'), $options);
    }
}
