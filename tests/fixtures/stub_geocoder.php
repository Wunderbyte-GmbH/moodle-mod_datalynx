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
 * A geocoder for tests.
 *
 * @package    mod_datalynx
 * @category   test
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use mod_datalynx\local\map\geocoder;
use mod_datalynx\local\map\place;

/**
 * A geocoder for tests that knows a fixed list of places and never goes online.
 *
 * @package    mod_datalynx
 * @category   test
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stub_geocoder implements geocoder {
    /** @var string[] The queries looked up, in order. */
    public array $queries = [];

    /**
     * Constructor.
     *
     * @param array $places lower case query => [label, lat, lng]
     */
    public function __construct(
        /** @var array lower case query => [label, lat, lng] */
        private array $places
    ) {
    }

    /**
     * Look up a known place.
     *
     * @param string $query
     * @param array $options
     * @return place[]
     */
    public function search(string $query, array $options = []): array {
        $this->queries[] = $query;
        $known = $this->places[\core_text::strtolower(trim($query))] ?? null;

        return $known ? [new place($known[0], $known[1], $known[2], 'at')] : [];
    }

    /**
     * Not used.
     *
     * @param float $lat
     * @param float $lng
     * @param array $options
     * @return place|null
     */
    public function reverse(float $lat, float $lng, array $options = []): ?place {
        return null;
    }

    /**
     * Not used.
     *
     * @return bool
     */
    public function supports_typeahead(): bool {
        return false;
    }

    /**
     * Not used.
     *
     * @return int
     */
    public function minimum_query_length(): int {
        return 3;
    }
}
