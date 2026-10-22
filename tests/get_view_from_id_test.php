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
 * Tests for datalynx::get_view_from_id().
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_datalynx\datalynx::get_view_from_id
 */
final class get_view_from_id_test extends advanced_testcase {
    /**
     * Set up the fixture.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Create a datalynx instance with two tabular views.
     *
     * @param bool $withdefault Whether the second view is the default view.
     * @return array [datalynx, first view id, second view id]
     */
    private function create_fixture(bool $withdefault): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);

        $viewids = [];
        foreach (['First', 'Second'] as $name) {
            $viewids[] = (int) $DB->insert_record('datalynx_views', (object) [
                'dataid' => $instance->id,
                'type' => 'tabular',
                'name' => $name,
                'description' => '',
                'visible' => 7,
                'filter' => 0,
                'perpage' => 0,
                'groupby' => '',
                'section' => '',
            ]);
        }
        $DB->set_field('datalynx', 'defaultview', $withdefault ? $viewids[1] : 0, ['id' => $instance->id]);

        return [new datalynx($instance->id), $viewids[0], $viewids[1]];
    }

    /**
     * An available view is returned as requested.
     */
    public function test_returns_requested_view(): void {
        [$dlx, $first] = $this->create_fixture(true);

        $this->assertSame($first, (int) $dlx->get_view_from_id($first)->id());
    }

    /**
     * An unavailable or missing view id falls back to the default view.
     */
    public function test_falls_back_to_default_view(): void {
        [$dlx, , $default] = $this->create_fixture(true);

        $this->assertSame($default, (int) $dlx->get_view_from_id(0)->id());
        $this->assertSame($default, (int) $dlx->get_view_from_id(-1)->id());
    }

    /**
     * Without a default view an unavailable view id yields false.
     */
    public function test_returns_false_without_default_view(): void {
        [$dlx] = $this->create_fixture(false);

        $this->assertFalse($dlx->get_view_from_id(-1));
    }
}
