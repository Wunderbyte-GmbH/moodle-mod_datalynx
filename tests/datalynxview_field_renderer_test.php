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
 * Tests for inline rendering of the datalynxview field.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \datalynxfield_datalynxview\renderer::render_display_mode
 * @covers \datalynxview_grid\view::display
 * @covers \datalynxfield_datalynxview\field::__construct
 */
final class datalynxview_field_renderer_test extends advanced_testcase {
    /**
     * Build a remote grid fixture and a local datalynxview field referencing it.
     *
     * @return array
     */
    private function create_fixture(): array {
        global $DB, $USER;

        $course = $this->getDataGenerator()->create_course();

        $remoteinstance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $remotedlx = new datalynx($remoteinstance->id);

        $remoteview = (object) [
            'dataid' => $remotedlx->id(),
            'type' => 'grid',
            'name' => 'Remote Grid',
            'description' => '',
            'visible' => 7,
            'filter' => 0,
            'perpage' => 0,
            'groupby' => '',
            'param5' => 0,
            'param10' => 0,
            'section' => '##entries##',
            'param2' => '<div class="remote-entry">[[Remote title]]</div>',
        ];
        $remoteview->id = (int) $DB->insert_record('datalynx_views', $remoteview);
        \mod_datalynx\local\definition_cache::purge();

        $remotetextfield = (object) [
            'dataid' => $remotedlx->id(),
            'type' => 'text',
            'name' => 'Remote title',
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
        $remotetextfield->id = (int) $DB->insert_record('datalynx_fields', $remotetextfield);
        \mod_datalynx\local\definition_cache::purge();

        $remoteentryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $remotedlx->id(),
            'userid' => $USER->id,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $remotetextfield->id,
            'entryid' => $remoteentryid,
            'content' => 'Inline remote entry',
        ]);

        $localinstance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $localdlx = new datalynx($localinstance->id);

        $localfield = (object) [
            'dataid' => $localdlx->id(),
            'type' => 'datalynxview',
            'name' => 'Remote view field',
            'description' => '',
            'param1' => $remotedlx->id(),
            'param2' => $remoteview->id,
            'param3' => '',
            'param4' => '',
            'param5' => '',
            'param6' => '1,0',
            'param7' => '',
            'param8' => '',
            'param9' => '',
            'param10' => '',
        ];
        $localfield->id = (int) $DB->insert_record('datalynx_fields', $localfield);
        \mod_datalynx\local\definition_cache::purge();

        $localentry = (object) $DB->get_record('datalynx_entries', [
            'id' => (int) $DB->insert_record('datalynx_entries', (object) [
                'dataid' => $localdlx->id(),
                'userid' => $USER->id,
                'groupid' => 0,
                'approved' => 1,
                'status' => 0,
                'timecreated' => time(),
                'timemodified' => time(),
            ]),
        ], '*', MUST_EXIST);

        return [$localdlx, $localfield->id, $localentry];
    }

    /**
     * The datalynxview field should render referenced browse views inline instead of returning an AJAX placeholder.
     */
    public function test_datalynxview_field_renders_remote_grid_entries_inline(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$localdlx, $fieldid, $localentry] = $this->create_fixture();

        $field = $localdlx->get_field_from_id($fieldid);
        $output = $field->renderer()->render_display_mode($localentry, []);

        $this->assertStringContainsString('Inline remote entry', $output);
        $this->assertStringNotContainsString('mod-datalynx-view-browser-state-loading', $output);
    }

    /**
     * A field whose referenced instance was deleted renders empty instead of failing (#262).
     */
    public function test_deleted_referenced_instance_renders_empty(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$localdlx, $fieldid, $localentry] = $this->create_fixture();
        $remoteid = (int) $DB->get_field('datalynx_fields', 'param1', ['id' => $fieldid]);
        $DB->delete_records('datalynx', ['id' => $remoteid]);

        $field = (new datalynx($localdlx->id()))->get_field_from_id($fieldid);

        $this->assertNull($field->refdatalynx);
        $this->assertSame('', $field->renderer()->render_display_mode($localentry, []));
    }

    /**
     * Custom sort and search patterns are resolved against the referenced view, in the referenced instance (#262).
     */
    public function test_sort_and_search_options_use_the_referenced_view(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$localdlx, $fieldid] = $this->create_fixture();
        $remoteid = (int) $DB->get_field('datalynx_fields', 'param1', ['id' => $fieldid]);
        $remotefieldid = (int) $DB->get_field('datalynx_fields', 'id', ['dataid' => $remoteid, 'name' => 'Remote title']);
        $DB->update_record('datalynx_fields', (object) [
            'id' => $fieldid,
            'param4' => '[[Remote title]],DESC',
            'param5' => "AND,[[Remote title]],,=,fixed value\nXOR,[[Remote title]],,=,ignored",
        ]);
        $localviewid = (int) $DB->insert_record('datalynx_views', (object) [
            'dataid' => $localdlx->id(), 'type' => 'grid', 'name' => 'Local', 'description' => '', 'visible' => 7,
            'filter' => 0, 'perpage' => 0, 'groupby' => '', 'section' => '##entries##', 'param2' => '[[Remote view field]]',
        ]);
        \mod_datalynx\local\definition_cache::purge();

        $field = (new datalynx($localdlx->id()))->get_field_from_id($fieldid);
        $field->localview = $localviewid;
        $renderer = $field->renderer();
        $call = function (string $method, ...$args) use ($renderer) {
            $reflection = new \ReflectionMethod($renderer, $method);
            $reflection->setAccessible(true);
            return $reflection->invoke($renderer, ...$args);
        };

        $this->assertSame([$remotefieldid => 1], $call('get_sort_options'));
        $this->assertEquals(
            [$remotefieldid => ['AND' => [['', '=', 'fixed value']], 'OR' => []]],
            $call('get_search_options', (object) ['id' => 0])
        );
    }
}
