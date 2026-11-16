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
    /**
     * A video address; validate() checks it is a URL.
     *
     * @param array $options
     * @return array
     */
    public function get_input_contract(array $options = []): array {
        return ['' => PARAM_TEXT];
    }

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
    public function supports_group_by(): bool {
        return false;
    }

    /**
     * Returns the supported search operators.
     *
     * @return array
     */
    public function get_supported_search_operators(): array {
        return [];
    }

    /**
     * Is this a custom filter field?
     *
     * @return bool
     */
    public static function is_customfilterfield(): bool {
        return false;
    }

    /**
     * Formats the content for the field.
     *
     * @param stdClass $entry The entry object.
     * @param ?array $values The values to format.
     * @return array
     */
    protected function format_content($entry, ?array $values = null): array {
        $oldcontents = [$entry->{"c{$this->field->id}_content"} ?? null];
        $value = $values ? reset($values) : '';
        if (is_array($value)) {
            $value = reset($value);
        }
        $videoid = self::get_video_id(trim((string) $value));

        return [$videoid === '' ? [] : [$videoid], $oldcontents];
    }

    /**
     * The video id of a YouTube address: the v parameter of a watch link, the path of a youtu.be,
     * embed or shorts link, or the value itself when it already is an id.
     *
     * @param string $url
     * @return string '' when there is none
     */
    public static function get_video_id(string $url): string {
        if ($url === '') {
            return '';
        }
        parse_str((string) parse_url($url, PHP_URL_QUERY), $vars);
        if (!empty($vars['v']) && is_string($vars['v'])) {
            return clean_param($vars['v'], PARAM_ALPHANUMEXT);
        }
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if (preg_match('~(?:^|/)(?:embed/|shorts/|v/)?([A-Za-z0-9_-]{6,})$~', $path, $matches)) {
            return $matches[1];
        }
        return '';
    }
}
