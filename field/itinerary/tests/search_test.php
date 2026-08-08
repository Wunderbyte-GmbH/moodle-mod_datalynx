<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace datalynxfield_itinerary;

use advanced_testcase;
use mod_datalynx\datalynx;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\filter\datalynx_filter;
use stdClass;

/**
 * Tests that corridor matching works through the real filter pipeline.
 *
 * The field returns an entry-id subquery with `$fromcontent = false` rather than a
 * condition on a joined content alias. That is an unusual shape, so it is worth
 * proving end to end through datalynx_entries rather than only unit testing the
 * SQL builder.
 *
 * @package    datalynxfield_itinerary
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxfield_itinerary\field::get_search_sql
 */
final class search_test extends advanced_testcase {
    /** @var array Real places, so the distances in the tests are meaningful. */
    const PLACES = [
        'wien' => [48.1852, 16.3775],
        'wienerneustadt' => [47.8149, 16.2372],
        'graz' => [47.0736, 15.4161],
        'linz' => [48.2907, 14.2921],
    ];

    /** @var datalynx */
    protected datalynx $dlx;

    /** @var field */
    protected $field;

    /**
     * Set up a datalynx instance with one itinerary field.
     */
    public function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $this->dlx = new datalynx($instance->id);

        $record = (object) [
            'dataid' => $this->dlx->id(),
            'name' => 'Journey',
            'type' => 'itinerary',
            'description' => '',
            'param1' => 10,
            'param5' => 5,
        ];
        $record->id = $DB->insert_record('datalynx_fields', $record);
        $this->field = $this->dlx->get_fields(null, false, true)[$record->id];
    }

    /**
     * Create an entry whose itinerary runs through the named places.
     *
     * @param string[] $places
     * @return int
     */
    protected function create_journey(array $places): int {
        global $DB;

        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $this->dlx->id(),
            'userid' => 2,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $waypoints = [];
        foreach ($places as $place) {
            [$lat, $lng] = self::PLACES[$place];
            $waypoints[] = ['address' => $place, 'lat' => $lat, 'lng' => $lng, 'time' => null];
        }

        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        $this->field->update_content($entry, ['waypoints' => json_encode($waypoints)]);

        return $entryid;
    }

    /**
     * Run a corridor search through the filter pipeline.
     *
     * @param string $from
     * @param string $to
     * @param bool $not Negate the condition.
     * @return int[] Matching entry ids.
     */
    protected function browse(string $from, string $to, bool $not = false): array {
        $fieldid = (int) $this->field->id();
        $value = [
            'fromlat' => self::PLACES[$from][0],
            'fromlng' => self::PLACES[$from][1],
            'tolat' => self::PLACES[$to][0],
            'tolng' => self::PLACES[$to][1],
            'radius' => 5,
        ];

        // The customsearch key is the public way in: the filter turns it into searchfields
        // when it prepares itself for searching.
        $filter = new datalynx_filter((object) [
            'dataid' => $this->dlx->id(),
            'id' => 0,
            'customsearch' => [$fieldid => ['AND' => [[$not ? 'NOT' : '', '', $value]]]],
        ]);

        $entries = new datalynx_entries($this->dlx, $filter);
        $entries->set_content();

        return array_map('intval', array_keys($entries->entries() ?: []));
    }

    /**
     * The filter returns only journeys that can carry the traveller.
     */
    public function test_filter_finds_matching_journeys(): void {
        $matching = $this->create_journey(['wien', 'wienerneustadt', 'graz']);
        $this->create_journey(['wien', 'linz']);

        $this->assertEquals([$matching], $this->browse('wienerneustadt', 'graz'));
    }

    /**
     * The filter is directional, exactly as the matcher is.
     */
    public function test_filter_is_directional(): void {
        $this->create_journey(['wien', 'graz']);

        $this->assertNotEmpty($this->browse('wien', 'graz'));
        $this->assertSame([], $this->browse('graz', 'wien'));
    }

    /**
     * Negating the condition returns everything that does not match.
     */
    public function test_filter_can_be_negated(): void {
        $this->create_journey(['wien', 'graz']);
        $elsewhere = $this->create_journey(['wien', 'linz']);

        $this->assertEquals([$elsewhere], $this->browse('wien', 'graz', true));
    }

    /**
     * A half-filled search form is not a search and must not filter anything out.
     */
    public function test_incomplete_search_is_ignored(): void {
        $fieldid = (int) $this->field->id();

        [$sql, $params, $fromcontent] = $this->field->get_search_sql(
            ['', '', ['fromlat' => 48.1, 'fromlng' => 16.3]]
        );

        $this->assertSame('', $sql);
        $this->assertSame([], $params);
        $this->assertFalse($fromcontent);

        // And parse_search() refuses to build one in the first place.
        $formdata = (object) [
            "f_0_{$fieldid}_fromlat" => 48.1,
            "f_0_{$fieldid}_fromlng" => 16.3,
        ];
        $this->assertFalse($this->field->parse_search($formdata, 0));
    }

    /**
     * A complete search form parses into a corridor.
     */
    public function test_parse_search_reads_a_complete_form(): void {
        $fieldid = (int) $this->field->id();
        $formdata = (object) [
            "f_0_{$fieldid}_fromaddress" => 'Wien',
            "f_0_{$fieldid}_fromlat" => 48.1852,
            "f_0_{$fieldid}_fromlng" => 16.3775,
            "f_0_{$fieldid}_toaddress" => 'Graz',
            "f_0_{$fieldid}_tolat" => 47.0736,
            "f_0_{$fieldid}_tolng" => 15.4161,
            "f_0_{$fieldid}_radius" => 10,
        ];

        $parsed = $this->field->parse_search($formdata, 0);

        $this->assertIsArray($parsed);
        $this->assertEquals(48.1852, $parsed['fromlat']);
        $this->assertEquals(15.4161, $parsed['tolng']);
        $this->assertEquals(10, $parsed['radius']);
        $this->assertEquals('Wien', $parsed['fromaddress']);
    }

    /**
     * The customfilter search form produces a working corridor criterion.
     *
     * The customfilter reads submitted values by splitting the element name on "_",
     * which sees six separate values here rather than one corridor. The field is
     * therefore reassembled through parse_search(); this proves that path end to end.
     */
    public function test_customfilter_form_produces_a_corridor(): void {
        $fieldid = (int) $this->field->id();
        $matching = $this->create_journey(['wien', 'wienerneustadt', 'graz']);
        $this->create_journey(['wien', 'linz']);

        $customfilter = (object) [
            'id' => 1,
            'dataid' => $this->dlx->id(),
            'fieldlist' => json_encode([$fieldid => ['name' => 'Journey', 'sortable' => 0]]),
        ];
        $formdata = (object) [
            "f_1_{$fieldid}_fromaddress" => 'Wiener Neustadt',
            "f_1_{$fieldid}_fromlat" => self::PLACES['wienerneustadt'][0],
            "f_1_{$fieldid}_fromlng" => self::PLACES['wienerneustadt'][1],
            "f_1_{$fieldid}_toaddress" => 'Graz',
            "f_1_{$fieldid}_tolat" => self::PLACES['graz'][0],
            "f_1_{$fieldid}_tolng" => self::PLACES['graz'][1],
            "f_1_{$fieldid}_radius" => 5,
        ];

        $filter = new datalynx_filter((object) ['id' => 0, 'dataid' => $this->dlx->id()]);
        $filter = $this->dlx->get_filter_manager()
            ->get_filter_from_customfilterform($filter, $formdata, $customfilter);

        $searchfields = unserialize($filter->customsearch);
        $this->assertCount(1, $searchfields[$fieldid]['AND'], 'The six elements must yield one criterion.');
        $this->assertSame('', $searchfields[$fieldid]['AND'][0][1], 'The corridor search takes no operator.');
        $this->assertEquals(5, $searchfields[$fieldid]['AND'][0][2]['radius']);

        $entries = new datalynx_entries($this->dlx, $filter);
        $entries->set_content();
        $this->assertEquals([$matching], array_map('intval', array_keys($entries->entries() ?: [])));
    }

    /**
     * The field returns an entry-id set, not a content-table condition.
     */
    public function test_search_sql_does_not_ask_for_a_content_join(): void {
        [$sql, $params, $fromcontent] = $this->field->get_search_sql(['', '', [
            'fromlat' => 48.1852, 'fromlng' => 16.3775,
            'tolat' => 47.0736, 'tolng' => 15.4161, 'radius' => 5,
        ]]);

        $this->assertFalse($fromcontent, 'A content join would be qualified with a c<fieldid> alias that does not exist.');
        $this->assertStringStartsWith('e.id IN (', $sql);
        $this->assertNotEmpty($params);
    }
}
