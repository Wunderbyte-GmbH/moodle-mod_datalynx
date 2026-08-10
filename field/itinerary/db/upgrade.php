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
 * Upgrade steps for the itinerary field type.
 *
 * @package    datalynxfield_itinerary
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the itinerary field type.
 *
 * @param int $oldversion the version being upgraded from
 * @return bool
 */
function xmldb_datalynxfield_itinerary_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026081000) {
        // A stop no longer carries a planned time: a date on a stop could not describe a journey
        // that runs every Monday, so when a journey happens is a schedule field's business now.
        // The column was only ever written, never read by any query.
        $table = new xmldb_table('datalynx_waypoints');
        $field = new xmldb_field('timeplanned');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026081000, 'datalynxfield', 'itinerary');
    }

    return true;
}
