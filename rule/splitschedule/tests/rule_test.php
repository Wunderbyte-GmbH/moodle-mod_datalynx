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

namespace datalynxrule_splitschedule;

use advanced_testcase;
use mod_datalynx\datalynx;
use mod_datalynx\local\ride\board_builder;
use mod_datalynx\local\ride\schedule;

/**
 * Split a weekly schedule into dated entries (mod_datalynx#342), on the ride sharing board.
 *
 * @package    datalynxrule_splitschedule
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxrule_splitschedule\rule
 * @covers     \mod_datalynx\local\ride\board_builder
 */
final class rule_test extends advanced_testcase {
    /** @var board_builder */
    protected board_builder $builder;

    /** @var int[] Field ids by name. */
    protected array $ids;

    /** @var \stdClass The driver. */
    protected \stdClass $driver;

    /** @var \stdClass */
    protected \stdClass $course;

    /**
     * A billing board and a driver.
     */
    protected function make_board(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        $this->builder = new board_builder($this->course, board_builder::VARIANT_BILLING);
        $this->builder->build();
        $this->ids = $this->builder->get_fieldids_by_name();
        $this->driver = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
    }

    /**
     * An offer of the driver with the given schedule, published or not.
     *
     * @param string $schedule JSON
     * @param bool $published
     * @return int entry id
     */
    protected function offer(string $schedule, bool $published): int {
        global $DB;
        $entryid = (int) $DB->insert_record('datalynx_entries', (object) ['dataid' => $this->builder->get_dataid(),
            'userid' => $this->driver->id, 'groupid' => 0, 'approved' => 1, 'status' => 0,
            'timecreated' => time(), 'timemodified' => time()]);
        $contents = [
            'Fahrttyp' => '1',
            'Termin' => $schedule,
            'Kennzeichen' => 'W-1234A',
            'Freie Plätze' => '3',
            'Passagiere' => json_encode([(int) $this->getDataGenerator()->create_and_enrol($this->course, 'student')->id]),
            'Route' => json_encode([['address' => 'Wien', 'lat' => 48.2, 'lng' => 16.37],
                ['address' => 'Graz', 'lat' => 47.07, 'lng' => 15.44]]),
        ];
        if ($published) {
            $contents['Veröffentlicht'] = '#1#';
        }
        foreach ($contents as $name => $content) {
            $DB->insert_record('datalynx_contents', (object) ['fieldid' => $this->ids[$name], 'entryid' => $entryid,
                'content' => $content]);
        }
        return $entryid;
    }

    /**
     * Fire "entry updated" for an entry, as a save does.
     *
     * @param int $entryid
     */
    protected function save(int $entryid): void {
        $dlx = new datalynx($this->builder->get_dataid());
        \mod_datalynx\event\entry_updated::create(['context' => $dlx->context, 'objectid' => $entryid,
            'other' => ['dataid' => $dlx->id(), 'changed_field_ids' => [$this->ids['Veröffentlicht']]]])->trigger();
    }

    /**
     * The entries of the board.
     *
     * @return int[]
     */
    protected function entries(): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_select(
            'datalynx_entries',
            'id',
            'dataid = ? ORDER BY id',
            [$this->builder->get_dataid()]
        ));
    }

    /**
     * The dates of a weekly schedule: its weekdays, from today or its start, to its end, at most months ahead.
     */
    public function test_dates(): void {
        $monday = strtotime('2027-03-01 12:00'); // A Monday.
        $weekly = fn(string $from, string $until) => new schedule(schedule::MODE_WEEKLY, '', [1, 3], 480, null, $from, $until);
        $this->assertSame(
            ['2027-03-01', '2027-03-03', '2027-03-08', '2027-03-10'],
            rule::dates($weekly('', '2027-03-11'), 6, $monday)
        );
        $this->assertSame(['2027-03-08', '2027-03-10'], rule::dates($weekly('2027-03-05', '2027-03-11'), 6, $monday));
        // At most one month ahead: 10 Mondays and Wednesdays from 1 March to 1 April.
        $this->assertCount(10, rule::dates($weekly('', '2027-12-31'), 1, $monday));
        $this->assertSame([], rule::dates(new schedule(schedule::MODE_ONCE, '2027-03-01'), 6, $monday));
    }

    /**
     * Publishing a weekly offer makes one entry per date; the offer is the first.
     */
    public function test_published_weekly_offer_is_split(): void {
        global $DB;
        $this->make_board();
        $today = usergetmidnight(time());
        $weekly = (new schedule(
            schedule::MODE_WEEKLY,
            '',
            [1, 3],
            7 * 60,
            9 * 60,
            date('Y-m-d', $today),
            date('Y-m-d', strtotime('+27 days', $today))
        ))->to_json();
        $draft = $this->offer($weekly, false);
        $this->save($draft);
        $this->assertSame([$draft], $this->entries(), 'an unpublished offer is not split');

        $offer = $this->offer($weekly, true);
        $this->save($offer);

        $expected = rule::dates(schedule::from_json($weekly), 6);
        $this->assertCount(8, $expected);
        $rides = array_values(array_diff($this->entries(), [$draft]));
        $this->assertCount(8, $rides);

        $dates = [];
        foreach ($rides as $ride) {
            $schedule = schedule::from_json($DB->get_field(
                'datalynx_contents',
                'content',
                ['entryid' => $ride, 'fieldid' => $this->ids['Termin']]
            ));
            $this->assertSame(schedule::MODE_ONCE, $schedule->mode());
            $this->assertSame(7 * 60, $schedule->start_minute());
            $dates[] = $schedule->date();
            $this->assertSame((int) $this->driver->id, (int) $DB->get_field('datalynx_entries', 'userid', ['id' => $ride]));
            $this->assertSame('W-1234A', $DB->get_field(
                'datalynx_contents',
                'content',
                ['entryid' => $ride, 'fieldid' => $this->ids['Kennzeichen']]
            ));
            if ($ride !== $offer) {
                $this->assertTrue($DB->record_exists('datalynx_waypoints', ['entryid' => $ride]), 'the route is indexed');
                $this->assertFalse($DB->record_exists(
                    'datalynx_contents',
                    ['entryid' => $ride, 'fieldid' => $this->ids['Passagiere']]
                ), 'a copy has no passengers');
            }
        }
        $this->assertSame($expected, $dates);

        // Saving again splits nothing more.
        $this->save($offer);
        $this->assertCount(9, $this->entries());

        // Every copy fires "entry created", so the other rules (matching) run for each ride.
        $another = $this->offer($weekly, true);
        $record = $DB->get_record('datalynx_rules', ['dataid' => $this->builder->get_dataid(), 'type' => 'splitschedule']);
        $events = $this->redirectEvents();
        (new rule(new datalynx($this->builder->get_dataid()), $record))->split($another);
        $created = array_filter($events->get_events(), fn($e) => $e instanceof \mod_datalynx\event\entry_created);
        $events->close();
        $this->assertCount(7, $created);
    }

    /**
     * A weekly schedule needs an end date at most 6 months ahead.
     */
    public function test_end_date_required(): void {
        $this->make_board();
        $dlx = new datalynx($this->builder->get_dataid());
        $renderer = $dlx->get_field_from_id($this->ids['Termin'], true)->renderer();
        $prefix = "field_{$this->ids['Termin']}_1";
        $submit = fn(string $until) => (object) ["{$prefix}_mode" => 'weekly', "{$prefix}_day1" => 1, "{$prefix}_start" => 480,
            "{$prefix}_until" => $until === '' ? 0 : strtotime($until)];
        $this->assertArrayHasKey("{$prefix}_until", $renderer->validate(1, [], $submit('')));
        $this->assertArrayHasKey("{$prefix}_until", $renderer->validate(1, [], $submit('+7 months')));
        $this->assertSame([], $renderer->validate(1, [], $submit('+5 months')));
    }

    /**
     * A ride with confirmed passengers cannot be deleted by its author; a manager can. Cancelling it
     * tells the passengers.
     */
    public function test_cancel_instead_of_delete(): void {
        global $DB;
        $this->make_board();
        $ride = $this->offer((new schedule(schedule::MODE_ONCE, date('Y-m-d', time() + 3 * DAYSECS), [], 480))->to_json(), true);
        $passenger = (int) json_decode($DB->get_field(
            'datalynx_contents',
            'content',
            ['entryid' => $ride, 'fieldid' => $this->ids['Passagiere']]
        ))[0];
        $dlx = new datalynx($this->builder->get_dataid());
        $entry = $DB->get_record('datalynx_entries', ['id' => $ride]);

        $this->setUser($this->driver);
        $this->assertFalse($dlx->user_can_delete_entry($entry));
        $this->setAdminUser();
        $this->assertTrue($dlx->user_can_delete_entry($entry));

        // Cancel: the passengers hear about it.
        $sink = $this->redirectMessages();
        $DB->insert_record('datalynx_contents', (object) ['fieldid' => $this->ids['Fahrtstatus'], 'entryid' => $ride,
            'content' => '2']);
        \mod_datalynx\event\entry_updated::create(['context' => $dlx->context, 'objectid' => $ride,
            'other' => ['dataid' => $dlx->id(), 'changed_field_ids' => [$this->ids['Fahrtstatus']]]])->trigger();
        ob_start();
        $this->run_all_adhoc_tasks();
        ob_end_clean();
        $to = array_map(fn($m) => (int) $m->useridto, $sink->get_messages());
        $sink->close();
        $this->assertContains($passenger, $to);
    }
}
