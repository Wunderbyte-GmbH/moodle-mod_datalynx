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

namespace mod_datalynx\local;

use advanced_testcase;
use backup;
use backup_controller;
use cache;
use mod_datalynx\datalynx;
use mod_datalynx\local\field\datalynxfield_behavior;
use mod_datalynx\local\field\datalynxfield_layout;
use mod_datalynx\local\field_format\manager as format_manager;
use restore_controller;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * The definition cache: definitions are read once, and every write path makes changes visible.
 *
 * @package    mod_datalynx
 * @category   test
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\definition_cache
 */
final class definition_cache_test extends advanced_testcase {
    /** @var datalynx */
    private datalynx $dlx;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // On PostgreSQL every test runs in a transaction, inside which nothing goes to the shared
        // cache. Without it, these tests exercise the application cache itself.
        $this->preventResetByRollback();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $this->dlx = new datalynx($instance->id);

        $this->add_field('First');
        $this->dlx->get_view('grid')->add((object) ['name' => 'Grid', 'esection' => '##entries##', 'eparam2' => '[[First]]']);
        $this->dlx->get_filter_manager()->save_filter(
            (object) ['id' => 0, 'dataid' => $this->dlx->id(), 'name' => 'Filter', 'description' => '']
        );
        datalynxfield_behavior::insert_behavior($this->behavior_form('Behavior'));
        datalynxfield_layout::insert_renderer($this->layout_form('Layout'));
        format_manager::save_format((object) [
            'dataid' => $this->dlx->id(), 'name' => 'shortdate', 'fieldtype' => 'time', 'settings' => '{}',
        ]);
        $rule = $this->dlx->get_rule_manager()->get_rule('eventnotification');
        $rule->insert_rule((object) ['name' => 'Rule']);
    }

    /**
     * With a warm cache, a fresh instance object reads every kind of definition without a query.
     */
    public function test_warm_cache_reads_without_queries(): void {
        global $DB;

        $this->read_everything(new datalynx($this->dlx->id()));

        $dlx = new datalynx($this->dlx->id());
        $filtermanager = $dlx->get_filter_manager();
        $customfiltermanager = $dlx->get_customfilter_manager();
        $rulemanager = $dlx->get_rule_manager();
        format_manager::reset_static_caches();

        $before = $DB->perf_get_queries();
        $counts = $this->read_everything($dlx, $filtermanager, $customfiltermanager, $rulemanager);
        $this->assertSame(0, $DB->perf_get_queries() - $before, 'no query for definitions');

        $this->assertSame(
            ['fields' => 1, 'views' => 1, 'filters' => 1, 'customfilters' => 0, 'rules' => 1, 'formats' => 1],
            $counts
        );
    }

    /**
     * Records handed out are copies: changing one does not change the cache.
     */
    public function test_records_are_copies(): void {
        $records = definition_cache::get_records($this->dlx->id(), 'fields');
        reset($records)->name = 'Changed';

        $this->assertSame(['First'], array_column(definition_cache::get_records($this->dlx->id(), 'fields'), 'name'));
    }

    /**
     * Every management write path makes its change visible to a new instance object at once.
     */
    public function test_write_paths_invalidate(): void {
        $dataid = $this->dlx->id();
        $names = fn(string $table): array => array_values(array_column(definition_cache::get_records($dataid, $table), 'name'));

        // Fields: add, update, delete.
        $fieldid = $this->add_field('Second');
        $this->assertSame(['First', 'Second'], $names('fields'));
        $field = (new datalynx($dataid))->get_field_from_id($fieldid);
        $field->update_field((object) ['id' => $fieldid, 'name' => 'Renamed']);
        $this->assertSame(['First', 'Renamed'], $names('fields'));
        (new datalynx($dataid))->get_field_from_id($fieldid)->delete_field();
        $this->assertSame(['First'], $names('fields'));

        // Views: add, update, delete.
        $viewid = (new datalynx($dataid))->get_view('grid')->add(
            (object) ['name' => 'Second view', 'esection' => '##entries##', 'eparam2' => '']
        );
        $this->assertSame(['Grid', 'Second view'], $names('views'));
        $view = (new datalynx($dataid))->get_view('grid', $viewid);
        $view->view->name = 'Renamed view';
        $view->update();
        $this->assertSame(['Grid', 'Renamed view'], $names('views'));
        (new datalynx($dataid))->get_view('grid', $viewid)->delete();
        $this->assertSame(['Grid'], $names('views'));

        // Behaviors: insert, update, delete.
        $behaviorid = datalynxfield_behavior::insert_behavior($this->behavior_form('Second behavior'));
        $this->assertSame(['Behavior', 'Second behavior'], $names('behaviors'));
        datalynxfield_behavior::update_behavior($this->behavior_form('Renamed behavior', $behaviorid));
        $this->assertSame(['Behavior', 'Renamed behavior'], $names('behaviors'));
        datalynxfield_behavior::delete_behavior($behaviorid);
        $this->assertSame(['Behavior'], $names('behaviors'));

        // Layouts: insert, update, delete.
        $layoutid = datalynxfield_layout::insert_renderer($this->layout_form('Second layout'));
        $this->assertSame(['Layout', 'Second layout'], $names('renderers'));
        datalynxfield_layout::update_renderer($this->layout_form('Renamed layout', $layoutid));
        $this->assertSame(['Layout', 'Renamed layout'], $names('renderers'));
        datalynxfield_layout::delete_renderer($layoutid);
        $this->assertSame(['Layout'], $names('renderers'));

        // Field formats: save, update, delete; the format objects of this request follow.
        $formatid = format_manager::save_format((object) [
            'dataid' => $dataid, 'name' => 'longdate', 'fieldtype' => 'time', 'settings' => '{}',
        ]);
        $this->assertCount(2, format_manager::get_formats_for_instance($dataid, 'time'));
        format_manager::save_format((object) [
            'id' => $formatid, 'dataid' => $dataid, 'name' => 'otherdate', 'fieldtype' => 'time', 'settings' => '{}',
        ]);
        $this->assertNotNull(format_manager::get_format_by_name($dataid, 'otherdate'));
        format_manager::delete_format($formatid);
        $this->assertSame(['shortdate'], $names('field_formats'));
        $this->assertCount(1, format_manager::get_formats_for_instance($dataid, 'time'));

        // Filters: save new, save changed.
        $filter = (new datalynx($dataid))->get_filter_manager()->save_filter(
            (object) ['id' => 0, 'dataid' => $dataid, 'name' => 'Second filter', 'description' => '']
        );
        $this->assertSame(['Filter', 'Second filter'], $names('filters'));
        $filter->name = 'Renamed filter';
        (new datalynx($dataid))->get_filter_manager()->save_filter($filter);
        $this->assertSame(['Filter', 'Renamed filter'], $names('filters'));

        // Rules: insert, update, delete.
        $rule = (new datalynx($dataid))->get_rule_manager()->get_rule('eventnotification');
        $ruleid = $rule->insert_rule((object) ['name' => 'Second rule']);
        $this->assertSame(['Rule', 'Second rule'], $names('rules'));
        $rule = (new datalynx($dataid))->get_rule_manager()->get_rule_from_id($ruleid);
        $rule->update_rule((object) ['id' => $ruleid, 'name' => 'Renamed rule']);
        $this->assertSame(['Rule', 'Renamed rule'], $names('rules'));
        (new datalynx($dataid))->get_rule_manager()->get_rule_from_id($ruleid)->delete_rule();
        $this->assertSame(['Rule'], $names('rules'));
    }

    /**
     * reset_definition_cache() of an instance object also clears the shared cache.
     */
    public function test_reset_definition_cache_invalidates(): void {
        global $DB;

        $this->assertCount(1, definition_cache::get_records($this->dlx->id(), 'fields'));
        $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $this->dlx->id(), 'type' => 'text', 'name' => 'Direct', 'description' => '',
        ]);
        $this->assertCount(1, definition_cache::get_records($this->dlx->id(), 'fields'), 'a direct write is not seen');

        $this->dlx->reset_definition_cache();
        $this->assertCount(2, definition_cache::get_records($this->dlx->id(), 'fields'));
    }

    /**
     * Definitions read inside a transaction never reach the shared cache, and an entry refilled
     * by another request before the commit is dropped once the transaction is over.
     */
    public function test_transaction_keeps_uncommitted_definitions_out_of_the_cache(): void {
        global $DB;

        $dataid = $this->dlx->id();
        $cache = cache::make('mod_datalynx', 'definitions');
        $this->assertCount(1, definition_cache::get_records($dataid, 'fields'));
        $committed = $cache->get($dataid);
        $this->assertIsArray($committed, 'the first read fills the cache');

        $transaction = $DB->start_delegated_transaction();
        $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $dataid, 'type' => 'text', 'name' => 'Second', 'description' => '',
        ]);
        definition_cache::invalidate($dataid);
        $this->assertCount(2, definition_cache::get_records($dataid, 'fields'), 'the transaction sees its own change');
        $this->assertFalse($cache->get($dataid), 'nothing stored while uncommitted');

        // Another request reads the committed state before this transaction commits.
        $cache->set($dataid, $committed);
        $transaction->allow_commit();

        $this->assertSame(
            ['First', 'Second'],
            array_values(array_column(definition_cache::get_records($dataid, 'fields'), 'name'))
        );
    }

    /**
     * After a rollback, the definitions read are those of the database, not the rolled back ones.
     */
    public function test_transaction_rollback_leaves_no_stale_definitions(): void {
        global $DB;

        $dataid = $this->dlx->id();
        $this->assertCount(1, definition_cache::get_records($dataid, 'fields'));

        $transaction = $DB->start_delegated_transaction();
        $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $dataid, 'type' => 'text', 'name' => 'Uncommitted', 'description' => '',
        ]);
        definition_cache::invalidate($dataid);
        $this->assertCount(2, definition_cache::get_records($dataid, 'fields'));
        try {
            $transaction->rollback(new \moodle_exception('rollback'));
        } catch (\moodle_exception $e) {
            $this->assertSame('rollback', $e->errorcode);
        }

        $this->assertSame(['First'], array_values(array_column(definition_cache::get_records($dataid, 'fields'), 'name')));
    }

    /**
     * A restored instance and an instance deleted and restored again read their own definitions.
     */
    public function test_restore_reads_restored_definitions(): void {
        global $DB, $USER;

        $this->read_everything($this->dlx);

        $bc = new backup_controller(
            backup::TYPE_1ACTIVITY,
            $this->dlx->cm->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );
        $bc->execute_plan();
        $backupid = $bc->get_backupid();
        $bc->destroy();

        $target = $this->getDataGenerator()->create_course();
        $rc = new restore_controller(
            $backupid,
            $target->id,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id,
            backup::TARGET_EXISTING_ADDING
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $dataid = (int) $DB->get_field('datalynx', 'id', ['course' => $target->id], MUST_EXIST);
        $dlx = new datalynx($dataid);
        $this->assertSame(['First'], array_values(array_column(definition_cache::get_records($dataid, 'fields'), 'name')));
        $this->assertSame('First', $dlx->get_field_by_name('First')->name());
        $this->assertSame('Behavior', $dlx->get_behavior_by_name('Behavior')->get_name());
        $this->assertSame(['Rule'], array_values(array_column(definition_cache::get_records($dataid, 'rules'), 'name')));
    }

    /**
     * Deleting the instance forgets its definitions.
     */
    public function test_instance_delete_invalidates(): void {
        $dataid = $this->dlx->id();
        $this->assertNotEmpty(definition_cache::get_records($dataid, 'fields'));

        course_delete_module($this->dlx->cm->id);

        $this->assertSame([], definition_cache::get_records($dataid, 'fields'));
    }

    /**
     * Only the definition tables can be asked for.
     */
    public function test_unknown_table(): void {
        $this->expectException(\coding_exception::class);
        definition_cache::get_records($this->dlx->id(), 'entries');
    }

    /**
     * Read every kind of definition through the code paths the pages use.
     *
     * @param datalynx $dlx
     * @param mixed $filtermanager the instance's filter manager, if already built
     * @param mixed $customfiltermanager the instance's custom filter manager, if already built
     * @param mixed $rulemanager the instance's rule manager, if already built
     * @return int[] number of records found per kind
     */
    private function read_everything(
        datalynx $dlx,
        $filtermanager = null,
        $customfiltermanager = null,
        $rulemanager = null
    ): array {
        $dlx->get_behavior_by_name('Behavior');
        $dlx->get_layout_by_name('Layout');
        $dlx->get_pattern_signature();
        return [
            'fields' => count(array_filter($dlx->get_fields(null, false, true), fn($field) => !$field::is_internal())),
            'views' => count($dlx->get_view_records(true)),
            'filters' => count(($filtermanager ?? $dlx->get_filter_manager())->get_filters(null, false, true) ?: []),
            'customfilters' => count(
                ($customfiltermanager ?? $dlx->get_customfilter_manager())->get_filters(null, false, true) ?: []
            ),
            'rules' => count(($rulemanager ?? $dlx->get_rule_manager())->get_rules(null, false, true)),
            'formats' => count(format_manager::get_formats_for_instance($dlx->id(), 'time')),
        ];
    }

    /**
     * Add a text field through the field API.
     *
     * @param string $name
     * @return int
     */
    private function add_field(string $name): int {
        $field = (new datalynx($this->dlx->id()))->get_field('text');
        return (int) $field->insert_field((object) ['name' => $name]);
    }

    /**
     * Form data of a behavior.
     *
     * @param string $name
     * @param int $id 0 for a new one
     * @return \stdClass
     */
    private function behavior_form(string $name, int $id = 0): \stdClass {
        return (object) [
            'id' => $id, 'd' => $this->dlx->id(), 'name' => $name, 'description' => '',
            'visibletopermission' => [datalynx::PERMISSION_MANAGER], 'visibletouser' => [], 'visibletoteammember' => [],
            'editableby' => [datalynx::PERMISSION_MANAGER], 'editable' => 1, 'required' => 0,
            'editableafterfinal' => 0, 'conditions' => ['match' => 'all', 'rules' => []],
        ];
    }

    /**
     * Form data of a field layout.
     *
     * @param string $name
     * @param int $id 0 for a new one
     * @return \stdClass
     */
    private function layout_form(string $name, int $id = 0): \stdClass {
        return (object) [
            'id' => $id, 'd' => $this->dlx->id(), 'name' => $name, 'description' => '',
            'notvisibletemplate' => datalynxfield_layout::NOT_VISIBLE_SHOW_NOTHING,
            'displaytemplate' => datalynxfield_layout::DISPLAY_MODE_TEMPLATE_NONE,
            'novaluetemplate' => datalynxfield_layout::NO_VALUE_SHOW_NOTHING,
            'edittemplate' => datalynxfield_layout::EDIT_MODE_TEMPLATE_NONE,
            'noteditabletemplate' => datalynxfield_layout::NOT_EDITABLE_SHOW_NOTHING,
        ];
    }
}
