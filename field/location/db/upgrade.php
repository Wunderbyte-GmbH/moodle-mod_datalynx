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
 * Upgrade steps for datalynxfield_location.
 *
 * @package    datalynxfield_location
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the location field type.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_datalynxfield_location_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026080702) {
        // The map provider, its API URL and its API key became site settings, so
        // the per-field copies are now ignored. Clear them rather than leave
        // stale values that suggest the field still controls its own provider.
        $DB->set_field('datalynx_fields', 'param1', null, ['type' => 'location']);
        $DB->set_field('datalynx_fields', 'param2', null, ['type' => 'location']);
        $DB->set_field('datalynx_fields', 'param3', null, ['type' => 'location']);

        upgrade_plugin_savepoint(true, 2026080702, 'datalynxfield', 'location');
    }

    return true;
}
