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
use mod_datalynx\local\view\datalynxview_patterns;
use mod_datalynx\local\view\manager\grid_view_manager;

/**
 * Tests for the cached view tag parsing and the view link lookup (#289).
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\view\base::set__patterns
 * @covers     \mod_datalynx\datalynx::get_pattern_signature
 * @covers     \mod_datalynx\local\view\datalynxview_patterns
 */
final class view_patterns_cache_test extends advanced_testcase {
    /**
     * Reset the request caches of the view tag lookups.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        datalynx::reset_static_caches();
        datalynxview_patterns::reset_static_caches();
    }

    /**
     * Create a datalynx instance.
     *
     * @return int datalynx id
     */
    private function create_instance(): int {
        $course = $this->getDataGenerator()->create_course();
        return (int) $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id])->id;
    }

    /**
     * Add a text field.
     *
     * @param int $dataid
     * @param string $name
     * @return int field id
     */
    private function create_field(int $dataid, string $name): int {
        global $DB;
        return (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $dataid, 'type' => 'text', 'name' => $name, 'description' => '',
        ]);
    }

    /**
     * Add a grid view.
     *
     * @param int $dataid
     * @param string $name
     * @param string $entrytemplate
     * @return int view id
     */
    private function create_view(int $dataid, string $name, string $entrytemplate = ''): int {
        global $DB;
        return (int) $DB->insert_record('datalynx_views', (object) [
            'dataid' => $dataid, 'type' => 'grid', 'name' => $name, 'description' => '',
            'visible' => 7, 'filter' => 0, 'perpage' => 0, 'groupby' => '',
            'param5' => 0, 'param10' => 0, 'section' => '##entries##', 'param2' => $entrytemplate,
        ]);
    }

    /**
     * Build a view with a fresh instance object, as a new request would.
     *
     * @param int $dataid
     * @param int $viewid
     * @return \mod_datalynx\local\view\base
     */
    private function build_view(int $dataid, int $viewid): \mod_datalynx\local\view\base {
        return (new datalynx($dataid))->get_view('grid', $viewid, false);
    }

    /**
     * Building a view, with and without cached tags, writes nothing to the database.
     */
    public function test_building_a_view_writes_nothing(): void {
        global $DB;

        $dataid = $this->create_instance();
        $this->create_field($dataid, 'Alpha');
        $viewid = $this->create_view($dataid, 'Main', '[[Alpha]]');

        $before = $DB->perf_get_writes();
        $this->build_view($dataid, $viewid);
        $this->build_view($dataid, $viewid);

        $this->assertSame($before, $DB->perf_get_writes());
        $this->assertNull($DB->get_field('datalynx_views', 'patterns', ['id' => $viewid]));
    }

    /**
     * A view built again in a later request takes its tags from the cache instead of parsing.
     */
    public function test_cached_tags_are_reused(): void {
        $dataid = $this->create_instance();
        $alphaid = $this->create_field($dataid, 'Alpha');
        $viewid = $this->create_view($dataid, 'Main', '<div>[[Alpha]]</div>');

        $parsed = $this->build_view($dataid, $viewid)->get__patterns();
        $this->assertSame(['[[Alpha]]'], array_values($parsed['field'][$alphaid]));

        // Mark the cached entry: a later build must return it unchanged rather than parse again.
        $cache = \cache::make('mod_datalynx', 'patterns');
        $entry = $cache->get($viewid);
        $this->assertSame($parsed, $entry['tags']);
        $entry['tags']['field'][$alphaid] = ['[[Alpha]]', 'marker'];
        $cache->set($viewid, $entry);

        $cached = $this->build_view($dataid, $viewid)->get__patterns();
        $this->assertSame(['[[Alpha]]', 'marker'], $cached['field'][$alphaid]);
    }

    /**
     * Changes to fields, views and the template show up in the tags right away.
     */
    public function test_tags_follow_definition_changes(): void {
        global $DB;

        $dataid = $this->create_instance();
        $alphaid = $this->create_field($dataid, 'Alpha');
        $template = '[[Alpha]] [[Beta]] ##viewlink:Target;Go;;##';
        $viewid = $this->create_view($dataid, 'Main', $template);

        $tags = $this->build_view($dataid, $viewid)->get__patterns();
        $this->assertSame(['[[Alpha]]'], array_values($tags['field'][$alphaid]));
        $this->assertNotContains('##viewlink:Target;Go;;##', $tags['view']);

        // A new field and a new view are recognised without touching the template.
        $betaid = $this->create_field($dataid, 'Beta');
        $this->create_view($dataid, 'Target');
        $tags = $this->build_view($dataid, $viewid)->get__patterns();
        $this->assertSame(['[[Beta]]'], array_values($tags['field'][$betaid]));
        $this->assertContains('##viewlink:Target;Go;;##', $tags['view']);

        // A renamed field no longer matches its old tag.
        $DB->set_field('datalynx_fields', 'name', 'Gamma', ['id' => $betaid]);
        $tags = $this->build_view($dataid, $viewid)->get__patterns();
        $this->assertSame([], array_values($tags['field'][$betaid]));

        // An edited template is parsed again.
        $DB->set_field('datalynx_views', 'param2', '[[Gamma]]', ['id' => $viewid]);
        $tags = $this->build_view($dataid, $viewid)->get__patterns();
        $this->assertSame([], array_values($tags['field'][$alphaid]));
        $this->assertSame(['[[Gamma]]'], array_values($tags['field'][$betaid]));
    }

    /**
     * A view link builds the view it points to, not every view of the instance.
     */
    public function test_view_link_builds_only_the_target_view(): void {
        $small = $this->measure_view_link_page(0);
        $large = $this->measure_view_link_page(10);

        $this->assertStringContainsString('view=' . $small['targetid'], $small['html']);
        $this->assertStringContainsString('view=' . $large['targetid'], $large['html']);
        $this->assertLessThanOrEqual($small['reads'] + 2, $large['reads']);
    }

    /**
     * Load a grid page whose entry template links to another view and count the reads.
     *
     * @param int $otherviews number of further views with templates in the instance
     * @return array reads, html of the entry and the id of the target view
     */
    private function measure_view_link_page(int $otherviews): array {
        global $DB, $USER;

        $dataid = $this->create_instance();
        $alphaid = $this->create_field($dataid, 'Alpha');
        $viewid = $this->create_view($dataid, 'Main', '[[Alpha]] ##viewlink:Target;Go;;##');
        $targetid = $this->create_view($dataid, 'Target', '[[Alpha]]');
        for ($i = 1; $i <= $otherviews; $i++) {
            $this->create_view($dataid, "Other$i", "[[Alpha]] ##viewlink:Main;Back;;##");
        }
        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dataid, 'userid' => $USER->id, 'groupid' => 0, 'approved' => 1, 'status' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $alphaid, 'entryid' => $entryid, 'lineid' => 0, 'content' => 'value',
        ]);

        $manager = new grid_view_manager();
        // Warm up the caches every request shares (strings, capabilities, parsed tags).
        $manager->get_browse_payload($dataid, $viewid);
        datalynx::reset_static_caches();
        datalynxview_patterns::reset_static_caches();

        $before = $DB->perf_get_reads();
        $payload = $manager->get_browse_payload($dataid, $viewid);
        $reads = $DB->perf_get_reads() - $before;

        return [
            'reads' => $reads,
            'html' => $payload['groups'][0]['entries'][0]['entryhtml'],
            'targetid' => $targetid,
        ];
    }
}
