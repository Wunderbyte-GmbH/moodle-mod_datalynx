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

use context_module;
use moodle_exception;

/**
 * Resolves one location field into the settings and permissions around it.
 *
 * Shared by the renderer, which needs the client side settings, and by the
 * geocoding web services, which additionally have to check that the caller is
 * allowed to use this field before spending one of the site's rate limited
 * lookups on them.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_context {
    /**
     * Constructor.
     *
     * @param \stdClass $field Record from datalynx_fields.
     * @param \stdClass $cm Course module record.
     */
    protected function __construct(
        /** @var \stdClass Record from datalynx_fields. */
        protected \stdClass $field,
        /** @var \stdClass Course module record. */
        protected \stdClass $cm
    ) {
    }

    /**
     * Field types whose editors need address lookups.
     *
     * @var string[]
     */
    const GEOCODING_FIELD_TYPES = ['location', 'itinerary'];

    /**
     * Load the context of one field that uses the map services.
     *
     * @param int $fieldid
     * @return self
     * @throws moodle_exception When the field does not exist or does not use geocoding.
     */
    public static function load(int $fieldid): self {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal(self::GEOCODING_FIELD_TYPES, SQL_PARAMS_NAMED, 'type');
        $params['id'] = $fieldid;

        $field = $DB->get_record_select('datalynx_fields', "id = :id AND type {$insql}", $params);
        if (!$field) {
            throw new moodle_exception('invalidfield', 'datalynx', '', $fieldid);
        }

        $cm = get_coursemodule_from_instance('datalynx', $field->dataid, 0, false, MUST_EXIST);

        return new self($field, $cm);
    }

    /**
     * The module context this field lives in.
     *
     * @return context_module
     */
    public function get_context(): context_module {
        return context_module::instance($this->cm->id);
    }

    /**
     * Require that the current user may use this field's geocoding.
     *
     * Anyone who can see the entries of the activity qualifies. The check exists
     * so the web services cannot be used as an open geocoding proxy against the
     * site's rate limit budget.
     */
    public function require_can_geocode(): void {
        require_login($this->cm->course, false, $this->cm);
        require_capability('mod/datalynx:viewentry', $this->get_context());
    }

    /**
     * Country restriction for this field: its own override, else the site default.
     *
     * @return string
     */
    public function get_countries(): string {
        $override = provider_config::clean_countries((string) ($this->field->param7 ?? ''));

        return $override !== '' ? $override : provider_config::geocoder_countries();
    }

    /**
     * Zoom level to use once a location is known.
     *
     * @return int
     */
    public function get_zoom(): int {
        $zoom = (int) ($this->field->param4 ?? 0);

        return $zoom > 0 ? $zoom : provider_config::default_view()['zoom'];
    }
}
