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
use datalynxview_csv\view as csv_view;
use mod_datalynx\datalynx;

/**
 * Tests that CSV export respects active custom filter search.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \datalynxview_csv\view
 */
final class csv_view_customfilter_test extends advanced_testcase {
    /**
     * Build a datalynx fixture with a CSV view, a text field, a multiselect field,
     * and three entries with different option selections.
     *
     * Options: Opt1 = key 1, Opt2 = key 2, Opt3 = key 3
     * Entry one  → Opt1 (content '#1#')
     * Entry two  → Opt2 (content '#2#')
     * Entry three → Opt3 (content '#3#')
     *
     * CSV view param2 exposes both the text and multiselect columns.
     *
     * @return array [$dlx, $viewrecord, $multiselectfieldid, $entryone, $entrytwo, $entrythree]
     */
    private function create_multiselect_fixture(): array {
        global $DB, $USER;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $view = (object) [
            'dataid'  => $dlx->id(),
            'type'    => 'csv',
            'name'    => 'CSV Export',
            'description' => '',
            'visible' => 7,
            'filter'  => 0,
            'perpage' => 0,
            'groupby' => '',
            'param1'  => '',
            'param2'  => '[[Name]]|Name|col-name,,[[Tags]]|Tags|col-tags',
            'param3'  => 'csv',
            'param5'  => 0,
            'param10' => 0,
            'section' => '',
        ];
        $view->id = (int) $DB->insert_record('datalynx_views', $view);

        $textfield = (object) [
            'dataid'  => $dlx->id(),
            'type'    => 'text',
            'name'    => 'Name',
            'description' => '',
            'param1'  => '',
            'param2'  => '', 'param3' => '', 'param4' => '', 'param5' => '',
            'param6'  => '', 'param7' => '', 'param8' => '', 'param9' => '', 'param10' => '',
        ];
        $textfield->id = (int) $DB->insert_record('datalynx_fields', $textfield);

        $multifield = (object) [
            'dataid'  => $dlx->id(),
            'type'    => 'multiselect',
            'name'    => 'Tags',
            'description' => '',
            'param1'  => "Opt1\nOpt2\nOpt3",
            'param2'  => '', 'param3' => '', 'param4' => '', 'param5' => '',
            'param6'  => '', 'param7' => '', 'param8' => '', 'param9' => '', 'param10' => '',
        ];
        $multifield->id = (int) $DB->insert_record('datalynx_fields', $multifield);

        $now = time();
        $entryone = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(), 'userid' => $USER->id, 'groupid' => 0,
            'approved' => 1, 'status' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $entrytwo = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(), 'userid' => $USER->id, 'groupid' => 0,
            'approved' => 1, 'status' => 0, 'timecreated' => $now + 1, 'timemodified' => $now + 1,
        ]);
        $entrythree = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(), 'userid' => $USER->id, 'groupid' => 0,
            'approved' => 1, 'status' => 0, 'timecreated' => $now + 2, 'timemodified' => $now + 2,
        ]);

        // Text content.
        foreach ([[$entryone, 'Entry one'], [$entrytwo, 'Entry two'], [$entrythree, 'Entry three']] as [$eid, $text]) {
            $DB->insert_record('datalynx_contents', (object) [
                'fieldid' => $textfield->id, 'entryid' => $eid, 'lineid' => 0, 'content' => $text,
            ]);
        }

        // Multiselect content: option keys 1, 2, 3 stored as '#1#', '#2#', '#3#'.
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $multifield->id, 'entryid' => $entryone, 'lineid' => 0, 'content' => '#1#',
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $multifield->id, 'entryid' => $entrytwo, 'lineid' => 0, 'content' => '#2#',
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $multifield->id, 'entryid' => $entrythree, 'lineid' => 0, 'content' => '#3#',
        ]);

        return [$dlx, $view, $multifield->id, $entryone, $entrytwo, $entrythree];
    }

    /**
     * A custom-filter search (ANY_OF option key '2' = Opt2) passed as filteroptions
     * must restrict get_csv_content('all') to only the matching entry.
     *
     * @covers ::get_csv_content
     */
    public function test_get_csv_content_respects_custom_filter(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$dlx, $viewrecord, $fieldid, $entryone, $entrytwo, $entrythree] = $this->create_multiselect_fixture();

        // Build a customsearch that matches only entries with Opt2 (key '2').
        $searchfields = [$fieldid => ['AND' => [['', 'ANY_OF', ['2']]]]];

        $view = new csv_view($dlx, $viewrecord->id, ['customsearch' => $searchfields]);
        $content = $view->get_csv_content('all');

        // Should return header + exactly 1 data row.
        $this->assertNotNull($content, 'Expected non-null CSV content');
        $this->assertCount(2, $content, 'Expected header row and one data row (Opt2 entry only)');

        // The data row should be the entry with Opt2.
        $datarow = $content[1];
        $rowtext = implode(' ', array_map('strval', $datarow));
        $this->assertStringContainsString('Entry two', $rowtext);
        $this->assertStringNotContainsString('Entry one', $rowtext);
        $this->assertStringNotContainsString('Entry three', $rowtext);
    }

    /**
     * When a custom filter search is active the base URL must carry a usearch parameter
     * so that derived links (e.g. Export All / Export Page) preserve the filter.
     *
     * This test will FAIL before the fix in base.php and PASS after.
     *
     * @covers ::get_baseurl
     */
    public function test_baseurl_contains_usearch_when_custom_filter_active(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$dlx, $viewrecord, $fieldid] = $this->create_multiselect_fixture();

        $searchfields = [$fieldid => ['AND' => [['', 'ANY_OF', ['2']]]]];

        $view = new csv_view($dlx, $viewrecord->id, ['customsearch' => $searchfields]);
        $url = $view->get_baseurl()->out(false);

        $this->assertStringContainsString(
            'usearch=',
            $url,
            'Export links are built from baseurl; without usearch the custom filter is silently ignored on export.'
        );
    }
}
