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
 * Finds journeys whose route can carry someone from one place to another.
 *
 * A journey matches when one of its stops lies within the radius of where the
 * traveller wants to be picked up, **and a later stop** lies within the radius of
 * where they want to get off. Requiring the drop-off to come later in the route is
 * what makes the match directional: the same stops travelled the other way round
 * are not a match.
 *
 * Everything runs against `datalynx_waypoints`, the derived index whose numeric
 * lat/lng columns are indexed — the coordinates in `datalynx_contents` are
 * unindexed TEXT and have to be cast, which no index can serve. The bounding box
 * comparisons are plain indexed range scans and cut the candidate set down before
 * any trigonometry runs.
 *
 * The same SQL backs both the interactive filter and the notification rule, so the
 * two can never drift apart.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class matcher {
    /** @var string The derived query index table. */
    const TABLE = 'datalynx_waypoints';

    /** @var float Kilometres per degree of latitude. */
    const KM_PER_DEGREE = 111.32;

    /** @var float Earth radius in kilometres, for the Haversine term. */
    const EARTH_RADIUS = 6371.0;

    /** @var int Distinguishes the placeholders of concurrent match clauses. */
    protected static int $sequence = 0;

    /**
     * Latitude and longitude half-widths of a box enclosing a radius.
     *
     * Longitude degrees shrink towards the poles, hence the cosine. Clamped so a
     * radius near a pole cannot produce a nonsensical box.
     *
     * @param float $lat Centre latitude in degrees.
     * @param float $radiuskm
     * @return array [dlat, dlng] in degrees.
     */
    public static function bounding_deltas(float $lat, float $radiuskm): array {
        $dlat = $radiuskm / self::KM_PER_DEGREE;
        $cos = cos(deg2rad(max(-89.9, min(89.9, $lat))));
        $dlng = $radiuskm / (self::KM_PER_DEGREE * max(0.01, $cos));

        return [$dlat, min(180.0, $dlng)];
    }

    /**
     * SQL expression for the great-circle distance from a table alias to a point.
     *
     * The target latitude appears twice in the Haversine formula, and Moodle's DML
     * counts every placeholder *occurrence* rather than every distinct name
     * ({@see \moodle_database::fix_sql_params()}), so a repeated name fails with
     * "Incorrect number of query parameters". Hence two names for the one value.
     *
     * @param string $alias Table alias holding lat and lng columns.
     * @param string $latone Placeholder for the target latitude, first use.
     * @param string $lattwo Placeholder for the target latitude, second use.
     * @param string $lngparam Placeholder for the target longitude.
     * @return string
     */
    protected static function distance_expression(
        string $alias,
        string $latone,
        string $lattwo,
        string $lngparam
    ): string {
        return '(' . self::EARTH_RADIUS . ' * 2 * ASIN(LEAST(1, SQRT('
            . "POWER(SIN(RADIANS({$alias}.lat - :{$latone}) / 2), 2)"
            . " + COS(RADIANS(:{$lattwo})) * COS(RADIANS({$alias}.lat))"
            . " * POWER(SIN(RADIANS({$alias}.lng - :{$lngparam}) / 2), 2)"
            . '))))';
    }

    /**
     * Build the entry-id subquery for one corridor match.
     *
     * @param int $fieldid Itinerary field the journeys belong to.
     * @param waypoint $from Where the traveller wants to be picked up.
     * @param waypoint $to Where the traveller wants to get off.
     * @param float $radiuskm How far from the route either end may be.
     * @param array|null $restrictentryids Only consider these entries; null for all.
     * @return array [$sql, $params] where $sql selects entry ids.
     */
    public static function corridor_subquery(
        int $fieldid,
        waypoint $from,
        waypoint $to,
        float $radiuskm,
        ?array $restrictentryids = null
    ): array {
        global $DB;

        $suffix = $fieldid . '_' . (++self::$sequence);
        $table = '{' . self::TABLE . '}';

        [$fromdlat, $fromdlng] = self::bounding_deltas($from->lat, $radiuskm);
        [$todlat, $todlng] = self::bounding_deltas($to->lat, $radiuskm);

        // Each occurrence needs its own placeholder; see distance_expression().
        $params = [
            "rmfield{$suffix}" => $fieldid,
            "rmpradius{$suffix}" => $radiuskm,
            "rmdradius{$suffix}" => $radiuskm,
            "rmplata{$suffix}" => $from->lat,
            "rmplatb{$suffix}" => $from->lat,
            "rmplng{$suffix}" => $from->lng,
            "rmdlata{$suffix}" => $to->lat,
            "rmdlatb{$suffix}" => $to->lat,
            "rmdlng{$suffix}" => $to->lng,
            "rmplatmin{$suffix}" => $from->lat - $fromdlat,
            "rmplatmax{$suffix}" => $from->lat + $fromdlat,
            "rmplngmin{$suffix}" => $from->lng - $fromdlng,
            "rmplngmax{$suffix}" => $from->lng + $fromdlng,
            "rmdlatmin{$suffix}" => $to->lat - $todlat,
            "rmdlatmax{$suffix}" => $to->lat + $todlat,
            "rmdlngmin{$suffix}" => $to->lng - $todlng,
            "rmdlngmax{$suffix}" => $to->lng + $todlng,
        ];

        $restrict = '';
        if ($restrictentryids !== null) {
            if (!$restrictentryids) {
                // An empty restriction can never match, and IN () is not valid SQL.
                return ['SELECT NULL WHERE 1 = 0', []];
            }
            [$insql, $inparams] = $DB->get_in_or_equal(
                array_map('intval', array_values($restrictentryids)),
                SQL_PARAMS_NAMED,
                "rment{$suffix}_"
            );
            $restrict = " AND pickup.entryid {$insql}";
            $params += $inparams;
        }

        $pickupdistance = self::distance_expression(
            'pickup',
            "rmplata{$suffix}",
            "rmplatb{$suffix}",
            "rmplng{$suffix}"
        );
        $dropoffdistance = self::distance_expression(
            'dropoff',
            "rmdlata{$suffix}",
            "rmdlatb{$suffix}",
            "rmdlng{$suffix}"
        );

        $sql = "SELECT DISTINCT pickup.entryid
                  FROM {$table} pickup
                  JOIN {$table} dropoff
                    ON dropoff.entryid = pickup.entryid
                   AND dropoff.fieldid = pickup.fieldid
                   AND dropoff.seq > pickup.seq
                 WHERE pickup.fieldid = :rmfield{$suffix}
                       {$restrict}
                   AND pickup.lat BETWEEN :rmplatmin{$suffix} AND :rmplatmax{$suffix}
                   AND pickup.lng BETWEEN :rmplngmin{$suffix} AND :rmplngmax{$suffix}
                   AND dropoff.lat BETWEEN :rmdlatmin{$suffix} AND :rmdlatmax{$suffix}
                   AND dropoff.lng BETWEEN :rmdlngmin{$suffix} AND :rmdlngmax{$suffix}
                   AND {$pickupdistance} <= :rmpradius{$suffix}
                   AND {$dropoffdistance} <= :rmdradius{$suffix}";

        return [$sql, $params];
    }

    /**
     * Entry ids of journeys that can carry a traveller between two places.
     *
     * @param int $fieldid
     * @param waypoint $from
     * @param waypoint $to
     * @param float $radiuskm
     * @param array|null $restrictentryids
     * @return int[]
     */
    public static function find_matching_entries(
        int $fieldid,
        waypoint $from,
        waypoint $to,
        float $radiuskm,
        ?array $restrictentryids = null
    ): array {
        global $DB;

        [$sql, $params] = self::corridor_subquery($fieldid, $from, $to, $radiuskm, $restrictentryids);

        return array_map('intval', array_keys($DB->get_records_sql($sql, $params)));
    }

    /**
     * Whether one journey can carry the traveller behind another journey.
     *
     * Used by the notification rule when an offer is saved and every open request
     * has to be re-tested against it.
     *
     * @param int $fieldid
     * @param int $offerentryid The journey doing the carrying.
     * @param itinerary $request The traveller's own journey; its ends are the
     *                           pickup and the drop-off.
     * @param float $radiuskm
     * @return bool
     */
    public static function entry_covers_request(
        int $fieldid,
        int $offerentryid,
        itinerary $request,
        float $radiuskm
    ): bool {
        if (!$request->is_route()) {
            return false;
        }

        return (bool) self::find_matching_entries(
            $fieldid,
            $request->first(),
            $request->last(),
            $radiuskm,
            [$offerentryid]
        );
    }
}
