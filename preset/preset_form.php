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
 * @package mod_datalynx
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 */
class mod_datalynx_preset_form extends moodleform {

    public function definition() {
        global $COURSE;

        $mform = &$this->_form;

        $mform->addElement('header', 'presetshdr', get_string('presetadd', 'datalynx'));
        // Preset source.
        $grp = array();
        $grp[] = &$mform->createElement('radio', 'preset_source', null,
                get_string('presetfromdatalynx', 'datalynx'), 'current');

        $packdata = array('nodata' => get_string('presetnodata', 'datalynx'),
                'data' => get_string('presetdata', 'datalynx'),
                'dataanon' => get_string('presetdataanon', 'datalynx'));
        $grp[] = &$mform->createElement('select', 'preset_data', null, $packdata);
        $grp[] = &$mform->createElement('radio', 'preset_source', null,
                get_string('presetfromfile', 'datalynx'), 'file');
        $mform->addGroup($grp, 'psourcegrp', null, array('  ', '<br />'), false);
        $mform->setDefault('preset_source', 'current');

        // Upload file.
        $options = array('subdirs' => 0, 'maxbytes' => $COURSE->maxbytes, 'maxfiles' => 1,
                'accepted_types' => array('*.zip', '*.mbz'));
        $mform->addElement('filepicker', 'uploadfile', null, null, $options);
        $mform->disabledIf('uploadfile', 'preset_source', 'neq', 'file');

        $mform->addElement('html', '<br /><div class="mdl-align">');
        $mform->addElement('submit', 'add', '    ' . get_string('add') . '    ');
        $mform->addElement('html', '</div>');
    }
}
