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

/**
 * Tests for datalynx::process_views().
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_datalynx\datalynx::process_views
 */
final class datalynx_process_views_test extends advanced_testcase {

    /**
     * Create a minimal datalynx fixture with one tabular view.
     *
     * @return array [$df, $viewid]
     */
    private function create_fixture(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $df = new datalynx($instance->id);

        $viewid = (int) $DB->insert_record('datalynx_views', (object) [
            'dataid' => $df->id(),
            'type' => 'tabular',
            'name' => 'My Tabular View',
            'description' => '',
            'visible' => 7,
            'filter' => 0,
            'perpage' => 0,
            'groupby' => '',
            'param1' => '',
            'param2' => '',
            'param3' => '',
            'param4' => '',
            'param5' => 0,
            'param10' => 0,
            'section' => '',
        ]);

        return [$df, $viewid];
    }

    /**
     * Duplicating a confirmed view creates a copy with "Copy of …" prefix in the database.
     */
    public function test_process_views_duplicate_creates_copy(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$df, $viewid] = $this->create_fixture();

        $before = $DB->count_records('datalynx_views', ['dataid' => $df->id()]);

        $df->process_views('duplicate', (string) $viewid, true);

        $after = $DB->count_records('datalynx_views', ['dataid' => $df->id()]);
        $this->assertSame($before + 1, $after, 'A new view record should be created after duplicate.');

        $copy = $DB->get_record('datalynx_views', [
            'dataid' => $df->id(),
            'name' => 'Copy of My Tabular View',
        ]);
        $this->assertNotFalse($copy, 'The duplicated view should be named "Copy of My Tabular View".');
        $this->assertSame('tabular', $copy->type, 'The duplicated view should retain its type.');
    }

    /**
     * Duplicating a view twice appends a counter to avoid name collisions.
     */
    public function test_process_views_duplicate_twice_avoids_name_collision(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$df, $viewid] = $this->create_fixture();

        $df->process_views('duplicate', (string) $viewid, true);
        $df->process_views('duplicate', (string) $viewid, true);

        $copies = $DB->get_records('datalynx_views', ['dataid' => $df->id(), 'type' => 'tabular']);
        $names = array_column($copies, 'name');

        $this->assertContains('Copy of My Tabular View', $names);
        $this->assertContains('Copy of My Tabular View (2)', $names);
    }
}
