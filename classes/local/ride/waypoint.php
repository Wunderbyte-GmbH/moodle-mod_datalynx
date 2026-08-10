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

/**
 * One stop on an itinerary.
 *
 * A waypoint describes a point on a journey, not a person: it is wherever that
 * particular trip passes through, which is typically a station, a campus or a
 * car park. Nothing here is tied to a user profile.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class waypoint {
    /** @var int Decimal places kept for an exact coordinate. */
    const PRECISION_EXACT = 6;

    /**
     * Decimal places kept for a coarsened coordinate.
     *
     * Two places is roughly a kilometre, enough to show which neighbourhood a
     * stop is in without pinpointing a doorstep.
     *
     * @var int
     */
    const PRECISION_COARSE = 2;

    /**
     * Constructor.
     *
     * @param string $address Human readable label for the stop.
     * @param float $lat Latitude in degrees.
     * @param float $lng Longitude in degrees.
     */
    public function __construct(
        /** @var string Human readable label for the stop. */
        public readonly string $address,
        /** @var float Latitude in degrees. */
        public readonly float $lat,
        /** @var float Longitude in degrees. */
        public readonly float $lng
    ) {
    }

    /**
     * Build a waypoint from a decoded JSON entry, or null when unusable.
     *
     * A stop without coordinates cannot be matched against anything, so it is
     * rejected rather than stored as a half-value.
     *
     * @param mixed $data
     * @return self|null
     */
    public static function from_array($data): ?self {
        if (!is_array($data)) {
            return null;
        }

        $lat = $data['lat'] ?? null;
        $lng = $data['lng'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return null;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;
        if (abs($lat) > 90 || abs($lng) > 180) {
            return null;
        }

        // Stops used to carry a planned time. When a journey happens is a schedule field's
        // business now, so a "time" key in older stored JSON is simply ignored.
        return new self(
            clean_param((string) ($data['address'] ?? ''), PARAM_TEXT),
            $lat,
            $lng
        );
    }

    /**
     * Export for storage, a web service response or a template.
     *
     * @param bool $exact False to round the coordinates before exporting.
     * @return array
     */
    public function to_array(bool $exact = true): array {
        $places = $exact ? self::PRECISION_EXACT : self::PRECISION_COARSE;

        return [
            'address' => $this->address,
            'lat' => round($this->lat, $places),
            'lng' => round($this->lng, $places),
        ];
    }

    /**
     * Great-circle distance to another waypoint in kilometres.
     *
     * @param waypoint $other
     * @return float
     */
    public function distance_to(waypoint $other): float {
        $earthradius = 6371.0;
        $dlat = deg2rad($other->lat - $this->lat);
        $dlng = deg2rad($other->lng - $this->lng);

        $a = sin($dlat / 2) ** 2
            + cos(deg2rad($this->lat)) * cos(deg2rad($other->lat)) * sin($dlng / 2) ** 2;

        return $earthradius * 2 * asin(min(1.0, sqrt($a)));
    }
}
