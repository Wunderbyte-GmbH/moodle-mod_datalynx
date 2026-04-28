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
use mod_datalynx\local\view\manager\grid_view_manager;
use stdClass;

/**
 * Tests for the Grid view browse payload manager.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_datalynx\local\view\manager\grid_view_manager
 */
final class grid_view_manager_test extends advanced_testcase {
    /**
     * Build a minimal datalynx fixture with a Grid view, one text field, and one entry.
     *
     * @return array
     */
    private function create_grid_fixture(): array {
        global $DB, $USER;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $df = new datalynx($instance->id);

        $view = (object) [
            'dataid' => $df->id(),
            'type' => 'grid',
            'name' => 'Pilot Grid',
            'description' => '',
            'visible' => 7,
            'filter' => 0,
            'perpage' => 0,
            'groupby' => '',
            'param5' => 0,
            'param10' => 0,
            'section' => '',
            'param2' => '',
        ];
        $view->id = (int) $DB->insert_record('datalynx_views', $view);

        $field = (object) [
            'dataid' => $df->id(),
            'type' => 'text',
            'name' => 'Title',
            'description' => '',
            'param1' => '',
            'param2' => '',
            'param3' => '',
            'param4' => '',
            'param5' => '',
            'param6' => '',
            'param7' => '',
            'param8' => '',
            'param9' => '',
            'param10' => '',
        ];
        $field->id = (int) $DB->insert_record('datalynx_fields', $field);

        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $df->id(),
            'userid' => $USER->id,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $field->id,
            'entryid' => $entryid,
            'lineid' => 0,
            'content' => 'Hello Grid',
        ]);

        return [$df, $view, $field, $entryid];
    }

    /**
     * The manager should return a structured browse payload for one Grid entry.
     *
     * @covers ::get_browse_payload
     */
    public function test_get_browse_payload_returns_structured_grid_entry(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$df, $view, , $entryid] = $this->create_grid_fixture();

        $manager = new grid_view_manager();
        $payload = $manager->get_browse_payload($df->id(), $view->id);

        $this->assertSame($df->id(), $payload['datalynxid']);
        $this->assertSame($view->id, $payload['viewid']);
        $this->assertTrue($payload['hasentries']);
        $this->assertCount(1, $payload['groups']);
        $this->assertCount(1, $payload['groups'][0]['entries']);
        $this->assertSame($entryid, $payload['groups'][0]['entries'][0]['id']);
        $this->assertSame('Title', $payload['groups'][0]['entries'][0]['fields'][0]['name']);
        $this->assertStringContainsString('Hello Grid', $payload['groups'][0]['entries'][0]['fields'][0]['valuehtml']);
        $this->assertStringContainsString('editentries=' . $entryid, $payload['groups'][0]['entries'][0]['edithtml']);
        $this->assertStringContainsString('eids=' . $entryid, $payload['groups'][0]['entries'][0]['edithtml']);
    }
}
