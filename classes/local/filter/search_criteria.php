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

namespace mod_datalynx\local\filter;

use invalid_parameter_exception;
use JsonException;
use mod_datalynx\datalynx;

/**
 * The JSON contract for search and sort criteria that arrive from a client.
 *
 * The filter engine takes custom search criteria as
 * `[fieldid => ['AND' => [[$not, $operator, $value], ...], 'OR' => [...]]]` and custom sort
 * criteria as `[fieldid => $direction]`. It accepts plain arrays for both, so a client can send
 * them as JSON. What it does not do is check them: a decoded array would otherwise reach
 * datalynx_filter unchanged. This class is the gate in front of it. Anything it returns is a
 * structure the engine was built for, made only of fields of this instance, operators those
 * fields offer and scalar values.
 *
 * Every violation throws, rather than being dropped silently: a criterion the server ignored
 * would show the user a result for a different question than the one they asked.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class search_criteria {
    /** @var int Maximum JSON nesting depth: fields, conjunction, criteria, criterion, value, list. */
    const MAX_DEPTH = 8;

    /** @var int Maximum number of fields one search may name. */
    const MAX_FIELDS = 50;

    /** @var int Maximum number of criteria under one conjunction of one field. */
    const MAX_CRITERIA = 20;

    /** @var int Maximum number of elements in a list value, such as the options of ANY_OF. */
    const MAX_VALUES = 100;

    /** @var int Maximum length of one string value. */
    const MAX_STRING = 1000;

    /** @var string[] The conjunctions the filter engine reads. */
    const CONJUNCTIONS = ['AND', 'OR'];

    /** @var string[] The values the negation slot of a criterion may hold. */
    const NEGATIONS = ['', 'NOT'];

    /**
     * Decode and validate custom search criteria.
     *
     * @param string $json JSON object `{fieldid: {AND: [[not, operator, value], ...], OR: [...]}}`
     * @param datalynx $dlx the instance the criteria are for
     * @return array criteria in the shape datalynx_filter::$customsearch accepts; empty for ''
     * @throws invalid_parameter_exception
     */
    public static function decode_search(string $json, datalynx $dlx): array {
        $decoded = self::decode($json);
        if (!$decoded) {
            return [];
        }
        if (count($decoded) > self::MAX_FIELDS) {
            throw self::invalid('too many fields');
        }

        $fields = $dlx->get_fields();
        $criteria = [];
        foreach ($decoded as $fieldid => $conjunctions) {
            $field = $fields[$fieldid] ?? null;
            if ($field === null || !$field->supports_search()) {
                throw self::invalid("unknown or unsearchable field '$fieldid'");
            }
            if (!is_array($conjunctions) || !$conjunctions) {
                throw self::invalid("field '$fieldid' carries no criteria");
            }

            $operators = array_map('strval', array_keys($field->get_supported_search_operators()));
            foreach ($conjunctions as $conjunction => $list) {
                if (!in_array($conjunction, self::CONJUNCTIONS, true)) {
                    throw self::invalid("unknown conjunction '$conjunction'");
                }
                if (!is_array($list) || !array_is_list($list) || count($list) > self::MAX_CRITERIA) {
                    throw self::invalid("conjunction '$conjunction' of field '$fieldid' is not a list of criteria");
                }
                foreach ($list as $criterion) {
                    $criteria[$fieldid][$conjunction][] = self::criterion($criterion, $operators, $field->has_composite_search());
                }
            }
        }

        return $criteria;
    }

    /**
     * Decode and validate custom sort criteria.
     *
     * @param string $json JSON object `{fieldid: direction}`, direction 0 ascending or 1 descending
     * @param datalynx $dlx the instance the criteria are for
     * @return array criteria in the shape datalynx_filter::$customsort accepts; empty for ''
     * @throws invalid_parameter_exception
     */
    public static function decode_sort(string $json, datalynx $dlx): array {
        $decoded = self::decode($json);
        if (!$decoded) {
            return [];
        }
        if (count($decoded) > self::MAX_FIELDS) {
            throw self::invalid('too many sort fields');
        }

        $fields = $dlx->get_fields();
        $sort = [];
        foreach ($decoded as $fieldid => $direction) {
            $field = $fields[$fieldid] ?? null;
            if ($field === null || !$field->supports_sort()) {
                throw self::invalid("unknown or unsortable field '$fieldid'");
            }
            if (!in_array($direction, [0, 1, '0', '1'], true)) {
                throw self::invalid("sort direction of field '$fieldid' must be 0 or 1");
            }
            $sort[$fieldid] = (int) $direction;
        }

        return $sort;
    }

    /**
     * Encode criteria for transport, the inverse of the decode methods.
     *
     * Empty criteria encode to '', which the decode methods read back as no criteria.
     *
     * @param array $criteria search or sort criteria
     * @return string
     */
    public static function encode(array $criteria): string {
        if (!$criteria) {
            return '';
        }

        // The outer cast keeps the field id map an object; the criterion lists inside stay lists.
        return json_encode((object) $criteria, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Decode a JSON object into an associative array.
     *
     * @param string $json
     * @return array empty for an empty string or an empty object
     * @throws invalid_parameter_exception
     */
    private static function decode(string $json): array {
        if (trim($json) === '') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, self::MAX_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw self::invalid('not valid JSON or nested too deeply');
        }

        // An empty JSON object and an empty array both decode to [], and both mean "no criteria".
        // Anything else that decodes to a list cannot be keyed by field id: no field has id 0.
        if (!is_array($decoded) || ($decoded && array_is_list($decoded))) {
            throw self::invalid('criteria must be a JSON object keyed by field id');
        }

        return $decoded;
    }

    /**
     * Validate one criterion.
     *
     * @param mixed $criterion expected `[$not, $operator, $value]`
     * @param string[] $operators the operators the field offers
     * @param bool $composite whether the field searches by a named set of values, such as a route
     * @return array the criterion with its parts normalised to strings where the engine expects them
     * @throws invalid_parameter_exception
     */
    private static function criterion($criterion, array $operators, bool $composite): array {
        if (!is_array($criterion) || !array_is_list($criterion) || count($criterion) !== 3) {
            throw self::invalid('a criterion must be a list of [not, operator, value]');
        }
        [$not, $operator, $value] = $criterion;

        if (!is_string($not) || !in_array($not, self::NEGATIONS, true)) {
            throw self::invalid("negation must be '' or 'NOT'");
        }
        if (!is_string($operator) || !in_array($operator, $operators, true)) {
            throw self::invalid('operator not supported by the field');
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                if (count($value) > self::MAX_VALUES) {
                    throw self::invalid('too many values');
                }
            } else if (!$composite) {
                throw self::invalid('only composite searches take a named value set');
            } else if (count($value) > self::MAX_CRITERIA) {
                throw self::invalid('too many named values');
            }
            $value = array_map([self::class, 'scalar'], $value);
        } else {
            $value = self::scalar($value);
        }

        if ($operator === 'BETWEEN' && (!is_array($value) || !array_is_list($value) || count($value) !== 2)) {
            throw self::invalid('BETWEEN needs exactly two values');
        }

        return [$not, $operator, $value];
    }

    /**
     * Reject anything but a short scalar, and return it as the string a submitted form would carry.
     *
     * The field search methods were written for form input and trim() or compare their values as
     * strings, so a JSON number or null is normalised here rather than in every one of them.
     *
     * @param mixed $value
     * @return string
     * @throws invalid_parameter_exception
     */
    private static function scalar($value): string {
        if ($value !== null && !is_scalar($value)) {
            throw self::invalid('values must be scalars');
        }
        $value = is_bool($value) ? (string) (int) $value : (string) $value;
        if (\core_text::strlen($value) > self::MAX_STRING) {
            throw self::invalid('value too long');
        }

        return $value;
    }

    /**
     * Build the exception every violation throws.
     *
     * @param string $reason
     * @return invalid_parameter_exception
     */
    private static function invalid(string $reason): invalid_parameter_exception {
        return new invalid_parameter_exception('Invalid search criteria: ' . $reason);
    }
}
