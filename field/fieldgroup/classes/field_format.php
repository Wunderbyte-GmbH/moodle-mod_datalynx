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

namespace datalynxfield_fieldgroup;

/**
 * Field format implementation for fieldgroup fields.
 *
 * @package    datalynxfield_fieldgroup
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_format extends \mod_datalynx\local\field_format\base {
    /**
     * Defines configuration elements on the form.
     *
     * The admin edit UI builds its form via the dedicated
     * {@see \datalynxfield_fieldgroup\form\field_format_form} class; this mirrors those
     * elements for parity with other field types (e.g. number).
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form(\MoodleQuickForm &$mform) {
        $mform->addElement(
            'select',
            'aggregation',
            get_string('fieldformat_aggregation', 'datalynxfield_fieldgroup'),
            self::get_aggregation_options()
        );
        $mform->setDefault('aggregation', 'sum');

        $mform->addElement(
            'text',
            'decimals',
            get_string('fieldformat_decimals', 'datalynxfield_fieldgroup'),
            ['size' => 3]
        );
        $mform->setType('decimals', PARAM_INT);

        $mform->addElement('text', 'label', get_string('fieldformat_label', 'datalynxfield_fieldgroup'));
        $mform->setType('label', PARAM_TEXT);
    }

    /**
     * Returns whether this field format supports custom configuration options.
     *
     * @return bool
     */
    public function has_options(): bool {
        return true;
    }

    /**
     * Returns the available aggregation operations keyed by their stored value.
     *
     * @return array<string, string>
     */
    public static function get_aggregation_options(): array {
        return [
            'sum' => get_string('fieldformat_agg_sum', 'datalynxfield_fieldgroup'),
            'avg' => get_string('fieldformat_agg_avg', 'datalynxfield_fieldgroup'),
            'min' => get_string('fieldformat_agg_min', 'datalynxfield_fieldgroup'),
            'max' => get_string('fieldformat_agg_max', 'datalynxfield_fieldgroup'),
            'count' => get_string('fieldformat_agg_count', 'datalynxfield_fieldgroup'),
        ];
    }

    /**
     * Returns the configured aggregation operation (defaults to sum).
     *
     * @return string
     */
    public function get_aggregation(): string {
        return (string) $this->get_setting('aggregation', 'sum');
    }

    /**
     * Aggregates a list of numeric values using the configured operation.
     *
     * @param float[] $values The raw numeric values to aggregate.
     * @return float|null The aggregate, or null when there is nothing to aggregate.
     */
    public function aggregate(array $values): ?float {
        if (empty($values)) {
            // Count of nothing is a meaningful zero; every other operation has no result.
            return $this->get_aggregation() === 'count' ? 0.0 : null;
        }
        switch ($this->get_aggregation()) {
            case 'avg':
                return array_sum($values) / count($values);
            case 'min':
                return min($values);
            case 'max':
                return max($values);
            case 'count':
                return (float) count($values);
            case 'sum':
            default:
                return array_sum($values);
        }
    }

    /**
     * Formats an aggregated value for display, applying the configured decimals
     * (falling back to the subfield's own decimal setting when none is configured).
     *
     * @param float|null $value The aggregated value, or null when there is nothing to show.
     * @param mixed $fallbackdecimals The subfield's own decimals setting (param1).
     * @return string
     */
    public function format_value(?float $value, $fallbackdecimals): string {
        if ($value === null) {
            return '';
        }
        $decimals = $this->get_setting('decimals');
        if ($decimals === null || $decimals === '') {
            $decimals = $fallbackdecimals;
        }
        if ($decimals !== null && $decimals !== '') {
            $decimals = (int) $decimals;
            return sprintf("%4.{$decimals}f", $value);
        }
        return (string) (float) $value;
    }

    /**
     * Returns the label shown next to the totals row.
     *
     * @return string
     */
    public function get_label(): string {
        $label = trim((string) $this->get_setting('label', ''));
        return $label !== '' ? $label : get_string('fieldformat_total', 'datalynxfield_fieldgroup');
    }
}
