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

namespace mod_datalynx\local\map;

/**
 * One geocoded location, normalised across the supported geocoding providers.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class place {
    /**
     * Constructor.
     *
     * @param string $address Human readable address label.
     * @param float $lat Latitude in degrees.
     * @param float $lng Longitude in degrees.
     * @param string $countrycode Lower case ISO 3166-1 alpha-2 code, empty when unknown.
     */
    public function __construct(
        /** @var string Human readable address label. */
        public readonly string $address,
        /** @var float Latitude in degrees. */
        public readonly float $lat,
        /** @var float Longitude in degrees. */
        public readonly float $lng,
        /** @var string Lower case ISO 3166-1 alpha-2 code, empty when unknown. */
        public readonly string $countrycode = ''
    ) {
    }

    /**
     * Export for a web service response or a template.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'address' => $this->address,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'countrycode' => $this->countrycode,
        ];
    }

    /**
     * Join the non-empty parts of an address into one label.
     *
     * Providers that return structured components rather than a ready made
     * label (Photon, for instance) build their label through this.
     *
     * @param array $parts Address components in display order.
     * @return string
     */
    public static function join_parts(array $parts): string {
        $clean = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part !== '' && !in_array($part, $clean, true)) {
                $clean[] = $part;
            }
        }

        return implode(', ', $clean);
    }
}
