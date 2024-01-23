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
 * @package datalynxtool
 * @subpackage entryperuser
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/entries_class.php");
require_once("$CFG->dirroot/mod/datalynx/field/entryauthor/field_class.php");

class datalynxtool_entryperuser {

    /**
     */
    public static function run($df) {
        global $DB;

        // Get gradebook users.
        $users = $df->get_gradebook_users();
        if (!$users) {
            return;
        }

        // Construct entries data.
        $data = (object) array('eids' => array());
        $fieldid = datalynxfield_entryauthor::_USERID;
        $entryid = -1;
        foreach ($users as $userid => $unused) {
            $data->eids[$entryid] = $entryid;
            $data->{"field_{$fieldid}_{$entryid}"} = $userid;
            $entryid--;
        }
        // Add entries.
        $em = new datalynx_entries($df);
        $processed = $em->process_entries('update', $data->eids, $data, true);

        if (is_array($processed)) {
            list($strnotify, $processedeids) = $processed;
            $entriesprocessed = $processedeids ? count($processedeids) : 0;
            if ($entriesprocessed) {
                return array('good', $strnotify);
            }
        }
        return array('bad', get_string('entriesupdated', 'datalynx', get_string('no')));
    }
}
