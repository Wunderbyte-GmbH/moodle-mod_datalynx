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
 * @package datalynxfield
 * @subpackage youtube
 * @copyright 2021 michael pollak <moodle@michaelpollak.org>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/../field_class.php');

class datalynxfield_youtube extends datalynxfield_base {

    public $type = 'youtube';

    /**
     * Can this field be used in fieldgroups?
     * @var boolean
     */
    protected $forfieldgroup = false;

    public function supports_group_by() {
        return false;
    }

    public function get_supported_search_operators() {
        return array();
    }

    public static function is_customfilterfield() {
        return false;
    }

    protected function format_content($entry, array $values = null) {
        $fieldid = $this->field->id;
        $oldcontents = array();
        $contents = array();

        if (!empty($values)) {
            if (count($values) === 1) {
                $values = reset($values);
            }
        }
        // Remove youtube prefix and only store the key.
        // Source: https://stackoverflow.com/questions/3392993/php-regex-to-get-youtube-video-id.
        parse_str( parse_url( $values, PHP_URL_QUERY ), $vars );
        $contents[] = $vars['v'];

        return array($contents);
    }
}
