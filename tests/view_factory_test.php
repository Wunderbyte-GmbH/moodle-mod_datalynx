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
 * Tests for datalynx view factory behaviour.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use coding_exception;
use datalynxview_email\view as email_view;

/**
 * Tests for strict view factory behaviour.
 */
final class view_factory_test extends advanced_testcase {
    /**
     * Set up the fixture.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Create a datalynx instance for testing.
     *
     * @return datalynx
     */
    private function create_test_datalynx(): datalynx {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->create_module('datalynx', ['course' => $course->id]);

        return new datalynx($instance->id);
    }

    /**
     * The factory should instantiate a view from an explicit type string.
     */
    public function test_get_view_instantiates_requested_type(): void {
        $df = $this->create_test_datalynx();

        $view = $df->get_view('email');

        $this->assertInstanceOf(email_view::class, $view);
        $this->assertSame('email', $view->type());
        $this->assertSame(0, $view->id());
        $this->assertSame($df->id(), $view->get_dl()->id());
    }

    /**
     * Empty view types should fail as a programmer error.
     */
    public function test_get_view_throws_for_empty_type(): void {
        $df = $this->create_test_datalynx();

        $this->expectException(coding_exception::class);
        $df->get_view('');
    }

    /**
     * Unknown view types should fail as a programmer error.
     */
    public function test_get_view_throws_for_invalid_type(): void {
        $df = $this->create_test_datalynx();

        $this->expectException(coding_exception::class);
        $df->get_view('definitelymissing');
    }

    /**
     * Inline rendering must keep user-facing URLs on the canonical activity page.
     */
    public function test_inline_external_context_still_uses_view_php_for_urls(): void {
        $df = $this->create_test_datalynx();

        $pageparams = [
            'js' => true,
            'css' => true,
            'rss' => true,
            'modjs' => true,
            'completion' => true,
            'comments' => true,
            'urlparams' => (object) ['d' => $df->id(), 'view' => 0],
        ];
        $df->set_page('external', $pageparams, true);

        $this->assertStringContainsString('/mod/datalynx/view.php', $df->get_baseurl()->out(false));

        $view = $df->get_view('tabular');
        $this->assertStringContainsString('/mod/datalynx/view.php', $view->get_baseurl()->out(false));
    }
}
