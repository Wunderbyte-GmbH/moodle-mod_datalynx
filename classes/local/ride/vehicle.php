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

use core_text;

/**
 * The vehicle of a ride offer: the car brands a driver picks from, and the brand read off a free text.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vehicle {
    /** @var string The choice for a brand that is not listed. */
    const OTHER = 'Andere';

    /**
     * Car brands sold in Germany, new or used, as the large car markets list them (plain spellings,
     * so that typing "Skoda" or "Citroen" finds them), then the choice for any other brand.
     *
     * @var string[]
     */
    const BRANDS = [
        'Abarth', 'Aiways', 'Aixam', 'Alfa Romeo', 'Alpina', 'Alpine', 'Aston Martin', 'Audi', 'BAIC', 'Bentley',
        'BMW', 'Borgward', 'Bugatti', 'BYD', 'Cadillac', 'Caterham', 'Chevrolet', 'Chrysler', 'Citroen', 'Cupra',
        'Dacia', 'Daewoo', 'Daihatsu', 'Dodge', 'Dongfeng', 'DS Automobiles', 'Ferrari', 'Fiat', 'Fisker', 'Ford',
        'Genesis', 'GMC', 'Great Wall', 'Honda', 'Hongqi', 'Hummer', 'Hyundai', 'Ineos', 'Infiniti', 'Isuzu',
        'Iveco', 'Jaecoo', 'Jaguar', 'Jeep', 'KGM', 'Kia', 'Lada', 'Lamborghini', 'Lancia', 'Land Rover',
        'Leapmotor', 'Lexus', 'Ligier', 'Lincoln', 'Lotus', 'Lucid', 'Lynk & Co', 'MAN', 'Maserati', 'Maxus',
        'Maybach', 'Mazda', 'McLaren', 'Mercedes-Benz', 'MG', 'Microcar', 'MINI', 'Mitsubishi', 'Morgan', 'NIO',
        'Nissan', 'Omoda', 'Opel', 'ORA', 'Peugeot', 'Piaggio', 'Polestar', 'Porsche', 'Renault', 'Rolls-Royce',
        'Rover', 'Saab', 'Seat', 'Seres', 'Skoda', 'Smart', 'SsangYong', 'Subaru', 'Suzuki', 'Tesla',
        'Toyota', 'VinFast', 'Volkswagen', 'Volvo', 'XPENG', 'Zeekr', self::OTHER,
    ];

    /** @var string[] Other names people write for a brand => the brand. */
    const ALIASES = [
        'VW' => 'Volkswagen',
        'Mercedes' => 'Mercedes-Benz',
        'Benz' => 'Mercedes-Benz',
        'Alfa' => 'Alfa Romeo',
        'Range Rover' => 'Land Rover',
        'DS' => 'DS Automobiles',
        'Rolls Royce' => 'Rolls-Royce',
    ];

    /**
     * Split a vehicle described in free text ("VW Polo") into its brand and its model.
     *
     * The text must start with a brand or one of its other names, as a whole word; the longest
     * name that fits wins, so "Land Rover Defender" is a Land Rover, not a Rover.
     *
     * @param string $text
     * @return array [brand from BRANDS or null, the model: the rest of the text, or all of it without a brand]
     */
    public static function split(string $text): array {
        $text = trim($text);
        $names = array_combine(self::BRANDS, self::BRANDS) + self::ALIASES;
        unset($names[self::OTHER]);
        uksort($names, fn(string $a, string $b): int => core_text::strlen($b) <=> core_text::strlen($a));
        $plain = self::plain($text);
        foreach ($names as $name => $brand) {
            $prefix = self::plain($name);
            $length = strlen($prefix);
            if (strncmp($plain, $prefix, $length) === 0 && ($length === strlen($plain) || $plain[$length] === ' ')) {
                // The plain text keeps the length of the text only for ASCII; cut by words instead.
                $words = count(explode(' ', $prefix));
                $model = implode(' ', array_slice(preg_split('/\s+/u', $text), $words));

                return [$brand, $model];
            }
        }

        return [null, $text];
    }

    /**
     * The text in lower case, without accents and with single spaces, to compare names.
     *
     * @param string $text
     * @return string
     */
    private static function plain(string $text): string {
        $text = core_text::strtolower(core_text::specialtoascii(trim($text)));

        return (string) preg_replace('/\s+/', ' ', $text);
    }
}
