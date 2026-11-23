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
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use advanced_testcase;
use backup;
use backup_controller;
use datalynxfield_fieldgroup\field as fieldgroup_field;
use restore_controller;

/**
 * The stored list of the subfields of a fieldgroup: "fieldid" or "fieldid:format|behavior|layout".
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxfield_fieldgroup\field
 * @covers     \restore_datalynx_activity_structure_step
 * @covers     \restore_datalynx_activity_task
 */
final class fieldgroup_subfield_list_test extends advanced_testcase {
    use mobile_test_trait;

    /**
     * Old lists (numbers, empty slots) and modifiers with trailing separators come out in the
     * stored form; entries without a field id are dropped.
     */
    public function test_normalise_subfields(): void {
        $this->assertSame(['5', '6'], fieldgroup_field::normalise_subfields([5, '6', '', '', null]));
        $this->assertSame(
            ['5', '6:|beh|lay', '7:fmt'],
            fieldgroup_field::normalise_subfields(['5:||', '6:|beh|lay', '7:fmt||', ':|beh|lay', '0', 'x:fmt'])
        );
        $this->assertSame(['50:|beh'], fieldgroup_field::normalise_subfields(['5:|beh', '6'], fn($id) => $id === 5 ? 50 : 0));
    }

    /**
     * The subfield ids come without their modifiers, so a subfield used with a behavior or layout
     * is found too, e.g. when it is about to be deleted.
     */
    public function test_subfield_ids_ignore_modifiers(): void {
        $this->resetAfterTest();
        [, $dlx] = $this->create_mobile_instance();
        $textid = $this->add_mobile_field($dlx, 'text', 'Text');
        $numberid = $this->add_mobile_field($dlx, 'number', 'Number');
        $groupid = $this->add_mobile_field($dlx, 'fieldgroup', 'Group', [
            'param1' => json_encode(["$textid:|beh|lay", (string) $numberid]), 'param2' => '3',
        ]);

        $this->assertSame([$textid, $numberid], $dlx->get_field_from_id($groupid)->get_subfield_ids());
    }

    /**
     * The upgrade and restore store every list in the current form and drop entries that name no
     * field of the instance.
     */
    public function test_normalize_stored_subfields(): void {
        global $DB;

        $this->resetAfterTest();
        [, $dlx] = $this->create_mobile_instance();
        $textid = $this->add_mobile_field($dlx, 'text', 'Text');
        $numberid = $this->add_mobile_field($dlx, 'number', 'Number');
        [, $other] = $this->create_mobile_instance();
        $elsewhere = $this->add_mobile_field($other, 'text', 'Elsewhere');
        $old = $this->add_mobile_field($dlx, 'fieldgroup', 'Old', ['param1' => json_encode([$textid, $numberid])]);
        $slots = $this->add_mobile_field($dlx, 'fieldgroup', 'Slots', [
            'param1' => json_encode([(string) $textid, '', '', (string) $elsewhere, '999999']),
        ]);
        $current = $this->add_mobile_field($dlx, 'fieldgroup', 'Current', [
            'param1' => json_encode(["$textid:||", "$numberid:|beh|lay", ':|beh|lay']),
        ]);

        $this->assertSame(3, fieldgroup_field::normalize_stored_subfields());
        $this->assertSame(0, fieldgroup_field::normalize_stored_subfields(), 'idempotent');

        $this->assertSame(json_encode(["$textid", "$numberid"]), $DB->get_field('datalynx_fields', 'param1', ['id' => $old]));
        $this->assertSame(json_encode(["$textid"]), $DB->get_field('datalynx_fields', 'param1', ['id' => $slots]));
        $this->assertSame(
            json_encode(["$textid", "$numberid:|beh|lay"]),
            $DB->get_field('datalynx_fields', 'param1', ['id' => $current])
        );
    }

    /**
     * Restoring maps the subfields to their new ids, also a subfield that comes after its
     * fieldgroup in the backup, and stores the list in the current form.
     */
    public function test_restore_maps_subfields(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $dlx] = $this->create_mobile_instance();
        $textid = $this->add_mobile_field($dlx, 'text', 'Text');
        $groupid = $this->add_mobile_field($dlx, 'fieldgroup', 'Group', ['param2' => '3']);
        $laterid = $this->add_mobile_field($dlx, 'number', 'Later');
        $DB->set_field('datalynx_fields', 'param1', json_encode([$textid, '', "$laterid:|beh||"]), ['id' => $groupid]);
        \mod_datalynx\local\definition_cache::purge();

        $bc = new backup_controller(
            backup::TYPE_1ACTIVITY,
            $dlx->cm->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id
        );
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();
        $backupid = 'fieldgroup_subfield_list_test';
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), make_backup_temp_directory($backupid));
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

        $newdataid = $DB->get_field('datalynx', 'id', ['course' => $target->id], MUST_EXIST);
        $newfields = $DB->get_records_menu('datalynx_fields', ['dataid' => $newdataid], '', 'name, id');
        $this->assertSame(
            json_encode([(string) $newfields['Text'], "{$newfields['Later']}:|beh"]),
            $DB->get_field('datalynx_fields', 'param1', ['id' => $newfields['Group']])
        );
        $this->assertNotEquals($course->id, $target->id);
    }
}
