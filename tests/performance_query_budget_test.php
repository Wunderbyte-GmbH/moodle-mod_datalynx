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
use dml_missing_record_exception;
use mod_datalynx\local\field\datalynxfield_behavior;
use mod_datalynx\local\view\manager\grid_view_manager;
use mod_datalynx\local\view\manager\tabular_view_manager;

/**
 * Query budgets for loading the entries of a view, so that N+1 patterns show up in CI.
 *
 * The browse payload of a view (the web service request that loads the entries) must cost a
 * constant number of queries per entry, independent of the number of field tags, behaviors and
 * layouts, and must not depend on fields the view does not show.
 *
 * @package    mod_datalynx
 * @category   test
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class performance_query_budget_test extends advanced_testcase {
    /** @var int Queries an additional entry may cost in a browse payload. */
    private const PER_ENTRY_BUDGET = 2;

    /** @var int Entries on the small page. */
    private const FEW = 5;

    /** @var int Entries on the large page. */
    private const MANY = 25;

    /**
     * Create an instance with text fields, a named behavior and layout, and entries by a student.
     *
     * The current user is set to that student.
     *
     * @param int $fieldcount number of text fields
     * @param int $entrycount number of entries, each with a value in every field
     * @return array [datalynx, string[] field names]
     */
    private function create_instance(int $fieldcount, int $entrycount): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $fieldids = [];
        for ($i = 1; $i <= $fieldcount; $i++) {
            $name = sprintf('F%02d', $i);
            $fieldids[$name] = (int) $DB->insert_record('datalynx_fields', (object) [
                'dataid' => $dlx->id(), 'type' => 'text', 'name' => $name, 'description' => '',
            ]);
        }

        $behavior = datalynxfield_behavior::form_to_db((object) [
            'id' => 0, 'd' => $dlx->id(), 'name' => 'B1', 'description' => '',
            'visibletopermission' => [
                datalynx::PERMISSION_MANAGER, datalynx::PERMISSION_TEACHER,
                datalynx::PERMISSION_STUDENT, datalynx::PERMISSION_AUTHOR,
            ],
            'visibletouser' => [], 'visibletoteammember' => [],
            'editableby' => [datalynx::PERMISSION_MANAGER], 'editable' => 1, 'required' => 0,
            'editableafterfinal' => 0, 'conditions' => ['match' => 'all', 'rules' => []],
        ]);
        $DB->insert_record('datalynx_behaviors', $behavior);
        $DB->insert_record('datalynx_renderers', (object) [
            'dataid' => $dlx->id(), 'type' => '', 'name' => 'L1', 'description' => '',
            'notvisibletemplate' => '___0___', 'displaytemplate' => '<span class="l1">#value</span>',
            'novaluetemplate' => '___0___', 'edittemplate' => '___0___', 'noteditabletemplate' => '___1___',
        ]);

        for ($e = 1; $e <= $entrycount; $e++) {
            $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
                'dataid' => $dlx->id(), 'userid' => $student->id, 'groupid' => 0, 'approved' => 1,
                'status' => 0, 'timecreated' => time(), 'timemodified' => time(),
            ]);
            foreach ($fieldids as $name => $fieldid) {
                $DB->insert_record('datalynx_contents', (object) [
                    'fieldid' => $fieldid, 'entryid' => $entryid, 'lineid' => 0, 'content' => "$name-$e",
                ]);
            }
        }

        $this->setUser($student);
        return [$dlx, array_keys($fieldids)];
    }

    /**
     * Add a view to the instance.
     *
     * @param datalynx $dlx
     * @param string $type grid or tabular
     * @param string $entrytemplate the grid entry template (param2)
     * @return int view id
     */
    private function create_view(datalynx $dlx, string $type, string $entrytemplate = ''): int {
        global $DB;
        return (int) $DB->insert_record('datalynx_views', (object) [
            'dataid' => $dlx->id(), 'type' => $type, 'name' => $type, 'description' => '',
            'visible' => 7, 'filter' => 0, 'perpage' => 0, 'groupby' => '',
            'param5' => 0, 'param10' => 0, 'section' => '', 'param2' => $entrytemplate,
        ]);
    }

    /**
     * Run the callback and count the database reads it makes.
     *
     * @param callable $callback
     * @return array [int reads, mixed result]
     */
    private function count_reads(callable $callback): array {
        global $DB;
        $before = $DB->perf_get_reads();
        $result = $callback();
        return [$DB->perf_get_reads() - $before, $result];
    }

    /**
     * Load one page of entries of a view and count the reads, after a warm-up request that fills
     * the caches every request shares (strings, capabilities, filters).
     *
     * @param object $manager a view manager
     * @param datalynx $dlx
     * @param int $viewid
     * @param int $perpage
     * @return array [int reads, array payload]
     */
    private function browse(object $manager, datalynx $dlx, int $viewid, int $perpage): array {
        $manager->get_browse_payload($dlx->id(), $viewid, ['perpage' => 1]);
        return $this->count_reads(fn() => $manager->get_browse_payload($dlx->id(), $viewid, ['perpage' => $perpage]));
    }

    /**
     * Entry template with a [[field|behavior|layout]] tag for each of the given fields.
     *
     * @param string[] $fieldnames
     * @return string
     */
    private function tagged_template(array $fieldnames): string {
        return implode(' ', array_map(fn($name) => "<div>[[$name|B1|L1]]</div>", $fieldnames));
    }

    /**
     * A grid entry template with behavior and layout tags costs a constant number of queries per entry.
     *
     * @covers \mod_datalynx\local\view\manager\grid_view_manager::get_browse_payload
     * @covers \mod_datalynx\datalynx::get_behavior_by_name
     * @covers \mod_datalynx\datalynx::get_layout_by_name
     */
    public function test_grid_entry_template_queries_per_entry_are_constant(): void {
        $this->resetAfterTest();
        [$dlx, $fieldnames] = $this->create_instance(12, self::MANY);
        $viewid = $this->create_view($dlx, 'grid', $this->tagged_template($fieldnames));

        $manager = new grid_view_manager();
        [$fewreads, $few] = $this->browse($manager, $dlx, $viewid, self::FEW);
        [$manyreads, $many] = $this->browse($manager, $dlx, $viewid, self::MANY);

        $this->assertCount(self::FEW, $few['groups'][0]['entries']);
        $this->assertCount(self::MANY, $many['groups'][0]['entries']);
        $entry = $many['groups'][0]['entries'][0];
        $this->assertStringContainsString('<span class="l1">F12-', $entry['entryhtml']);
        $this->assertSame([], $entry['fields'], 'Field values are not built when the entry template is used.');

        $perentry = ($manyreads - $fewreads) / (self::MANY - self::FEW);
        $this->assertLessThanOrEqual(
            self::PER_ENTRY_BUDGET,
            $perentry,
            "Each additional entry costs $perentry queries ($fewreads reads for " . self::FEW .
                " entries, $manyreads for " . self::MANY . ')'
        );
    }

    /**
     * Fields the entry template does not show must not add queries.
     *
     * @covers \mod_datalynx\local\view\manager\grid_view_manager::get_browse_payload
     */
    public function test_grid_entry_template_ignores_unused_fields(): void {
        $this->resetAfterTest();

        $manager = new grid_view_manager();
        // Each instance has its own course and student, so measure each right after creating it.
        [$small, $smallnames] = $this->create_instance(2, self::FEW);
        $smallviewid = $this->create_view($small, 'grid', $this->tagged_template($smallnames));
        [$smallreads] = $this->browse($manager, $small, $smallviewid, self::FEW);

        [$large, $largenames] = $this->create_instance(40, self::FEW);
        $largeviewid = $this->create_view($large, 'grid', $this->tagged_template(array_slice($largenames, 0, 2)));
        [$largereads] = $this->browse($manager, $large, $largeviewid, self::FEW);

        // 38 more fields may cost a few queries in total (definitions are loaded per instance), but
        // nothing per field and entry.
        $this->assertLessThanOrEqual($smallreads + 10, $largereads, "$smallreads reads with 2 fields, $largereads with 40");
    }

    /**
     * The generic field list (grid without entry template) and the tabular view stay constant per entry.
     *
     * @dataProvider generic_view_provider
     * @covers \mod_datalynx\local\view\manager\grid_view_manager::get_browse_payload
     * @covers \mod_datalynx\local\view\manager\tabular_view_manager::get_browse_payload
     * @param string $type view type
     * @param string $managerclass
     */
    public function test_generic_views_queries_per_entry_are_constant(string $type, string $managerclass): void {
        $this->resetAfterTest();
        [$dlx] = $this->create_instance(12, self::MANY);
        $viewid = $this->create_view($dlx, $type);

        $manager = new $managerclass();
        [$fewreads] = $this->browse($manager, $dlx, $viewid, self::FEW);
        [$manyreads] = $this->browse($manager, $dlx, $viewid, self::MANY);

        $perentry = ($manyreads - $fewreads) / (self::MANY - self::FEW);
        $this->assertLessThanOrEqual(
            self::PER_ENTRY_BUDGET,
            $perentry,
            "Each additional $type entry costs $perentry queries ($fewreads / $manyreads reads)"
        );
    }

    /**
     * View types rendered by the generic field list.
     *
     * @return array
     */
    public static function generic_view_provider(): array {
        return [
            'grid without entry template' => ['grid', grid_view_manager::class],
            'tabular' => ['tabular', tabular_view_manager::class],
        ];
    }

    /**
     * Behaviors, layouts and permissions are read once per instance.
     *
     * @covers \mod_datalynx\datalynx::get_behavior_by_name
     * @covers \mod_datalynx\datalynx::get_layout_by_name
     * @covers \mod_datalynx\datalynx::get_user_datalynx_permissions
     */
    public function test_definitions_are_loaded_once_per_instance(): void {
        $this->resetAfterTest();
        [$dlx] = $this->create_instance(1, 0);

        [$reads, $behavior] = $this->count_reads(fn() => $dlx->get_behavior_by_name('B1'));
        $this->assertSame('B1', $behavior->get_name());
        $this->assertSame(1, $reads);
        [$reads, $layout] = $this->count_reads(fn() => $dlx->get_layout_by_name('L1'));
        $this->assertSame('L1', $layout->get_name());
        $this->assertSame(1, $reads);

        [$reads] = $this->count_reads(function () use ($dlx, $behavior, $layout) {
            $this->assertSame($behavior, $dlx->get_behavior_by_name('B1'));
            $this->assertSame($layout, $dlx->get_layout_by_name('L1'));
        });
        $this->assertSame(0, $reads);

        $permissions = $dlx->get_user_datalynx_permissions(0, 'view');
        [$reads, $again] = $this->count_reads(fn() => $dlx->get_user_datalynx_permissions(0, 'view'));
        $this->assertSame($permissions, $again);
        $this->assertSame(0, $reads);

        // Unknown names behave as before: no behavior, and a missing layout is an error.
        $this->assertFalse($dlx->get_behavior_by_name('nosuchbehavior'));
        $this->expectException(dml_missing_record_exception::class);
        $dlx->get_layout_by_name('nosuchlayout');
    }

    /**
     * Changes become visible after resetting the instance's definition cache.
     *
     * @covers \mod_datalynx\datalynx::reset_definition_cache
     */
    public function test_reset_definition_cache(): void {
        global $DB;
        $this->resetAfterTest();
        [$dlx] = $this->create_instance(1, 0);

        $this->assertFalse($dlx->get_behavior_by_name('B2'));
        $record = $DB->get_record('datalynx_behaviors', ['dataid' => $dlx->id(), 'name' => 'B1']);
        $record->name = 'B2';
        $DB->update_record('datalynx_behaviors', $record);

        $this->assertFalse($dlx->get_behavior_by_name('B2'));
        $dlx->reset_definition_cache();
        $this->assertSame('B2', $dlx->get_behavior_by_name('B2')->get_name());
    }
}
