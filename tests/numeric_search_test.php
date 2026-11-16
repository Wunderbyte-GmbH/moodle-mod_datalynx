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

/**
 * Numbers stored as text are searched and sorted as numbers (mod_datalynx#316).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\field\datalynxfield_base::get_numeric_search_sql
 * @covers     \datalynxfield_number\field
 * @covers     \datalynxfield_duration\field
 */
final class numeric_search_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var int[] */
    private array $fieldids = [];

    /** @var int[] entry ids by number value */
    private array $entries = [];

    /** @var int */
    private int $viewid;

    #[\Override]
    protected function setUp(): void {
        global $USER;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        $this->fieldids['number'] = $this->add_mobile_field($dlx, 'number', 'Amount');
        $this->fieldids['duration'] = $this->add_mobile_field($dlx, 'duration', 'Duration');
        $this->viewid = $this->add_mobile_view($dlx, 'grid', 'Entries', '[[Amount]] [[Duration]]');
        $this->dlx = new datalynx($dlx->id());
        foreach (['3' => '600', '10' => '3600', '2.5' => '7200', '2,75' => '', 'none' => ''] as $number => $duration) {
            $contents = $number === 'none' ? [] : [$this->fieldids['number'] => $number];
            if ($duration !== '') {
                $contents[$this->fieldids['duration']] = $duration;
            }
            $this->entries[$number] = $this->add_mobile_entry($this->dlx, $USER->id, $contents);
        }
    }

    /**
     * Number criteria and the entries they select.
     *
     * @return array
     */
    public static function number_criteria(): array {
        return [
            '> 5' => [['', '>', ['5']], ['10']],
            '>= 3' => [['', '>=', ['3']], ['3', '10']],
            '< 3' => [['', '<', ['3']], ['2.5', '2,75']],
            '<= 2.5' => [['', '<=', ['2.5']], ['2.5']],
            '= 2.50' => [['', '=', ['2.50']], ['2.5']],
            '= 2,75' => [['', '=', ['2,75']], ['2,75']],
            'between 2.6 and 10' => [['', 'BETWEEN', ['2.6', '10']], ['2,75', '3']],
            'NOT > 5' => [['NOT', '>', ['5']], ['3', '2.5', '2,75', 'none']],
            'empty' => [['', '', ''], ['none']],
            'NOT empty' => [['NOT', '', ''], ['3', '10', '2.5', '2,75']],
        ];
    }

    /**
     * Number criteria compare numbers.
     *
     * @dataProvider number_criteria
     * @param array $criterion
     * @param string[] $expected entry names
     */
    public function test_number_criteria(array $criterion, array $expected): void {
        $this->assertSame($this->ids($expected), $this->search($this->fieldids['number'], $criterion));
    }

    /**
     * Duration criteria compare numbers, also NOT.
     */
    public function test_duration_criteria(): void {
        $field = $this->fieldids['duration'];

        $this->assertSame($this->ids(['10', '2.5']), $this->search($field, ['', '>', ['3000']]));
        $this->assertSame($this->ids(['3', '2,75', 'none']), $this->search($field, ['NOT', '>', ['3000']]));
    }

    /**
     * Sorting by a number field sorts by number.
     */
    public function test_sorted_by_number(): void {
        $view = $this->dlx->get_view('grid', $this->viewid, false);
        $view->set_filter(['customsort' => [$this->fieldids['number'] => 0], 'customsearch' => [
            $this->fieldids['number'] => ['AND' => [['', '>', ['2.6']]]],
        ]], true);
        $order = array_map('intval', array_keys((new datalynx_entries($this->dlx, $view->get_filter()))->get_entries()->entries));

        $this->assertSame([$this->entries['2,75'], $this->entries['3'], $this->entries['10']], $order);
    }

    /**
     * A numeric text field keeps decimals, and its unique check compares whole values.
     *
     * @covers \datalynxfield_text\renderer::validate
     */
    public function test_numeric_text_and_unique_check(): void {
        global $DB;

        $textid = $this->add_mobile_field($this->dlx, 'text', 'Code', ['param4' => 'numeric', 'param8' => '1']);
        $nameid = $this->add_mobile_field($this->dlx, 'text', 'Name', ['param8' => '1']);
        $viewid = $this->add_mobile_view($this->dlx, 'grid', 'Edit', '[[Code]] [[Name]] ##edit##', [
            'section' => '##addnewentry## ##entries##',
        ]);
        $dlx = new datalynx($this->dlx->id());
        $service = fn() => new entry_submission_service($dlx->get_view('grid', $viewid, false));

        $result = $service()->save([-1], (object) ["field_{$textid}_-1" => '3.5', "field_{$nameid}_-1" => 'abc']);
        $this->assertSame([], $result['errors']);
        $this->assertSame('3.5', $DB->get_field('datalynx_contents', 'content', ['fieldid' => $textid]));

        // The value "a_c" is not "abc", and "%" is no wildcard.
        $this->assertSame([], $service()->validate([-1], ["field_{$nameid}_-1" => 'a_c']));
        $this->assertSame([], $service()->validate([-1], ["field_{$nameid}_-1" => '%']));
        $this->assertArrayHasKey("field_{$nameid}_-1", $service()->validate([-1], ["field_{$nameid}_-1" => 'abc']));
    }

    /**
     * The entries a criterion on a field selects.
     *
     * @param int $fieldid
     * @param array $criterion
     * @return int[]
     */
    private function search(int $fieldid, array $criterion): array {
        $view = $this->dlx->get_view('grid', $this->viewid, false);
        $view->set_filter(['customsearch' => [$fieldid => ['AND' => [$criterion]]]], true);
        $entries = new datalynx_entries($this->dlx, $view->get_filter());
        $ids = array_map('intval', array_keys($entries->get_entries()->entries ?? []));
        sort($ids);
        return $ids;
    }

    /**
     * Entry ids by name, sorted.
     *
     * @param string[] $names
     * @return int[]
     */
    private function ids(array $names): array {
        $ids = array_map(fn($name) => $this->entries[$name], $names);
        sort($ids);
        return $ids;
    }
}
