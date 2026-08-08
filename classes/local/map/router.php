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

namespace mod_datalynx\local\map;

/**
 * Turns an ordered list of stops into a driving route.
 *
 * Like the geocoder, implementations always run on the server: the services want
 * an identifying User-Agent that fetch() may not set, keys must never reach a
 * browser, and the results have to be cached to stay inside the usage policies.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface router {
    /**
     * Route through the given stops, in order.
     *
     * @param array $stops Ordered list of [$lat, $lng] pairs, at least two.
     * @return route_result|null Null when the service failed or found no route.
     */
    public function route(array $stops): ?route_result;

    /**
     * Largest number of stops this service accepts in one request.
     *
     * @return int
     */
    public function max_stops(): int;
}
