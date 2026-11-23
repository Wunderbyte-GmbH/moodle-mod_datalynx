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
 * A router for tests.
 *
 * @package    mod_datalynx
 * @category   test
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use mod_datalynx\local\map\route_result;
use mod_datalynx\local\map\router;

/**
 * A router for tests: 100 km and an hour per leg, never online.
 *
 * @package    mod_datalynx
 * @category   test
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stub_router implements router {
    /**
     * A route of 100 km and an hour per leg.
     *
     * @param array $stops
     * @return route_result|null
     */
    public function route(array $stops): ?route_result {
        $legs = count($stops) - 1;

        return $legs > 0 ? new route_result($legs * 100000.0, $legs * 3600.0, 'stub') : null;
    }

    /**
     * Any number of stops.
     *
     * @return int
     */
    public function max_stops(): int {
        return 25;
    }
}
