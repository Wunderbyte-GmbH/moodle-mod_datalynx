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

    /**
     * Every placeholder must occur exactly once in the comment template.
     *
     * M.core_comment.render() in comment/comment.js substitutes them with
     * String.prototype.replace() and a string pattern, which only replaces the first match, so a
     * duplicated placeholder would silently render as literal text in the browser while the PHP
     * path (str_replace) still looked correct.
     *
     * @covers ::datalynx_comment_template
     */
    public function test_comment_template_contains_each_placeholder_exactly_once(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/datalynx/lib.php');

        $template = datalynx_comment_template(new \stdClass());

        foreach (['___picture___', '___name___', '___time___', '___content___'] as $placeholder) {
            $this->assertSame(
                1,
                substr_count($template, $placeholder),
                "Placeholder {$placeholder} must appear exactly once in the comment template."
            );
        }
    }

    /**
     * Core must actually pick the template callback up for datalynx comments.
     *
     * comment_template is a long-standing but undocumented extension point, and Moodle 5.x
     * renames the comment class to \core_comment\manager. If a future core release stops
     * invoking the callback, the widget would quietly fall back to core's markup and only the
     * styling would look wrong; this test turns that into a visible failure.
     *
     * @covers ::datalynx_comment_template
     */
    public function test_core_applies_the_datalynx_comment_template(): void {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/comment/lib.php');

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, $course->id, false, MUST_EXIST);

        $PAGE = new moodle_page();
        $PAGE->set_url('/mod/datalynx/view.php', ['id' => $cm->id]);

        $args = new \stdClass();
        $args->context = \context_module::instance($cm->id);
        $args->courseid = $course->id;
        $args->cm = $cm;
        $args->component = 'mod_datalynx';
        $args->area = 'entry';
        $args->itemid = 1;

        $comment = new \comment($args);

        $property = new \ReflectionProperty(\comment::class, 'template');
        $property->setAccessible(true);
        $template = $property->getValue($comment);

        $this->assertStringContainsString('datalynx-comment-body', $template);
        $this->assertSame(datalynx_comment_template($args), $template);
    }
}
