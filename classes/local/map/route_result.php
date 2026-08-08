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
 * What a routing engine says about a journey: how far, how long, and along which line.
 *
 * The geometry is kept in Google's encoded polyline format at precision 5, which
 * both supported engines emit and which Leaflet can decode in a few lines - far
 * cheaper to store and ship than a coordinate array.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class route_result {
    /**
     * Constructor.
     *
     * @param float $distancem Road distance in metres.
     * @param float $durations Driving time in seconds.
     * @param string $polyline Encoded route geometry, empty when the engine returned none.
     */
    public function __construct(
        /** @var float Road distance in metres. */
        public readonly float $distancem,
        /** @var float Driving time in seconds. */
        public readonly float $durations,
        /** @var string Encoded route geometry. */
        public readonly string $polyline = ''
    ) {
    }

    /**
     * Rebuild from the array shape stored on an entry, or null if it is not one.
     *
     * @param mixed $data
     * @return self|null
     */
    public static function from_array($data): ?self {
        if (!is_array($data) || !isset($data['distance'], $data['duration'])) {
            return null;
        }

        $distance = (float) $data['distance'];
        $duration = (float) $data['duration'];
        if ($distance < 0 || $duration < 0) {
            return null;
        }

        return new self($distance, $duration, (string) ($data['polyline'] ?? ''));
    }

    /**
     * Rebuild from the JSON stored beside an itinerary.
     *
     * @param ?string $json
     * @return self|null
     */
    public static function from_json(?string $json): ?self {
        if ($json === null || trim($json) === '') {
            return null;
        }

        return self::from_array(json_decode($json, true));
    }

    /**
     * The array shape used for storage and for the web service.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'distance' => round($this->distancem, 1),
            'duration' => round($this->durations),
            'polyline' => $this->polyline,
        ];
    }

    /**
     * Encode for storage.
     *
     * @return string
     */
    public function to_json(): string {
        return json_encode($this->to_array());
    }

    /**
     * Road distance in kilometres.
     *
     * @return float
     */
    public function distance_km(): float {
        return $this->distancem / 1000;
    }

    /**
     * Driving time, rounded to whole minutes.
     *
     * @return int
     */
    public function duration_minutes(): int {
        return (int) round($this->durations / MINSECS);
    }
}
