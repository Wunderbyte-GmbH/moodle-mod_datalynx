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
 * @copyright 2014 onwards by edulabs.org and associated programmers
 * @copyright based on the work by 2013 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx\form;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");
use core_form\dynamic_form;
use mod_datalynx\form;
use mod_datalynx\moodle_exception;
use moodle_url;
use context;
use mod_datalynx\datalynx;

/*
 *
 */

/*
 *
 */

/**
 * Class customfilter_frontend_form to display the customfilter options in browse mode
 *
 */
class customfilter_frontend_form extends form\filter_base_form {

    /*
     * This customfilter form  predefined by the admin is displayed
     */
    public function definition() {
        $view = $this->_customdata['view'];

        if (!$customfilter = $this->_customfilter) {
            throw new moodle_exception('nocustomfilter', 'datalynx');
        }

        $customfilterfieldlistfields = array();
        if ($customfilter->fieldlist) {
            $customfilterfieldlistfields = json_decode($customfilter->fieldlist);
        }
        $fields = $view->get_view_fields();
        $fieldoptions = array();
        $sortfields = array();
        foreach ($fields as $fieldid => $field) {
            $select = false;
            foreach ($customfilterfieldlistfields as $fid => $listfield) {
                if ($field->field->id == $fid) {
                    $select = true;
                    if ($listfield->sortable) {
                        $sortfields[$fid] = $listfield->name;
                    }
                    break;
                }
            }
            if ($select == false) {
                switch ($field->field->name) {
                    case (get_string("approved", "datalynx")):
                        if ($customfilter->approve) {
                            $select = true;
                        }
                        break;
                    case (get_string("timecreated", "datalynx")):
                        if ($customfilter->timecreated) {
                            $select = true;
                        }
                        if ($customfilter->timecreated_sortable) {
                            $sortfields[$fieldid] = $field->field->name;
                        }
                        break;
                    case (get_string("timemodified", "datalynx")):
                        if ($customfilter->timemodified) {
                            $select = true;
                        }
                        if ($customfilter->timemodified_sortable) {
                            $sortfields[$fieldid] = $field->field->name;
                        }
                        break;
                    case (get_string("status", "datalynx")):
                        if ($customfilter->status) {
                            $select = true;
                        }
                        break;
                }
            }
            if ($select) {
                $fieldoptions[$fieldid] = $field->field->name;
            }
        }

        $mform = &$this->_form;
        $mform->addElement('header', 'collapseCustomfilter', get_string('search'));
        $mform->setExpanded('collapseCustomfilter', false);

        if ($customfilter->fulltextsearch) {
            $mform->addElement('text', 'search', get_string('search'));
            $mform->setType('search', PARAM_TEXT);
        }

        // Search for author.
        if (isset($customfilter->authorsearch) && $customfilter->authorsearch) {

            // Add users that have written an entry in the current datalynx instance to list.
            global $DB, $PAGE;
            $entryauthors = $DB->get_records_sql('SELECT DISTINCT userid, firstname, lastname
                FROM {datalynx_entries}
                INNER JOIN {user} on {datalynx_entries}.userid = {user}.id
                WHERE {datalynx_entries}.dataid = '. $this->_df->id() .';');

            $menu = array();
            foreach ($entryauthors as $userid => $author) {
                $menu[$userid] = $author->firstname . " " . $author->lastname;
            }
            $options = array('multiple' => true);
            $mform->addElement('autocomplete', 'authorsearch', get_string('authorsearch', 'datalynx'), $menu, $options);
            $mform->setType('authorsearch', PARAM_INT);
        }

        // Custom search.
        if ($customfilter->fieldlist) {
            $this->customfilter_search_definition($fields, $fieldoptions);
        }

        if (!empty($sortfields)) {
            // Important, keep fieldids intact.
            $sortfields = array(0 => get_string('choosedots')) + $sortfields;

            $grp = array();
            $grp[] = $mform->createElement('select', 'customfiltersortfield', '', $sortfields);
            $directions = array( "0" => get_string('asc'), "1" => get_string('desc'));

            $grp[] = $mform->createElement('select', 'customfiltersortdirection', '', $directions);
            $mform->addGroup($grp, "customfiltersort_grp", get_string('sortby'), ' ', false);
        }

        // Show buttons in line with each other.
        $buttonarray = array();
        $buttonarray[] = &$mform->createElement('submit', 'customsearch', get_string("search"));

        // Add a button that resets all custom filter values at once.
        $clearcustomsearch = '<a class="btn btn-secondary" href="';
        $clearcustomsearch .= new moodle_url('/mod/datalynx/view.php',
            array('id' => $this->_df->cm->id, 'view' => $view->view->id, 'filter' => 0));
        $clearcustomsearch .= '"> ' . get_string('resetsettings', 'datalynx') . '</a>';
        $buttonarray[] = &$mform->createElement('static', 'clearcustomsearch', '',  $clearcustomsearch);

        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
    }
}
