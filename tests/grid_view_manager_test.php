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
        $dlx = new datalynx($instance->id);

        $view = (object) [
            'dataid' => $dlx->id(),
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
            'dataid' => $dlx->id(),
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
            'dataid' => $dlx->id(),
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

        return [$dlx, $view, $field, $entryid];
    }

    /**
     * The manager should return a structured browse payload for one Grid entry.
     *
     * @covers ::get_browse_payload
     */
    public function test_get_browse_payload_returns_structured_grid_entry(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$dlx, $view, , $entryid] = $this->create_grid_fixture();

        $manager = new grid_view_manager();
        $payload = $manager->get_browse_payload($dlx->id(), $view->id);

        $this->assertSame($dlx->id(), $payload['datalynxid']);
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

    /**
     * Test wrapper settings payload output.
     *
     * @covers ::get_browse_payload
     */
    public function test_get_browse_payload_with_wrapper_settings(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$dlx, $view, , ] = $this->create_grid_fixture();

        // 1. Default (legacy) wrapper setting when param3 is empty.
        $manager = new grid_view_manager();
        $payload = $manager->get_browse_payload($dlx->id(), $view->id);
        $this->assertFalse($payload['nowrapper']);
        $this->assertSame('entry', $payload['entrywrapperclass']);
        $this->assertSame('', $payload['groupclass']);

        // 2. Bootstrap row-cols setting.
        $DB->set_field('datalynx_views', 'param3', 'col', ['id' => $view->id]);
        $payload = $manager->get_browse_payload($dlx->id(), $view->id);
        $this->assertFalse($payload['nowrapper']);
        $this->assertSame('col', $payload['entrywrapperclass']);
        $this->assertSame('row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4', $payload['groupclass']);

        // 3. Custom class setting with col- definition.
        $DB->set_field('datalynx_views', 'param3', 'custom', ['id' => $view->id]);
        $DB->set_field('datalynx_views', 'param4', 'col-12 col-md-6 col-lg-3', ['id' => $view->id]);
        $payload = $manager->get_browse_payload($dlx->id(), $view->id);
        $this->assertFalse($payload['nowrapper']);
        $this->assertSame('col-12 col-md-6 col-lg-3', $payload['entrywrapperclass']);
        $this->assertSame('row g-4', $payload['groupclass']);

        // 4. Custom class setting without col- definition.
        $DB->set_field('datalynx_views', 'param3', 'custom', ['id' => $view->id]);
        $DB->set_field('datalynx_views', 'param4', 'my-custom-class', ['id' => $view->id]);
        $payload = $manager->get_browse_payload($dlx->id(), $view->id);
        $this->assertFalse($payload['nowrapper']);
        $this->assertSame('my-custom-class', $payload['entrywrapperclass']);
        $this->assertSame('', $payload['groupclass']);

        // 5. No wrapper setting.
        $DB->set_field('datalynx_views', 'param3', 'none', ['id' => $view->id]);
        $payload = $manager->get_browse_payload($dlx->id(), $view->id);
        $this->assertTrue($payload['nowrapper']);
        $this->assertSame('', $payload['entrywrapperclass']);
        $this->assertSame('', $payload['groupclass']);
    }
}
