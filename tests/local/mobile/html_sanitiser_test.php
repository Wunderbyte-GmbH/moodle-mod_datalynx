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

namespace mod_datalynx\local\mobile;

use basic_testcase;

/**
 * Tests for the entry HTML clean-up for the Moodle App.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\mobile\html_sanitiser
 */
final class html_sanitiser_test extends basic_testcase {
    /**
     * Scripts, styles and form controls go, with their content.
     */
    public function test_removes_scripts_styles_and_controls(): void {
        $html = '<div class="card"><script>alert(1)</script><style>.x{}</style>'
            . '<input type="checkbox" name="entryselector" value="3"><select><option>a</option></select>'
            . '<button>Go</button><p>Kept</p></div>';

        $this->assertSame('<div class="card"><p>Kept</p></div>', html_sanitiser::for_app($html));
    }

    /**
     * A form wrapper is dropped but what it wraps stays.
     */
    public function test_unwraps_forms(): void {
        $this->assertSame(
            '<div><p>Inside</p></div>',
            html_sanitiser::for_app('<div><form action="view.php" method="post"><p>Inside</p></form></div>')
        );
    }

    /**
     * Event handlers and script URLs are removed; ordinary attributes and links stay.
     */
    public function test_cleans_attributes(): void {
        $html = '<a href="https://example.org/x" class="btn" onclick="steal()">Link</a>'
            . '<a href=" JaVa&#x09;script:alert(1)">Bad</a><img src="pic.png" onerror="x()" alt="p">'
            . '<iframe src="data:text/html;base64,PHNjcmlwdD4="></iframe>';

        $out = html_sanitiser::for_app($html);

        $this->assertStringContainsString('<a href="https://example.org/x" class="btn">Link</a>', $out);
        $this->assertStringContainsString('<a>Bad</a>', $out);
        $this->assertStringContainsString('<img src="pic.png" alt="p">', $out);
        $this->assertStringNotContainsStringIgnoringCase('onclick', $out);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $out);
        $this->assertStringNotContainsStringIgnoringCase('javascript', $out);
        $this->assertStringNotContainsString('data:text/html', $out);
    }

    /**
     * Non-ASCII text survives, and a fragment comes back as a fragment.
     */
    public function test_keeps_utf8_text_and_fragments(): void {
        $html = '<p>Mitfahrbörse – Wien → Graz</p> Text';
        $this->assertSame($html, html_sanitiser::for_app($html));
        $this->assertSame('', html_sanitiser::for_app('  '));
        $this->assertSame('plain', html_sanitiser::for_app('plain'));
    }

    /**
     * Angular interpolation in user content is left as text: the app binds it as data, where it is inert.
     */
    public function test_leaves_curly_braces_as_text(): void {
        $this->assertSame('<p>{{ 1 + 1 }}</p>', html_sanitiser::for_app('<p>{{ 1 + 1 }}</p>'));
    }
}
