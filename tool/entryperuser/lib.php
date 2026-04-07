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
 * @package datalynxtool_entryperuser
 * @subpackage entryperuser
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_datalynx\local\datalynx_entries;
use datalynxfield_entryauthor\field as datalynxfield_entryauthor;

/**
 * Class datalynxtool_entryperuser
 *
 * @package    datalynxtool_entryperuser
 * @copyright  2013 onwards edulabs.org and associated programmers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datalynxtool_entryperuser {
    /**
     * Run the tool to create entries for each user.
     *
     * @param mod_datalynx\datalynx $df
     */
    public static function run($df) {
        global $DB;

        // Get gradebook users.
        $users = $df->get_gradebook_users();
        if (!$users) {
            return;
        }

        // Construct entries data.
        $data = (object) ['eids' => []];
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
            [$strnotify, $processedeids] = $processed;
            $entriesprocessed = $processedeids ? count($processedeids) : 0;
            if ($entriesprocessed) {
                return ['good', $strnotify];
            }
        }
        return ['bad', get_string('entriesupdated', 'datalynx', get_string('no'))];
    }
}
