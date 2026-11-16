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
 * @package datalynxfield_number
 * @subpackage number
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_number;

use datalynxfield_text\field as TextField;
use mod_datalynx\local\field\datalynxfield_base;
use mod_datalynx\local\rule\match_compiler;
use stdClass;

/**
 * Field class for the number field type.
 *
 * @package datalynxfield_number
 */
class field extends TextField {
    /**
     * A number as typed; validate() checks it is numeric.
     *
     * @param array $options
     * @return array
     */
    public function get_input_contract(array $options = []): array {
        return ['' => PARAM_RAW_TRIMMED];
    }

    /** @var string The field type. */
    public $type = 'number';

    /**
     * Can this field be used in fieldgroups? Override if yes.
     * @var bool
     */
    protected $forfieldgroup = true;

    /**
     * A number can also be matched within a tolerance of another entry's number.
     *
     * @return string[]
     */
    public function supported_relative_criteria(): array {
        return [match_compiler::OP_SAME, match_compiler::OP_DIFFERENT, match_compiler::OP_WITHIN];
    }

    /**
     * {@inheritDoc}
     *
     * The search values of this field type are arrays - get_search_sql() reads $value[0] - so a
     * bare string would be read one character at a time.
     *
     * @param string $relation
     * @param string $storedvalue
     * @param array $options
     * @return array|null
     * @see datalynxfield_base::compile_relative_criterion()
     */
    public function compile_relative_criterion(string $relation, string $storedvalue, array $options = []): ?array {
        if (!in_array($relation, $this->supported_relative_criteria(), true) || !is_numeric(trim($storedvalue))) {
            return null;
        }
        $value = (float) trim($storedvalue);

        if ($relation === match_compiler::OP_WITHIN) {
            $tolerance = (float) ($options['tolerance'] ?? 0);
            if ($tolerance <= 0) {
                return null;
            }
            return ['', 'BETWEEN', $this->tolerance_bounds($value, $tolerance)];
        }

        return [$relation === match_compiler::OP_DIFFERENT ? 'NOT' : '', '=', [$value]];
    }

    /**
     *
     * {@inheritDoc}
     * @param array $search Search criteria array.
     * @return array
     * @see datalynxfield_base::get_search_sql()
     */
    public function get_search_sql(array $search): array {
        return $this->get_numeric_search_sql($search);
    }

    /**
     * Sorted as numbers where the stored text is a number (also with a decimal comma); other
     * contents sort as empty.
     *
     * @return string|null
     */
    public function get_sort_select_sql(): ?string {
        global $DB;
        if (!$DB->sql_regex_supported()) {
            return null;
        }
        $column = "c{$this->field->id}.content";
        // No "?" in the pattern: Moodle would read it as a query parameter.
        // A decimal comma counts as a decimal point, as in the search (see to_number()).
        return "CASE WHEN $column " . $DB->sql_regex() . " '^-{0,1}[0-9]+([.,][0-9]+){0,1}$' THEN " .
            $DB->sql_cast_char2real("REPLACE($column, ',', '.')", true) . " END";
    }

    /**
     * Formats the content for the field.
     *
     * @param stdClass $entry The entry object.
     * @param ?array $values The values to format.
     * @return array
     */
    protected function format_content($entry, ?array $values = null): array {
        $fieldid = $this->field->id;
        $contents = [];
        $oldcontents = [];

        // Old contents.
        if (isset($entry->{"c{$fieldid}_content"})) {
            $oldcontents[] = $entry->{"c{$fieldid}_content"};
        }

        $value = reset($values);

        // We want to store empty numbers as well.
        $contents[] = $value;

        return [$contents, $oldcontents];
    }

    /**
     *
     * {@inheritDoc}
     * @param mixed $formdata The submitted form data.
     * @param int $i The search index.
     * @return array|false
     * @see datalynxfield_base::parse_search()
     */
    public function parse_search($formdata, $i) {
        $values = [];

        $name = 'f_' . $i . '_' . $this->field->id;

        $data = isset($formdata->$name) ? $formdata->$name : optional_param_array($name, [], PARAM_RAW);
        if (empty($data)) {
            return false;
        }

        $field0 = isset($data[0]) ? $data[0] : '';
        if (!empty($field0) || "$field0" === "0") {
            $values[0] = $field0;
        }

        $field1 = isset($data[1]) ? $data[1] : '';
        if (!empty($field1) || "$field1" === "0") {
            $values[1] = $field1;
        }

        if (!empty($values)) {
            return $values;
        } else {
            return false;
        }
    }

    /**
     *
     * {@inheritDoc}
     * @param array $searchparams The search parameters array.
     * @return string
     * @see datalynxfield_base::format_search_value()
     */
    public function format_search_value($searchparams): string {
        [$not, $operator, $value] = $searchparams;
        if (is_array($value)) {
            if (count($value) > 1 && $operator == 'BETWEEN') {
                $value = '(' . implode(',', $value) . ')';
            } else {
                $value = $value[0];
            }
        }
        return $not . ' ' . $operator . ' ' . $value;
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_text::get_supported_search_operators()
     * @return array
     */
    public function get_supported_search_operators(): array {
        return ['' => get_string('empty', 'datalynx'), '=' => get_string('equal', 'datalynx'),
                '>' => get_string('greaterthan', 'datalynx'),
                '>=' => get_string('greaterequal', 'datalynx'),
                '<' => get_string('lessthan', 'datalynx'), '<=' => get_string('lessequal', 'datalynx'),
                'BETWEEN' => get_string('between', 'datalynx')];
    }
}
