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

namespace mod_datalynx;

use advanced_testcase;
use backup;
use backup_controller;
use restore_controller;
use restore_datalynx_activity_task;
use xmldb_index;
use xmldb_table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/db/upgrade.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Unique behavior and field layout names per instance, and the composite indexes (#294, #302).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::mod_datalynx_upgrade_unique_definition_names
 * @covers ::mod_datalynx_upgrade_composite_indexes
 * @covers \restore_datalynx_activity_structure_step
 */
final class definition_names_test extends advanced_testcase {
    /**
     * Set up the fixture.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * The definition tables with a unique name per instance.
     *
     * @return array
     */
    public static function definition_tables_provider(): array {
        return [
            'behaviors' => ['datalynx_behaviors'],
            'field layouts' => ['datalynx_renderers'],
        ];
    }

    /**
     * Insert a behavior or field layout.
     *
     * @param string $table datalynx_behaviors or datalynx_renderers
     * @param int $dataid The instance id.
     * @param string $name The name.
     * @return int The record id.
     */
    private function insert_definition(string $table, int $dataid, string $name): int {
        global $DB;
        $record = (object) ['dataid' => $dataid, 'name' => $name, 'description' => ''];
        if ($table === 'datalynx_renderers') {
            $record->type = '';
        }
        $id = (int) $DB->insert_record($table, $record);
        \mod_datalynx\local\definition_cache::invalidate($dataid);
        return $id;
    }

    /**
     * The upgrade keeps the name of the record in use and numbers the others, then adds the index.
     *
     * @dataProvider definition_tables_provider
     * @param string $table datalynx_behaviors or datalynx_renderers
     */
    public function test_upgrade_renames_duplicate_names(string $table): void {
        global $DB;
        $dbman = $DB->get_manager();
        $xmldbtable = new xmldb_table($table);
        $index = new xmldb_index('dataid-name', XMLDB_INDEX_UNIQUE, ['dataid', 'name']);

        $course = $this->getDataGenerator()->create_course();
        $one = (int) $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id])->id;
        $two = (int) $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id])->id;

        // The duplicates of sites from before the index can only be inserted without it.
        $dbman->drop_index($xmldbtable, $index);
        try {
            $keep = $this->insert_definition($table, $one, 'Main');
            $taken = $this->insert_definition($table, $one, 'Main (2)');
            $second = $this->insert_definition($table, $one, 'Main');
            $third = $this->insert_definition($table, $one, 'Main');
            // A duplicate only where the collation ignores case (e.g. utf8mb4_unicode_ci), as for the index.
            $othercase = $this->insert_definition($table, $one, 'MAIN');
            $caseinsensitive = $DB->count_records($table, ['dataid' => $one, 'name' => 'MAIN']) > 1;
            $other = $this->insert_definition($table, $two, 'Main');
            $long = str_repeat('x', 255);
            $longkeep = $this->insert_definition($table, $one, $long);
            $longcopy = $this->insert_definition($table, $one, $long);

            mod_datalynx_upgrade_unique_definition_names($table);
        } finally {
            mod_datalynx_upgrade_composite_indexes();
        }

        $names = $DB->get_records_menu($table, null, 'id ASC', 'id, name');
        $this->assertSame('Main', $names[$keep]);
        $this->assertSame('Main (2)', $names[$taken]);
        $this->assertSame('Main (3)', $names[$second]);
        $this->assertSame('Main (4)', $names[$third]);
        $this->assertSame('Main', $names[$other], 'Names only need to be unique within an instance.');
        $this->assertSame($caseinsensitive ? 'MAIN (5)' : 'MAIN', $names[$othercase]);
        $this->assertSame($long, $names[$longkeep]);
        $this->assertSame(str_repeat('x', 251) . ' (2)', $names[$longcopy]);

        $this->assertTrue($dbman->index_exists($xmldbtable, $index));
        $this->expectException(\dml_write_exception::class);
        $this->insert_definition($table, $one, 'Main');
    }

    /**
     * Adding the indexes again (e.g. by a repair step) changes nothing.
     */
    public function test_composite_indexes_are_idempotent(): void {
        global $DB;
        $dbman = $DB->get_manager();

        mod_datalynx_upgrade_composite_indexes();

        $expected = [
            'datalynx_contents' => ['entryid-fieldid', XMLDB_INDEX_NOTUNIQUE, ['entryid', 'fieldid']],
            'datalynx_entries' => ['dataid-userid', XMLDB_INDEX_NOTUNIQUE, ['dataid', 'userid']],
            'datalynx_behaviors' => ['dataid-name', XMLDB_INDEX_UNIQUE, ['dataid', 'name']],
            'datalynx_renderers' => ['dataid-name', XMLDB_INDEX_UNIQUE, ['dataid', 'name']],
        ];
        foreach ($expected as $table => [$name, $type, $fields]) {
            $this->assertTrue($dbman->index_exists(new xmldb_table($table), new xmldb_index($name, $type, $fields)));
        }
        $this->assertTrue($dbman->index_exists(
            new xmldb_table('datalynx_entries'),
            new xmldb_index('dataid-status', XMLDB_INDEX_NOTUNIQUE, ['dataid', 'status'])
        ));
        $this->assertFalse($dbman->field_exists('datalynx_contents', 'lineid'));
    }

    /**
     * Applying a preset to an instance that has its behaviors and layouts already keeps those.
     */
    public function test_preset_applied_again_keeps_existing_definitions(): void {
        global $DB, $USER;
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);
        $behaviorid = $this->insert_definition('datalynx_behaviors', $dlx->id(), 'Shared behavior');
        $layoutid = $this->insert_definition('datalynx_renderers', $dlx->id(), 'Shared layout');

        // A preset is a backup of the instance without user data.
        $bc = new backup_controller(
            backup::TYPE_1ACTIVITY,
            $dlx->cm->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(false);
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();
        $backupid = 'definition_names_preset_test';
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), make_backup_temp_directory($backupid));

        // Restore it into the same instance, as preset_manager::apply_preset() does.
        $rc = new restore_controller(
            $backupid,
            $course->id,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_CURRENT_ADDING
        );
        $this->assertTrue($rc->execute_precheck());
        foreach ($rc->get_plan()->get_tasks() as $task) {
            if ($task instanceof restore_datalynx_activity_task) {
                $task->set_activityid($dlx->id());
                $task->set_moduleid($dlx->cm->id);
                $task->set_contextid($dlx->context->id);
            }
        }
        $rc->execute_plan();
        $rc->destroy();

        $this->assertSame(
            [$behaviorid => 'Shared behavior'],
            $DB->get_records_menu('datalynx_behaviors', ['dataid' => $dlx->id()], '', 'id, name')
        );
        $this->assertSame(
            [$layoutid => 'Shared layout'],
            $DB->get_records_menu('datalynx_renderers', ['dataid' => $dlx->id()], '', 'id, name')
        );
    }
}
