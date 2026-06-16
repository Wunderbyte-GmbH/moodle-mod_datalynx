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

namespace mod_datalynx;

use advanced_testcase;
use mod_datalynx\local\field\datalynxfield_layout;

/**
 * Tests for field-layout (renderer) deletion and pattern rewriting across connected views.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_datalynx\local\field\datalynxfield_layout::update_render_pattern
 * @covers \mod_datalynx\local\field\datalynxfield_layout::delete_renderer
 */
final class layout_render_pattern_test extends advanced_testcase {
    /**
     * Insert a field layout (renderer) for the given datalynx instance.
     *
     * @param int $dataid Datalynx instance id.
     * @param string $name Renderer name.
     * @return int The new renderer id.
     */
    private function create_renderer(int $dataid, string $name): int {
        global $DB;
        return (int) $DB->insert_record('datalynx_renderers', (object) [
            'dataid' => $dataid,
            'type' => 'field',
            'name' => $name,
            'description' => '',
            'displaytemplate' => '#value',
        ]);
    }

    /**
     * Deleting a layout must not fail when a connected view has NULL patterns/param2.
     *
     * Regression: update_render_pattern() passed those NULL columns straight to strpos(), which is
     * a deprecation (treated as an error under the test/debug error handler) in PHP 8.1+.
     */
    public function test_delete_renderer_with_null_view_patterns(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        // A view that legitimately has no patterns/param2 yet (both NULL).
        $viewid = (int) $DB->insert_record('datalynx_views', (object) [
            'dataid' => $dlx->id(),
            'type' => 'grid',
            'name' => 'Empty view',
            'description' => '',
            'visible' => 7,
            'patterns' => null,
            'param2' => null,
        ]);

        $rendererid = $this->create_renderer($dlx->id(), 'My layout');

        // Must not raise the "Passing null to strpos()" deprecation, and must remove the renderer.
        datalynxfield_layout::delete_renderer($rendererid);

        $this->assertFalse($DB->record_exists('datalynx_renderers', ['id' => $rendererid]));
        // The untouched view is left intact.
        $this->assertTrue($DB->record_exists('datalynx_views', ['id' => $viewid]));
    }

    /**
     * Deleting a layout strips its pattern reference (||name) out of connected views.
     */
    public function test_delete_renderer_rewrites_connected_view_patterns(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $rendererid = $this->create_renderer($dlx->id(), 'My layout');

        // A view referencing the renderer via the ||name layout tag syntax.
        $viewid = (int) $DB->insert_record('datalynx_views', (object) [
            'dataid' => $dlx->id(),
            'type' => 'grid',
            'name' => 'Using view',
            'description' => '',
            'visible' => 7,
            'patterns' => '[[Title||My layout]]',
            'param2' => '##entries## [[Title||My layout]]',
        ]);

        datalynxfield_layout::delete_renderer($rendererid);

        $view = $DB->get_record('datalynx_views', ['id' => $viewid]);
        $this->assertStringNotContainsString('||My layout', $view->patterns);
        $this->assertStringNotContainsString('||My layout', $view->param2);
    }
}
