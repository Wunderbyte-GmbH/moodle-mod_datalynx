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
 * Tests for comparing one entry's schedule with another's.
 *
 * @package    datalynxfield_schedule
 * @category   test
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_schedule;

use advanced_testcase;
use mod_datalynx\datalynx;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\filter\datalynx_filter;
use mod_datalynx\local\ride\schedule;
use mod_datalynx\local\rule\match_compiler;

/**
 * A one-off and a weekly pattern have to be comparable in both directions, because a traveller
 * asking for one particular Monday should meet the commuter who drives every Monday. That is the
 * case the four separate configuration fields could never express.
 *
 * @group mod_datalynx
 * @covers \datalynxfield_schedule\field::compile_relative_criterion
 * @covers \mod_datalynx\local\rule\match_compiler
 */
final class matching_test extends advanced_testcase {
    /** @var string A Monday. */
    const MONDAY = '2026-08-10';

    /** @var string The Tuesday after it. */
    const TUESDAY = '2026-08-11';

    /** @var string The Monday a week later. */
    const NEXT_MONDAY = '2026-08-17';

    /** @var datalynx */
    protected datalynx $dlx;

    /** @var field */
    protected $field;

    /**
     * Set up a datalynx instance with one schedule field.
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
            'name' => 'Termin',
            'type' => 'schedule',
            'description' => '',
            'param1' => 30,
            'param2' => field::TOLERANCE_WHOLE_DAY,
            'param5' => 0,
        ];
        $record->id = $DB->insert_record('datalynx_fields', $record);
        $this->field = $this->dlx->get_fields(null, false, true)[$record->id];
    }

    /**
     * Create an entry holding the given schedule.
     *
     * @param array $value
     * @return int entry id
     */
    protected function create_entry(array $value): int {
        global $DB, $USER;

        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $this->dlx->id(), 'userid' => $USER->id, 'groupid' => 0,
            'approved' => 1, 'status' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        $this->field->update_content($entry, ['schedule' => json_encode($value)]);

        return $entryid;
    }

    /**
     * A ride happening once.
     *
     * @param string $date
     * @param int $minute
     * @return int
     */
    protected function one_off(string $date, int $minute = 420): int {
        return $this->create_entry(['mode' => 'once', 'date' => $date, 'start' => $minute]);
    }

    /**
     * A ride happening every week.
     *
     * @param int[] $days
     * @param int $minute
     * @param string $from
     * @param string $until
     * @return int
     */
    protected function weekly(array $days, int $minute = 420, string $from = '', string $until = ''): int {
        return $this->create_entry([
            'mode' => 'weekly', 'days' => $days, 'start' => $minute, 'from' => $from, 'until' => $until,
        ]);
    }

    /**
     * The entries whose schedule overlaps the given entry's, as the matching rule would find them.
     *
     * @param int $subjectid
     * @param int|null $tolerance minutes, or null for the field default
     * @return int[]
     */
    protected function counterparts(int $subjectid, ?int $tolerance = null): array {
        global $DB;

        $criterion = ['fieldid' => (int) $this->field->id(), 'op' => match_compiler::OP_WITHIN];
        if ($tolerance !== null) {
            $criterion['tolerance'] = $tolerance;
            $criterion['unit'] = MINSECS;
        }

        $compiled = (new match_compiler($this->dlx))->compile([$criterion], $subjectid);
        if ($compiled['impossible']) {
            return [];
        }

        $filter = new datalynx_filter((object) [
            'dataid' => $this->dlx->id(),
            'id' => 0,
            'customsearch' => $compiled['customsearch'],
        ]);
        $entries = new datalynx_entries($this->dlx, $filter);
        $entries->set_content();

        $found = array_map('intval', array_keys($entries->entries() ?: []));

        // The rule excludes the subject itself; here only the criterion is under test.
        return array_values(array_diff($found, [$subjectid]));
    }

    /**
     * Two one-off rides on the same day match; on different days they do not.
     */
    public function test_once_matches_once_on_the_same_day(): void {
        $subject = $this->one_off(self::MONDAY, 8 * 60);
        $sameday = $this->one_off(self::MONDAY, 17 * 60);
        $this->one_off(self::TUESDAY, 8 * 60);
        $this->one_off(self::NEXT_MONDAY, 8 * 60);

        $this->assertEquals([$sameday], $this->counterparts($subject));
    }

    /**
     * The case the four separate fields could not reach: a one-off request meets the commute that
     * runs every week on that weekday.
     */
    public function test_once_matches_a_weekly_ride_on_that_weekday(): void {
        $subject = $this->one_off(self::MONDAY);
        $monday = $this->weekly([1]);
        $this->weekly([2, 3]);

        $this->assertEquals([$monday], $this->counterparts($subject));
    }

    /**
     * And the other way round: saving the commute finds the one-off requests on its weekdays.
     */
    public function test_a_weekly_ride_matches_the_one_offs_on_its_weekdays(): void {
        $subject = $this->weekly([1, 5]);
        $monday = $this->one_off(self::MONDAY);
        $this->one_off(self::TUESDAY);

        $this->assertEquals([$monday], $this->counterparts($subject));
    }

    /**
     * A one-off outside the weekly ride's period does not match it.
     */
    public function test_a_weekly_period_bounds_the_match(): void {
        $this->weekly([1], 420, self::MONDAY, self::MONDAY);
        $inside = $this->one_off(self::MONDAY);
        $outside = $this->one_off(self::NEXT_MONDAY);

        $this->assertNotEmpty($this->counterparts($inside));
        $this->assertSame([], $this->counterparts($outside));
    }

    /**
     * Two weekly rides match when they share a weekday.
     */
    public function test_weekly_matches_weekly_on_a_shared_weekday(): void {
        $subject = $this->weekly([1, 3]);
        $shares = $this->weekly([3, 4]);
        $this->weekly([2, 5]);

        $this->assertEquals([$shares], $this->counterparts($subject));
    }

    /**
     * Two weekly rides sharing a weekday but not a period do not match.
     */
    public function test_weekly_periods_have_to_overlap(): void {
        $subject = $this->weekly([1], 420, '2026-08-01', '2026-08-31');
        $this->weekly([1], 420, '2026-10-01', '2026-10-31');

        $this->assertSame([], $this->counterparts($subject));
    }

    /**
     * By default the time of day does not narrow anything - the point is to introduce two people
     * who travel on the same day, and they settle the hour themselves.
     */
    public function test_the_default_tolerance_matches_across_the_whole_day(): void {
        $subject = $this->weekly([1], 6 * 60);
        $evening = $this->weekly([1], 21 * 60);

        $this->assertEquals([$evening], $this->counterparts($subject));
    }

    /**
     * A rule that asks for a narrow tolerance gets one.
     */
    public function test_a_narrow_tolerance_narrows_the_match(): void {
        $subject = $this->weekly([1], 6 * 60);
        $close = $this->weekly([1], 6 * 60 + 30);
        $this->weekly([1], 21 * 60);

        $this->assertEquals([$close], $this->counterparts($subject, 60));
    }

    /**
     * The regression this field exists for: an entry that says nothing about when it happens
     * matches nothing, instead of matching everything.
     */
    public function test_an_entry_without_a_schedule_matches_nothing(): void {
        $subject = $this->create_entry(['mode' => 'weekly', 'days' => [], 'start' => 420]);
        $this->weekly([1, 2, 3, 4, 5]);
        $this->one_off(self::MONDAY);

        $compiled = (new match_compiler($this->dlx))->compile(
            [['fieldid' => (int) $this->field->id(), 'op' => match_compiler::OP_WITHIN]],
            $subject
        );

        $this->assertTrue($compiled['impossible']);
        $this->assertSame([], $this->counterparts($subject));
    }

    /**
     * The criterion is an ordinary search criterion, not a post-filter, so it narrows before any
     * limit is applied rather than after it.
     */
    public function test_the_criterion_is_an_ordinary_search_criterion(): void {
        $subject = $this->one_off(self::MONDAY);

        $compiled = (new match_compiler($this->dlx))->compile(
            [['fieldid' => (int) $this->field->id(), 'op' => match_compiler::OP_WITHIN]],
            $subject
        );

        $this->assertSame([], $compiled['postfilters']);
        $this->assertArrayHasKey((int) $this->field->id(), $compiled['customsearch']);
    }

    /**
     * A one-off carries the weekday of its own date, which is what lets one predicate serve both
     * shapes.
     */
    public function test_a_one_off_carries_its_own_weekday(): void {
        $monday = schedule::from_json(json_encode(['mode' => 'once', 'date' => self::MONDAY, 'start' => 420]));
        $weekly = schedule::from_json(json_encode(['mode' => 'weekly', 'days' => [1], 'start' => 420]));

        $this->assertSame(1, $monday->weekday_bits(), 'bit 0 is Monday');
        $this->assertSame($weekly->weekday_bits(), $monday->weekday_bits());
    }
}
