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
use mod_datalynx\local\view\manager\tabular_view_manager;

/**
 * Tests for the Tabular view browse payload manager.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_datalynx\local\view\manager\tabular_view_manager
 */
final class tabular_view_manager_test extends advanced_testcase {
    /**
     * Build a minimal datalynx fixture with a Tabular view, one text field, and one entry.
     *
     * @return array
     */
    private function create_tabular_fixture(): array {
        global $DB, $USER;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $view = (object) [
            'dataid' => $dlx->id(),
            'type' => 'tabular',
            'name' => 'Pilot Tabular',
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
            'content' => 'Hello Tabular',
        ]);

        return [$dlx, $view, $entryid];
    }

    /**
     * The manager should return a structured browse payload for one Tabular row.
     *
     * @covers ::get_browse_payload
     */
    public function test_get_browse_payload_returns_structured_tabular_row(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$dlx, $view, $entryid] = $this->create_tabular_fixture();

        $manager = new tabular_view_manager();
        $payload = $manager->get_browse_payload($dlx->id(), $view->id);

        $this->assertSame($dlx->id(), $payload['datalynxid']);
        $this->assertSame($view->id, $payload['viewid']);
        $this->assertTrue($payload['hasentries']);
        $this->assertCount(1, $payload['groups']);
        $this->assertCount(7, $payload['groups'][0]['columns']);
        $this->assertCount(1, $payload['groups'][0]['rows']);
        $this->assertSame($entryid, $payload['groups'][0]['rows'][0]['id']);
        $this->assertStringContainsString('Title', $payload['groups'][0]['columns'][2]['headerhtml']);
        $this->assertStringContainsString('Hello Tabular', $payload['groups'][0]['rows'][0]['cells'][2]['valuehtml']);
        $this->assertStringContainsString('editentries=' . $entryid, $payload['groups'][0]['rows'][0]['cells'][3]['valuehtml']);
        $this->assertStringContainsString('name="entryselector"', $payload['groups'][0]['rows'][0]['cells'][6]['valuehtml']);
    }

    /**
     * Regression test: calling set_filter with customsort as a serialised string (the format stored
     * in the database and previously forwarded verbatim by tabular/view.php::display) must NOT
     * throw a TypeError.
     *
     * Scenario reproduces:
     *   - filter with approved=1 (selection=1) and customsort by a duration field ASC
     *   - tabular view display path that passes the raw serialised string to set_filter
     *
     * @covers \mod_datalynx\local\view\base::set_filter
     * @covers \mod_datalynx\local\filter\datalynx_filter::append_sort_options
     */
    public function test_tabular_view_set_filter_with_duration_sort_does_not_throw(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        // Create a duration field named 'dur'.
        $fieldrecord = (object) [
            'dataid' => $dlx->id(),
            'type' => 'duration',
            'name' => 'dur',
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
        $fieldid = (int) $DB->insert_record('datalynx_fields', $fieldrecord);

        // Create a filter: approved entries only (selection=1), sort by duration field ASC (0=ASC).
        $filterrecord = (object) [
            'dataid' => $dlx->id(),
            'name' => 'Duration Sort Filter',
            'description' => '',
            'visible' => 1,
            'perpage' => 50,
            'selection' => 1,
            'groupby' => '',
            'customsort' => serialize([$fieldid => 0]),
            'customsearch' => '',
            'search' => '',
        ];
        $filterid = (int) $DB->insert_record('datalynx_filters', $filterrecord);

        // Create a tabular view with the filter assigned.
        $viewrecord = (object) [
            'dataid' => $dlx->id(),
            'type' => 'tabular',
            'name' => 'Duration Tabular',
            'description' => '',
            'visible' => 7,
            'filter' => $filterid,
            'perpage' => 0,
            'groupby' => '',
            'param5' => 0,
            'param10' => 0,
            'section' => '',
            'param2' => '',
        ];
        $viewrecord->id = (int) $DB->insert_record('datalynx_views', $viewrecord);

        // Create two approved entries with different duration values (in seconds).
        $entry1id = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(),
            'userid' => $USER->id,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid,
            'entryid' => $entry1id,
            'lineid' => 0,
            'content' => 7200,
        ]);

        $entry2id = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(),
            'userid' => $USER->id,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid,
            'entryid' => $entry2id,
            'lineid' => 0,
            'content' => 3600,
        ]);

        // Instantiate the tabular view. The constructor calls set_filter(true) which loads the
        // filter from the DB. This must not throw a TypeError.
        $view = $dlx->get_view('tabular', $viewrecord);

        // Reproduce the exact pre-fix bug path: pass customsort as a raw serialised string,
        // exactly as tabular/view.php::display() was forwarding it before the fix.
        $filteroptions = [
            'filterid' => $filterid,
            'customsort' => serialize([$fieldid => 0]),
        ];

        // Must not throw TypeError: "Argument #1 ($sorties) must be of type array, string given".
        $view->set_filter($filteroptions);

        // Verify the sort was applied: customsort on the filter object must contain the field.
        $sortoptions = unserialize($view->get_filter()->customsort);
        $this->assertIsArray($sortoptions, 'customsort must be unserializable to an array');
        $this->assertArrayHasKey($fieldid, $sortoptions, 'Sort options must contain the duration field id');
        $this->assertSame(0, $sortoptions[$fieldid], 'Sort direction must be 0 (ASC)');

        // Verify the generated ORDER BY SQL contains an ASC clause for the duration field content.
        $fields = $dlx->get_fields(null, false, true);
        [, , $sortorder] = $view->get_filter()->get_sql($fields);
        $this->assertStringContainsString(
            'ASC',
            $sortorder,
            'ORDER BY clause must contain ASC direction for duration field sort'
        );
    }
}
