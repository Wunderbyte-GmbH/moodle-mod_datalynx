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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use advanced_testcase;
use backup;
use backup_controller;
use mod_datalynx\local\field_format\manager as format_manager;
use mod_datalynx\task\migrate_tags_task;
use restore_controller;

/**
 * Restore knows the datalynx version of a backup and migrates only what the backup needs
 * (mod_datalynx#313).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \restore_datalynx_activity_task
 * @covers     \restore_datalynx_activity_structure_step
 * @covers     \backup_datalynx_activity_structure_step
 * @covers     \mod_datalynx\task\migrate_tags_task
 */
final class backup_version_restore_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var int[] grid view ids by wrapper */
    private array $grids = [];

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        [$this->course, $this->dlx] = $this->create_mobile_instance();
        $this->add_mobile_field($this->dlx, 'time', 'mytime');
        foreach (array_keys(\datalynxview_grid\view::ENTRY_WRAPPERS) as $wrapper) {
            $this->grids[$wrapper] = $this->add_mobile_view($this->dlx, 'grid', "Grid $wrapper", '[[mytime]]', [
                'param3' => $wrapper,
                'param4' => $wrapper === 'custom' ? 'my-card' : '',
            ]);
        }
        $this->grids['empty'] = $this->add_mobile_view($this->dlx, 'grid', 'Grid empty', '[[mytime]]', ['param3' => '']);
        $this->grids['unknown'] = $this->add_mobile_view($this->dlx, 'grid', 'Grid unknown', '[[mytime]]', [
            'param3' => 'something-old', 'param4' => 'x',
        ]);
        $this->add_mobile_view($this->dlx, 'grid', 'Legacy tags', 'Date: [[mytime:legacyformat]]', [
            'section' => 'Go #{{viewlink:Other;Link;;}}# ##entries##',
        ]);
    }

    /**
     * A backup of the current version keeps every wrapper, also an empty or unknown one, and runs
     * no migration for older backups.
     */
    public function test_current_backup_keeps_everything(): void {
        $dataid = $this->backup_and_restore();

        foreach ($this->grids as $key => $viewid) {
            $this->assertSame($this->wrapper($this->dlx->id(), $viewid), $this->wrapper($dataid, $viewid, $key), $key);
        }
        $this->assertNull(format_manager::get_format_by_name($dataid, 'legacyformat'), 'no format migration');
        $this->assertStringContainsString('#{{viewlink:Other', $this->section($dataid), 'no tag migration');
    }

    /**
     * A backup without the version value keeps a wrapper it names and gets "entry" otherwise;
     * the migrations for older backups run, and change only what is old.
     */
    public function test_backup_without_version(): void {
        $dataid = $this->backup_and_restore(fn(string $xml): string => $this->set_version($xml, ''));

        foreach (array_keys(\datalynxview_grid\view::ENTRY_WRAPPERS) as $wrapper) {
            $expected = [$wrapper, $wrapper === 'custom' ? 'my-card' : ''];
            $this->assertSame($expected, $this->wrapper($dataid, $this->grids[$wrapper], $wrapper));
        }
        $this->assertSame(['entry', ''], $this->wrapper($dataid, $this->grids['empty'], 'empty'));
        $this->assertSame(['entry', ''], $this->wrapper($dataid, $this->grids['unknown'], 'unknown'));
        $this->assertNotNull(format_manager::get_format_by_name($dataid, 'legacyformat'));
        $this->assertStringContainsString('##viewlink:Other;Link;;##', $this->section($dataid));
    }

    /**
     * A backup from before the view link tags and field formats changed gets both migrations.
     */
    public function test_old_backup_is_migrated(): void {
        $dataid = $this->backup_and_restore(
            fn(string $xml): string => $this->set_version($xml, '<pluginversion>2026030000</pluginversion>')
        );

        $this->assertNotNull(format_manager::get_format_by_name($dataid, 'legacyformat'));
        $this->assertStringContainsString('##viewlink:Other;Link;;##', $this->section($dataid));
        $this->assertSame(['col-12', ''], $this->wrapper($dataid, $this->grids['col-12'], 'col-12'));
    }

    /**
     * The migrations change nothing on current data.
     */
    public function test_migrations_are_idempotent(): void {
        global $DB;

        migrate_tags_task::migrate_view_link_tags($this->dlx->id());
        format_manager::auto_create_formats_from_templates($this->dlx->id());
        $views = $DB->get_records('datalynx_views', ['dataid' => $this->dlx->id()]);
        $formats = $DB->count_records('datalynx_field_formats', ['dataid' => $this->dlx->id()]);

        $this->assertSame(0, migrate_tags_task::migrate_view_link_tags($this->dlx->id()));
        format_manager::auto_create_formats_from_templates($this->dlx->id());
        $this->assertEquals($views, $DB->get_records('datalynx_views', ['dataid' => $this->dlx->id()]));
        $this->assertSame($formats, $DB->count_records('datalynx_field_formats', ['dataid' => $this->dlx->id()]));
    }

    /**
     * Duplicating the activity keeps the grid wrappers.
     */
    public function test_duplicate_keeps_wrappers(): void {
        global $DB;

        $newcm = duplicate_module($this->course, get_fast_modinfo($this->course)->get_cm($this->dlx->cm->id));
        $dataid = (int) $newcm->instance;

        foreach ($this->grids as $key => $viewid) {
            $this->assertSame($this->wrapper($this->dlx->id(), $viewid), $this->wrapper($dataid, $viewid, $key), $key);
        }
        $this->assertSame(count($this->grids) + 1, $DB->count_records('datalynx_views', ['dataid' => $dataid]));
    }

    /**
     * The values stored serialized (filter sort and search, behaviour permissions) come through a
     * backup and restore in the same format, with the field ids mapped to the restored fields.
     */
    public function test_serialized_values_are_restored(): void {
        global $DB;

        $fieldid = (int) $DB->get_field('datalynx_fields', 'id', ['dataid' => $this->dlx->id(), 'name' => 'mytime']);
        $search = ['AND' => [['', '>', 5], ['NOT', 'ANY_OF', [1, 2]]]];
        $DB->insert_record('datalynx_filters', (object) [
            'dataid' => $this->dlx->id(), 'name' => 'Stored', 'description' => '', 'visible' => 1,
            'perpage' => 0, 'selection' => 0, 'groupby' => '', 'search' => '',
            'customsort' => serialize([$fieldid => 1, 'timecreated' => 0]),
            'customsearch' => serialize([$fieldid => $search, 'approve' => ['OR' => [['', '', 1]]]]),
        ]);
        $visibleto = ['permissions' => [1, 2], 'users' => [], 'teammember' => []];
        $DB->insert_record('datalynx_behaviors', (object) [
            'dataid' => $this->dlx->id(), 'name' => 'Stored', 'description' => '',
            'visibleto' => serialize($visibleto), 'editableby' => serialize([1]), 'required' => 0,
        ]);

        $dataid = $this->backup_and_restore();

        $newfieldid = (int) $DB->get_field('datalynx_fields', 'id', ['dataid' => $dataid, 'name' => 'mytime']);
        $this->assertNotSame($fieldid, $newfieldid);
        $filter = $DB->get_record('datalynx_filters', ['dataid' => $dataid, 'name' => 'Stored'], '*', MUST_EXIST);
        $this->assertSame(serialize([$newfieldid => 1, 'timecreated' => 0]), $filter->customsort);
        $this->assertSame(
            serialize([$newfieldid => $search, 'approve' => ['OR' => [['', '', 1]]]]),
            $filter->customsearch
        );
        $behavior = $DB->get_record('datalynx_behaviors', ['dataid' => $dataid, 'name' => 'Stored'], '*', MUST_EXIST);
        $this->assertSame(serialize($visibleto), $behavior->visibleto);
        $this->assertSame(serialize([1]), $behavior->editableby);
    }

    /**
     * Back up the activity, optionally change its datalynx.xml, and restore it into a new course.
     *
     * @param callable|null $editxml changes the backup's datalynx.xml
     * @return int the restored instance's id
     */
    private function backup_and_restore(?callable $editxml = null): int {
        global $DB, $USER;

        $bc = new backup_controller(
            backup::TYPE_1ACTIVITY,
            $this->dlx->cm->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id
        );
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();
        $backupid = 'backup_version_restore_test';
        $dir = make_backup_temp_directory($backupid);
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $dir);
        $xmlfile = glob("$dir/activities/datalynx_*/datalynx.xml")[0];
        $xml = file_get_contents($xmlfile);
        $this->assertMatchesRegularExpression('~<pluginversion>\d+</pluginversion>~', $xml, 'the backup says its version');
        if ($editxml) {
            file_put_contents($xmlfile, $editxml($xml));
        }

        $target = $this->getDataGenerator()->create_course();
        $rc = new restore_controller(
            $backupid,
            $target->id,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_EXISTING_ADDING
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return (int) $DB->get_field('datalynx', 'id', ['course' => $target->id], MUST_EXIST);
    }

    /**
     * The wrapper (param3, param4) of a grid view, found in another instance by its name.
     *
     * @param int $dataid
     * @param int $viewid the original view
     * @param string $key for messages
     * @return array
     */
    private function wrapper(int $dataid, int $viewid, string $key = ''): array {
        global $DB;
        $name = $DB->get_field('datalynx_views', 'name', ['id' => $viewid]);
        $view = $DB->get_record('datalynx_views', ['dataid' => $dataid, 'name' => $name], 'param3, param4', MUST_EXIST);
        return [(string) $view->param3, (string) $view->param4];
    }

    /**
     * Replace the version element of a backup's datalynx.xml.
     *
     * @param string $xml
     * @param string $element the new element, '' to remove it
     * @return string
     */
    private function set_version(string $xml, string $element): string {
        return preg_replace('~<pluginversion>\d+</pluginversion>~', $element, $xml);
    }

    /**
     * The section of the view with the legacy tags.
     *
     * @param int $dataid
     * @return string
     */
    private function section(int $dataid): string {
        global $DB;
        return (string) $DB->get_field('datalynx_views', 'section', ['dataid' => $dataid, 'name' => 'Legacy tags']);
    }
}
