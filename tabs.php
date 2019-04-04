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
 * @package mod_datalynx
 * @copyright 2013 onwards Ivan Sakic, Michael Pollak, Thomas Niedermaier, David Bogner
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
defined('MOODLE_INTERNAL') or die();

$istemplatemanager = has_capability('mod/datalynx:managetemplates', $this->context);

// Tabs are displayed only for template managers.
if (isloggedin() and $istemplatemanager) {
    if (empty($currenttab) or empty($this->data) or empty($this->course)) {
        throw new moodle_exception('emptytab', 'datalynx');
    }

    $inactive = array();
    $activated = array();
    $tabs = array();

    // Browse/Management.
    $row = array();
    $row[] = new tabobject('browse',
            new moodle_url('/mod/datalynx/view.php', array('d' => $this->id())), get_string('browse', 'datalynx'));
    $row[] = new tabobject('manage',
            new moodle_url('/mod/datalynx/view/index.php', array('d' => $this->id())), get_string('manage', 'datalynx'));
    // Add view edit tab.
    if ($currenttab == 'browse' and !empty($this->_currentview)) {
        $params = array('d' => $this->id(), 'sesskey' => sesskey(),
                'vedit' => $this->_currentview->id());
        $editviewurl = new moodle_url('/mod/datalynx/view/view_edit.php', $params);
        $row[] = new tabobject('editview', $editviewurl,
                $OUTPUT->pix_icon('t/edit', get_string('vieweditthis', 'datalynx')), get_string('vieweditthis', 'datalynx'));
    }

    $tabs[] = $row;

    if ($currenttab != 'browse') {
        $inactive[] = 'manage';
        $activated[] = 'manage';

        $row = array();
        // Template manager can do everything.
        if ($istemplatemanager) {
            $row[] = new tabobject('views',
                    new moodle_url('/mod/datalynx/view/index.php', array('d' => $this->id())),
                    get_string('views', 'datalynx'));
            $row[] = new tabobject('fields',
                    new moodle_url('/mod/datalynx/field/index.php', array('d' => $this->id())),
                    get_string('fields', 'datalynx'));
            $row[] = new tabobject('filters',
                    new moodle_url('/mod/datalynx/filter/index.php', array('d' => $this->id())),
                    get_string('filters', 'datalynx'));
            $row[] = new tabobject('customfilters',
                    new moodle_url('/mod/datalynx/customfilter/index.php', array('d' => $this->id())),
                    get_string('customfilters', 'datalynx'));
            $row[] = new tabobject('rules',
                    new moodle_url('/mod/datalynx/rule/index.php', array('d' => $this->id())),
                    get_string('rules', 'datalynx'));
            $row[] = new tabobject('tools',
                    new moodle_url('/mod/datalynx/tool/index.php', array('d' => $this->id())),
                    get_string('tools', 'datalynx'));
            $row[] = new tabobject('js',
                    new moodle_url('/mod/datalynx/js.php', array('d' => $this->id(), 'jsedit' => 1)),
                    get_string('jsinclude', 'datalynx'));
            $row[] = new tabobject('css',
                    new moodle_url('/mod/datalynx/css.php', array('d' => $this->id(), 'cssedit' => 1)),
                    get_string('cssinclude', 'datalynx'));
            $row[] = new tabobject('presets',
                    new moodle_url('/mod/datalynx/preset/index.php', array('d' => $this->id())),
                    get_string('presets', 'datalynx'));
            $row[] = new tabobject('import',
                    new moodle_url('/mod/datalynx/import.php', array('d' => $this->id())),
                    get_string('import', 'datalynx'));
            $row[] = new tabobject('statistics',
                    new moodle_url('/mod/datalynx/statistics/index.php', array('d' => $this->id())),
                    get_string('statistics', 'datalynx'));
        }
        $tabs[] = $row;
    }

    if ($currenttab == 'fields' || $currenttab == 'fields2' || $currenttab == 'behaviors' ||
            $currenttab == 'renderers' || $currenttab == 'fieldgroups'
    ) {
        $inactive[] = 'fields';
        $activated[] = 'fields';
        if ($currenttab == 'fields') {
            $inactive[] = 'fields2';
            $activated[] = 'fields2';
            $currenttab = 'fields2';
        }

        $row = array();
        // Template manager can do everything.
        if ($istemplatemanager) {
            $row[] = new tabobject('fields2',
                    new moodle_url('/mod/datalynx/field/index.php', array('d' => $this->id())),
                    get_string('fields', 'datalynx'));
            $row[] = new tabobject('behaviors',
                    new moodle_url('/mod/datalynx/behavior/index.php', array('d' => $this->id())),
                    get_string('behaviors', 'datalynx'));
            $row[] = new tabobject('renderers',
                    new moodle_url('/mod/datalynx/renderer/index.php', array('d' => $this->id())),
                    get_string('renderers', 'datalynx'));
            $row[] = new tabobject('fieldgroups',
                    new moodle_url('/mod/datalynx/field/fieldgroup/index.php', array('d' => $this->id())),
                    get_string('fieldgroups', 'datalynx'));
        }

        $tabs[] = $row;
    }

    // Print out the tabs and continue!
    print_tabs($tabs, $currenttab, $inactive, $activated);
}
