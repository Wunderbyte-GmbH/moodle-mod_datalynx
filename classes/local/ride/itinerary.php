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
 * An ordered journey: the value of one itinerary field.
 *
 * Owns the JSON representation stored in `datalynx_contents.content` and the
 * derived values cached beside it — the bounding box, the straight-line length
 * and the earliest departure — which is what lets matching pre-filter cheaply
 * before doing any distance maths.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itinerary {
    /**
     * Constructor.
     *
     * @param waypoint[] $waypoints Stops in travel order.
     */
    public function __construct(
        /** @var waypoint[] Stops in travel order. */
        protected array $waypoints = []
    ) {
        $this->waypoints = array_values($waypoints);
    }

    /**
     * Build from the JSON stored in `content`.
     *
     * Unusable stops are dropped rather than failing the whole itinerary, so a
     * partially broken value still yields whatever is matchable.
     *
     * @param string|null $json
     * @param int $max Maximum stops to keep; 0 for no limit.
     * @return self
     */
    public static function from_json(?string $json, int $max = 0): self {
        $decoded = json_decode((string) $json, true);
        if (!is_array($decoded)) {
            return new self();
        }

        $waypoints = [];
        foreach ($decoded as $item) {
            $waypoint = waypoint::from_array($item);
            if ($waypoint !== null) {
                $waypoints[] = $waypoint;
            }
            if ($max > 0 && count($waypoints) >= $max) {
                break;
            }
        }

        return new self($waypoints);
    }

    /**
     * The stops, in travel order.
     *
     * @return waypoint[]
     */
    public function waypoints(): array {
        return $this->waypoints;
    }

    /**
     * Number of stops.
     *
     * @return int
     */
    public function count(): int {
        return count($this->waypoints);
    }

    /**
     * Whether this itinerary describes a journey at all.
     *
     * A single stop is not a journey: there is nothing to travel along and
     * nothing to match.
     *
     * @return bool
     */
    public function is_route(): bool {
        return count($this->waypoints) >= 2;
    }

    /**
     * First stop, or null when empty.
     *
     * @return waypoint|null
     */
    public function first(): ?waypoint {
        return $this->waypoints[0] ?? null;
    }

    /**
     * Last stop, or null when empty.
     *
     * @return waypoint|null
     */
    public function last(): ?waypoint {
        return $this->waypoints ? $this->waypoints[count($this->waypoints) - 1] : null;
    }

    /**
     * Encode for storage in `content`.
     *
     * @param bool $exact False to round coordinates before encoding.
     * @return string
     */
    public function to_json(bool $exact = true): string {
        $items = [];
        foreach ($this->waypoints as $waypoint) {
            $items[] = $waypoint->to_array($exact);
        }

        return json_encode($items, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Bounding box enclosing every stop.
     *
     * @return array|null [minlat, minlng, maxlat, maxlng], or null when empty.
     */
    public function bbox(): ?array {
        if (!$this->waypoints) {
            return null;
        }

        $lats = array_map(static fn(waypoint $w) => $w->lat, $this->waypoints);
        $lngs = array_map(static fn(waypoint $w) => $w->lng, $this->waypoints);

        return [min($lats), min($lngs), max($lats), max($lngs)];
    }

    /**
     * Bounding box formatted for the `content1` column.
     *
     * @return string
     */
    public function bbox_string(): string {
        $bbox = $this->bbox();

        return $bbox === null ? '' : implode(',', array_map(
            static fn(float $value) => sprintf('%.6F', $value),
            $bbox
        ));
    }

    /**
     * Parse a bounding box back out of the `content1` column.
     *
     * @param string|null $value
     * @return array|null [minlat, minlng, maxlat, maxlng]
     */
    public static function parse_bbox(?string $value): ?array {
        $parts = array_map('trim', explode(',', (string) $value));
        if (count($parts) !== 4) {
            return null;
        }
        foreach ($parts as $part) {
            if (!is_numeric($part)) {
                return null;
            }
        }

        return array_map('floatval', $parts);
    }

    /**
     * Whether two bounding boxes overlap, used to pre-filter candidate rides.
     *
     * @param array $a [minlat, minlng, maxlat, maxlng]
     * @param array $b [minlat, minlng, maxlat, maxlng]
     * @param float $paddingkm Grow both boxes by this many kilometres first.
     * @return bool
     */
    public static function bbox_overlaps(array $a, array $b, float $paddingkm = 0.0): bool {
        $pad = $paddingkm / 111.32;

        return ($a[0] - $pad) <= $b[2] && ($a[2] + $pad) >= $b[0]
            && ($a[1] - $pad) <= $b[3] && ($a[3] + $pad) >= $b[1];
    }

    /**
     * Straight-line length of the journey in kilometres.
     *
     * Segment-by-segment great-circle distance, not a routed distance: a real
     * road distance needs a routing engine and arrives with that work.
     *
     * @return float
     */
    public function length_km(): float {
        $total = 0.0;
        for ($i = 1; $i < count($this->waypoints); $i++) {
            $total += $this->waypoints[$i - 1]->distance_to($this->waypoints[$i]);
        }

        return round($total, 3);
    }

    /**
     * Earliest planned time across the stops, or null when none carry a time.
     *
     * @return int|null
     */
    public function earliest_departure(): ?int {
        $times = [];
        foreach ($this->waypoints as $waypoint) {
            if ($waypoint->time !== null) {
                $times[] = $waypoint->time;
            }
        }

        return $times ? min($times) : null;
    }

    /**
     * Whether every stop carries a planned time.
     *
     * @return bool
     */
    public function has_all_times(): bool {
        foreach ($this->waypoints as $waypoint) {
            if ($waypoint->time === null) {
                return false;
            }
        }

        return (bool) $this->waypoints;
    }

    /**
     * Export for a template or a web service.
     *
     * @param bool $exact False to coarsen coordinates for a viewer who is not
     *                    a participant in this ride.
     * @return array
     */
    public function export(bool $exact = true): array {
        $items = [];
        foreach ($this->waypoints as $index => $waypoint) {
            $items[] = array_merge($waypoint->to_array($exact), [
                'seq' => $index,
                'isfirst' => $index === 0,
                'islast' => $index === count($this->waypoints) - 1,
            ]);
        }

        return $items;
    }
}
