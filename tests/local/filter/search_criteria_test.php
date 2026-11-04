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

namespace mod_datalynx\local\filter;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use advanced_testcase;
use invalid_parameter_exception;
use mod_datalynx\datalynx;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\mobile_test_trait;

/**
 * Tests for the JSON search and sort criteria contract.
 *
 * This is the gate between client input and the filter engine, so most cases here are payloads
 * that must be refused.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\filter\search_criteria
 */
final class search_criteria_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var int */
    private int $titleid;

    /** @var int */
    private int $seatsid;

    /** @var int */
    private int $routeid;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $this->dlx] = $this->create_mobile_instance();
        $this->titleid = $this->add_mobile_field($this->dlx, 'text', 'Title');
        $this->seatsid = $this->add_mobile_field($this->dlx, 'number', 'Seats');
        $this->routeid = $this->add_mobile_field($this->dlx, 'itinerary', 'Route');
    }

    /**
     * Valid criteria come back in the shape the filter engine reads, values as strings.
     */
    public function test_decode_search_accepts_valid_criteria(): void {
        $json = json_encode([
            $this->titleid => ['AND' => [['', 'LIKE', 'Graz']], 'OR' => [['NOT', '=', 'Wien']]],
            $this->seatsid => ['AND' => [['', 'BETWEEN', [1, 4]]]],
            'timecreated' => ['AND' => [['', '>', [1700000000]]]],
        ]);

        $criteria = search_criteria::decode_search($json, $this->dlx);

        $this->assertSame([['', 'LIKE', 'Graz']], $criteria[$this->titleid]['AND']);
        $this->assertSame([['NOT', '=', 'Wien']], $criteria[$this->titleid]['OR']);
        $this->assertSame([['', 'BETWEEN', ['1', '4']]], $criteria[$this->seatsid]['AND']);
        $this->assertSame([['', '>', ['1700000000']]], $criteria['timecreated']['AND']);
    }

    /**
     * Composite searches keep their named value set.
     */
    public function test_decode_search_accepts_composite_value_for_composite_field(): void {
        $json = json_encode([$this->routeid => ['AND' => [['', '', [
            'fromlat' => 48.2, 'fromlng' => 16.37, 'tolat' => 47.07, 'tolng' => 15.44, 'radius' => 10,
        ]]]]]);

        $criteria = search_criteria::decode_search($json, $this->dlx);

        $this->assertSame('48.2', $criteria[$this->routeid]['AND'][0][2]['fromlat']);
        $this->assertSame('10', $criteria[$this->routeid]['AND'][0][2]['radius']);
    }

    /**
     * Empty input means no criteria.
     */
    public function test_empty_input_means_no_criteria(): void {
        $this->assertSame([], search_criteria::decode_search('', $this->dlx));
        $this->assertSame([], search_criteria::decode_search('{}', $this->dlx));
        $this->assertSame([], search_criteria::decode_sort('  ', $this->dlx));
        $this->assertSame('', search_criteria::encode([]));
    }

    /**
     * Payloads the gate must refuse.
     *
     * @return array
     */
    public static function invalid_search_provider(): array {
        return [
            'serialized object' => ['O:8:"stdClass":0:{}'],
            'serialized array' => ['a:1:{i:1;a:1:{s:3:"AND";a:0:{}}}'],
            'not json' => ['{"1":'],
            'json list' => ['[1, 2]'],
            'json scalar' => ['"text"'],
            'unknown field' => ['{"999999":{"AND":[["","=","x"]]}}'],
            'unknown internal field' => ['{"nosuchfield":{"AND":[["","=","x"]]}}'],
            'bad conjunction' => ['{"TITLE":{"XOR":[["","=","x"]]}}'],
            'criteria not a list' => ['{"TITLE":{"AND":{"a":["","=","x"]}}}'],
            'criterion too short' => ['{"TITLE":{"AND":[["","="]]}}'],
            'criterion keyed' => ['{"TITLE":{"AND":[{"not":"","op":"=","value":"x"}]}}'],
            'bad negation' => ['{"TITLE":{"AND":[["MAYBE","=","x"]]}}'],
            'bad operator' => ['{"TITLE":{"AND":[["","; DROP TABLE x","x"]]}}'],
            'operator of another field' => ['{"TITLE":{"AND":[["","BETWEEN",["a","b"]]]}}'],
            'between with one value' => ['{"SEATS":{"AND":[["","BETWEEN",[1]]]}}'],
            'between with three values' => ['{"SEATS":{"AND":[["","BETWEEN",[1,2,3]]]}}'],
            'between with scalar' => ['{"SEATS":{"AND":[["","BETWEEN","1"]]}}'],
            'nested value' => ['{"TITLE":{"AND":[["","=",[["x"]]]]}}'],
            'named value on plain field' => ['{"TITLE":{"AND":[["","=",{"a":"x"}]]}}'],
            'too deep' => ['{"TITLE":{"AND":[["","=",[[[[[["x"]]]]]]]]}}'],
            'no criteria' => ['{"TITLE":{}}'],
        ];
    }

    /**
     * Every invalid payload throws.
     *
     * @dataProvider invalid_search_provider
     * @param string $json with TITLE / SEATS standing for the field ids
     */
    public function test_decode_search_rejects(string $json): void {
        $json = str_replace(['"TITLE"', '"SEATS"'], ['"' . $this->titleid . '"', '"' . $this->seatsid . '"'], $json);

        $this->expectException(invalid_parameter_exception::class);
        search_criteria::decode_search($json, $this->dlx);
    }

    /**
     * Too many fields, criteria, values or characters are refused.
     */
    public function test_decode_search_rejects_oversized_input(): void {
        $cases = [
            json_encode([$this->titleid => ['AND' => array_fill(0, search_criteria::MAX_CRITERIA + 1, ['', '=', 'x'])]]),
            json_encode([$this->titleid => ['AND' => [['', '=', array_fill(0, search_criteria::MAX_VALUES + 1, 'x')]]]]),
            json_encode([$this->titleid => ['AND' => [['', '=', str_repeat('x', search_criteria::MAX_STRING + 1)]]]]),
        ];
        foreach ($cases as $json) {
            try {
                search_criteria::decode_search($json, $this->dlx);
                $this->fail('Oversized criteria accepted: ' . substr($json, 0, 60));
            } catch (invalid_parameter_exception $e) {
                $this->assertStringContainsString('Invalid search criteria', $e->getMessage());
            }
        }
    }

    /**
     * Sort criteria: known sortable fields, directions 0 and 1 only.
     */
    public function test_decode_sort(): void {
        $sort = search_criteria::decode_sort(json_encode([$this->titleid => 1, 'timecreated' => '0']), $this->dlx);
        $this->assertSame([$this->titleid => 1, 'timecreated' => 0], $sort);

        foreach (['{"999999":1}', '{"' . $this->titleid . '":2}', '{"' . $this->titleid . '":"DESC"}', '[1]'] as $json) {
            try {
                search_criteria::decode_sort($json, $this->dlx);
                $this->fail("Sort accepted: $json");
            } catch (invalid_parameter_exception $e) {
                $this->assertStringContainsString('Invalid search criteria', $e->getMessage());
            }
        }
    }

    /**
     * Encoded criteria decode to the same criteria.
     */
    public function test_encode_round_trip(): void {
        $criteria = [$this->titleid => ['AND' => [['', 'LIKE', 'Grüß']]]];

        $this->assertSame($criteria, search_criteria::decode_search(search_criteria::encode($criteria), $this->dlx));
    }

    /**
     * What the gate lets through, the filter engine runs: decoded criteria select the entries.
     */
    public function test_decoded_criteria_drive_the_filter_engine(): void {
        global $USER;

        $graz = $this->add_mobile_entry($this->dlx, $USER->id, [$this->titleid => 'Wien - Graz', $this->seatsid => 3]);
        $this->add_mobile_entry($this->dlx, $USER->id, [$this->titleid => 'Linz - Salzburg', $this->seatsid => 2]);
        $viewid = $this->add_mobile_view($this->dlx, 'grid', 'Rides');

        $json = json_encode([$this->titleid => ['AND' => [['', 'LIKE', 'Graz']]]]);
        $view = $this->dlx->get_view('grid', $viewid, false);
        $view->set_filter(['customsearch' => search_criteria::decode_search($json, $this->dlx)], true);
        $entries = new datalynx_entries($this->dlx, $view->get_filter());
        $result = $entries->get_entries();

        $this->assertSame([$graz], array_map('intval', array_keys($result->entries)));
    }
}
