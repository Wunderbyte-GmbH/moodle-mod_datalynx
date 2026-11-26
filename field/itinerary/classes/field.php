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

use core_text;
use invalid_parameter_exception;
use mod_datalynx\local\field\datalynxfield_base;
use mod_datalynx\local\map\geocoder_factory;
use mod_datalynx\local\map\place;
use mod_datalynx\local\map\provider_config;
use mod_datalynx\local\map\route_result;
use mod_datalynx\local\map\router_factory;
use mod_datalynx\local\ride\itinerary;
use mod_datalynx\local\ride\matcher;
use mod_datalynx\local\ride\waypoint;
use mod_datalynx\local\rule\match_compiler;
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
 * | content3 | unused; a schedule field says when a ride happens   |
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
    /**
     * The stops and the route as JSON, which the field parses and checks itself.
     *
     * @param array $options
     * @return array
     */
    public function get_input_contract(array $options = []): array {
        return ['waypoints' => PARAM_RAW, 'route' => PARAM_RAW];
    }

    /**
     * A journey is its stops, posted as JSON in field_{fieldid}_{entryid}_waypoints.
     *
     * @param string $name
     * @param stdClass $data
     * @return bool
     */
    public function is_submitted_value_empty(string $name, stdClass $data): bool {
        $json = (string) ($data->{$name . '_waypoints'} ?? '');
        if ($json === '') {
            return true;
        }
        // A stop given by its address only (the Moodle App) is resolved on save.
        foreach ((array) json_decode($json, true) as $stop) {
            if (is_array($stop) && (trim((string) ($stop['address'] ?? '')) !== '' || is_numeric($stop['lat'] ?? null))) {
                return false;
            }
        }
        return true;
    }

    /** @var string Field type */
    public $type = 'itinerary';

    /** @var string The derived query index table. */
    const INDEX_TABLE = 'datalynx_waypoints';

    /** @var int Waypoint limit used when the field has none configured. */
    const DEFAULT_MAX_WAYPOINTS = 10;

    /** @var int[] The search radii offered, in kilometres. */
    const SEARCH_RADII = [1, 2, 5, 10, 20, 50];

    /** @var float Largest search radius accepted from outside, in kilometres. */
    const MAX_SEARCH_RADIUS = 100;

    /** @var int Longest address accepted in a search. */
    const MAX_ADDRESS_LENGTH = 255;

    /** @var array Addresses looked up in this request: "countries|address" => place or null. */
    private static array $resolved = [];

    /**
     * The single submitted value: the waypoint array as JSON.
     *
     * @return array
     */
    protected function content_names(): array {
        return ['waypoints', 'route'];
    }

    /**
     * Content database column names this field writes.
     *
     * @return array
     */
    public function get_content_parts(): array {
        return ['content', 'content1', 'content2', 'content3', 'content4'];
    }

    /**
     * Grouping entries by a whole journey is not meaningful.
     *
     * @return bool
     */
    public function supports_group_by(): bool {
        return false;
    }

    /**
     * Corridor matching is implemented in get_search_sql().
     *
     * @return bool
     */
    public function supports_search(): bool {
        return true;
    }

    /**
     * Offer the corridor search in customfilters, so travellers can search for a ride.
     *
     * @return bool
     */
    public static function is_customfilterfield(): bool {
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
    protected function format_content(stdClass $entry, ?array $values = null): array {
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
        [$raw, , $resolved] = $this->resolve_stops((string) $raw, $oldcontents[0] ?? null);
        $journey = itinerary::from_json($raw, $this->get_max_waypoints());

        // A single stop is not a journey, and nothing can be matched against it.
        if (!$journey->is_route()) {
            return [$contents, $oldcontents];
        }

        $contents[] = $journey->to_json();
        $contents[] = $journey->bbox_string();
        $contents[] = (string) $journey->length_km();
        // This column held the earliest stop time back when a journey carried its own dates.
        // When a ride happens is a schedule field's business now - a date on a stop could not
        // describe a commute that runs every Monday, and left one that did unconstrained in time.
        $route = $this->resolve_route($journey, $values, $oldcontents, $resolved);
        // Billing mode: which router computed the distance, and when (printed on the Fahrtenschein).
        $contents[] = $this->is_billing() && $route !== ''
            ? json_encode(['provider' => \mod_datalynx\local\map\provider_config::router_engine(), 'time' => time()])
            : '';
        $contents[] = $route;

        return [$contents, $oldcontents];
    }

    /**
     * Give the stops that come with an address only their coordinates.
     *
     * The web form sends each stop with the coordinates of the suggestion picked; the Moodle App
     * sends what was typed. A stop whose address is the same as one stored before keeps that
     * stop's position; any other is looked up with the site's geocoder, and the place found names
     * the stop, as a suggestion picked on the web does. Empty rows are dropped.
     *
     * @param string $json the submitted stops
     * @param string|null $previousjson the stops stored before, if any
     * @return array [the stops as JSON, the addresses not found, whether any stop was looked up]
     */
    public function resolve_stops(string $json, ?string $previousjson): array {
        $stops = json_decode($json, true);
        if (!is_array($stops)) {
            return [$json, [], false];
        }

        $known = [];
        $passengers = [];
        foreach (itinerary::from_json($previousjson)->waypoints() as $waypoint) {
            $known[core_text::strtolower(trim($waypoint->address))] = $waypoint;
            // A pickup stop keeps its passenger (mod_datalynx#327); the browser does not send it.
            if ($waypoint->userid > 0) {
                $passengers[round($waypoint->lat, 6) . ',' . round($waypoint->lng, 6)] = $waypoint->userid;
            }
        }

        $result = [];
        $notfound = [];
        $resolved = false;
        foreach ($stops as $stop) {
            if (!is_array($stop)) {
                continue;
            }
            if (is_numeric($stop['lat'] ?? null) && is_numeric($stop['lng'] ?? null)) {
                // Only a stored pickup stop names a passenger; a submitted userid is not trusted, except
                // when the server itself stores a pickup stop (add_pickup()).
                if (!$this->trustpassengers) {
                    unset($stop['userid']);
                }
                $key = round((float) $stop['lat'], 6) . ',' . round((float) $stop['lng'], 6);
                if (isset($passengers[$key]) && empty($stop['userid'])) {
                    $stop['userid'] = $passengers[$key];
                }
                $result[] = $stop;
                continue;
            }
            $address = trim(clean_param((string) ($stop['address'] ?? ''), PARAM_TEXT));
            if ($address === '') {
                continue;
            }
            $waypoint = $known[core_text::strtolower($address)] ?? null;
            if ($waypoint) {
                $result[] = $waypoint->to_array();
                continue;
            }
            $place = core_text::strlen($address) <= self::MAX_ADDRESS_LENGTH ? $this->resolve_address($address) : null;
            if ($place === null) {
                $notfound[] = $address;
                continue;
            }
            $resolved = true;
            $result[] = ['address' => $place->address, 'lat' => $place->lat, 'lng' => $place->lng];
        }

        return [json_encode($result), $notfound, $resolved];
    }

    /**
     * The route summary to store beside the stops.
     *
     * The picker sends a freshly routed summary along with the stops. When it does
     * not - a CSV import, a rule, an older browser - the previous summary is kept
     * only while the stops are unchanged, because a cached route for a journey that
     * has been re-routed would be worse than none at all.
     *
     * Stops looked up from their addresses (the Moodle App) come without a summary; their route
     * is calculated here with the site's router.
     *
     * @param itinerary $journey The journey being saved.
     * @param array $values Submitted values.
     * @param array $oldcontents Previously stored contents, indexed like get_content_parts().
     * @param bool $lookedup Whether stops were looked up from their addresses.
     * @return string JSON route summary, or an empty string.
     */
    protected function resolve_route(itinerary $journey, array $values, array $oldcontents, bool $lookedup = false): string {
        // A billing-relevant distance is always routed on the server; what the browser sends is ignored.
        if ($this->is_billing()) {
            $route = $this->server_route($journey);
            return $route !== null ? $route->to_json() : '';
        }
        if (!empty($values['route'])) {
            $route = route_result::from_json((string) $values['route']);
            if ($route !== null) {
                return $route->to_json();
            }
        }

        if ($lookedup) {
            $router = router_factory::instance();
            $route = $router?->route(array_map(
                static fn(waypoint $stop): array => [$stop->lat, $stop->lng],
                $journey->waypoints()
            ));
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
     * @return int|string|bool
     */
    public function update_content(stdClass $entry, ?array $values = null): int|string|bool {
        global $DB;
        $before = $this->passengers_of(itinerary::from_json($DB->get_field(
            'datalynx_contents',
            'content',
            ['fieldid' => $this->field->id, 'entryid' => (int) $entry->id],
            IGNORE_MULTIPLE
        ) ?: null));

        $result = parent::update_content($entry, $values);

        $this->reindex_entry((int) $entry->id);

        // A pickup stop removed from the route takes its passenger out of the team (mod_datalynx#327).
        $after = $this->passengers_of(itinerary::from_json($DB->get_field(
            'datalynx_contents',
            'content',
            ['fieldid' => $this->field->id, 'entryid' => (int) $entry->id],
            IGNORE_MULTIPLE
        ) ?: null));
        if ($removed = array_diff($before, $after)) {
            foreach ($this->dlx->get_fields() as $field) {
                $pickupfield = $field->type === 'teammemberselect' ? (int) ($field->get_settings()['pickupfield'] ?? 0) : 0;
                if ($pickupfield === (int) $this->field->id) {
                    $membership = $field->membership((int) $entry->id);
                    foreach ($removed as $userid) {
                        if ($membership->status($userid) === \datalynxfield_teammemberselect\membership::MEMBER) {
                            $membership->remove($userid);
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Remove the pickup stops of users from the routes (privacy: deleted users, deletion requests).
     *
     * @param int[] $userids
     * @param int|null $dataid one instance, or all
     * @return int routes changed
     */
    public static function remove_passengers(array $userids, ?int $dataid = null): int {
        global $DB;
        $userids = array_map('intval', $userids);
        if (!$userids) {
            return 0;
        }
        $sql = "SELECT c.id, c.entryid, c.fieldid, c.content
                  FROM {datalynx_contents} c
                  JOIN {datalynx_fields} f ON f.id = c.fieldid
                 WHERE f.type = 'itinerary' AND " . $DB->sql_like('c.content', ':userkey');
        $params = ['userkey' => '%"userid"%'];
        if ($dataid !== null) {
            $sql .= ' AND f.dataid = :dataid';
            $params['dataid'] = $dataid;
        }
        $changed = 0;
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $journey = itinerary::from_json($row->content);
            $kept = array_filter($journey->waypoints(), fn(waypoint $stop) => !in_array($stop->userid, $userids, true));
            if (count($kept) === $journey->count()) {
                continue;
            }
            $DB->set_field('datalynx_contents', 'content', (new itinerary($kept))->to_json(), ['id' => $row->id]);
            $dlx = new \mod_datalynx\datalynx((int) $DB->get_field('datalynx_fields', 'dataid', ['id' => $row->fieldid]));
            $dlx->get_field_from_id((int) $row->fieldid)->reindex_entry((int) $row->entryid);
            $changed++;
        }
        return $changed;
    }

    /**
     * The passengers of the pickup stops of a journey.
     *
     * @param itinerary $journey
     * @return int[]
     */
    protected function passengers_of(itinerary $journey): array {
        return array_values(array_filter(array_map(fn(waypoint $stop) => $stop->userid, $journey->waypoints())));
    }

    /**
     * Put a passenger's pickup stop into the route of an entry, where it makes the smallest detour,
     * and route the journey again (mod_datalynx#327).
     *
     * @param int $entryid
     * @param waypoint $pickup with the passenger
     */
    public function add_pickup(int $entryid, waypoint $pickup): void {
        $this->save_journey($entryid, $this->stored_journey($entryid)->without_pickup($pickup->userid)->with_pickup($pickup));
    }

    /**
     * Take a passenger's pickup stop out of the route of an entry, and route the journey again.
     *
     * @param int $entryid
     * @param int $userid
     */
    public function remove_pickup(int $entryid, int $userid): void {
        $journey = $this->stored_journey($entryid);
        if (in_array($userid, $this->passengers_of($journey), true)) {
            $this->save_journey($entryid, $journey->without_pickup($userid));
        }
    }

    /**
     * The stored journey of an entry.
     *
     * @param int $entryid
     * @return itinerary
     */
    public function stored_journey(int $entryid): itinerary {
        global $DB;
        return itinerary::from_json($DB->get_field(
            'datalynx_contents',
            'content',
            ['fieldid' => $this->field->id, 'entryid' => $entryid],
            IGNORE_MULTIPLE
        ) ?: null, $this->get_max_waypoints());
    }

    /**
     * Store a journey for an entry with its route from the site's router.
     *
     * @param int $entryid
     * @param itinerary $journey
     */
    protected function save_journey(int $entryid, itinerary $journey): void {
        global $DB;
        $row = $DB->get_record('datalynx_contents', ['fieldid' => $this->field->id, 'entryid' => $entryid], '*', IGNORE_MULTIPLE);
        $entry = (object) ['id' => $entryid];
        if ($row) {
            foreach (['', '1', '2', '3', '4'] as $suffix) {
                $entry->{"c{$this->field->id}_content{$suffix}"} = $row->{"content{$suffix}"};
            }
            $entry->{"c{$this->field->id}_id"} = $row->id;
        }
        $values = ['waypoints' => $journey->to_json()];
        $route = $this->server_route($journey);
        if ($route !== null) {
            $values['route'] = $route->to_json();
        }
        $this->trustpassengers = true;
        try {
            parent::update_content($entry, $values);
        } finally {
            $this->trustpassengers = false;
        }
        $this->reindex_entry($entryid);
    }

    /**
     * Delete the value, then drop its index rows.
     *
     * @param int $entryid
     * @return bool
     */
    public function delete_content($entryid = 0): bool {
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
     * The billing settings (param9, JSON; mod_datalynx#328): billing (1 when the distance is
     * billing-relevant), returnfield (a checkbox doubling the distance), requestedfield (a number of
     * km the driver asks for) and justificationfield (required when more is asked for).
     *
     * @return array
     */
    public function get_billing_settings(): array {
        $settings = json_decode((string) ($this->field->param9 ?? ''), true);
        return is_array($settings) ? $settings : [];
    }

    /**
     * Whether the distance is billing-relevant: computed on the server only.
     *
     * @return bool
     */
    public function is_billing(): bool {
        return !empty($this->get_billing_settings()['billing']);
    }

    /** @var bool Whether the stops being stored come from the server, with their passengers. */
    protected bool $trustpassengers = false;

    /** @var array Routes computed in this request, by stops. */
    protected static array $serverroutes = [];

    /**
     * The route of a journey from the site's router. Null when there is no router or no route.
     *
     * @param itinerary $journey
     * @return route_result|null
     */
    public function server_route(itinerary $journey): ?route_result {
        $key = $journey->to_json();
        if (!array_key_exists($key, self::$serverroutes)) {
            $router = router_factory::instance();
            self::$serverroutes[$key] = $router?->route(array_map(
                static fn(waypoint $stop): array => [$stop->lat, $stop->lng],
                $journey->waypoints()
            ));
        }
        return self::$serverroutes[$key];
    }

    /**
     * Forget the routes of this request (tests).
     */
    public static function reset_server_routes(): void {
        self::$serverroutes = [];
    }

    /**
     * The stored value of another field of the entry, or the submitted one when given.
     *
     * @param int $fieldid
     * @param int|string $entryid
     * @param \stdClass|null $formdata
     * @return mixed
     */
    protected function value_of(int $fieldid, $entryid, ?\stdClass $formdata = null) {
        global $DB;
        $name = "field_{$fieldid}_{$entryid}";
        if ($formdata !== null && property_exists($formdata, $name)) {
            $value = $formdata->$name;
            return is_array($value) && isset($value['text']) ? $value['text'] : $value;
        }
        return is_numeric($entryid) && (int) $entryid > 0
            ? $DB->get_field('datalynx_contents', 'content', ['fieldid' => $fieldid, 'entryid' => (int) $entryid], IGNORE_MULTIPLE)
            : null;
    }

    /**
     * Whether the return trip is ticked for an entry.
     *
     * @param int|string $entryid
     * @param \stdClass|null $formdata
     * @return bool
     */
    public function is_return_trip($entryid, ?\stdClass $formdata = null): bool {
        $fieldid = (int) ($this->get_billing_settings()['returnfield'] ?? 0);
        if (!$fieldid) {
            return false;
        }
        $value = $this->value_of($fieldid, $entryid, $formdata);
        if (is_array($value)) {
            return (bool) array_filter($value, fn($v) => (int) $v > 0);
        }
        return trim((string) $value, " #,0") !== '';
    }

    /**
     * The billed distance in km: the routed distance, doubled for a return trip.
     *
     * @param int $entryid
     * @param float|null $onewaykm the one-way distance; null to read the stored route
     * @param \stdClass|null $formdata
     * @return float|null null without a route
     */
    public function billed_km(int $entryid, ?float $onewaykm = null, ?\stdClass $formdata = null): ?float {
        global $DB;
        if ($onewaykm === null) {
            $route = route_result::from_json($DB->get_field(
                'datalynx_contents',
                'content4',
                ['fieldid' => $this->field->id, 'entryid' => $entryid],
                IGNORE_MULTIPLE
            ) ?: null);
            if ($route === null) {
                return null;
            }
            $onewaykm = $route->distance_km();
        }
        return $onewaykm * ($this->is_return_trip($entryid, $formdata) ? 2 : 1);
    }

    /**
     * The errors of a billing route: no route, or more km asked for than billed without a reason.
     *
     * @param int|string $entryid
     * @param itinerary $journey the submitted stops
     * @param string $waypointsname the element of the stops
     * @param \stdClass $formdata
     * @return array element => error
     */
    public function billing_errors($entryid, itinerary $journey, string $waypointsname, \stdClass $formdata): array {
        if (!$this->is_billing() || !$journey->is_route()) {
            return [];
        }
        $route = $this->server_route($journey);
        if ($route === null) {
            return [$waypointsname => get_string('errnoroute', 'datalynxfield_itinerary')];
        }
        $settings = $this->get_billing_settings();
        $requestedid = (int) ($settings['requestedfield'] ?? 0);
        $justificationid = (int) ($settings['justificationfield'] ?? 0);
        if (!$requestedid || !$justificationid) {
            return [];
        }
        $requested = $this->value_of($requestedid, $entryid, $formdata);
        $billed = $this->billed_km(is_numeric($entryid) ? (int) $entryid : 0, $route->distance_km(), $formdata);
        $justification = trim(strip_tags((string) $this->value_of($justificationid, $entryid, $formdata)));
        if (is_numeric($requested) && round((float) $requested, 1) > round($billed, 1) && $justification === '') {
            return ["field_{$justificationid}_{$entryid}" => get_string(
                'errjustification',
                'datalynxfield_itinerary',
                format_float($billed, 1)
            )];
        }
        return [];
    }

    /**
     * Copy the route to another entry, and index its stops there.
     *
     * @param int $fromentryid
     * @param int $toentryid
     */
    public function copy_content_to_new_entry(int $fromentryid, int $toentryid): void {
        parent::copy_content_to_new_entry($fromentryid, $toentryid);
        $this->reindex_entry($toentryid);
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
     * An itinerary can be matched against another entry's route.
     *
     * @return string[]
     */
    public function supported_relative_criteria(): array {
        return [match_compiler::OP_ROUTE];
    }

    /**
     * {@inheritDoc}
     *
     * A corridor match does not become a search criterion. It has to be able to run in both
     * directions - does their route carry me, or does mine carry them - and the filter engine
     * combines criteria as one flat AND group with one flat OR group, so it cannot hold that
     * alternative. It is applied by {@see \mod_datalynx\local\rule\match_compiler} as a
     * post-filter over the candidates the searchable criteria produced.
     *
     * @param string $relation
     * @param string $storedvalue
     * @param array $options
     * @return array|null always null
     * @see datalynxfield_base::compile_relative_criterion()
     */
    public function compile_relative_criterion(string $relation, string $storedvalue, array $options = []): ?array {
        return null;
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

        // One end is a search in its own right: "where can I get to from here" and
        // "what comes here" are both worth asking. An end whose coordinates never
        // arrived - the address was typed but no suggestion picked - is dropped, so
        // it cannot narrow the search by accident.
        foreach (['from', 'to'] as $end) {
            if (!is_numeric($search["{$end}lat"]) || !is_numeric($search["{$end}lng"])) {
                $search["{$end}lat"] = null;
                $search["{$end}lng"] = null;
            }
        }

        $hasend = $search['fromlat'] !== null || $search['tolat'] !== null;

        return $hasend ? $search : false;
    }

    /**
     * The route search from the app's inputs: {key}_from and {key}_to as addresses, {key}_radius.
     *
     * The addresses are looked up with the site's geocoder, the best match each. An end that is
     * not found is left out, like an end without coordinates on the web; with neither end found
     * there is no search.
     *
     * @param array $inputs
     * @param string $key
     * @return array|null
     */
    public function customfilter_value(array $inputs, string $key): ?array {
        $value = [
            'fromlat' => null, 'fromlng' => null, 'tolat' => null, 'tolng' => null,
            'fromaddress' => '', 'toaddress' => '',
            'radius' => $this->search_radius($inputs["{$key}_radius"] ?? ''),
        ];
        foreach (['from', 'to'] as $end) {
            $text = self::search_text($inputs["{$key}_{$end}"] ?? '');
            if ($text === '') {
                continue;
            }
            $place = $this->resolve_address($text);
            $value["{$end}address"] = $place ? $place->address : $text;
            if ($place) {
                $value["{$end}lat"] = $place->lat;
                $value["{$end}lng"] = $place->lng;
            }
        }

        return ($value['fromlat'] !== null || $value['tolat'] !== null) ? $value : null;
    }

    /**
     * The route control: the addresses entered, the place each was matched to, the radius.
     *
     * @param array $inputs
     * @param string $key
     * @return array
     */
    public function customfilter_describe(array $inputs, string $key): array {
        $radius = $this->search_radius($inputs["{$key}_radius"] ?? '');
        $radii = self::SEARCH_RADII;
        if (!in_array((int) $radius, $radii, true) && (float) (int) $radius === $radius) {
            $radii[] = (int) $radius;
            sort($radii);
        }
        $described = [
            'fromname' => get_string('searchfrom', 'datalynxfield_itinerary'),
            'toname' => get_string('searchto', 'datalynxfield_itinerary'),
            'radiusname' => get_string('searchradius', 'datalynxfield_itinerary'),
            'radius' => (string) $radius,
            'radiusoptions' => array_map(fn(int $km): array => [
                'value' => (string) $km,
                'label' => get_string('radiuskm', 'datalynxfield_location', $km),
                'selected' => (float) $km === $radius,
            ], $radii),
            'notes' => [],
        ];
        foreach (['from', 'to'] as $end) {
            $text = self::search_text($inputs["{$key}_{$end}"] ?? '');
            $described[$end] = $text;
            $described["{$end}place"] = '';
            if ($text === '') {
                continue;
            }
            $place = $this->resolve_address($text);
            if ($place) {
                $described["{$end}place"] = get_string('searchmatched', 'datalynxfield_itinerary', $place->address);
            } else {
                $described['notes'][] = get_string('searchnotfound', 'datalynxfield_itinerary', $text);
            }
        }

        return $described;
    }

    /**
     * Check a route search from outside: coordinates in range, a sensible radius, short addresses.
     *
     * @param array $value
     * @return array
     * @throws invalid_parameter_exception
     */
    public function clean_composite_search(array $value): array {
        foreach (['fromlat' => 90, 'fromlng' => 180, 'tolat' => 90, 'tolng' => 180] as $part => $limit) {
            $coordinate = $value[$part] ?? '';
            if ($coordinate === '' || $coordinate === null) {
                continue;
            }
            if (!is_numeric($coordinate) || abs((float) $coordinate) > $limit) {
                throw new invalid_parameter_exception("Route search: '$part' is not a coordinate");
            }
        }
        $radius = $value['radius'] ?? '';
        if ($radius !== '' && $radius !== null && (!is_numeric($radius) || $radius <= 0 || $radius > self::MAX_SEARCH_RADIUS)) {
            throw new invalid_parameter_exception(
                'Route search: the radius must be between 0 and ' . self::MAX_SEARCH_RADIUS . ' km'
            );
        }
        foreach (['fromaddress', 'toaddress'] as $part) {
            if (core_text::strlen((string) ($value[$part] ?? '')) > self::MAX_ADDRESS_LENGTH) {
                throw new invalid_parameter_exception("Route search: '$part' is too long");
            }
        }

        return $value;
    }

    /**
     * Whether the route search can be offered outside the web: it needs the site's geocoder.
     *
     * @return bool
     */
    public function can_search_by_address(): bool {
        return geocoder_factory::instance() !== null;
    }

    /**
     * The best match of the site's geocoder for an address, looked up once per request.
     *
     * The geocoder caches its results across requests and keeps to the service's rate limit.
     *
     * @param string $address
     * @return place|null
     */
    protected function resolve_address(string $address): ?place {
        $countries = provider_config::clean_countries((string) ($this->field->param7 ?? ''))
            ?: provider_config::geocoder_countries();
        $memo = $countries . '|' . core_text::strtolower($address);
        if (!array_key_exists($memo, self::$resolved)) {
            $geocoder = geocoder_factory::instance();
            $places = $geocoder ? $geocoder->search($address, ['countries' => $countries, 'limit' => 1]) : [];
            self::$resolved[$memo] = $places ? reset($places) : null;
        }

        return self::$resolved[$memo];
    }

    /**
     * Forget the addresses looked up in this request (tests switch geocoders).
     *
     * @return void
     */
    public static function reset_resolved_addresses(): void {
        self::$resolved = [];
    }

    /**
     * A radius from the inputs, the field's default when none or none that makes sense.
     *
     * @param mixed $radius
     * @return float
     */
    protected function search_radius($radius): float {
        $radius = is_numeric($radius) ? (float) $radius : 0.0;

        return ($radius > 0 && $radius <= self::MAX_SEARCH_RADIUS) ? $radius : $this->get_match_radius();
    }

    /**
     * An address as typed, trimmed and cut to the length a search accepts.
     *
     * @param mixed $text
     * @return string
     */
    protected static function search_text($text): string {
        return is_scalar($text) ? core_text::substr(trim((string) $text), 0, self::MAX_ADDRESS_LENGTH) : '';
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

        // Either end alone still describes a search; neither does not.
        if ($from === null && $to === null) {
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
    public function get_supported_search_operators(): array {
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
    public function format_search_value($searchparams): string {
        [$not, , $value] = $searchparams;

        if (!is_array($value)) {
            return (string) $value;
        }

        // An end without coordinates is not part of the search, whatever text the
        // address box still holds, so it is left blank rather than described.
        $describe = function (string $end) use ($value): string {
            if (!is_numeric($value["{$end}lat"] ?? null) || !is_numeric($value["{$end}lng"] ?? null)) {
                return '';
            }

            $address = trim((string) ($value["{$end}address"] ?? ''));
            if ($address !== '') {
                return $address;
            }

            return round((float) $value["{$end}lat"], 4) . ', '
                . round((float) $value["{$end}lng"], 4);
        };

        $radius = !empty($value['radius']) ? (float) $value['radius'] : $this->get_match_radius();

        return trim($not . ' ' . get_string('matchesroute', 'datalynxfield_itinerary'))
            . ' ' . trim($describe('from') . ' &rarr; ' . $describe('to'))
            . ' (' . get_string('radiuskm', 'datalynxfield_location', $radius) . ')';
    }

    /**
     * Is $value an empty itinerary?
     *
     * @param mixed $value
     * @return bool
     */
    public static function is_fieldvalue_empty($value): bool {
        if (is_array($value)) {
            $value = $value['waypoints'] ?? reset($value);
        }

        return !itinerary::from_json((string) $value)->is_route();
    }
}
