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
 * Tests that saving a fieldgroup stores only lines with a value.
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
 * Empty fieldgroup lines used to be stored when the group had a date subfield (a disabled date posts
 * nothing, so the line was never recognised as empty) or when the entry used several fieldgroups.
 * Every stored line is shown again in the edit form. See mod_datalynx#284.
 *
 * @covers \mod_datalynx\local\datalynx_entries::process_entries
 */
final class fieldgroup_empty_lines_test extends advanced_testcase {
    /** @var datalynx */
    private $dlx;

    /** @var int[] Field ids by name. */
    private $f = [];

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $this->dlx = new datalynx($instance->id);

        $this->f['service'] = $this->create_field('text', 'Service');
        $this->f['date'] = $this->create_field('time', 'Date', ['param1' => '1']);
        $this->f['amount'] = $this->create_field('number', 'Amount', ['param1' => '2']);
        $this->f['costs'] = $this->create_field('fieldgroup', 'Costs', [
            'param1' => json_encode(["{$this->f['service']}:||", "{$this->f['date']}:||", "{$this->f['amount']}:||"]),
            'param2' => '20', 'param3' => '3', 'param4' => '1',
        ]);
        $this->f['guest'] = $this->create_field('text', 'Guest');
        $this->f['guests'] = $this->create_field('fieldgroup', 'Guests', [
            'param1' => json_encode(["{$this->f['guest']}:||"]), 'param2' => '10', 'param3' => '3', 'param4' => '0',
        ]);
    }

    /**
     * Only the filled lines are stored: three lines posted, one filled, the date disabled in the others.
     * The second fieldgroup has a value in line 2, which must not keep line 2 of the first group.
     */
    public function test_only_filled_lines_are_stored(): void {
        $entryid = $this->create_entry();
        $this->save($entryid, $this->posted($entryid));

        $this->assertSame(['Rent'], $this->lines($this->f['service'], $entryid));
        $this->assertSame([(string) make_timestamp(2026, 11, 9)], $this->lines($this->f['date'], $entryid));
        $this->assertCount(1, $this->lines($this->f['amount'], $entryid));
        $this->assertSame(['Jane Doe'], $this->lines($this->f['guest'], $entryid), 'guest line kept, empty ones dropped');
    }

    /**
     * Empty lines stored by earlier saves are removed on the next save, the filled line is kept.
     */
    public function test_stored_empty_lines_are_removed(): void {
        $entryid = $this->create_entry([
            $this->f['service'] => ['Rent', '', '', ''],
            $this->f['date'] => [(string) make_timestamp(2026, 11, 9), '0', '0', '0'],
            $this->f['amount'] => ['350', '', '', ''],
        ]);
        $this->save($entryid, $this->posted($entryid, 4));

        $this->assertSame(['Rent'], $this->lines($this->f['service'], $entryid));
        $this->assertCount(1, $this->lines($this->f['date'], $entryid));
        $this->assertCount(1, $this->lines($this->f['amount'], $entryid));
    }

    /**
     * Form data as the browser posts it: line 0 filled, the other lines empty with the date disabled
     * (disabled inputs are not posted). The guest group has its value in line 1.
     *
     * @param int $entryid
     * @param int $lines Number of lines rendered.
     * @return array
     */
    private function posted(int $entryid, int $lines = 3): array {
        $costs = "fieldgroup_{$this->f['costs']}";
        $guests = "fieldgroup_{$this->f['guests']}";
        $data = [$costs => $this->f['costs'], $guests => $this->f['guests']];
        for ($i = 0; $i < $lines; $i++) {
            $data["field_{$this->f['service']}_{$entryid}_{$costs}_{$i}"] = $i ? '' : 'Rent';
            $data["field_{$this->f['amount']}_{$entryid}_{$costs}_{$i}"] = $i ? '' : '350';
            $data["field_{$this->f['guest']}_{$entryid}_{$guests}_{$i}"] = $i == 1 ? 'Jane Doe' : '';
        }
        $data["field_{$this->f['date']}_{$entryid}_{$costs}_0"] = ['enabled' => 1, 'day' => 9, 'month' => 11, 'year' => 2026];
        return $data;
    }

    /**
     * Run an update through the entries processor.
     *
     * @param int $entryid
     * @param array $data
     */
    private function save(int $entryid, array $data): void {
        $filter = new datalynx_filter((object) ['dataid' => $this->dlx->id(), 'eids' => (string) $entryid]);
        $filter->contentfields = array_values(array_filter(array_keys($this->dlx->get_fields()), 'is_numeric'));
        $entries = new datalynx_entries($this->dlx, $filter);
        $entries->set_content();
        $this->assertNotEmpty($entries->process_entries('update', (string) $entryid, (object) $data, true));
    }

    /**
     * Create a draft entry with the given stored lines.
     *
     * @param array $lines fieldid => list of line contents.
     * @return int
     */
    private function create_entry(array $lines = []): int {
        global $DB;
        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $this->dlx->id(), 'userid' => get_admin()->id, 'groupid' => 0, 'approved' => 1,
            'status' => datalynxfield_status::STATUS_DRAFT, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        foreach ($lines as $fieldid => $values) {
            foreach ($values as $value) {
                $DB->insert_record('datalynx_contents', (object) [
                    'fieldid' => $fieldid, 'entryid' => $entryid, 'lineid' => 0, 'content' => $value,
                ]);
            }
        }
        return $entryid;
    }

    /**
     * The stored lines of a subfield in line order.
     *
     * @param int $fieldid
     * @param int $entryid
     * @return array
     */
    private function lines(int $fieldid, int $entryid): array {
        global $DB;
        return array_values($DB->get_fieldset_select(
            'datalynx_contents',
            'content',
            'fieldid = ? AND entryid = ? ORDER BY id',
            [$fieldid, $entryid]
        ));
    }

    /**
     * Create a field and return its id.
     *
     * @param string $type
     * @param string $name
     * @param array $params
     * @return int
     */
    private function create_field(string $type, string $name, array $params = []): int {
        global $DB;
        $record = (object) ['dataid' => $this->dlx->id(), 'type' => $type, 'name' => $name, 'description' => ''];
        for ($i = 1; $i <= 10; $i++) {
            $record->{"param$i"} = $params["param$i"] ?? '';
        }
        $id = (int) $DB->insert_record('datalynx_fields', $record);
        $this->dlx->get_fields(null, false, true);
        return $id;
    }
}
