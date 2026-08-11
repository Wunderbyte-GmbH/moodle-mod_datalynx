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

use mod_datalynx\datalynx;

/**
 * Turns a ride board's four "when" fields into one schedule value.
 *
 * A board built before the schedule field described its timing with a pattern radio button, a
 * weekday multi-select, a time-of-day select and - for one-off rides only - a date on the first
 * stop of the itinerary. Nothing enforced which of those applied, so entries exist that carry
 * both, and some carry a date whose weekday contradicts the weekdays they claim to run on.
 *
 * This resolves each entry to a single schedule and reports every one where it had to choose,
 * so a human can look. Nothing is guessed silently.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schedule_migrator {
    /** @var datalynx The activity being migrated. */
    protected datalynx $dlx;

    /** @var int Field id of the pattern radio button (Einmalig / Regelmäßig). */
    protected int $patternfieldid;

    /** @var int Field id of the weekday multi-select. */
    protected int $weekdayfieldid;

    /** @var int Field id of the departure time select. */
    protected int $timefieldid;

    /** @var int Field id of the itinerary whose stop times carried one-off dates. */
    protected int $itineraryfieldid;

    /** @var int Option position of the "recurring" choice. */
    protected int $recurringoption;

    /** @var int Minutes per step of the time select. */
    protected int $timestep;

    /** @var string[] Notes about entries where the two representations disagreed. */
    protected array $notes = [];

    /**
     * Constructor.
     *
     * @param datalynx $dlx
     * @param array $fieldids ['pattern', 'weekday', 'time', 'itinerary'] => field id, 0 when absent
     * @param int $recurringoption option position meaning "recurring"
     * @param int $timestep minutes between the time select's options
     */
    public function __construct(
        datalynx $dlx,
        array $fieldids,
        int $recurringoption = 2,
        int $timestep = 30
    ) {
        $this->dlx = $dlx;
        $this->patternfieldid = (int) ($fieldids['pattern'] ?? 0);
        $this->weekdayfieldid = (int) ($fieldids['weekday'] ?? 0);
        $this->timefieldid = (int) ($fieldids['time'] ?? 0);
        $this->itineraryfieldid = (int) ($fieldids['itinerary'] ?? 0);
        $this->recurringoption = $recurringoption;
        $this->timestep = $timestep > 0 ? $timestep : 30;
    }

    /**
     * The schedule an entry's old fields describe.
     *
     * @param array $values the entry's stored values, keyed by field id
     * @param int $timecreated used as the start of a recurring pattern's validity
     * @return schedule|null null when the entry says nothing about when it happens
     */
    public function schedule_for(array $values, int $timecreated = 0): ?schedule {
        $pattern = (string) ($values[$this->patternfieldid] ?? '');
        $weekdays = self::parse_option_list((string) ($values[$this->weekdayfieldid] ?? ''));
        $timeoption = (string) ($values[$this->timefieldid] ?? '');
        $departure = (int) ($values['departure'] ?? 0);

        $isrecurring = $pattern !== '' && (int) $pattern === $this->recurringoption;
        $minute = $this->minute_of_option($timeoption);

        if ($isrecurring) {
            if (!$weekdays && $departure) {
                // Marked recurring but described by a date. The date's weekday is the only thing
                // it can mean, and its time of day is the only departure available.
                $weekdays = [(int) schedule::weekday_of(schedule::date_of($departure))];
                $minute ??= schedule::minute_of($departure);
                $this->note('recurring entry described only by a date; taking its weekday');
            }
            if (!$weekdays || $minute === null) {
                $this->note('recurring entry with no weekdays and no time; left without a schedule');

                return null;
            }
            if ($departure) {
                $this->note('recurring entry also carried a date on its route; the weekdays win');
            }

            return new schedule(
                schedule::MODE_WEEKLY,
                '',
                $weekdays,
                $minute,
                null,
                $timecreated ? schedule::date_of($timecreated) : '',
                ''
            );
        }

        if ($departure) {
            if ($weekdays) {
                $this->note('one-off entry also carried weekdays; the date wins');
            }

            return new schedule(
                schedule::MODE_ONCE,
                schedule::date_of($departure),
                [],
                $minute ?? schedule::minute_of($departure)
            );
        }

        if ($weekdays && $minute !== null) {
            // No pattern set, but it is described like a recurring one.
            $this->note('entry with weekdays but no pattern; treated as recurring');

            return new schedule(
                schedule::MODE_WEEKLY,
                '',
                $weekdays,
                $minute,
                null,
                $timecreated ? schedule::date_of($timecreated) : '',
                ''
            );
        }

        return null;
    }

    /**
     * Notes about the entries where the old fields disagreed.
     *
     * @return string[]
     */
    public function notes(): array {
        return $this->notes;
    }

    /**
     * Forget the notes, so they can be collected per entry.
     */
    public function reset_notes(): void {
        $this->notes = [];
    }

    /**
     * Record a note about the entry being converted.
     *
     * @param string $note
     */
    protected function note(string $note): void {
        $this->notes[] = $note;
    }

    /**
     * The minute of the day an option position of the time select stands for.
     *
     * The options are a list of equally spaced times starting at midnight, so position k means
     * (k - 1) steps past midnight.
     *
     * @param string $option
     * @return int|null
     */
    protected function minute_of_option(string $option): ?int {
        $option = trim($option);
        if ($option === '' || !ctype_digit($option) || (int) $option < 1) {
            return null;
        }
        $minute = ((int) $option - 1) * $this->timestep;

        return $minute < schedule::DAY_MINUTES ? $minute : null;
    }

    /**
     * The option positions a multi-select stored as "#1#,#3#".
     *
     * @param string $content
     * @return int[]
     */
    public static function parse_option_list(string $content): array {
        if (!preg_match_all('/#(\d+)#/', $content, $matches)) {
            return [];
        }
        $values = array_values(array_unique(array_map('intval', $matches[1])));
        sort($values);

        return $values;
    }

    /**
     * Check that a multi-select's options really are the weekdays in ISO order.
     *
     * The conversion maps option position n to ISO weekday n, which is only right if the field
     * lists Monday first. Rather than assume it, the caller can assert it.
     *
     * The labels are compared against the day names of every language installed on the site: a
     * board is usually written in one language and the site runs in another, as a German ride
     * board on an English site does.
     *
     * @param string $param1 the field's newline separated option list
     * @return bool
     */
    public static function options_are_iso_weekdays(string $param1): bool {
        $options = array_values(array_filter(array_map('trim', explode("\n", $param1)), 'strlen'));
        if (count($options) !== 7) {
            return false;
        }
        $options = array_map([\core_text::class, 'strtolower'], $options);

        $names = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $manager = get_string_manager();
        foreach (array_keys($manager->get_list_of_translations()) as $lang) {
            $expected = [];
            foreach ($names as $name) {
                $expected[] = \core_text::strtolower($manager->get_string($name, 'core_calendar', null, $lang));
            }
            if ($expected === $options) {
                return true;
            }
        }

        return false;
    }
}
