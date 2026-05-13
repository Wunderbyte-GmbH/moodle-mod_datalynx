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
 * Tests for the internal comment field renderer.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use datalynxfield_comment\field as comment_field;
use moodle_page;

/**
 * Tests for the internal comment field renderer.
 */
final class comment_field_renderer_test extends advanced_testcase {
    /**
     * Set up the test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * The comment count renderer should set a valid fallback page URL when the page has none yet.
     *
     * @covers \datalynxfield_comment\renderer::display_browse
     */
    public function test_display_browse_count_sets_page_url_to_datalynx_view(): void {
        global $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $fieldrecord = comment_field::get_field_objects($dlx->id())[comment_field::_COMMENT];
        $field = new comment_field($dlx, $fieldrecord);
        $renderer = $field->renderer();

        $PAGE = new moodle_page();

        $result = $renderer->display_browse((object) ['id' => 42], ['count' => true]);

        $this->assertSame('0', trim((string) $result));
        $this->assertTrue($PAGE->has_set_url());
        $this->assertSame(
            '/mod/datalynx/view.php?id=' . $dlx->cm->id,
            $PAGE->url->out_as_local_url(false)
        );
    }
}
