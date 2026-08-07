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

namespace datalynxfield_location;

use mod_datalynx\local\field\datalynxfield_base;
use stdClass;

/**
 * Location field class.
 *
 * @package    datalynxfield_location
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field extends datalynxfield_base {
    /** @var string Field type */
    public $type = 'location';

    /**
     * Can this field be used in fieldgroups?
     * @var bool
     */
    protected $forfieldgroup = true;

    /**
     * Get content column identifiers.
     * Maps to content, content1 (lat), content2 (lng).
     *
     * @return array
     */
    protected function content_names() {
        return ['address', 'lat', 'lng'];
    }

    /**
     * Get content database column names.
     *
     * @return array
     */
    public function get_content_parts() {
        return ['content', 'content1', 'content2'];
    }

    /**
     * Check if group by is supported.
     *
     * @return bool
     */
    public function supports_group_by() {
        return false;
    }

    /**
     * Check if search is supported.
     *
     * @return bool
     */
    public function supports_search() {
        return true;
    }

    /**
     * Format content for storage in datalynx_contents.
     *
     * @param stdClass $entry
     * @param ?array $values
     * @return array [array of new contents, array of old contents]
     */
    protected function format_content(stdClass $entry, ?array $values = null) {
        $fieldid = $this->field->id;
        $oldcontents = [];
        $contents = [];

        // Old contents from database.
        if (isset($entry->{"c{$fieldid}_content"})) {
            $oldcontents[] = $entry->{"c{$fieldid}_content"} ?? null;
            $oldcontents[] = $entry->{"c{$fieldid}_content1"} ?? null;
            $oldcontents[] = $entry->{"c{$fieldid}_content2"} ?? null;
        }

        // New contents submitted from form.
        $address = null;
        $lat = null;
        $lng = null;

        if (!empty($values)) {
            if (isset($values['address']) || isset($values['lat']) || isset($values['lng'])) {
                $address = isset($values['address']) ? clean_param($values['address'], PARAM_TEXT) : null;
                $lat     = isset($values['lat'])     ? clean_param($values['lat'], PARAM_RAW) : null;
                $lng     = isset($values['lng'])     ? clean_param($values['lng'], PARAM_RAW) : null;
            } else {
                // If submitted directly as plain values or import string (e.g. "Address##Lat##Lng").
                $rawvalue = reset($values);
                if (strpos($rawvalue, '##') !== false) {
                    $parts = explode('##', $rawvalue);
                    $address = clean_param($parts[0] ?? '', PARAM_TEXT);
                    $lat     = clean_param($parts[1] ?? '', PARAM_RAW);
                    $lng     = clean_param($parts[2] ?? '', PARAM_RAW);
                } else {
                    $address = clean_param($rawvalue, PARAM_TEXT);
                }
            }
        }

        if (!is_null($address) && $address !== '') {
            $contents[] = $address;
            $contents[] = (string) $lat;
            $contents[] = (string) $lng;
        }

        return [$contents, $oldcontents];
    }

    /**
     * Extract search inputs from submitted search form.
     *
     * @param stdClass $formdata
     * @param int $i
     * @return array|bool
     */
    public function parse_search($formdata, $i) {
        $fieldid = $this->field->id;

        $addresskey = "f_{$i}_{$fieldid}_address";
        $latkey     = "f_{$i}_{$fieldid}_lat";
        $lngkey     = "f_{$i}_{$fieldid}_lng";
        $radiuskey  = "f_{$i}_{$fieldid}_radius";
        $basekey    = "f_{$i}_{$fieldid}";

        $searchdata = [
            'address' => '',
            'lat'     => '',
            'lng'     => '',
            'radius'  => (float) ($this->field->param5 ?? 5),
        ];

        $found = false;

        if (!empty($formdata->$addresskey)) {
            $searchdata['address'] = clean_param($formdata->$addresskey, PARAM_TEXT);
            $found = true;
        } else if (!empty($formdata->$basekey) && is_string($formdata->$basekey)) {
            $searchdata['address'] = clean_param($formdata->$basekey, PARAM_TEXT);
            $found = true;
        }

        if (isset($formdata->$latkey) && $formdata->$latkey !== '') {
            $searchdata['lat'] = (float) $formdata->$latkey;
            $found = true;
        }

        if (isset($formdata->$lngkey) && $formdata->$lngkey !== '') {
            $searchdata['lng'] = (float) $formdata->$lngkey;
            $found = true;
        }

        if (!empty($formdata->$radiuskey)) {
            $searchdata['radius'] = (float) $formdata->$radiuskey;
            $found = true;
        }

        return $found ? $searchdata : false;
    }

    /**
     * Get search SQL expression. Uses Haversine radius calculation when Lat/Lng are present.
     *
     * @param array $search [$not, $operator, $value]
     * @return array [$sql, $params, $fromcontent]
     */
    public function get_search_sql(array $search): array {
        global $DB;

        [$not, $operator, $searchdata] = $search;

        static $i = 0;
        $i++;
        $fieldid = $this->field->id;
        $alias = "c{$fieldid}";

        if (empty($searchdata) || !is_array($searchdata)) {
            return ['', [], false];
        }

        $address = $searchdata['address'] ?? '';
        $lat     = isset($searchdata['lat']) && $searchdata['lat'] !== '' ? (float) $searchdata['lat'] : null;
        $lng     = isset($searchdata['lng']) && $searchdata['lng'] !== '' ? (float) $searchdata['lng'] : null;
        $radius  = !empty($searchdata['radius']) ? (float) $searchdata['radius'] : 5.0;

        // 1. Haversine distance search if Lat and Lng are provided.
        if (!is_null($lat) && !is_null($lng)) {
            $paramlat = "df_lat_{$fieldid}_{$i}";
            $paramlng = "df_lng_{$fieldid}_{$i}";
            $paramrad = "df_rad_{$fieldid}_{$i}";

            $sql = "(6371 * ACOS(
                COS(RADIANS(:$paramlat)) * COS(RADIANS(CAST({$alias}.content1 AS DECIMAL(10,6))))
                * COS(RADIANS(CAST({$alias}.content2 AS DECIMAL(10,6))) - RADIANS(:$paramlng))
                + SIN(RADIANS(:$paramlat)) * SIN(RADIANS(CAST({$alias}.content1 AS DECIMAL(10,6))))
            )) <= :$paramrad";

            $params = [
                $paramlat => $lat,
                $paramlng => $lng,
                $paramrad => $radius,
            ];

            return [$sql, $params, true];
        }

        // 2. Text search fallback if only address is specified.
        if ($address !== '') {
            $paramname = "df_addr_{$fieldid}_{$i}";
            $varcharcontent = $this->get_sql_compare_text('content');
            $sql = $DB->sql_like($varcharcontent, ":$paramname", false);
            $params = [$paramname => "%$address%"];

            return [$sql, $params, true];
        }

        return ['', [], false];
    }

    /**
     * Get supported search operators.
     *
     * @return array
     */
    public function get_supported_search_operators() {
        return [
            ''     => get_string('empty', 'datalynx'),
            'LIKE' => get_string('contains', 'datalynx'),
        ];
    }

    /**
     * Is $value a valid content or do we see an empty input?
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public static function is_fieldvalue_empty($value) {
        if (empty($value)) {
            return true;
        }
        if (is_array($value)) {
            return empty($value['address']) && empty($value[0]);
        }
        return false;
    }
}
