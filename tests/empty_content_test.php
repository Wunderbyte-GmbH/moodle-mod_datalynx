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
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use advanced_testcase;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\entry\entry_submission_service;
use mod_datalynx\local\field\datalynxfield_base;

/**
 * A missing content row means no value: fields left empty store no row, and "is empty" finds
 * them (mod_datalynx#308).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\datalynx_entries::process_entries
 * @covers     \mod_datalynx\local\field\datalynxfield_base
 */
final class empty_content_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var int[] field ids by type */
    private array $fieldids = [];

    /** @var int */
    private int $viewid;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        \mod_datalynx\local\field\datalynxfield_behavior::reset_condition_cache();

        [, $this->dlx] = $this->create_mobile_instance();
        $types = [
            'text' => [], 'time' => [], 'duration' => [], 'number' => [], 'editor' => [],
            'multiselect' => ['param1' => "Red\nBlue"], 'teammemberselect' => ['param2' => json_encode([1, 2, 3, 4, 5])],
        ];
        $tags = '';
        foreach ($types as $type => $params) {
            $this->fieldids[$type] = $this->add_mobile_field($this->dlx, $type, ucfirst($type), $params);
            $tags .= '[[' . ucfirst($type) . ']] ';
        }
        $this->viewid = $this->add_mobile_view($this->dlx, 'grid', 'Edit', $tags . '##edit##', [
            'section' => '##addnewentry## ##entries##',
        ]);
        $this->dlx = new datalynx($this->dlx->id());
    }

    /**
     * Each field left empty in the entry form: what the form posts for it.
     *
     * @return array
     */
    public static function empty_input_provider(): array {
        return [
            'time' => ['time', '', 0],
            'duration' => ['duration', '', 0],
            'number' => ['number', '', ''],
            'editor' => ['editor', '_editor', ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0]],
            'multiselect' => ['multiselect', '', []],
            'teammemberselect' => ['teammemberselect', '', []],
        ];
    }

    /**
     * A field saved empty stores no row; "is empty" finds the entry, NOT empty does not.
     *
     * @dataProvider empty_input_provider
     * @param string $type
     * @param string $suffix element name suffix
     * @param mixed $value what the form posts
     */
    public function test_field_saved_empty_stores_no_row(string $type, string $suffix, $value): void {
        global $DB;

        $fieldid = $this->fieldids[$type];
        $view = $this->dlx->get_view('grid', $this->viewid, false);
        $result = (new entry_submission_service($view))->save([-1], (object) [
            "field_{$this->fieldids['text']}_-1" => 'Saved',
            "field_{$fieldid}_-1{$suffix}" => $value,
        ]);
        $this->assertSame([], $result['errors']);
        $entryid = (int) reset($result['entryids']);

        $this->assertFalse($DB->record_exists('datalynx_contents', ['fieldid' => $fieldid, 'entryid' => $entryid]));
        $this->assertSame([$entryid], $this->search($fieldid, ''));
        $this->assertSame([], $this->search($fieldid, 'NOT'));
    }

    /**
     * Clearing a value removes its row, and the field counts as changed.
     */
    public function test_cleared_value_removes_its_row(): void {
        global $DB, $USER;

        $fieldid = $this->fieldids['number'];
        $entryid = $this->add_mobile_entry($this->dlx, $USER->id, [$this->fieldids['text'] => 'Kept', $fieldid => '7']);
        $view = $this->dlx->get_view('grid', $this->viewid, false);
        $result = (new entry_submission_service($view))->save([$entryid], (object) [
            "field_{$this->fieldids['text']}_{$entryid}" => 'Kept',
            "field_{$fieldid}_{$entryid}" => '',
        ]);

        $this->assertSame([$entryid], array_map('intval', $result['entryids']));
        $this->assertFalse($DB->record_exists('datalynx_contents', ['fieldid' => $fieldid, 'entryid' => $entryid]));
    }

    /**
     * Rows stored by earlier versions for fields left empty count as empty in the search and are
     * removed by the cleanup of the upgrade and restore, except where several rows are the lines
     * of a fieldgroup; a 0 in a number field is a value.
     */
    public function test_stored_empty_contents(): void {
        global $USER;

        $time = $this->fieldids['time'];
        $team = $this->fieldids['teammemberselect'];
        $number = $this->fieldids['number'];
        $placeholders = $this->add_mobile_entry($this->dlx, $USER->id, [$time => '0', $team => '[]', $number => '']);
        $zero = $this->add_mobile_entry($this->dlx, $USER->id, [$number => '0']);

        // The second entry has no row for the date and the team at all: empty too.
        $this->assertSame([$placeholders, $zero], $this->search($time, ''));
        $this->assertSame([], $this->search($time, 'NOT'));
        $this->assertSame([$placeholders, $zero], $this->search($team, ''));
        $this->assertSame([], $this->search($team, 'NOT'));
        $this->assertSame([$placeholders], $this->search($number, ''));
        $this->assertSame([$zero], $this->search($number, 'NOT'));

        $this->assertSame(3, datalynxfield_base::delete_stored_empty_contents($this->dlx->id()));
        $this->assertSame([$placeholders, $zero], $this->search($time, ''));
        $this->assertSame([$zero], $this->search($number, 'NOT'));
        $this->assertSame(0, datalynxfield_base::delete_stored_empty_contents($this->dlx->id()), 'idempotent');
    }

    /**
     * Fieldgroup subfields keep their empty rows, which hold the places of their lines.
     */
    public function test_fieldgroup_rows_are_kept(): void {
        global $DB, $USER;

        $time = $this->fieldids['time'];
        $this->add_mobile_field($this->dlx, 'fieldgroup', 'Group', [
            'param1' => json_encode([(string) $this->fieldids['text'], (string) $time]), 'param2' => '3',
        ]);
        $entryid = $this->add_mobile_entry($this->dlx, $USER->id, [$time => '0']);

        $this->assertSame(0, datalynxfield_base::delete_stored_empty_contents($this->dlx->id()));
        $this->assertTrue($DB->record_exists('datalynx_contents', ['fieldid' => $time, 'entryid' => $entryid]));
    }

    /**
     * The entries an "empty" or NOT empty criterion on a field selects.
     *
     * @param int $fieldid
     * @param string $not '' or 'NOT'
     * @return int[]
     */
    private function search(int $fieldid, string $not): array {
        $view = $this->dlx->get_view('grid', $this->viewid, false);
        $view->set_filter(['customsearch' => [$fieldid => ['AND' => [[$not, '', '']]]]], true);
        $entries = new datalynx_entries($this->dlx, $view->get_filter());
        $ids = array_map('intval', array_keys($entries->get_entries()->entries ?? []));
        sort($ids);
        return $ids;
    }
}
