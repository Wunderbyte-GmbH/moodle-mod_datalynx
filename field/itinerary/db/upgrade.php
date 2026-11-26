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
 * Upgrade steps of the itinerary field.
 *
 * @package    datalynxfield_itinerary
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the itinerary field.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_datalynxfield_itinerary_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026112601) {
        // The query index holds points along the whole route now, not only the stops: rebuild it.
        $fields = $DB->get_records('datalynx_fields', ['type' => 'itinerary'], 'id', 'id, dataid');
        $instances = [];
        foreach ($fields as $record) {
            if (!$DB->record_exists('datalynx', ['id' => $record->dataid])) {
                continue;
            }
            $instances[$record->dataid] ??= new \mod_datalynx\datalynx($record->dataid);
            $field = $instances[$record->dataid]->get_field_from_id((int) $record->id, true);
            if ($field) {
                $field->rebuild_index();
            }
        }
        upgrade_plugin_savepoint(true, 2026112601, 'datalynxfield', 'itinerary');
    }

    return true;
}
