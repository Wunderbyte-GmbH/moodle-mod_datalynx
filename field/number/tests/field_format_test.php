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
 * Tests for the number field format.
 *
 * @package    datalynxfield_number
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_number;

use advanced_testcase;
use stdClass;

/**
 * Tests for the slider configuration of the number field format.
 *
 * @covers \datalynxfield_number\field_format
 */
final class field_format_test extends advanced_testcase {
    /**
     * Build a format instance carrying the given settings.
     *
     * @param array $settings
     * @return field_format
     */
    private function make_format(array $settings): field_format {
        $record = new stdClass();
        $record->id = 0;
        $record->dataid = 0;
        $record->name = 'slider';
        $record->fieldtype = 'number';
        $record->settings = json_encode($settings);

        return new field_format($record);
    }

    /**
     * Data provider for the generated value sequences.
     *
     * @return array
     */
    public static function slider_values_provider(): array {
        return [
            'linear integer steps' => [
                0.0, 5.0, field_format::SCALE_LINEAR, 1.0,
                [0, 1, 2, 3, 4, 5],
            ],
            'linear step wider than range end' => [
                0.0, 10.0, field_format::SCALE_LINEAR, 4.0,
                [0, 4, 8],
            ],
            'linear fractional steps do not accumulate float error' => [
                0.0, 1.0, field_format::SCALE_LINEAR, 0.1,
                [0, 0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1],
            ],
            'linear negative start' => [
                -2.0, 2.0, field_format::SCALE_LINEAR, 1.0,
                [-2, -1, 0, 1, 2],
            ],
            'fibonacci from zero' => [
                0.0, 50.0, field_format::SCALE_FIBONACCI, 1.0,
                [0, 1, 2, 3, 5, 8, 13, 21, 34],
            ],
            'fibonacci skips members below the start value' => [
                5.0, 40.0, field_format::SCALE_FIBONACCI, 1.0,
                [5, 8, 13, 21, 34],
            ],
            'one two five from zero' => [
                0.0, 50.0, field_format::SCALE_ONETWOFIVE, 1.0,
                [0, 1, 2, 5, 10, 20, 50],
            ],
            'one two five skips members below the start value' => [
                3.0, 200.0, field_format::SCALE_ONETWOFIVE, 1.0,
                [3, 5, 10, 20, 50, 100, 200],
            ],
        ];
    }

    /**
     * The generated sequences match the documented scales.
     *
     * @dataProvider slider_values_provider
     * @param float $start
     * @param float $end
     * @param string $scale
     * @param float $step
     * @param array $expected
     */
    public function test_generate_slider_values(
        float $start,
        float $end,
        string $scale,
        float $step,
        array $expected
    ): void {
        $values = field_format::generate_slider_values($start, $end, $scale, $step);

        $this->assertEqualsWithDelta($expected, $values, 0.000001);
    }

    /**
     * Configurations that cannot produce a usable scale yield no values.
     */
    public function test_generate_slider_values_rejects_broken_ranges(): void {
        $this->assertSame([], field_format::generate_slider_values(5.0, 5.0, field_format::SCALE_LINEAR, 1.0));
        $this->assertSame([], field_format::generate_slider_values(10.0, 5.0, field_format::SCALE_LINEAR, 1.0));
        $this->assertSame([], field_format::generate_slider_values(0.0, 5.0, field_format::SCALE_LINEAR, 0.0));
        $this->assertSame([], field_format::generate_slider_values(0.0, 5.0, field_format::SCALE_LINEAR, -1.0));
    }

    /**
     * A range far too fine for a slider is capped rather than exhausting memory.
     */
    public function test_generate_slider_values_is_capped(): void {
        $values = field_format::generate_slider_values(0.0, 1000000.0, field_format::SCALE_LINEAR, 1.0);

        $this->assertCount(field_format::MAX_SLIDER_VALUES, $values);
    }

    /**
     * A format that is not configured as a slider offers no slider values.
     */
    public function test_get_slider_values_only_for_slider_input(): void {
        $format = $this->make_format([
            'decimals' => 2,
            'sliderstart' => 0,
            'sliderend' => 10,
            'sliderscale' => field_format::SCALE_LINEAR,
            'sliderstep' => 1,
        ]);

        $this->assertFalse($format->is_slider());
        $this->assertSame([], $format->get_slider_values());
    }

    /**
     * A configured slider format exposes its values.
     */
    public function test_get_slider_values(): void {
        $format = $this->make_format([
            'inputtype' => field_format::INPUT_SLIDER,
            'sliderstart' => 0,
            'sliderend' => 50,
            'sliderscale' => field_format::SCALE_FIBONACCI,
            'sliderstep' => 1,
        ]);

        $this->assertTrue($format->is_slider());
        $this->assertEqualsWithDelta([0, 1, 2, 3, 5, 8, 13, 21, 34], $format->get_slider_values(), 0.000001);
    }

    /**
     * An incomplete slider configuration falls back to the plain number input.
     */
    public function test_get_slider_values_with_incomplete_settings(): void {
        $format = $this->make_format([
            'inputtype' => field_format::INPUT_SLIDER,
            'sliderstart' => '',
            'sliderend' => '',
        ]);

        $this->assertSame([], $format->get_slider_values());
    }

    /**
     * Slider settings are only validated when the slider input type is selected.
     */
    public function test_validate_settings_ignores_unused_slider_settings(): void {
        $errors = field_format::validate_settings([
            'inputtype' => field_format::INPUT_TEXT,
            'sliderstart' => '10',
            'sliderend' => '5',
            'sliderscale' => field_format::SCALE_LINEAR,
            'sliderstep' => '0',
        ]);

        $this->assertSame([], $errors);
    }

    /**
     * Broken slider configurations are reported on the offending element.
     */
    public function test_validate_settings_reports_broken_configurations(): void {
        $base = [
            'inputtype' => field_format::INPUT_SLIDER,
            'sliderstart' => '0',
            'sliderend' => '50',
            'sliderscale' => field_format::SCALE_LINEAR,
            'sliderstep' => '1',
        ];

        $this->assertSame([], field_format::validate_settings($base));

        $this->assertArrayHasKey(
            'sliderstart',
            field_format::validate_settings(array_merge($base, ['sliderstart' => 'abc']))
        );
        $this->assertArrayHasKey(
            'sliderend',
            field_format::validate_settings(array_merge($base, ['sliderend' => '0']))
        );
        $this->assertArrayHasKey(
            'sliderstep',
            field_format::validate_settings(array_merge($base, ['sliderstep' => '0']))
        );
        $this->assertArrayHasKey(
            'sliderstep',
            field_format::validate_settings(array_merge($base, ['sliderstep' => '0.0001']))
        );
        $this->assertArrayHasKey(
            'sliderscale',
            field_format::validate_settings(array_merge($base, [
                'sliderstart' => '100',
                'sliderend' => '140',
                'sliderscale' => field_format::SCALE_FIBONACCI,
            ]))
        );
    }
}
