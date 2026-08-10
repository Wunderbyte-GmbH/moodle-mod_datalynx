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
 * Tests that searching a schedule by day works through the real filter pipeline.
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

/**
 * The field returns an entry-id subquery with `$fromcontent = false` rather than a condition on a
 * joined content alias, so it is worth proving end to end rather than only unit testing the SQL.
 *
 * @group mod_datalynx
 * @covers \datalynxfield_schedule\field::get_search_sql
 * @covers \datalynxfield_schedule\field::parse_search
 * @covers \mod_datalynx\local\ride\schedule
 */
final class search_test extends advanced_testcase {
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
     * @param array $value the schedule as it is stored
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
     * A ride happening once, on the given date.
     *
     * @param string $date
     * @param int $minute
     * @return int
     */
    protected function create_once(string $date, int $minute = 420): int {
        return $this->create_entry(['mode' => 'once', 'date' => $date, 'start' => $minute]);
    }

    /**
     * A ride happening every week on the given weekdays.
     *
     * @param int[] $days
     * @param int $minute
     * @param string $from
     * @param string $until
     * @return int
     */
    protected function create_weekly(array $days, int $minute = 420, string $from = '', string $until = ''): int {
        return $this->create_entry([
            'mode' => 'weekly', 'days' => $days, 'start' => $minute, 'from' => $from, 'until' => $until,
        ]);
    }

    /**
     * Search through the filter pipeline.
     *
     * @param string|null $date ISO date, or null for "any day"
     * @param int|null $minute time of day, or null for "any time"
     * @param int $tolerance minutes
     * @return int[] matching entry ids
     */
    protected function browse(?string $date, ?int $minute = null, int $tolerance = field::TOLERANCE_WHOLE_DAY): array {
        $value = [
            'date' => $date === null ? 0 : (int) schedule::day_start($date),
            'minute' => $minute,
            'tolerance' => $tolerance,
        ];

        $filter = new datalynx_filter((object) [
            'dataid' => $this->dlx->id(),
            'id' => 0,
            'customsearch' => [(int) $this->field->id() => ['AND' => [['', '', $value]]]],
        ]);

        $entries = new datalynx_entries($this->dlx, $filter);
        $entries->set_content();

        return array_map('intval', array_keys($entries->entries() ?: []));
    }

    /**
     * The case this whole field exists for: asking for a date finds the ride that runs every week
     * on that date's weekday, because the 10th of August 2026 is a Monday.
     */
    public function test_a_date_finds_the_weekly_ride_on_that_weekday(): void {
        $weekly = $this->create_weekly([1]);

        $this->assertEquals([$weekly], $this->browse(self::MONDAY));
        $this->assertSame([], $this->browse(self::TUESDAY));
    }

    /**
     * A one-off is found on its own date and on no other, even one with the same weekday.
     */
    public function test_a_one_off_is_found_only_on_its_date(): void {
        $once = $this->create_once(self::MONDAY);

        $this->assertEquals([$once], $this->browse(self::MONDAY));
        $this->assertSame([], $this->browse(self::NEXT_MONDAY), 'same weekday, different date');
        $this->assertSame([], $this->browse(self::TUESDAY));
    }

    /**
     * Both kinds come back from one search - which is what the four separate fields could never do.
     */
    public function test_both_kinds_are_found_by_the_same_search(): void {
        $once = $this->create_once(self::MONDAY);
        $weekly = $this->create_weekly([1, 3]);

        $this->assertEqualsCanonicalizing([$once, $weekly], $this->browse(self::MONDAY));
    }

    /**
     * A weekly pattern is only found inside the period it runs.
     */
    public function test_a_weekly_ride_is_bounded_by_its_period(): void {
        $weekly = $this->create_weekly([1], 420, self::MONDAY, self::MONDAY);

        $this->assertEquals([$weekly], $this->browse(self::MONDAY));
        $this->assertSame([], $this->browse(self::NEXT_MONDAY), 'the period has passed');
    }

    /**
     * An open ended weekly pattern keeps being found.
     */
    public function test_an_open_ended_weekly_ride_has_no_last_date(): void {
        $weekly = $this->create_weekly([1]);

        $this->assertEquals([$weekly], $this->browse(self::NEXT_MONDAY));
    }

    /**
     * At the default tolerance the time of day does not narrow anything: a traveller looking for
     * a Monday ride is shown the Monday rides, whatever time they leave.
     */
    public function test_the_whole_day_tolerance_ignores_the_time(): void {
        $early = $this->create_weekly([1], 6 * 60);
        $late = $this->create_weekly([1], 21 * 60);

        $this->assertEqualsCanonicalizing([$early, $late], $this->browse(self::MONDAY, 7 * 60));
    }

    /**
     * A narrower tolerance does narrow it.
     */
    public function test_a_narrow_tolerance_narrows_by_time(): void {
        $early = $this->create_weekly([1], 6 * 60);
        $this->create_weekly([1], 21 * 60);

        $this->assertEquals([$early], $this->browse(self::MONDAY, 7 * 60, 60));
    }

    /**
     * A time on its own, with no date, searches every weekday.
     */
    public function test_a_time_without_a_date_searches_every_day(): void {
        $monday = $this->create_weekly([1], 7 * 60);
        $friday = $this->create_weekly([5], 7 * 60);
        $this->create_weekly([1], 21 * 60);

        $this->assertEqualsCanonicalizing([$monday, $friday], $this->browse(null, 7 * 60, 60));
    }

    /**
     * An empty form is not a search.
     */
    public function test_an_empty_form_is_not_a_search(): void {
        $formdata = (object) [
            "f_1_{$this->field->id()}_date" => 0,
            "f_1_{$this->field->id()}_minute" => '',
        ];

        $this->assertFalse($this->field->parse_search($formdata, 1));
    }

    /**
     * The search resolves to an entry-id set, not to a condition on a joined content row.
     */
    public function test_the_search_returns_an_entry_id_set(): void {
        [$sql, $params, $fromcontent] = $this->field->get_search_sql(['', '', [
            'date' => (int) schedule::day_start(self::MONDAY), 'minute' => null,
            'tolerance' => field::TOLERANCE_WHOLE_DAY,
        ]]);

        $this->assertStringStartsWith('e.id IN (', $sql);
        $this->assertFalse($fromcontent);
        $this->assertNotEmpty($params);
    }

    /**
     * An entry whose schedule says nothing is stored empty, so it can never match everything.
     */
    public function test_an_incomplete_schedule_stores_nothing(): void {
        global $DB;

        $entryid = $this->create_entry(['mode' => 'weekly', 'days' => [], 'start' => 420]);

        $this->assertFalse($DB->record_exists('datalynx_contents', [
            'entryid' => $entryid, 'fieldid' => $this->field->id(),
        ]));
        $this->assertSame([], $this->browse(self::MONDAY));
    }

    /**
     * Expired schedules drop out of searches when the field asks for that.
     */
    public function test_expired_schedules_can_be_hidden(): void {
        global $DB;

        $past = schedule::date_of(time() - 30 * DAYSECS);
        $entryid = $this->create_once($past);

        $this->assertEquals([$entryid], $this->browse($past));

        // The filter reads the field through the datalynx instance, which caches it, so the whole
        // instance has to be rebuilt for the changed setting to be seen.
        $DB->set_field('datalynx_fields', 'param5', 1, ['id' => $this->field->id()]);
        $fieldid = (int) $this->field->id();
        $this->dlx = new datalynx($this->dlx->id());
        $this->field = $this->dlx->get_fields(null, false, true)[$fieldid];

        $this->assertSame([], $this->browse($past));
    }
}
