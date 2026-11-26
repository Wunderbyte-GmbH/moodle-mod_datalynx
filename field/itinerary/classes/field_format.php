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

namespace datalynxfield_itinerary;

/**
 * Field format of the itinerary: show the distance instead of the stops (mod_datalynx#328).
 *
 * - distance: the routed distance one way, "42,3 km".
 * - billeddistance: the billed distance, doubled for a return trip, "84,6 km".
 *
 * @package    datalynxfield_itinerary
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_format extends \mod_datalynx\local\field_format\base {
    /**
     * The choices of what to show.
     *
     * @return array
     */
    public static function show_options(): array {
        return [
            'distance' => get_string('showdistance', 'datalynxfield_itinerary'),
            'billeddistance' => get_string('showbilleddistance', 'datalynxfield_itinerary'),
        ];
    }

    /**
     * Defines configuration elements on the form.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form(\MoodleQuickForm &$mform): void {
        $mform->addElement('select', 'show', get_string('showformat', 'datalynxfield_itinerary'), self::show_options());
        $mform->setType('show', PARAM_ALPHA);
    }

    /**
     * Named formats get their setting from the name: [[Route:distance]], [[Route:billeddistance]].
     *
     * @param string $name
     * @return array
     */
    public function get_default_settings_for_name(string $name): array {
        return array_key_exists($name, self::show_options()) ? ['show' => $name] : [];
    }
}
