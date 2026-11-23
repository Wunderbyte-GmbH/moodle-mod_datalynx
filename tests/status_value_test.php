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
use datalynxfield_status\field as datalynxfield_status;
use mod_datalynx\local\datalynx_entries;

/**
 * Only the three entry statuses can be stored, and an unknown stored value is shown as nothing (#261).
 *
 * @package    mod_datalynx
 * @category   test
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxfield_status\field::to_status
 * @covers     \datalynxfield_status\renderer::display_browse
 * @covers     \mod_datalynx\local\datalynx_entries::process_entries
 */
final class status_value_test extends advanced_testcase {
    /** @var datalynx */
    private datalynx $dlx;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $this->dlx = new datalynx($instance->id);
    }

    /**
     * Statuses as submitted (integers or digit strings) are recognised; anything else is not.
     */
    public function test_to_status(): void {
        $this->assertSame(datalynxfield_status::STATUS_NOT_SET, datalynxfield_status::to_status(0));
        $this->assertSame(datalynxfield_status::STATUS_DRAFT, datalynxfield_status::to_status('1'));
        $this->assertSame(datalynxfield_status::STATUS_FINAL_SUBMISSION, datalynxfield_status::to_status(2));
        foreach ([3, '3', -1, '', 'final', '2.0', null, true, 1.0] as $value) {
            $this->assertNull(datalynxfield_status::to_status($value), var_export($value, true));
        }
    }

    /**
     * An update with a value that is not a status keeps the entry's status; a valid one is stored.
     */
    public function test_update_ignores_unknown_status(): void {
        global $DB;
        $entryid = $this->make_entry(datalynxfield_status::STATUS_DRAFT);

        $this->update($entryid, '3');
        $this->assertEquals(datalynxfield_status::STATUS_DRAFT, $DB->get_field('datalynx_entries', 'status', ['id' => $entryid]));

        $this->update($entryid, '2');
        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        $this->assertEquals(datalynxfield_status::STATUS_FINAL_SUBMISSION, $entry->status);
        $this->assertNotEmpty($entry->timesubmitted);
    }

    /**
     * A new entry submitted with a value that is not a status is stored as "not set".
     */
    public function test_new_entry_with_unknown_status(): void {
        global $DB;
        $entries = new datalynx_entries(new datalynx($this->dlx->id()));
        $processed = $entries->process_entries('update', '-1', (object) ['field_status_-1' => '7'], true);
        $entryid = (int) reset($processed[1]);

        $this->assertGreaterThan(0, $entryid);
        $this->assertEquals(datalynxfield_status::STATUS_NOT_SET, $DB->get_field('datalynx_entries', 'status', ['id' => $entryid]));
    }

    /**
     * A stored value that is not a status shows as nothing, without a warning.
     */
    public function test_unknown_stored_status_displays_empty(): void {
        $field = $this->dlx->get_field_from_id(datalynxfield_status::_STATUS);
        $method = new \ReflectionMethod($field->renderer(), 'display_browse');
        $method->setAccessible(true);

        $this->assertSame('', $method->invoke($field->renderer(), (object) ['id' => 1, 'status' => 3]));
        $this->assertSame(
            get_string('statusfinalsubmission', 'datalynx'),
            $method->invoke($field->renderer(), (object) ['id' => 1, 'status' => '2'])
        );
    }

    /**
     * Create an entry of the admin with the given status.
     *
     * @param int $status
     * @return int entry id
     */
    private function make_entry(int $status): int {
        global $DB, $USER;
        return (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $this->dlx->id(), 'userid' => $USER->id, 'groupid' => 0, 'approved' => 1,
            'status' => $status, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Submit a status for an entry through the entry processing.
     *
     * @param int $entryid
     * @param string $status the submitted value
     */
    private function update(int $entryid, string $status): void {
        $entries = new datalynx_entries(new datalynx($this->dlx->id()));
        $entries->process_entries('update', (string) $entryid, (object) ["field_status_{$entryid}" => $status], true);
    }
}
