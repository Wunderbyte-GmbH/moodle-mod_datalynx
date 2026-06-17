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
 * Tests for the file field renderer's tag menu / field-format integration.
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
 * Tests for the file field renderer.
 *
 * @covers \datalynxfield_file\renderer::patterns
 */
final class file_field_renderer_test extends advanced_testcase {
    /**
     * Set up the test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Build a datalynx instance with a single file field and return [$dlx, $fieldid].
     *
     * @return array{0: datalynx, 1: int}
     */
    private function create_file_field_instance(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $dlx = new datalynx($this->getDataGenerator()->create_module('datalynx', ['course' => $course->id])->id);

        $fieldid = (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $dlx->id(),
            'type' => 'file',
            'name' => 'Attachment',
            'description' => '',
            'param2' => '2',
        ]);

        return [$dlx, $fieldid];
    }

    /**
     * Return the protected patterns() array of a field's renderer.
     *
     * @param datalynx $dlx
     * @param int $fieldid
     * @return array
     */
    private function renderer_patterns(datalynx $dlx, int $fieldid): array {
        $field = $dlx->get_fields()[$fieldid];
        $renderer = $field->renderer();
        $method = new ReflectionMethod($renderer, 'patterns');
        $method->setAccessible(true);
        return $method->invoke($renderer);
    }

    /**
     * With no custom file formats, the legacy suffix patterns are still offered.
     */
    public function test_patterns_without_format_keep_legacy_tags(): void {
        [$dlx, $fieldid] = $this->create_file_field_instance();

        $patterns = $this->renderer_patterns($dlx, $fieldid);

        $this->assertArrayHasKey('[[Attachment]]', $patterns);
        $this->assertArrayHasKey('[[Attachment:alt]]', $patterns);
        $this->assertArrayHasKey('[[Attachment:download]]', $patterns);
    }

    /**
     * Once a "file" field format exists it is surfaced as a tag and the legacy suffix tags are
     * dropped from the menu (regression: custom file formats never appeared in the Field tags list).
     */
    public function test_patterns_with_format_surface_format_and_drop_legacy(): void {
        [$dlx, $fieldid] = $this->create_file_field_instance();

        manager::save_format((object) [
            'dataid' => $dlx->id(),
            'name' => 'downloadlink',
            'fieldtype' => 'file',
            'settings' => json_encode(['mode' => 'download']),
        ]);

        $patterns = $this->renderer_patterns($dlx, $fieldid);

        // The custom field format is now an available tag.
        $this->assertArrayHasKey('[[Attachment:downloadlink]]', $patterns);
        // The plain field tag is always available.
        $this->assertArrayHasKey('[[Attachment]]', $patterns);
        // Legacy suffix tags are no longer advertised.
        $this->assertArrayNotHasKey('[[Attachment:alt]]', $patterns);
        $this->assertArrayNotHasKey('[[Attachment:download]]', $patterns);
        $this->assertArrayNotHasKey('[[Attachment:url]]', $patterns);
    }

    /**
     * The format tag must be marked visible so it actually shows in the Field tags dropdown menu.
     */
    public function test_format_tag_is_shown_in_menu(): void {
        [$dlx, $fieldid] = $this->create_file_field_instance();

        manager::save_format((object) [
            'dataid' => $dlx->id(),
            'name' => 'downloadlink',
            'fieldtype' => 'file',
            'settings' => json_encode(['mode' => 'download']),
        ]);

        $field = $dlx->get_fields()[$fieldid];
        $menu = $field->renderer()->get_menu();

        // Flatten the nested category => category => [tag => tag] menu structure.
        $tags = [];
        foreach ($menu as $sub) {
            foreach ($sub as $items) {
                $tags = array_merge($tags, array_keys($items));
            }
        }

        $this->assertContains('[[Attachment:downloadlink]]', $tags);
        $this->assertNotContains('[[Attachment:alt]]', $tags);
    }
}
