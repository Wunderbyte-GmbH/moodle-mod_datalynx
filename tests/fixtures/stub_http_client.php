<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_datalynx\local\map;

/**
 * Test double that records requests and replays canned responses.
 *
 * Lets the geocoding adapter tests exercise request building and response
 * parsing without touching the network.
 *
 * @package    mod_datalynx
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stub_http_client extends http_client {
    /** @var array Requests seen, as [url, params] pairs. */
    public array $requests = [];

    /** @var array|null Response handed to the next caller. */
    public ?array $response = null;

    /**
     * Record the request and return the canned response.
     *
     * @param string $url
     * @param array $params
     * @return array|null
     */
    public function get_json(string $url, array $params): ?array {
        $this->requests[] = [$url, $params];

        return $this->response;
    }
}
