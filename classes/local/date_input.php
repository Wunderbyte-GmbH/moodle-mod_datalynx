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

namespace mod_datalynx\local;

use core_date;
use DateTime;

/**
 * Dates as date inputs send and show them: YYYY-MM-DD, or YYYY-MM-DDTHH:MM, in the user's
 * timezone. The one conversion for the custom filter contract (web and app) and the app's entry
 * form.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class date_input {
    /**
     * A day, or a day and a time, as a timestamp.
     *
     * @param mixed $value YYYY-MM-DD or YYYY-MM-DDTHH:MM in the user's timezone; a timestamp is
     *                     taken as it is
     * @return int 0 when none, or not a valid date
     */
    public static function to_timestamp($value): int {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return max(0, (int) $value);
        }
        if (
            !is_string($value)
                || !preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/', $value, $m)
                || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])
        ) {
            return 0;
        }
        $date = new DateTime(
            sprintf('%s-%s-%s %s:%s:00', $m[1], $m[2], $m[3], $m[4] ?? '00', $m[5] ?? '00'),
            core_date::get_user_timezone_object()
        );

        return $date->getTimestamp();
    }

    /**
     * The first (or last) second of the day a value falls on, in the user's timezone.
     *
     * @param mixed $value see to_timestamp()
     * @param bool $end the last second of the day rather than the first
     * @return int 0 when none
     */
    public static function to_day($value, bool $end = false): int {
        $timestamp = self::to_timestamp($value);
        if (!$timestamp) {
            return 0;
        }
        $date = (new DateTime('@' . $timestamp))->setTimezone(core_date::get_user_timezone_object())->setTime(0, 0);
        if ($end) {
            $date->modify('+1 day')->modify('-1 second');
        }

        return $date->getTimestamp();
    }

    /**
     * A timestamp as a date input shows it.
     *
     * @param mixed $timestamp
     * @param bool $includetime
     * @return string YYYY-MM-DD or YYYY-MM-DDTHH:MM, '' for none
     */
    public static function to_string($timestamp, bool $includetime = false): string {
        if (!is_numeric($timestamp) || (int) $timestamp <= 0) {
            return '';
        }
        $date = (new DateTime('@' . (int) $timestamp))->setTimezone(core_date::get_user_timezone_object());

        return $date->format($includetime ? 'Y-m-d\TH:i' : 'Y-m-d');
    }
}
