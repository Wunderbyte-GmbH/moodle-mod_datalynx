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
 * Tests that the eids URL parameter restricts the entries shown by a view.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\filter\datalynx_filter;

/**
 * Tests that the eids URL parameter restricts the entries shown by a view.
 *
 * @covers \mod_datalynx\local\view\base::set_filter
 * @covers \mod_datalynx\local\datalynx_entries::get_entries
 */
final class view_eids_filter_test extends advanced_testcase {
    /**
     * Set up the fixture.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Create three plain (approved) entries in a fresh datalynx instance.
     *
     * @return array [datalynx $dlx, int[] $entryids]
     */
    private function create_instance_with_entries(): array {
        global $DB, $USER;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $entryids = [];
        for ($i = 0; $i < 3; $i++) {
            $entryids[] = (int) $DB->insert_record('datalynx_entries', (object) [
                'dataid' => $dlx->id(),
                'userid' => $USER->id,
                'groupid' => 0,
                'approved' => 1,
                'status' => 0,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }

        return [$dlx, $entryids];
    }

    /**
     * The eids URL parameter must restrict the view to the requested entry/entries, even when the
     * view forces a filter (which otherwise causes URL filter options to be ignored). This is the
     * regression: previously a forced filter dropped eids and every entry was shown.
     */
    public function test_eids_url_param_honored_when_view_forces_filter(): void {
        global $DB;

        [$dlx, $entryids] = $this->create_instance_with_entries();

        // A filter that matches every entry (no search constraints).
        $filterid = (int) $DB->insert_record('datalynx_filters', (object) [
            'dataid' => $dlx->id(),
            'name' => 'All entries',
            'description' => '',
            'customsort' => '',
            'customsearch' => '',
            'search' => '',
            'groupby' => '',
            'perpage' => 50,
            'selection' => 0,
            'page' => 0,
            'eids' => '',
        ]);

        // A view that FORCES the filter: overridefilter (param5) is off, so is_forcing_filter()
        // returns the filter id and set_filter() is called with $ignoreurl = true.
        $viewrecord = (object) [
            'dataid' => $dlx->id(),
            'type' => 'grid',
            'name' => 'Forced filter view',
            'description' => '',
            'visible' => 7,
            'filter' => $filterid,
            'perpage' => 0,
            'groupby' => '',
            'param5' => 0,
            'param9' => 0,
            'param10' => 0,
            'section' => '',
            'param2' => '',
        ];
        $viewrecord->id = (int) $DB->insert_record('datalynx_views', $viewrecord);

        // Simulate the request ...&editentries=1&eids=<entry>. optional_param() reads from $_POST.
        $target = $entryids[1];
        $_POST['eids'] = (string) $target;
        $_POST['editentries'] = (string) $target;

        try {
            // Constructing the view runs set_filter() through the forced-filter path.
            $view = $dlx->get_view('grid', $DB->get_record('datalynx_views', ['id' => $viewrecord->id]));

            // The eids parameter survived the forced filter.
            $this->assertEquals((string) $target, $view->get_filter()->eids);

            // The query returns only the requested entry, not all three.
            $entriesclass = new datalynx_entries($dlx, $view->get_filter());
            $result = $entriesclass->get_entries();
            $this->assertCount(1, $result->entries);
            $this->assertArrayHasKey($target, $result->entries);
        } finally {
            unset($_POST['eids'], $_POST['editentries']);
        }
    }

    /**
     * eids is an additional constraint, not a replacement for the filter: an entry id that does not
     * match the (forced) filter must not be shown, while a matching id is shown.
     */
    public function test_eids_does_not_override_filter_constraints(): void {
        global $DB, $USER;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        // A numeric field we can filter on.
        $fieldrecord = (object) [
            'dataid' => $dlx->id(),
            'type' => 'duration',
            'name' => 'DurationField',
            'description' => '',
            'param1' => 'seconds',
            'param2' => '', 'param3' => '', 'param4' => '', 'param5' => '',
            'param6' => '', 'param7' => '', 'param8' => '', 'param9' => '', 'param10' => '',
        ];
        $fieldid = (int) $DB->insert_record('datalynx_fields', $fieldrecord);

        // Entry A (100) is excluded by the filter; entry B (300) is included.
        $entryaid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(), 'userid' => $USER->id, 'approved' => 1, 'status' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid, 'entryid' => $entryaid, 'content' => '100',
        ]);
        $entrybid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(), 'userid' => $USER->id, 'approved' => 1, 'status' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid, 'entryid' => $entrybid, 'content' => '300',
        ]);

        // Filter: duration >= 200 (matches only entry B).
        $customsearch = [$fieldid => ['AND' => [['', '>=', ['200']]]]];
        $filterrecord = (object) [
            'dataid' => $dlx->id(),
            'name' => 'AtLeast200',
            'description' => '',
            'customsort' => '',
            'customsearch' => serialize($customsearch),
            'search' => '',
            'groupby' => '',
            'perpage' => 50,
            'selection' => 0,
            'page' => 0,
            'eids' => '',
        ];
        $filterid = (int) $DB->insert_record('datalynx_filters', $filterrecord);

        // Requesting the filtered-out entry A via eids yields nothing.
        $filter = new datalynx_filter($DB->get_record('datalynx_filters', ['id' => $filterid]));
        $filter->contentfields = [$fieldid];
        $filter->eids = (string) $entryaid;
        $result = (new datalynx_entries($dlx, $filter))->get_entries();
        $this->assertEmpty($result->entries);

        // Requesting the matching entry B via eids yields exactly that entry.
        $filter = new datalynx_filter($DB->get_record('datalynx_filters', ['id' => $filterid]));
        $filter->contentfields = [$fieldid];
        $filter->eids = (string) $entrybid;
        $result = (new datalynx_entries($dlx, $filter))->get_entries();
        $this->assertCount(1, $result->entries);
        $this->assertArrayHasKey($entrybid, $result->entries);
    }
}
