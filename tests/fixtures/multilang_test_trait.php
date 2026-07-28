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
 * Shared helpers for tests that assert content filters are applied.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

/**
 * Helpers for asserting that the content filter chain reaches a piece of datalynx output.
 *
 * Deliberately uses core's multilang filter rather than the third party filter_multilang2 that
 * this site runs: filter_multilang2 is not installed in the plugin's own CI, where
 * filter_set_global_state() on an absent filter silently does nothing and every assertion would
 * fail. Proving "the filter chain is applied" does not need the third party plugin — whichever
 * filters an installation enables then apply on their own.
 */
trait multilang_test_trait {
    /** @var string Marker rendered when the current language is German. */
    private const GERMAN_MARKER = 'GERMANMARKER';

    /** @var string Marker rendered when the current language is English. */
    private const ENGLISH_MARKER = 'ENGLISHMARKER';

    /**
     * Enable core's multilang filter for content and headings.
     */
    private function enable_multilang_filter(): void {
        filter_set_global_state('multilang', TEXTFILTER_ON);
        filter_set_applies_to_strings('multilang', true);
        \filter_manager::reset_caches();
    }

    /**
     * Multilang markup resolving to {@see GERMAN_MARKER} in German and {@see ENGLISH_MARKER} otherwise.
     *
     * @return string
     */
    private function multilang_markup(): string {
        return '<span lang="de" class="multilang">' . self::GERMAN_MARKER . '</span>' .
            '<span lang="en" class="multilang">' . self::ENGLISH_MARKER . '</span>';
    }

    /**
     * Force the current language for the request.
     *
     * Sets $SESSION->forcelang directly rather than calling force_current_language(), which is a
     * no-op when the language pack is not installed in the test environment.
     *
     * @param string $lang the language code to force, e.g. 'de' or 'en'.
     */
    private function set_current_language(string $lang): void {
        global $SESSION;
        $SESSION->forcelang = $lang;
        \filter_manager::reset_caches();
    }

    /**
     * Assert that $html shows only the marker for $lang and carries no unresolved markup.
     *
     * @param string $lang 'de' or 'en'.
     * @param string $html Rendered output.
     * @param string $message Context for the failure message.
     */
    private function assert_localised(string $lang, string $html, string $message = ''): void {
        [$expected, $unexpected] = $lang === 'de'
            ? [self::GERMAN_MARKER, self::ENGLISH_MARKER]
            : [self::ENGLISH_MARKER, self::GERMAN_MARKER];

        $this->assertStringNotContainsString('class="multilang"', $html, "unresolved markup: $message");
        $this->assertStringContainsString($expected, $html, "missing $lang text: $message");
        $this->assertStringNotContainsString($unexpected, $html, "other language leaked: $message");
    }
}
