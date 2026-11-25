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

namespace mod_datalynx\local\ride;

use basic_testcase;

/**
 * The brand of a vehicle read off its free-text description.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\ride\vehicle
 */
final class vehicle_test extends basic_testcase {
    /**
     * Descriptions and the brand and model they are split into.
     *
     * @return array
     */
    public static function descriptions(): array {
        return [
            'brand and model' => ['BMW 320d', 'BMW', '320d'],
            'other name' => ['VW Polo', 'Volkswagen', 'Polo'],
            'other case' => ['Vw Polo', 'Volkswagen', 'Polo'],
            'accent' => ['Škoda Octavia Combi', 'Skoda', 'Octavia Combi'],
            'two words' => ['Alfa Romeo Giulia', 'Alfa Romeo', 'Giulia'],
            'longest name wins' => ['Land Rover Defender', 'Land Rover', 'Defender'],
            'brand only' => ['Tesla', 'Tesla', ''],
            'whole word only' => ['Opelix 3', null, 'Opelix 3'],
            'no brand' => ['Kleiner roter Flitzer', null, 'Kleiner roter Flitzer'],
        ];
    }

    /**
     * A description starting with a brand is split into the brand and the rest.
     *
     * @dataProvider descriptions
     * @param string $text
     * @param string|null $brand
     * @param string $model
     */
    public function test_split(string $text, ?string $brand, string $model): void {
        $this->assertSame([$brand, $model], vehicle::split($text));
    }

    /**
     * The brands are unique, and the choice for other brands comes last.
     */
    public function test_brands(): void {
        $this->assertSame(vehicle::BRANDS, array_values(array_unique(vehicle::BRANDS)));
        $this->assertSame(vehicle::OTHER, vehicle::BRANDS[count(vehicle::BRANDS) - 1]);
        foreach (vehicle::ALIASES as $brand) {
            $this->assertContains($brand, vehicle::BRANDS);
        }
    }
}
