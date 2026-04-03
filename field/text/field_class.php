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
 * @package datalynxfield_text
 * @subpackage text
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_datalynx\local\field\datalynxfield_base;

defined('MOODLE_INTERNAL') || die();

/**
 * Text field class.
 */
class datalynxfield_text extends datalynxfield_base {
    /** @var string Field type */
    public $type = 'text';

    /**
     * Can this field be used in fieldgroups?
     * @var bool
     */
    protected $forfieldgroup = true;

    /**
     * Check if group by is supported.
     *
     * @return bool
     */
    public function supports_group_by() {
        return true;
    }

    /**
     * Get supported search operators.
     *
     * @return array
     */
    public function get_supported_search_operators() {
        return ['' => get_string('empty', 'datalynx'), '=' => get_string('equal', 'datalynx'),
                'LIKE' => get_string('contains', 'datalynx')];
    }

    /**
     * Check if field is suitable for custom filters.
     *
     * @return bool
     */
    public static function is_customfilterfield() {
        return true;
    }
}
