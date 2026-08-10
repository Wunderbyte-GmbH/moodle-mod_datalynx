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
// along with this program.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for safe parsing of the usort URL query parameter.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use mod_datalynx\local\filter\datalynx_filter_manager;

/**
 * Tests for {@see datalynx_filter_manager::get_sort_options_from_query()}.
 *
 * Guards against the PHP object-injection regression (see issue #239): the usort
 * request parameter must be parsed with strict typing and never unserialized.
 *
 * @coversDefaultClass \mod_datalynx\local\filter\datalynx_filter_manager
 */
final class sort_query_parsing_test extends advanced_testcase {
    /**
     * The encode/decode round-trip must preserve the [fieldid => direction] options.
     *
     * @covers ::get_sort_options_from_query
     * @covers ::get_sort_url_query
     */
    public function test_round_trip(): void {
        $options = [12 => 0, 34 => 1, 56 => 0];
        $query = datalynx_filter_manager::get_sort_url_query($options);
        $this->assertSame($options, datalynx_filter_manager::get_sort_options_from_query($query));
    }

    /**
     * A crafted serialized-object payload must not be unserialized; it yields no options.
     *
     * @covers ::get_sort_options_from_query
     */
    public function test_serialized_payload_is_not_unserialized(): void {
        // A serialized stdClass instance — the pre-fix code would have passed this to unserialize().
        $payload = urlencode('O:8:"stdClass":1:{s:3:"foo";s:3:"bar";}');
        $this->assertSame([], datalynx_filter_manager::get_sort_options_from_query($payload));
    }

    /**
     * Malformed and out-of-domain fragments are skipped or normalised safely.
     *
     * @covers ::get_sort_options_from_query
     * @dataProvider malformed_query_provider
     * @param string $query
     * @param array $expected
     */
    public function test_malformed_input(string $query, array $expected): void {
        $this->assertSame($expected, datalynx_filter_manager::get_sort_options_from_query($query));
    }

    /**
     * Data provider for {@see test_malformed_input()}.
     *
     * @return array
     */
    public static function malformed_query_provider(): array {
        return [
            'empty' => ['', []],
            'non-numeric field id' => ['abc 1', []],
            'sql fragment as field id' => ['1);DROP TABLE 1', []],
            'missing direction' => ['7', []],
            'direction normalised to 0' => ['7 9', [7 => 0]],
            'direction 1 kept' => ['7 1', [7 => 1]],
            'negative internal field id' => ['-3 1', [-3 => 1]],
            'trailing comma ignored' => ['4 0,', [4 => 0]],
        ];
    }
}
