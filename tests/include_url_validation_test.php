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

namespace mod_datalynx;

use advanced_testcase;

/**
 * Tests for datalynx::is_valid_include_url().
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_datalynx\datalynx::is_valid_include_url
 */
final class include_url_validation_test extends advanced_testcase {
    /**
     * Data provider: [line, expected].
     *
     * @return array
     */
    public static function include_url_provider(): array {
        return [
            'absolute https url' => ['https://example.com/style.css', true],
            'relative plugin path' => ['/mod/datalynx/field/picture/zoomable/zoomable.css', true],
            'url with query string' => ['theme/custom.css?v=3', true],
            'empty line' => ['', false],
            'whitespace only' => ['   ', false],
            'at-rule (css source)' => ['@media (max-width: 992px) {', false],
            'css comment' => ['/* Layout fixes */', false],
            'css declaration' => ['    padding: 0.5rem;', false],
            'css selector with block' => [':root {', false],
            'full rule on one line' => ['.col-status { width: 15%; }', false],
            'url containing a space' => ['https://example.com/my style.css', false],
        ];
    }

    /**
     * Tests datalynx::is_valid_include_url() with various input lines.
     *
     * @dataProvider include_url_provider
     * @param string $line
     * @param bool $expected
     */
    public function test_is_valid_include_url(string $line, bool $expected): void {
        $this->assertSame($expected, datalynx::is_valid_include_url($line));
    }
}
