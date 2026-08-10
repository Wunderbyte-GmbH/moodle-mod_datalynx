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

namespace datalynxfield_schedule;

use mod_datalynx\local\field\datalynxfield_base;
use mod_datalynx\local\ride\schedule;
use mod_datalynx\local\rule\match_compiler;
use stdClass;

/**
 * Schedule field: when something happens, once or every week.
 *
 * The value is a JSON schedule in `content`, with the parts that have to be queried cached
 * beside it:
 *
 * | column   | contents                                                        |
 * |----------|-----------------------------------------------------------------|
 * | content  | the schedule as JSON ({@see schedule})                          |
 * | content1 | weekdays as a bitmask, bit 0 = Monday .. bit 6 = Sunday          |
 * | content2 | departure, minutes after local midnight                          |
 * | content3 | first instant the schedule is valid                              |
 * | content4 | last such instant, or 0 when it runs open ended                  |
 *
 * A one-off sets the bit of its own date's weekday and a validity window of exactly that day.
 * That is what removes every case distinction from the queries below: "runs on the Monday the
 * traveller asked about" is one test for both shapes, and the window narrows a one-off to its
 * actual date on its own. The same holds when comparing two entries, so a one-off request and a
 * weekly commute are compared by the same predicate that compares two one-offs.
 *
 * The derived columns are a cache, not the truth: `content` is authoritative and
 * {@see self::format_content()} rebuilds the rest from it on every save.
 *
 * @package    datalynxfield_schedule
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field extends datalynxfield_base {
    /** @var string Field type */
    public $type = 'schedule';

    /** @var int Tolerance meaning "anywhere on the day", which drops the time test entirely. */
    const TOLERANCE_WHOLE_DAY = schedule::DAY_MINUTES;

    /** @var bool Can be used inside fieldgroups. */
    protected $forfieldgroup = true;

    /**
     * The form values that together make up one schedule.
     *
     * The value is stored as one JSON blob, but it is entered through a control per part, so
     * every part has to be named here for {@see datalynxfield_base::get_content_from_data()} to
     * collect it. {@see self::schedule_from_values()} puts them back together.
     *
     * @return array
     */
    protected function content_names() {
        return array_merge(
            ['mode', 'date', 'start', 'end', 'from', 'until'],
            array_map(static fn($day) => "day{$day}", range(1, 7))
        );
    }

    /**
     * Content database columns this field writes.
     *
     * @return array
     */
    public function get_content_parts() {
        return ['content', 'content1', 'content2', 'content3', 'content4'];
    }

    /**
     * Grouping entries by a whole schedule is not meaningful.
     *
     * @return bool
     */
    public function supports_group_by() {
        return false;
    }

    /**
     * Schedules are searchable.
     *
     * @return bool
     */
    public function supports_search() {
        return true;
    }

    /**
     * Offer the schedule search in customfilters, so travellers can ask for a day.
     *
     * @return bool
     */
    public static function is_customfilterfield() {
        return true;
    }

    /**
     * One search is spread over a date, a time of day and a tolerance.
     *
     * @return bool
     */
    public function has_composite_search(): bool {
        return true;
    }

    /**
     * Step between the offered times of day, in minutes.
     *
     * @return int
     */
    public function get_granularity(): int {
        $granularity = (int) ($this->field->param1 ?? 0);

        return $granularity > 0 ? $granularity : 30;
    }

    /**
     * How far apart two departures may be and still count as the same journey, in minutes.
     *
     * A whole day is the default on purpose: a rule is there to introduce two people who might
     * share a ride, and the exact hour is something they will settle between themselves. Narrower
     * settings are for boards that really do run several departures a day.
     *
     * @return int
     */
    public function get_match_tolerance(): int {
        $tolerance = (int) ($this->field->param2 ?? 0);

        return ($tolerance > 0 && $tolerance < self::TOLERANCE_WHOLE_DAY)
            ? $tolerance : self::TOLERANCE_WHOLE_DAY;
    }

    /**
     * Which shapes this field accepts: 'both', 'once' or 'weekly'.
     *
     * @return string
     */
    public function get_allowed_mode(): string {
        $mode = (string) ($this->field->param3 ?? '');

        return in_array($mode, [schedule::MODE_ONCE, schedule::MODE_WEEKLY], true) ? $mode : 'both';
    }

    /**
     * Whether the field asks for an arrival time as well as a departure.
     *
     * @return bool
     */
    public function wants_arrival(): bool {
        return !empty($this->field->param4);
    }

    /**
     * Whether schedules whose validity window has passed are hidden from searches and matches.
     *
     * @return bool
     */
    public function hides_expired(): bool {
        return !empty($this->field->param5);
    }

    /**
     * The schedule an entry holds.
     *
     * @param stdClass|null $entry
     * @return schedule
     */
    public function schedule_of($entry): schedule {
        $fieldid = $this->field->id;

        return schedule::from_json($entry->{"c{$fieldid}_content"} ?? null);
    }

    /**
     * Build the stored value and its derived columns.
     *
     * @param stdClass $entry
     * @param array|null $values
     * @return array [$contents, $oldcontents]
     */
    protected function format_content(stdClass $entry, ?array $values = null) {
        $fieldid = $this->field->id;
        $oldcontents = [];
        $contents = [];

        if (isset($entry->{"c{$fieldid}_content"})) {
            foreach ($this->get_content_parts() as $part) {
                $suffix = $part === 'content' ? '' : substr($part, strlen('content'));
                $oldcontents[] = $entry->{"c{$fieldid}_content{$suffix}"} ?? null;
            }
        }

        if (empty($values)) {
            return [$contents, $oldcontents];
        }

        $schedule = self::schedule_from_values($values);

        // An incomplete schedule stores nothing at all, the way a one-stop itinerary does: a value
        // that cannot say when it happens must not be left behind to match everything.
        if (!$schedule->is_valid()) {
            return [$contents, $oldcontents];
        }

        [$from, $until] = $schedule->window();

        $contents[] = $schedule->to_json();
        $contents[] = (string) $schedule->weekday_bits();
        $contents[] = (string) $schedule->start_minute();
        $contents[] = (string) $from;
        $contents[] = (string) $until;

        return [$contents, $oldcontents];
    }

    /**
     * Assemble the submitted controls into one schedule.
     *
     * Also accepts a plain JSON string under a `schedule` key, which is how an import or a
     * migration supplies a ready-made value.
     *
     * @param array $values as collected by {@see datalynxfield_base::get_content_from_data()}
     * @return schedule
     */
    public static function schedule_from_values(array $values): schedule {
        if (isset($values['schedule'])) {
            return schedule::from_json((string) $values['schedule']);
        }

        $read = static function (string $key) use ($values) {
            return array_key_exists($key, $values) ? $values[$key] : null;
        };

        $mode = (string) $read('mode');
        $start = $read('start');
        $end = $read('end');
        $days = [];
        for ($day = 1; $day <= 7; $day++) {
            if (!empty($read("day{$day}"))) {
                $days[] = $day;
            }
        }
        $todate = static function ($value): string {
            $value = (int) $value;

            return $value > 0 ? schedule::date_of($value) : '';
        };

        return new schedule(
            $mode === schedule::MODE_WEEKLY ? schedule::MODE_WEEKLY : schedule::MODE_ONCE,
            $todate($read('date')),
            $days,
            ($start === null || $start === '') ? null : (int) $start,
            ($end === null || $end === '') ? null : (int) $end,
            $todate($read('from')),
            $todate($read('until'))
        );
    }

    /**
     * A schedule can be compared with the one another entry holds.
     *
     * @return string[]
     */
    public function supported_relative_criteria(): array {
        return [match_compiler::OP_WITHIN];
    }

    /**
     * {@inheritDoc}
     *
     * Turns "runs when this entry runs" into an ordinary search criterion. Unlike a route
     * corridor, which has to be tested in either direction and therefore cannot live in the
     * filter engine's single flat AND group, a schedule overlap is symmetric and compiles to one
     * entry-id set.
     *
     * @param string $relation
     * @param string $storedvalue the other entry's stored schedule JSON
     * @param array $options ['tolerance' => float] in seconds
     * @return array|null
     * @see datalynxfield_base::compile_relative_criterion()
     */
    public function compile_relative_criterion(string $relation, string $storedvalue, array $options = []): ?array {
        if ($relation !== match_compiler::OP_WITHIN) {
            return null;
        }

        $schedule = schedule::from_json($storedvalue);
        if (!$schedule->is_valid()) {
            return null;
        }

        $tolerance = isset($options['tolerance']) && $options['tolerance'] > 0
            ? (int) round(((float) $options['tolerance']) / MINSECS)
            : $this->get_match_tolerance();

        return ['', '', [
            'overlaps' => $schedule->export(),
            'tolerance' => min($tolerance, self::TOLERANCE_WHOLE_DAY),
        ]];
    }

    /**
     * Read the search form.
     *
     * @param stdClass $formdata
     * @param int $i
     * @return array|bool
     */
    public function parse_search($formdata, $i) {
        $prefix = "f_{$i}_{$this->field->id}";
        $read = function (string $suffix) use ($formdata, $prefix) {
            $key = "{$prefix}_{$suffix}";

            return isset($formdata->$key) ? $formdata->$key : null;
        };

        $date = (int) ($read('date') ?: 0);
        $minute = $read('minute');
        $tolerance = (int) ($read('tolerance') ?: 0);

        // Neither half given is not a search, the way an itinerary with no end is not one.
        if (!$date && ($minute === null || $minute === '')) {
            return false;
        }

        return [
            'date' => $date,
            'minute' => ($minute === null || $minute === '') ? null : (int) $minute,
            'tolerance' => $tolerance > 0 ? $tolerance : $this->get_match_tolerance(),
        ];
    }

    /**
     * Entries whose schedule covers the day (and time) that was asked for.
     *
     * Returns an entry-id set rather than a condition on the joined content table, so
     * `$fromcontent` is false and the filter neither joins `datalynx_contents` for this field nor
     * qualifies the clause with a content alias.
     *
     * @param array $search [$not, $operator, $value]
     * @return array [$sql, $params, $fromcontent]
     */
    public function get_search_sql(array $search): array {
        [$not, , $value] = $search;

        if (!is_array($value)) {
            return ['', [], false];
        }

        $subquery = isset($value['overlaps'])
            ? $this->overlap_subquery($value)
            : $this->coverage_subquery($value);
        if ($subquery === null) {
            return ['', [], false];
        }

        [$sql, $params] = $subquery;
        $operator = $not ? 'NOT IN' : 'IN';

        return ["e.id {$operator} ({$sql})", $params, false];
    }

    /**
     * Entries running on a given date, optionally near a given time.
     *
     * @param array $value as returned by {@see self::parse_search()}
     * @return array|null [$sql, $params]
     */
    protected function coverage_subquery(array $value): ?array {
        $date = (int) ($value['date'] ?? 0);
        $minute = $value['minute'] ?? null;
        $tolerance = (int) ($value['tolerance'] ?? self::TOLERANCE_WHOLE_DAY);

        $conditions = [];
        $params = [];
        $suffix = self::next_suffix();

        if ($date) {
            // The date decides the weekday, which is how a traveller asking for the 10th of
            // August finds the commute that runs every Monday: the 10th is a Monday.
            $daystart = (int) schedule::day_start(schedule::date_of($date));
            $weekday = (int) schedule::weekday_of(schedule::date_of($date));
            $conditions[] = $this->weekday_condition(1 << ($weekday - 1), $suffix, $params);
            $conditions[] = "{$this->numeric('content3')} <= :schdayend{$suffix}";
            $conditions[] = "({$this->numeric('content4')} = 0"
                . " OR {$this->numeric('content4')} >= :schdaystart{$suffix})";
            $params["schdayend{$suffix}"] = $daystart + DAYSECS - 1;
            $params["schdaystart{$suffix}"] = $daystart;
        }

        if ($minute !== null && $tolerance < self::TOLERANCE_WHOLE_DAY) {
            $conditions[] = $this->minute_condition((int) $minute, $tolerance, $suffix, $params);
        }

        if ($this->hides_expired()) {
            $conditions[] = "({$this->numeric('content4')} = 0"
                . " OR {$this->numeric('content4')} >= :schnow{$suffix})";
            $params["schnow{$suffix}"] = time();
        }

        return $conditions ? [$this->wrap($conditions, $params, $suffix), $params] : null;
    }

    /**
     * Entries whose schedule overlaps the one given.
     *
     * Every term is a constant taken from the other entry, so the three cases - two one-offs, a
     * one-off and a weekly, two weeklies - are the same query.
     *
     * @param array $value ['overlaps' => exported schedule, 'tolerance' => minutes]
     * @return array|null [$sql, $params]
     */
    protected function overlap_subquery(array $value): ?array {
        $other = schedule::from_json((string) json_encode($value['overlaps']));
        if (!$other->is_valid()) {
            return null;
        }
        $tolerance = (int) ($value['tolerance'] ?? self::TOLERANCE_WHOLE_DAY);

        $suffix = self::next_suffix();
        $params = [];
        [$from, $until] = $other->window();

        $conditions = [$this->weekday_condition($other->weekday_bits(), $suffix, $params)];

        // The validity windows have to overlap: this one starts before the other ends, and ends
        // after the other starts. An open ended window drops its half of the test.
        if ($until !== 0) {
            $conditions[] = "{$this->numeric('content3')} <= :schouruntil{$suffix}";
            $params["schouruntil{$suffix}"] = $until;
        }
        $conditions[] = "({$this->numeric('content4')} = 0"
            . " OR {$this->numeric('content4')} >= :schourfrom{$suffix})";
        $params["schourfrom{$suffix}"] = $from;

        if ($tolerance < self::TOLERANCE_WHOLE_DAY && $other->start_minute() !== null) {
            $conditions[] = $this->minute_condition($other->start_minute(), $tolerance, $suffix, $params);
        }

        if ($this->hides_expired()) {
            $conditions[] = "({$this->numeric('content4')} = 0"
                . " OR {$this->numeric('content4')} >= :schnow{$suffix})";
            $params["schnow{$suffix}"] = time();
        }

        return [$this->wrap($conditions, $params, $suffix), $params];
    }

    /**
     * "Runs on at least one of these weekdays".
     *
     * @param int $bits weekday bitmask
     * @param string $suffix placeholder suffix
     * @param array $params
     * @return string
     */
    protected function weekday_condition(int $bits, string $suffix, array &$params): string {
        global $DB;

        $params["schdays{$suffix}"] = $bits;

        return $DB->sql_bitand($this->numeric('content1'), ":schdays{$suffix}") . ' <> 0';
    }

    /**
     * "Departs within the tolerance of this minute of the day".
     *
     * The window is clamped rather than wrapped around midnight: a board matching at
     * day granularity - the default - never reaches this, and a board that has narrowed the
     * tolerance is asking about a time of day, not about the small hours of the next one.
     *
     * @param int $minute
     * @param int $tolerance minutes
     * @param string $suffix
     * @param array $params
     * @return string
     */
    protected function minute_condition(int $minute, int $tolerance, string $suffix, array &$params): string {
        $params["schminlo{$suffix}"] = max(0, $minute - $tolerance);
        $params["schminhi{$suffix}"] = min(schedule::DAY_MINUTES - 1, $minute + $tolerance);

        return "{$this->numeric('content2')} BETWEEN :schminlo{$suffix} AND :schminhi{$suffix}";
    }

    /**
     * Wrap the conditions into a subquery over this field's content rows.
     *
     * @param array $conditions
     * @param array $params
     * @param string $suffix
     * @return string
     */
    protected function wrap(array $conditions, array &$params, string $suffix): string {
        $params["schfield{$suffix}"] = $this->field->id;

        return 'SELECT entryid FROM {datalynx_contents} WHERE fieldid = :schfield' . $suffix
            . ' AND ' . implode(' AND ', $conditions);
    }

    /**
     * The derived columns are TEXT, so every comparison has to cast.
     *
     * @param string $column
     * @return string
     */
    protected function numeric(string $column): string {
        global $DB;

        return $DB->sql_cast_char2int($column, true);
    }

    /**
     * A per-request counter keeping placeholder names unique.
     *
     * @return string
     */
    protected static function next_suffix(): string {
        static $sequence = 0;

        return (string) ++$sequence;
    }

    /**
     * A schedule either covers the day asked about or it does not.
     *
     * @return array
     */
    public function get_supported_search_operators() {
        return ['' => get_string('runson', 'datalynxfield_schedule')];
    }

    /**
     * Describe a stored criterion for the filter overview.
     *
     * @param array $searchparams
     * @return string
     */
    public function format_search_value($searchparams) {
        [, , $value] = $searchparams;
        if (!is_array($value)) {
            return '';
        }

        if (isset($value['overlaps'])) {
            return get_string('searchoverlaps', 'datalynxfield_schedule');
        }

        $parts = [];
        if (!empty($value['date'])) {
            $parts[] = userdate((int) $value['date'], get_string('strftimedaydate', 'core_langconfig'));
        }
        if (isset($value['minute']) && $value['minute'] !== null) {
            $parts[] = self::format_minute((int) $value['minute']);
        }
        $tolerance = (int) ($value['tolerance'] ?? self::TOLERANCE_WHOLE_DAY);
        if ($tolerance < self::TOLERANCE_WHOLE_DAY) {
            $parts[] = '± ' . format_time($tolerance * MINSECS);
        }

        return ' ' . implode(', ', $parts);
    }

    /**
     * A minute of the day as HH:MM.
     *
     * @param int $minute
     * @return string
     */
    public static function format_minute(int $minute): string {
        return sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }

    /**
     * The times of day this field offers, keyed by minute.
     *
     * @return array
     */
    public function times_menu(): array {
        $times = [];
        for ($minute = 0; $minute < schedule::DAY_MINUTES; $minute += $this->get_granularity()) {
            $times[$minute] = self::format_minute($minute);
        }

        return $times;
    }

    /**
     * The weekdays, keyed by ISO weekday number.
     *
     * @return array
     */
    public static function weekdays_menu(): array {
        $days = [];
        // 1 January 2024 was a Monday, which yields the localised day names without a hardcoded list.
        $monday = make_timestamp(2024, 1, 1, 12);
        for ($day = 1; $day <= 7; $day++) {
            $days[$day] = userdate($monday + ($day - 1) * DAYSECS, '%A');
        }

        return $days;
    }

    /**
     * Is this an empty value?
     *
     * @param mixed $value
     * @return bool
     */
    public static function is_fieldvalue_empty($value) {
        return !schedule::from_json((string) $value)->is_valid();
    }
}
