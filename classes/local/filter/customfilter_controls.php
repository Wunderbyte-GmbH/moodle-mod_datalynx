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

use core_date;
use DateTime;
use mod_datalynx\local\view\base;
use stdClass;

/**
 * The controls of a custom filter and the values they send: one contract for every client.
 *
 * The custom filter form of the web (datalynx_customfilter_frontend_form) and the one of the
 * Moodle App (templates/mobile/view_page) are both built from describe() and both send their
 * inputs under the same names; values() reads them for customfilter_search::criteria()
 * (mod_datalynx#270). The two forms differ only in how the controls look.
 *
 * Inputs, all prefixed with PREFIX in a form:
 * - search: the full-text search; userid: author user ids
 * - {key}: the value of a text, select, multiselect, approve, status or presence control
 * - {key}_from, {key}_to: the bounds of a range or daterange control
 * - {key}_mode: 'any' or 'all' options of a multiselect control
 * - sort, sortdir: the sort field and 0 ascending / 1 descending
 *
 * A list may arrive as an array or as "a,b"; a day as a timestamp of its start or as YYYY-MM-DD.
 * {key} is the field id, for an internal field its name (approve, status, timecreated,
 * timemodified).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class customfilter_controls {
    /** @var string The prefix of the inputs in a form. */
    const PREFIX = 'cf_';

    /**
     * The controls of the filter in a view, with the values entered so far.
     *
     * A field whose search no control can express (control 'unsupported', e.g. the route search
     * of an itinerary) is listed with that control; the web shows its own search elements for it,
     * the app sends the user to the web.
     *
     * @param customfilter_search $search
     * @param base|null $view null: the whole instance
     * @param array $inputs input name without the prefix => value
     * @return array ['controls' => [...], 'unsupported' => field names, 'sortfields' => [...], 'sort' => …, 'sortdir' => …]
     */
    public static function describe(customfilter_search $search, ?base $view, array $inputs): array {
        $customfilter = $search->get_customfilter();
        $context = $search->get_context();
        $controls = [];
        $unsupported = [];

        if (!empty($customfilter->fulltextsearch)) {
            $controls[] = self::control('search', 'text', get_string('search'), [], $inputs);
        }
        if (!empty($customfilter->authorsearch)) {
            $label = get_string('authorsearch', 'datalynx');
            $controls[] = self::control('userid', 'select', $label, $search->get_authors(), $inputs);
        }
        foreach ($search->get_fields($view) as $fieldid => $field) {
            $renderer = $field->renderer();
            $control = $renderer->get_customfilter_control();
            $name = format_string($field->name(), true, ['context' => $context]);
            if ($control === 'unsupported') {
                $unsupported[] = $name;
            }
            $controls[] = self::control((string) $fieldid, $control, $name, $renderer->get_customfilter_options(), $inputs);
        }

        $sortfields = [];
        foreach ($search->get_sort_fields($view) as $fieldid => $name) {
            $sortfields[] = ['value' => (string) $fieldid, 'label' => format_string($name, true, ['context' => $context])];
        }

        return [
            'controls' => $controls,
            'unsupported' => $unsupported,
            'sortfields' => $sortfields,
            'sort' => self::scalar($inputs['sort'] ?? ''),
            'sortdir' => self::scalar($inputs['sortdir'] ?? '') === '1' ? '1' : '0',
        ];
    }

    /**
     * The values the inputs stand for, in the shape customfilter_search::criteria() takes.
     *
     * @param customfilter_search $search
     * @param base|null $view null: the whole instance
     * @param array $inputs input name without the prefix => value
     * @param stdClass|null $formdata the submitted web form, for the search elements of a field
     *                                no control can express (read by the field's parse_search())
     * @return array
     */
    public static function values(customfilter_search $search, ?base $view, array $inputs, ?stdClass $formdata = null): array {
        $customfilter = $search->get_customfilter();
        $values = [];
        if (!empty($customfilter->fulltextsearch)) {
            $values['search'] = self::scalar($inputs['search'] ?? '');
        }
        if (!empty($customfilter->authorsearch)) {
            $values['userid'] = self::split($inputs['userid'] ?? '');
        }
        foreach ($search->get_fields($view) as $fieldid => $field) {
            $key = (string) $fieldid;
            $value = match ($field->renderer()->get_customfilter_control()) {
                'text', 'presence', 'approve', 'status' => self::scalar($inputs[$key] ?? ''),
                'range' => ['from' => self::scalar($inputs["{$key}_from"] ?? ''), 'to' => self::scalar($inputs["{$key}_to"] ?? '')],
                'daterange' => [
                    'from' => self::day_to_timestamp($inputs["{$key}_from"] ?? '', false),
                    'to' => self::day_to_timestamp($inputs["{$key}_to"] ?? '', true),
                ],
                'select' => self::split($inputs[$key] ?? ''),
                'multiselect' => [
                    'ids' => self::split($inputs[$key] ?? ''),
                    'mode' => self::scalar($inputs["{$key}_mode"] ?? '') === 'all' ? 'all' : 'any',
                ],
                default => $formdata ? self::composite_value($field, $key, $formdata) : null,
            };
            if ($value !== null) {
                $values[$key] = $value;
            }
        }
        $values['sort'] = [
            'field' => self::scalar($inputs['sort'] ?? ''),
            'dir' => self::scalar($inputs['sortdir'] ?? '') === '1' ? 1 : 0,
        ];

        return $values;
    }

    /**
     * The inputs of a submitted form: its PREFIX values, without the prefix.
     *
     * @param array|stdClass $data
     * @return array
     */
    public static function inputs_from($data): array {
        $inputs = [];
        foreach ((array) $data as $name => $value) {
            if (is_string($name) && str_starts_with($name, self::PREFIX)) {
                $inputs[substr($name, strlen(self::PREFIX))] = $value;
            }
        }

        return $inputs;
    }

    /**
     * The value of a field that keeps its own search elements, f_{i}_{fieldid}..., on the web.
     *
     * @param object $field
     * @param string $key
     * @param stdClass $formdata
     * @return mixed null when the form holds none
     */
    private static function composite_value(object $field, string $key, stdClass $formdata) {
        if (!$field->has_composite_search()) {
            return null;
        }
        $pattern = '/^f_(\d+)_' . preg_quote($key, '/') . '(?:$|_)/';
        foreach (array_keys(get_object_vars($formdata)) as $name) {
            if (preg_match($pattern, $name, $matches)) {
                return $field->parse_search($formdata, (int) $matches[1]) ?: null;
            }
        }

        return null;
    }

    /**
     * One control.
     *
     * @param string $key
     * @param string $control
     * @param string $name
     * @param array $options value => label
     * @param array $inputs
     * @return array
     */
    private static function control(string $key, string $control, string $name, array $options, array $inputs): array {
        $selected = self::split($inputs[$key] ?? '');
        $choices = [];
        foreach ($options as $value => $label) {
            $choices[] = [
                'value' => (string) $value,
                'label' => (string) $label,
                'selected' => in_array((string) $value, $selected, true),
            ];
        }

        return [
            'key' => $key,
            'control' => $control,
            'name' => $name,
            'options' => $choices,
            'selected' => $selected,
            'value' => self::scalar($inputs[$key] ?? ''),
            'from' => self::day_or_text($inputs["{$key}_from"] ?? '', $control),
            'to' => self::day_or_text($inputs["{$key}_to"] ?? '', $control),
            'mode' => self::scalar($inputs["{$key}_mode"] ?? '') === 'all' ? 'all' : 'any',
        ];
    }

    /**
     * A single value as text; lists are no single value.
     *
     * @param mixed $value
     * @return string
     */
    private static function scalar($value): string {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * A list, sent as an array or as "a,b,c".
     *
     * @param mixed $value
     * @return string[]
     */
    private static function split($value): array {
        $items = is_array($value) ? $value : (is_scalar($value) ? explode(',', (string) $value) : []);
        $items = array_map(fn($item) => is_scalar($item) ? trim((string) $item) : '', $items);

        return array_values(array_filter($items, fn(string $item) => $item !== ''));
    }

    /**
     * A bound as a form shows it again: a day as YYYY-MM-DD, other bounds as entered.
     *
     * @param mixed $value
     * @param string $control
     * @return string
     */
    private static function day_or_text($value, string $control): string {
        if ($control !== 'daterange') {
            return self::scalar($value);
        }
        $timestamp = self::day_to_timestamp($value, false);

        return $timestamp ? userdate($timestamp, '%Y-%m-%d', core_date::get_user_timezone(), false) : '';
    }

    /**
     * The start (or the end) of a day, sent as YYYY-MM-DD or as the timestamp of its start, in
     * the user's timezone.
     *
     * @param mixed $day
     * @param bool $end the last second of the day rather than the first
     * @return int 0 when no day
     */
    private static function day_to_timestamp($day, bool $end): int {
        $tz = core_date::get_user_timezone_object();
        if (is_numeric($day) && (int) $day > 0) {
            $date = (new DateTime('@' . (int) $day))->setTimezone($tz)->setTime(0, 0);
        } else if (
            is_string($day) && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $day, $m)
                && checkdate((int) $m[2], (int) $m[3], (int) $m[1])
        ) {
            $date = new DateTime(sprintf('%04d-%02d-%02d 00:00:00', $m[1], $m[2], $m[3]), $tz);
        } else {
            return 0;
        }
        if ($end) {
            $date->modify('+1 day')->modify('-1 second');
        }

        return $date->getTimestamp();
    }
}
