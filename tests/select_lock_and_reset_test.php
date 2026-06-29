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
 * Tests for locking a single-choice field after first save and the reset-entry (data wipe) action.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use datalynxfield_status\field as datalynxfield_status;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\filter\datalynx_filter;

/**
 * Tests for the single-choice lock and the reset-entry action.
 *
 * @covers \datalynxfield_select\field::is_locked_for_entry
 * @covers \mod_datalynx\local\datalynx_entries::process_entries
 */
final class select_lock_and_reset_test extends advanced_testcase {
    /** @var datalynx The datalynx instance under test. */
    private datalynx $dlx;

    /**
     * Set up the fixture.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $this->dlx = new datalynx($instance->id);
    }

    /**
     * Create a single-choice field.
     *
     * @param string $type 'select' or 'radiobutton'.
     * @param int $lock Value of the param7 "lock after save" flag.
     * @return int New field id.
     */
    private function make_field(string $type, int $lock): int {
        global $DB;
        return (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $this->dlx->id(),
            'type' => $type,
            'name' => $type . '_' . $lock,
            'description' => '',
            'param1' => "Option A\nOption B",
            'param2' => '', 'param3' => '', 'param4' => '', 'param5' => '', 'param6' => '',
            'param7' => $lock,
            'param8' => '', 'param9' => '', 'param10' => '',
        ]);
    }

    /**
     * Create an entry.
     *
     * @param int $status Entry status.
     * @param int $approved Approval flag.
     * @return int New entry id.
     */
    private function make_entry(int $status = 0, int $approved = 0): int {
        global $DB, $USER;
        return (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $this->dlx->id(),
            'userid' => $USER->id,
            'groupid' => 0,
            'approved' => $approved,
            'status' => $status,
            'timecreated' => time(),
            'timemodified' => time(),
            'timesubmitted' => $status === datalynxfield_status::STATUS_FINAL_SUBMISSION ? time() : null,
        ]);
    }

    /**
     * Store a content value for a field on an entry.
     *
     * @param int $fieldid Field id.
     * @param int $entryid Entry id.
     * @param string $content Stored value.
     */
    private function set_content(int $fieldid, int $entryid, string $content): void {
        global $DB;
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid, 'entryid' => $entryid, 'content' => $content,
        ]);
    }

    /**
     * The select field locks only when param7 is on, the entry is saved, and a value exists.
     */
    public function test_is_locked_for_entry_select(): void {
        // Create both field records before fetching either object, since get_field_from_id() caches.
        $lockedid = $this->make_field('select', 1);
        $unlockedid = $this->make_field('select', 0);
        $locked = $this->dlx->get_field_from_id($lockedid);
        $unlocked = $this->dlx->get_field_from_id($unlockedid);
        $entryid = $this->make_entry();

        // New (unsaved) entry: never locked.
        $this->assertFalse($locked->is_locked_for_entry((object) ['id' => 0]));

        // Saved entry but no value yet: not locked.
        $this->assertFalse($locked->is_locked_for_entry((object) ['id' => $entryid]));

        // Saved entry with a value: locked when param7 on, not locked when off.
        $this->set_content($locked->id(), $entryid, '1');
        $this->set_content($unlocked->id(), $entryid, '1');
        $this->assertTrue($locked->is_locked_for_entry((object) ['id' => $entryid]));
        $this->assertFalse($unlocked->is_locked_for_entry((object) ['id' => $entryid]));

        // The loaded entry property is honored without a DB hit (empty value => not locked).
        $emptyloaded = (object) ['id' => $entryid, "c{$locked->id()}_content" => ''];
        $this->assertFalse($locked->is_locked_for_entry($emptyloaded));
    }

    /**
     * The radiobutton field inherits the lock behavior from the select field.
     */
    public function test_is_locked_for_entry_radiobutton(): void {
        $field = $this->dlx->get_field_from_id($this->make_field('radiobutton', 1));
        $entryid = $this->make_entry();

        $this->assertFalse($field->is_locked_for_entry((object) ['id' => $entryid]));
        $this->set_content($field->id(), $entryid, '2');
        $this->assertTrue($field->is_locked_for_entry((object) ['id' => $entryid]));
    }

    /**
     * Resetting an entry wipes all its field data, keeps the entry record, and returns it to draft.
     */
    public function test_reset_wipes_data_and_keeps_entry(): void {
        global $DB;

        $selectid = $this->make_field('select', 1);
        $textid = (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $this->dlx->id(), 'type' => 'text', 'name' => 'Notes', 'description' => '',
            'param1' => '', 'param2' => '', 'param3' => '', 'param4' => '', 'param5' => '',
            'param6' => '', 'param7' => '', 'param8' => '', 'param9' => '', 'param10' => '',
        ]);
        $entryid = $this->make_entry(datalynxfield_status::STATUS_SUBMISSION, 1);
        $this->set_content($selectid, $entryid, '1');
        $this->set_content($textid, $entryid, 'some answer');
        $this->assertEquals(2, $DB->count_records('datalynx_contents', ['entryid' => $entryid]));

        $entries = new datalynx_entries($this->dlx, new datalynx_filter((object) ['dataid' => $this->dlx->id()]));
        $entries->process_entries('reset', (string) $entryid, null, true);

        // All field data gone, but the entry record remains, reset to a clean draft state.
        $this->assertEquals(0, $DB->count_records('datalynx_contents', ['entryid' => $entryid]));
        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        $this->assertNotEmpty($entry);
        $this->assertEquals(datalynxfield_status::STATUS_DRAFT, (int) $entry->status);
        $this->assertEquals(0, (int) $entry->approved);
        $this->assertNull($entry->timesubmitted);

        // With the value gone the single-choice field is editable again.
        $select = $this->dlx->get_field_from_id($selectid);
        $this->assertFalse($select->is_locked_for_entry((object) ['id' => $entryid]));
    }

    /**
     * A final-submission entry can never be reset, even by a manager, and its data is left intact.
     */
    public function test_reset_blocked_after_final_submission(): void {
        global $DB;

        $selectid = $this->make_field('select', 1);
        $entryid = $this->make_entry(datalynxfield_status::STATUS_FINAL_SUBMISSION, 1);
        $this->set_content($selectid, $entryid, '1');
        $this->assertEquals(1, $DB->count_records('datalynx_contents', ['entryid' => $entryid]));

        // Running as admin (manageentries). Reset must still be refused once final.
        $entries = new datalynx_entries($this->dlx, new datalynx_filter((object) ['dataid' => $this->dlx->id()]));
        $entries->process_entries('reset', (string) $entryid, null, true);

        // Nothing was wiped and the entry stays final.
        $this->assertEquals(1, $DB->count_records('datalynx_contents', ['entryid' => $entryid]));
        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        $this->assertEquals(datalynxfield_status::STATUS_FINAL_SUBMISSION, (int) $entry->status);
    }
}
