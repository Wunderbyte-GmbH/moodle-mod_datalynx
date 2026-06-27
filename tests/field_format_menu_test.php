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
 * Tests that user-created field formats are surfaced in the field-tags menu and recognised for
 * replacement across every [[-style field type.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use mod_datalynx\local\field_format\manager;
use ReflectionMethod;

/**
 * Tests for the datalynx field format pattern menu.
 *
 * @covers \mod_datalynx\local\field\datalynxfield_renderer::format_patterns
 * @covers \mod_datalynx\local\field\datalynxfield_renderer::add_legacy_suffix_patterns
 */
final class field_format_menu_test extends advanced_testcase {
    /**
     * Set up the test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Build a datalynx instance with a single field of the given type; return [$dlx, $fieldid].
     *
     * @param string $type
     * @param string $name
     * @return array
     */
    private function instance_with_field(string $type, string $name): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $dlx = new datalynx($this->getDataGenerator()->create_module('datalynx', ['course' => $course->id])->id);
        $fieldid = (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $dlx->id(),
            'type' => $type,
            'name' => $name,
            'description' => '',
            // Generous defaults so field object construction never trips on a missing param.
            'param1' => "A\nB\nC",
            'param2' => '1',
            'param3' => '1',
        ]);
        return [$dlx, $fieldid];
    }

    /**
     * Flatten the nested category => category => [tag => tag] menu into a flat list of tags.
     *
     * @param array $menu
     * @return array
     */
    private function flatten_menu(array $menu): array {
        $tags = [];
        foreach ($menu as $sub) {
            foreach ($sub as $items) {
                $tags = array_merge($tags, array_keys($items));
            }
        }
        return $tags;
    }

    /**
     * Every [[-style field type that supports formats must surface a user-created format in the menu
     * and recognise its tag for replacement.
     *
     * @dataProvider format_field_types_provider
     * @param string $type Field type.
     * @param array $settings Sample settings for the format.
     */
    public function test_user_format_is_surfaced_and_recognised(string $type, array $settings): void {
        [$dlx, $fieldid] = $this->instance_with_field($type, 'Myfield');

        manager::save_format((object) [
            'dataid' => $dlx->id(),
            'name' => 'myfmt',
            'fieldtype' => $type,
            'settings' => json_encode($settings),
        ]);

        $field = $dlx->get_fields()[$fieldid];
        $renderer = $field->renderer();

        // 1. The format tag is a visible key in patterns().
        $method = new ReflectionMethod($renderer, 'patterns');
        $method->setAccessible(true);
        $patterns = $method->invoke($renderer);
        $this->assertArrayHasKey('[[Myfield:myfmt]]', $patterns, "$type: format tag missing from patterns()");
        $this->assertTrue($patterns['[[Myfield:myfmt]]'][0], "$type: format tag not flagged visible");

        // 2. The format tag appears in the field-tags dropdown menu.
        $this->assertContains(
            '[[Myfield:myfmt]]',
            $this->flatten_menu($renderer->get_menu()),
            "$type: format tag missing from get_menu()"
        );

        // 3. The format tag is recognised by search() so it is actually replaced (the reported bug).
        $this->assertContains(
            '[[Myfield:myfmt]]',
            $renderer->search('before [[Myfield:myfmt]] after'),
            "$type: format tag not recognised by search()"
        );
    }

    /**
     * Field types whose renderer reads $options['field_format'] and whose tag must reach the menu.
     *
     * @return array
     */
    public static function format_field_types_provider(): array {
        return [
            'number' => ['number', ['decimals' => 2]],
            'textarea' => ['textarea', ['maxlength' => 5]],
            'select' => ['select', ['option' => 'key']],
            'multiselect' => ['multiselect', ['option' => 'comma']],
            'url' => ['url', ['option' => 'link']],
            'time' => ['time', ['dateformat' => '%Y-%m-%d']],
            'duration' => ['duration', ['mode' => 'unit']],
            'picture' => ['picture', ['mode' => 'thumb']],
            'coursegroup' => ['coursegroup', ['mode' => 'course']],
            'gradeitem' => ['gradeitem', ['decimals' => 1]],
            'editor' => ['editor', ['excerpt' => 1]],
            'file' => ['file', ['mode' => 'download']],
            'tag' => ['tag', ['linked' => 0]],
        ];
    }

    /**
     * A number "decimals" format actually applies at render time — the originally reported failure
     * (the format was selectable but never called).
     */
    public function test_number_decimals_format_applies(): void {
        [$dlx, $fieldid] = $this->instance_with_field('number', 'Amount');
        manager::save_format((object) [
            'dataid' => $dlx->id(),
            'name' => 'twodp',
            'fieldtype' => 'number',
            'settings' => json_encode(['decimals' => 2]),
        ]);

        $field = $dlx->get_fields()[$fieldid];
        $format = manager::get_format_by_name($dlx->id(), 'twodp');
        $entry = (object) ['id' => 1, "c{$fieldid}_content" => '3.14159'];

        $out = $field->renderer()->render_display_mode($entry, ['field_format' => $format]);
        $this->assertSame('3.14', trim($out));
    }
}
