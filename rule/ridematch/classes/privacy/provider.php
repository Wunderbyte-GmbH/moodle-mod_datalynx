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

namespace datalynxrule_ridematch\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;

/**
 * Privacy provider implementation for datalynxrule_ridematch.
 *
 * The rule stores no personal data of its own. What it does keep is a note of
 * which pairs of entries have already been announced, so that editing an entry
 * does not notify the same people twice; that references entries rather than
 * users, and disappears with the entries.
 *
 * @package    datalynxrule_ridematch
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements metadata_provider {
    /**
     * Describe the data this rule keeps.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'datalynx_ride_matches',
            [
                'offerentryid' => 'privacy:metadata:datalynx_ride_matches:offerentryid',
                'requestentryid' => 'privacy:metadata:datalynx_ride_matches:requestentryid',
                'timenotified' => 'privacy:metadata:datalynx_ride_matches:timenotified',
            ],
            'privacy:metadata:datalynx_ride_matches'
        );

        $collection->link_subsystem('core_message', 'privacy:metadata:core_message');

        return $collection;
    }
}
