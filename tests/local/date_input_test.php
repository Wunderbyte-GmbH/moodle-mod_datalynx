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

use advanced_testcase;

/**
 * The one conversion of date inputs, in the user's timezone.
 *
 * @package    mod_datalynx
 * @category   test
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\date_input
 */
final class date_input_test extends advanced_testcase {
    /**
     * Dates and times are read in the user's timezone, and shown back the same way.
     */
    public function test_round_trip_in_user_timezone(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['timezone' => 'Europe/Vienna']);
        $this->setUser($user);

        $day = date_input::to_timestamp('2026-11-30');
        $this->assertSame((new \DateTime('2026-11-30 00:00', new \DateTimeZone('Europe/Vienna')))->getTimestamp(), $day);
        $this->assertSame($day + 7 * HOURSECS + 30 * MINSECS, date_input::to_timestamp('2026-11-30T07:30'));
        $this->assertSame('2026-11-30', date_input::to_string($day));
        $this->assertSame('2026-11-30T07:30', date_input::to_string($day + 7 * HOURSECS + 30 * MINSECS, true));

        // A day from a date or any moment of it; its last second on request.
        $this->assertSame($day, date_input::to_day('2026-11-30'));
        $this->assertSame($day, date_input::to_day((string) ($day + 5 * HOURSECS)));
        $this->assertSame($day + DAYSECS - 1, date_input::to_day('2026-11-30', true));
    }

    /**
     * Nothing usable gives 0 or ''.
     */
    public function test_invalid_values(): void {
        foreach (['', 'tomorrow', '2026-02-30', '30.11.2026', null, [], '-5'] as $value) {
            $this->assertSame(0, date_input::to_timestamp($value), json_encode($value));
            $this->assertSame(0, date_input::to_day($value), json_encode($value));
        }
        $this->assertSame('', date_input::to_string(''));
        $this->assertSame('', date_input::to_string('0'));
        $this->assertSame('', date_input::to_string('abc'));
    }
}
