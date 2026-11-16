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

namespace datalynxfield_text;

use mod_datalynx\local\field\datalynxfield_base;

/**
 * Text field class.
 */
class field extends datalynxfield_base {
    /**
     * One line of text, typed by its format setting (param4); an autocomplete takes plain text.
     *
     * @param array $options
     * @return array
     */
    public function get_input_contract(array $options = []): array {
        $formats = [
            'alphanumeric' => PARAM_ALPHANUM,
            // The format check accepts decimals ("3.5"); PARAM_INT would cut them off before it.
            'numeric' => PARAM_RAW_TRIMMED,
            'email' => PARAM_EMAIL,
            'lettersonly' => PARAM_TEXT,
            'iban' => PARAM_TEXT,
            'bicswift' => PARAM_TEXT,
            'ipv4' => PARAM_TEXT,
            'phone' => PARAM_TEXT,
        ];
        $default = $this->get('param9') ? PARAM_NOTAGS : PARAM_TEXT;
        return ['' => $formats[(string) $this->get('param4')] ?? $default];
    }

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
    public function supports_group_by(): bool {
        return true;
    }

    /**
     * Get supported search operators.
     *
     * @return array
     */
    public function get_supported_search_operators(): array {
        return ['' => get_string('empty', 'datalynx'), '=' => get_string('equal', 'datalynx'),
                'LIKE' => get_string('contains', 'datalynx')];
    }

    /**
     * Check if field is suitable for custom filters.
     *
     * @return bool
     */
    public static function is_customfilterfield(): bool {
        return true;
    }

    /**
     * A custom filter value is text the field contains.
     *
     * @param mixed $value
     * @return array
     */
    public function get_customfilter_criteria($value): array {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? [] : [['', 'LIKE', $value]];
    }
}
