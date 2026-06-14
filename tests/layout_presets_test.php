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
 * Tests for the field-layout preset catalogue.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use mod_datalynx\local\layout_presets;

/**
 * Guards the preset data contract the layout editor and renderer rely on.
 *
 * @covers \mod_datalynx\local\layout_presets
 */
final class layout_presets_test extends advanced_testcase {
    /**
     * The catalogue exposes the three expected sections with localized families.
     */
    public function test_structure(): void {
        $all = layout_presets::all();

        $this->assertArrayHasKey('families', $all);
        $this->assertArrayHasKey('presets', $all);

        $familykeys = array_column($all['families'], 'key');
        $this->assertEqualsCanonicalizing(['cards', 'inline', 'edit', 'typographic'], $familykeys);
        foreach ($all['families'] as $family) {
            $this->assertNotEmpty($family['label']);
        }
    }

    /**
     * Every single-scope preset is well-formed and carries the render token its scope requires,
     * so it cannot break the form's #value/#input validation. No preset relies on #name (the
     * renderer never substitutes it).
     */
    public function test_presets_are_well_formed(): void {
        $familykeys = ['cards', 'inline', 'edit', 'typographic'];
        $scopes = ['display', 'edit', 'novalue', 'notvisible'];

        foreach (layout_presets::all()['presets'] as $preset) {
            $context = $preset['id'] ?? '(no id)';
            foreach (['id', 'family', 'familylabel', 'label', 'scope', 'templates'] as $key) {
                $this->assertArrayHasKey($key, $preset, "preset {$context} missing {$key}");
            }
            $this->assertContains($preset['family'], $familykeys, "preset {$context} family");
            $this->assertContains($preset['scope'], $scopes, "preset {$context} scope");
            $this->assertNotEmpty($preset['label'], "preset {$context} label");
            $this->assertArrayHasKey($preset['scope'], $preset['templates'], "preset {$context} scope template");

            $snippet = $preset['templates'][$preset['scope']];
            if ($preset['scope'] === 'edit') {
                $this->assertStringContainsString('#input', $snippet, "preset {$context} needs #input");
            } else {
                $this->assertStringContainsString('#value', $snippet, "preset {$context} needs #value");
            }
            $this->assertStringNotContainsString('#name', $snippet, "preset {$context} must not use #name");
        }
    }
}
