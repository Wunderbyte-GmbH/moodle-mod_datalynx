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
 * @package datalynx_rule
 * @subpackage ftpsyncfiles
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/rule/rule_form.php");
require_once($CFG->libdir . '/csvlib.class.php');

class datalynx_rule_ftpsyncfiles_form extends datalynx_rule_form {

    public function rule_definition() {
        $br = html_writer::empty_tag('br');
        $mform = &$this->_form;

        $mform->addElement('header', 'settingshdr', get_string('sftpsettings', 'datalynxrule_ftpsyncfiles'));
        $sftpgrp = array();
        $mform->addElement('text', 'param2', get_string('sftpserver', 'datalynxrule_ftpsyncfiles'));
        $mform->addElement('text', 'param3', get_string('sftpport', 'datalynxrule_ftpsyncfiles'));
        $mform->addElement('text', 'param4', get_string('sftpusername', 'datalynxrule_ftpsyncfiles'));
        $mform->addElement('text', 'param5', get_string('sftppassword', 'datalynxrule_ftpsyncfiles'));
        $mform->addElement('text', 'param6', get_string('sftppath', 'datalynxrule_ftpsyncfiles'));
        // TODO: ADD SELECT for mode.
        $mform->addElement('header', 'settingshdr', get_string('settings'));

        // Delimiter.
        $delimiters = csv_import_reader::get_delimiter_list();
        $mform->addElement('select', 'delimiter', get_string('csvdelimiter', 'datalynx'), $delimiters);
        $mform->setDefault('delimiter', 'comma');

        // Enclosure.
        $mform->addElement('text', 'enclosure', get_string('csvenclosure', 'datalynx'), array('size' => '10'));
        $mform->setType('enclosure', PARAM_NOTAGS);
        $mform->setDefault('enclosure', '"');

        // Encoding.
        $choices = core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('encoding', 'grades'), $choices);
        $mform->setDefault('encoding', 'UTF-8');

    }

    /**
     */
    public function data_preprocessing(&$data) {
        // CSV settings.
        if (!empty($data->param7)) {
            list($data->delimiter, $data->enclosure, $data->encoding) = explode(',', $data->param7);
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
            $data->param7 = "$data->delimiter,$data->enclosure,$data->encoding";
        }
        return $data;
    }
}
