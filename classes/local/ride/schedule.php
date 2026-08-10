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

namespace mod_datalynx\local\ride;

use DateTimeImmutable;

/**
 * When something happens: one date, or a weekly pattern.
 *
 * A journey that runs every Monday has no date, and a journey on the 13th of August has no
 * weekday pattern - yet both have to be comparable, because a traveller asking for "Monday the
 * 10th" wants both the one-off ride that day and the commute that runs every Monday. Holding both
 * shapes in one value is what makes that comparison possible: the mode travels with the data
 * instead of being inferred from which of several fields happens to be filled in.
 *
 * The trick that removes all case analysis downstream is {@see self::weekday_bits()}: a one-off
 * sets the bit of *its own* date's weekday, so "does this run on a Monday" is the same test for
 * both shapes, and the validity window - a single day for a one-off - narrows it to the right
 * date on its own.
 *
 * Weekdays and times of day are wall clock in the **site** timezone, on both write and read. A
 * recurring "Monday 18:00" is not an instant, so it cannot be stored as one; interpreting it in
 * the viewer's timezone instead would move it to a different weekday for anyone abroad.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schedule {
    /** @var string A single date and time. */
    const MODE_ONCE = 'once';

    /** @var string A time of day on one or more weekdays. */
    const MODE_WEEKLY = 'weekly';

    /** @var int Minutes in a day, the exclusive upper bound of a time of day. */
    const DAY_MINUTES = 1440;

    /**
     * Constructor.
     *
     * @param string $mode self::MODE_ONCE or self::MODE_WEEKLY
     * @param string $date ISO date (Y-m-d) for a one-off, empty for a weekly
     * @param int[] $days ISO weekdays 1..7 for a weekly, empty for a one-off
     * @param int|null $start departure, minutes after local midnight
     * @param int|null $end arrival, minutes after local midnight; display only
     * @param string $from ISO date the pattern starts being valid, empty for "immediately"
     * @param string $until ISO date it stops being valid, empty for "open ended"
     */
    public function __construct(
        /** @var string Which shape this schedule has. */
        protected string $mode = self::MODE_ONCE,
        /** @var string ISO date of a one-off. */
        protected string $date = '',
        /** @var int[] ISO weekdays of a weekly pattern. */
        protected array $days = [],
        /** @var int|null Departure, minutes after local midnight. */
        protected ?int $start = null,
        /** @var int|null Arrival, minutes after local midnight. */
        protected ?int $end = null,
        /** @var string ISO date the pattern starts being valid. */
        protected string $from = '',
        /** @var string ISO date the pattern stops being valid. */
        protected string $until = ''
    ) {
        $this->days = self::clean_days($days);
    }

    /**
     * Build from the JSON stored in `content`.
     *
     * Unusable parts are dropped rather than failing the whole value, matching
     * {@see itinerary::from_json()}: a half-broken schedule still yields whatever is comparable,
     * and {@see self::is_valid()} is what decides whether it is worth storing.
     *
     * @param string|null $json
     * @return self
     */
    public static function from_json(?string $json): self {
        $decoded = json_decode((string) $json, true);
        if (!is_array($decoded)) {
            return new self('');
        }

        $mode = (string) ($decoded['mode'] ?? '');
        if ($mode !== self::MODE_ONCE && $mode !== self::MODE_WEEKLY) {
            return new self('');
        }

        return new self(
            $mode,
            self::clean_date($decoded['date'] ?? ''),
            (array) ($decoded['days'] ?? []),
            self::clean_minute($decoded['start'] ?? null),
            self::clean_minute($decoded['end'] ?? null),
            self::clean_date($decoded['from'] ?? ''),
            self::clean_date($decoded['until'] ?? '')
        );
    }

    /**
     * The canonical JSON for `datalynx_contents.content`.
     *
     * @return string
     */
    public function to_json(): string {
        return (string) json_encode($this->export(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * The value as an array, in a stable key order.
     *
     * @return array
     */
    public function export(): array {
        if ($this->mode === self::MODE_ONCE) {
            return [
                'mode' => $this->mode,
                'date' => $this->date,
                'start' => $this->start,
                'end' => $this->end,
            ];
        }

        return [
            'mode' => $this->mode,
            'days' => $this->days,
            'start' => $this->start,
            'end' => $this->end,
            'from' => $this->from,
            'until' => $this->until,
        ];
    }

    /**
     * Whether this schedule says enough to be stored and compared.
     *
     * The analogue of {@see itinerary::is_route()}: a one-off needs a date, a weekly needs at
     * least one weekday, and both need a departure - without one there is no time of day to
     * compare and the value would silently match everything.
     *
     * @return bool
     */
    public function is_valid(): bool {
        if ($this->start === null) {
            return false;
        }
        if ($this->mode === self::MODE_ONCE) {
            return $this->date !== '';
        }
        if ($this->mode === self::MODE_WEEKLY) {
            return (bool) $this->days && !$this->window_is_impossible();
        }

        return false;
    }

    /**
     * The weekdays this runs on, as a bitmask: bit 0 is Monday, bit 6 is Sunday.
     *
     * A one-off contributes the bit of its own date, which is what lets one predicate serve both
     * shapes - "runs on a Monday" needs no knowledge of which shape it is asking about.
     *
     * @return int 0 when nothing can be derived
     */
    public function weekday_bits(): int {
        if ($this->mode === self::MODE_ONCE) {
            $day = self::weekday_of($this->date);

            return $day === null ? 0 : (1 << ($day - 1));
        }

        $bits = 0;
        foreach ($this->days as $day) {
            $bits |= 1 << ($day - 1);
        }

        return $bits;
    }

    /**
     * Departure as minutes after local midnight.
     *
     * @return int|null
     */
    public function start_minute(): ?int {
        return $this->start;
    }

    /**
     * Arrival as minutes after local midnight, when the field asks for one.
     *
     * @return int|null
     */
    public function end_minute(): ?int {
        return $this->end;
    }

    /**
     * The mode.
     *
     * @return string
     */
    public function mode(): string {
        return $this->mode;
    }

    /**
     * The weekdays of a weekly pattern.
     *
     * @return int[] ISO weekdays, ascending
     */
    public function days(): array {
        return $this->days;
    }

    /**
     * The ISO date of a one-off.
     *
     * @return string
     */
    public function date(): string {
        return $this->date;
    }

    /**
     * The period during which this schedule produces occurrences.
     *
     * A one-off's window is exactly its own day, which is what narrows the weekday test down to
     * the right date without any special casing. A weekly pattern with no end runs open ended.
     *
     * @return array [$from, $until] unix timestamps; $until is 0 when open ended
     */
    public function window(): array {
        if ($this->mode === self::MODE_ONCE) {
            $start = self::day_start($this->date);

            return $start === null ? [0, 0] : [$start, $start + DAYSECS - 1];
        }

        $from = self::day_start($this->from);
        $until = self::day_start($this->until);

        return [$from ?? 0, $until === null ? 0 : $until + DAYSECS - 1];
    }

    /**
     * ISO date the weekly pattern stops being valid, or '' when open ended.
     *
     * @return string
     */
    public function until(): string {
        return $this->until;
    }

    /**
     * ISO date the weekly pattern starts being valid, or '' when it starts immediately.
     *
     * @return string
     */
    public function from(): string {
        return $this->from;
    }

    /**
     * Whether a given instant falls inside the validity window.
     *
     * @param int $timestamp
     * @return bool
     */
    public function covers(int $timestamp): bool {
        [$from, $until] = $this->window();

        return $timestamp >= $from && ($until === 0 || $timestamp <= $until);
    }

    /**
     * Whether the validity window has passed.
     *
     * @param int|null $now
     * @return bool
     */
    public function has_expired(?int $now = null): bool {
        [, $until] = $this->window();

        return $until !== 0 && $until < ($now ?? time());
    }

    /**
     * A weekly window whose end is before its start produces no occurrence at all.
     *
     * @return bool
     */
    private function window_is_impossible(): bool {
        $from = self::day_start($this->from);
        $until = self::day_start($this->until);

        return $from !== null && $until !== null && $until < $from;
    }

    /**
     * The ISO weekday of a date, read in the site timezone.
     *
     * @param string $date ISO date
     * @return int|null 1 = Monday .. 7 = Sunday
     */
    public static function weekday_of(string $date): ?int {
        $day = self::date_in_site_timezone($date);

        return $day === null ? null : (int) $day->format('N');
    }

    /**
     * Midnight of a date, in the site timezone.
     *
     * @param string $date ISO date
     * @return int|null unix timestamp
     */
    public static function day_start(string $date): ?int {
        $day = self::date_in_site_timezone($date);

        return $day === null ? null : $day->getTimestamp();
    }

    /**
     * The ISO date an instant falls on, in the site timezone.
     *
     * @param int $timestamp
     * @return string
     */
    public static function date_of(int $timestamp): string {
        return (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone(\core_date::get_server_timezone_object())
            ->format('Y-m-d');
    }

    /**
     * The minute of the day an instant falls on, in the site timezone.
     *
     * @param int $timestamp
     * @return int 0..1439
     */
    public static function minute_of(int $timestamp): int {
        $local = (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone(\core_date::get_server_timezone_object());

        return ((int) $local->format('G')) * 60 + (int) $local->format('i');
    }

    /**
     * Parse an ISO date at midnight in the site timezone.
     *
     * @param string $date
     * @return DateTimeImmutable|null
     */
    private static function date_in_site_timezone(string $date) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        try {
            return new DateTimeImmutable($date . ' 00:00:00', \core_date::get_server_timezone_object());
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Keep only real ISO weekdays, sorted and without repeats.
     *
     * @param array $days
     * @return int[]
     */
    private static function clean_days(array $days): array {
        $clean = [];
        foreach ($days as $day) {
            $day = (int) $day;
            if ($day >= 1 && $day <= 7) {
                $clean[$day] = $day;
            }
        }
        ksort($clean);

        return array_values($clean);
    }

    /**
     * Keep only a well formed ISO date.
     *
     * @param mixed $value
     * @return string
     */
    private static function clean_date($value): string {
        $value = trim((string) $value);

        return self::date_in_site_timezone($value) === null ? '' : $value;
    }

    /**
     * Keep only a real minute of the day.
     *
     * @param mixed $value
     * @return int|null
     */
    private static function clean_minute($value): ?int {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        $minute = (int) $value;

        return ($minute >= 0 && $minute < self::DAY_MINUTES) ? $minute : null;
    }
}
