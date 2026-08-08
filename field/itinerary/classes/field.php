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

namespace datalynxfield_itinerary;

use mod_datalynx\local\field\datalynxfield_base;
use mod_datalynx\local\map\route_result;
use mod_datalynx\local\ride\itinerary;
use mod_datalynx\local\ride\matcher;
use mod_datalynx\local\ride\waypoint;
use stdClass;

/**
 * Itinerary field: an ordered journey of two or more stops.
 *
 * The value is a JSON waypoint array in `content`, with derived values cached
 * beside it so that matching can pre-filter without parsing every itinerary:
 *
 * | column   | contents                                          |
 * |----------|---------------------------------------------------|
 * | content  | ordered waypoints as JSON                         |
 * | content1 | bounding box `minlat,minlng,maxlat,maxlng`         |
 * | content2 | straight-line length in km                         |
 * | content3 | earliest planned departure, or empty               |
 * | content4 | routed distance, duration and polyline as JSON     |
 *
 * A second, derived copy of the coordinates is kept in `datalynx_waypoints`.
 * That table is purely a query index: `datalynx_contents.content1`/`content2` are
 * unindexed TEXT columns and the Haversine filter has to cast them, so matching
 * routes against routes at any scale needs indexed numeric columns. Nothing
 * authoritative lives there, and {@see rebuild_index()} recreates it in full.
 *
 * @package    datalynxfield_itinerary
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field extends datalynxfield_base {
    /** @var string Field type */
    public $type = 'itinerary';

    /** @var string The derived query index table. */
    const INDEX_TABLE = 'datalynx_waypoints';

    /** @var int Waypoint limit used when the field has none configured. */
    const DEFAULT_MAX_WAYPOINTS = 10;

    /**
     * The single submitted value: the waypoint array as JSON.
     *
     * @return array
     */
    protected function content_names() {
        return ['waypoints', 'route'];
    }

    /**
     * Content database column names this field writes.
     *
     * @return array
     */
    public function get_content_parts() {
        return ['content', 'content1', 'content2', 'content3', 'content4'];
    }

    /**
     * Grouping entries by a whole journey is not meaningful.
     *
     * @return bool
     */
    public function supports_group_by() {
        return false;
    }

    /**
     * Corridor matching is implemented in get_search_sql().
     *
     * @return bool
     */
    public function supports_search() {
        return true;
    }

    /**
     * Offer the corridor search in customfilters, so travellers can search for a ride.
     *
     * @return bool
     */
    public static function is_customfilterfield() {
        return true;
    }

    /**
     * One corridor search is spread over from/to addresses, four hidden coordinates and a radius.
     *
     * @return bool
     */
    public function has_composite_search(): bool {
        return true;
    }

    /**
     * Maximum number of stops allowed on one itinerary.
     *
     * @return int
     */
    public function get_max_waypoints(): int {
        $max = (int) ($this->field->param1 ?? 0);

        return $max > 0 ? $max : self::DEFAULT_MAX_WAYPOINTS;
    }

    /**
     * Whether coordinates are coarsened for viewers who are not participants.
     *
     * @return bool
     */
    public function coarsens_coordinates(): bool {
        return ($this->field->param6 ?? 'approximate') !== 'exact';
    }

    /**
     * Read this field's itinerary out of an entry.
     *
     * @param stdClass $entry
     * @return itinerary
     */
    public function get_itinerary(stdClass $entry): itinerary {
        $fieldid = $this->field->id;

        return itinerary::from_json(
            $entry->{"c{$fieldid}_content"} ?? null,
            $this->get_max_waypoints()
        );
    }

    /**
     * Format content for storage, recomputing the derived columns.
     *
     * @param stdClass $entry
     * @param ?array $values
     * @return array [array of new contents, array of old contents]
     */
    protected function format_content(stdClass $entry, ?array $values = null) {
        $fieldid = $this->field->id;
        $oldcontents = [];
        $contents = [];

        if (isset($entry->{"c{$fieldid}_content"})) {
            $oldcontents[] = $entry->{"c{$fieldid}_content"} ?? null;
            $oldcontents[] = $entry->{"c{$fieldid}_content1"} ?? null;
            $oldcontents[] = $entry->{"c{$fieldid}_content2"} ?? null;
            $oldcontents[] = $entry->{"c{$fieldid}_content3"} ?? null;
            $oldcontents[] = $entry->{"c{$fieldid}_content4"} ?? null;
        }

        if (empty($values)) {
            return [$contents, $oldcontents];
        }

        // The browser submits one hidden input holding the whole waypoint array
        // as JSON; the visible rows are managed client side. An import may pass
        // the same JSON positionally.
        $raw = array_key_exists('waypoints', $values) ? $values['waypoints'] : reset($values);
        $journey = itinerary::from_json((string) $raw, $this->get_max_waypoints());

        // A single stop is not a journey, and nothing can be matched against it.
        if (!$journey->is_route()) {
            return [$contents, $oldcontents];
        }

        $departure = $journey->earliest_departure();

        $contents[] = $journey->to_json();
        $contents[] = $journey->bbox_string();
        $contents[] = (string) $journey->length_km();
        $contents[] = $departure === null ? '' : (string) $departure;
        $contents[] = $this->resolve_route($journey, $values, $oldcontents);

        return [$contents, $oldcontents];
    }

    /**
     * The route summary to store beside the stops.
     *
     * The picker sends a freshly routed summary along with the stops. When it does
     * not - a CSV import, a rule, an older browser - the previous summary is kept
     * only while the stops are unchanged, because a cached route for a journey that
     * has been re-routed would be worse than none at all.
     *
     * @param itinerary $journey The journey being saved.
     * @param array $values Submitted values.
     * @param array $oldcontents Previously stored contents, indexed like get_content_parts().
     * @return string JSON route summary, or an empty string.
     */
    protected function resolve_route(itinerary $journey, array $values, array $oldcontents): string {
        if (!empty($values['route'])) {
            $route = route_result::from_json((string) $values['route']);
            if ($route !== null) {
                return $route->to_json();
            }
        }

        $previousstops = $oldcontents[0] ?? null;
        if ($previousstops === null || $previousstops === '') {
            return '';
        }

        $unchanged = itinerary::from_json($previousstops, $this->get_max_waypoints())->to_json()
            === $journey->to_json();

        return $unchanged ? (string) ($oldcontents[4] ?? '') : '';
    }

    /**
     * The stored route summary of an entry, if it has one.
     *
     * @param stdClass $entry
     * @return route_result|null
     */
    public function get_route(stdClass $entry): ?route_result {
        $fieldid = $this->field->id;

        return route_result::from_json($entry->{"c{$fieldid}_content4"} ?? null);
    }

    /**
     * Save the value, then bring the query index in step.
     *
     * Overriding here rather than hooking the form means the entry form, CSV
     * import and the rules all keep the index correct, since they all funnel
     * through this one method.
     *
     * @param stdClass $entry
     * @param ?array $values
     * @return bool|int
     */
    public function update_content(stdClass $entry, ?array $values = null) {
        $result = parent::update_content($entry, $values);

        $this->reindex_entry((int) $entry->id);

        return $result;
    }

    /**
     * Delete the value, then drop its index rows.
     *
     * @param int $entryid
     * @return bool
     */
    public function delete_content($entryid = 0) {
        global $DB;

        $result = parent::delete_content($entryid);

        $conditions = ['fieldid' => $this->field->id];
        if ($entryid) {
            $conditions['entryid'] = $entryid;
        }
        $DB->delete_records(self::INDEX_TABLE, $conditions);

        return $result;
    }

    /**
     * Rewrite the index rows for one entry from its stored value.
     *
     * @param int $entryid
     */
    public function reindex_entry(int $entryid): void {
        global $DB;

        $fieldid = (int) $this->field->id;
        $DB->delete_records(self::INDEX_TABLE, ['fieldid' => $fieldid, 'entryid' => $entryid]);

        $content = $DB->get_field(
            'datalynx_contents',
            'content',
            ['fieldid' => $fieldid, 'entryid' => $entryid],
            IGNORE_MISSING
        );
        if ($content === false || $content === null || $content === '') {
            return;
        }

        $journey = itinerary::from_json($content, $this->get_max_waypoints());
        $rows = [];
        foreach ($journey->waypoints() as $seq => $waypoint) {
            $rows[] = (object) [
                'entryid' => $entryid,
                'fieldid' => $fieldid,
                'seq' => $seq,
                'lat' => $waypoint->lat,
                'lng' => $waypoint->lng,
                'timeplanned' => $waypoint->time,
            ];
        }

        if ($rows) {
            $DB->insert_records(self::INDEX_TABLE, $rows);
        }
    }

    /**
     * Rebuild the whole index for this field from `datalynx_contents`.
     *
     * Safe to run at any time: the index holds nothing authoritative.
     *
     * @return int Number of entries reindexed.
     */
    public function rebuild_index(): int {
        global $DB;

        $fieldid = (int) $this->field->id;
        $DB->delete_records(self::INDEX_TABLE, ['fieldid' => $fieldid]);

        $entryids = $DB->get_fieldset_select(
            'datalynx_contents',
            'entryid',
            'fieldid = :fieldid',
            ['fieldid' => $fieldid]
        );
        foreach ($entryids as $entryid) {
            $this->reindex_entry((int) $entryid);
        }

        return count($entryids);
    }

    /**
     * Default corridor radius for the search form, in kilometres.
     *
     * @return float
     */
    public function get_match_radius(): float {
        $radius = (float) ($this->field->param5 ?? 0);

        return $radius > 0 ? $radius : 5.0;
    }

    /**
     * Extract the from/to/radius the user typed into the filter form.
     *
     * @param stdClass $formdata
     * @param int $i
     * @return array|bool
     */
    public function parse_search($formdata, $i) {
        $fieldid = $this->field->id;
        $prefix = "f_{$i}_{$fieldid}";

        $read = function (string $suffix) use ($formdata, $prefix) {
            $key = "{$prefix}_{$suffix}";

            return isset($formdata->$key) ? $formdata->$key : null;
        };

        $search = [
            'fromlat' => $read('fromlat'),
            'fromlng' => $read('fromlng'),
            'tolat' => $read('tolat'),
            'tolng' => $read('tolng'),
            'fromaddress' => (string) ($read('fromaddress') ?? ''),
            'toaddress' => (string) ($read('toaddress') ?? ''),
            'radius' => $read('radius') ?: $this->get_match_radius(),
        ];

        // Only a pair of real coordinate pairs describes a corridor; a half-filled
        // form is not a search.
        $complete = is_numeric($search['fromlat']) && is_numeric($search['fromlng'])
            && is_numeric($search['tolat']) && is_numeric($search['tolng']);

        return $complete ? $search : false;
    }

    /**
     * Corridor search: entries whose route can carry the traveller.
     *
     * Returns an entry-id set rather than a condition on the joined content table,
     * so `$fromcontent` is false and the filter neither joins `datalynx_contents`
     * for this field nor qualifies the clause with a content alias.
     *
     * @param array $search [$not, $operator, $value]
     * @return array [$sql, $params, $fromcontent]
     */
    public function get_search_sql(array $search): array {
        [$not, , $value] = $search;

        if (!is_array($value)) {
            return ['', [], false];
        }

        $from = waypoint::from_array(['lat' => $value['fromlat'] ?? null, 'lng' => $value['fromlng'] ?? null]);
        $to = waypoint::from_array(['lat' => $value['tolat'] ?? null, 'lng' => $value['tolng'] ?? null]);
        if ($from === null || $to === null) {
            return ['', [], false];
        }

        $radius = !empty($value['radius']) ? (float) $value['radius'] : $this->get_match_radius();
        [$subquery, $params] = matcher::corridor_subquery((int) $this->field->id, $from, $to, $radius);

        $operator = $not ? 'NOT IN' : 'IN';

        return ["e.id {$operator} ({$subquery})", $params, false];
    }

    /**
     * A corridor search has no operator choice: it either matches or it does not.
     *
     * @return array
     */
    public function get_supported_search_operators() {
        return ['' => get_string('matchesroute', 'datalynxfield_itinerary')];
    }

    /**
     * Describe a stored corridor criterion for the filter overview.
     *
     * The base implementation concatenates the raw value, which is an array here.
     *
     * @param array $searchparams [$not, $operator, $value]
     * @return string
     */
    public function format_search_value($searchparams) {
        [$not, , $value] = $searchparams;

        if (!is_array($value)) {
            return (string) $value;
        }

        $describe = function (string $end) use ($value): string {
            $address = trim((string) ($value["{$end}address"] ?? ''));
            if ($address !== '') {
                return $address;
            }

            return round((float) ($value["{$end}lat"] ?? 0), 4) . ', '
                . round((float) ($value["{$end}lng"] ?? 0), 4);
        };

        $radius = !empty($value['radius']) ? (float) $value['radius'] : $this->get_match_radius();

        return trim($not . ' ' . get_string('matchesroute', 'datalynxfield_itinerary'))
            . ' ' . $describe('from') . ' &rarr; ' . $describe('to')
            . ' (' . get_string('radiuskm', 'datalynxfield_location', $radius) . ')';
    }

    /**
     * Is $value an empty itinerary?
     *
     * @param mixed $value
     * @return bool
     */
    public static function is_fieldvalue_empty($value) {
        if (is_array($value)) {
            $value = $value['waypoints'] ?? reset($value);
        }

        return !itinerary::from_json((string) $value)->is_route();
    }
}
