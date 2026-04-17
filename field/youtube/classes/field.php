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

/**
 *
 * @package datalynxfield_youtube
 * @subpackage youtube
 * @copyright 2021 michael pollak <moodle@michaelpollak.org>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_youtube;

use mod_datalynx\local\field\datalynxfield_base;
use stdClass;


/**
 * Field class for the youtube field type.
 */
class field extends datalynxfield_base {
    /** @var string The field type. */
    public $type = 'youtube';

    /**
     * Can this field be used in fieldgroups?
     * @var bool
     */
    protected $forfieldgroup = false;

    /**
     * Does this field support group by?
     *
     * @return bool
     */
    public function supports_group_by() {
        return false;
    }

    /**
     * Returns the supported search operators.
     *
     * @return array
     */
    public function get_supported_search_operators() {
        return [];
    }

    /**
     * Is this a custom filter field?
     *
     * @return bool
     */
    public static function is_customfilterfield() {
        return false;
    }

    /**
     * Formats the content for the field.
     *
     * @param stdClass $entry The entry object.
     * @param array|null $values The values to format.
     * @return array
     */
    protected function format_content($entry, array $values = null) {
        $contents = [];

        if (!empty($values)) {
            if (count($values) === 1) {
                $values = reset($values);
            }
        }
        // Remove youtube prefix and only store the key.
        // Source: https://stackoverflow.com/questions/3392993/php-regex-to-get-youtube-video-id.
        parse_str(parse_url($values, PHP_URL_QUERY), $vars);
        $contents[] = $vars['v'];

        return [$contents];
    }
}
