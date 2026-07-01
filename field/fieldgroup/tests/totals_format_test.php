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
 * Tests for the fieldgroup "totals" field format.
 *
 * @package    datalynxfield_fieldgroup
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_fieldgroup;

use advanced_testcase;
use mod_datalynx\datalynx;
use mod_datalynx\local\field_format\manager;
use ReflectionClass;
use stdClass;

/**
 * Tests the aggregation logic and renderer integration of the fieldgroup totals format.
 *
 * @covers \datalynxfield_fieldgroup\field_format
 * @covers \datalynxfield_fieldgroup\renderer
 */
final class totals_format_test extends advanced_testcase {
    /**
     * Build a field_format instance with the given settings (no DB needed).
     *
     * @param array $settings
     * @return field_format
     */
    private function make_format(array $settings): field_format {
        return manager::get_format_instance((object) [
            'fieldtype' => 'fieldgroup',
            'name' => 'totals',
            'settings' => json_encode($settings),
        ]);
    }

    /**
     * The aggregation operations combine values as expected.
     */
    public function test_aggregate_operations(): void {
        $this->resetAfterTest();

        $values = [10.0, 20.0, 30.0];

        $this->assertEquals(60.0, $this->make_format(['aggregation' => 'sum'])->aggregate($values));
        $this->assertEquals(20.0, $this->make_format(['aggregation' => 'avg'])->aggregate($values));
        $this->assertEquals(10.0, $this->make_format(['aggregation' => 'min'])->aggregate($values));
        $this->assertEquals(30.0, $this->make_format(['aggregation' => 'max'])->aggregate($values));
        $this->assertEquals(3.0, $this->make_format(['aggregation' => 'count'])->aggregate($values));

        // Default (no setting) is sum.
        $this->assertEquals(60.0, $this->make_format([])->aggregate($values));
    }

    /**
     * Empty input yields null for value operations but a real zero for count.
     */
    public function test_aggregate_empty(): void {
        $this->resetAfterTest();

        $this->assertNull($this->make_format(['aggregation' => 'sum'])->aggregate([]));
        $this->assertNull($this->make_format(['aggregation' => 'avg'])->aggregate([]));
        $this->assertSame(0.0, $this->make_format(['aggregation' => 'count'])->aggregate([]));
    }

    /**
     * Decimal handling: explicit setting wins, otherwise the subfield's own decimals, otherwise raw.
     */
    public function test_format_value_decimals(): void {
        $this->resetAfterTest();

        // Explicit decimals on the format.
        $this->assertSame('60.00', $this->make_format(['decimals' => 2])->format_value(60.0, ''));
        // Fall back to the subfield decimals when the format has none.
        $this->assertSame('60.0', $this->make_format([])->format_value(60.0, 1));
        // No decimals anywhere -> plain numeric string.
        $this->assertSame('60', $this->make_format([])->format_value(60.0, ''));
        // Null aggregate renders as empty.
        $this->assertSame('', $this->make_format(['decimals' => 2])->format_value(null, ''));
    }

    /**
     * The label defaults to the lang string and honours a custom value.
     */
    public function test_label(): void {
        $this->resetAfterTest();

        $this->assertSame(
            get_string('fieldformattotal', 'datalynxfield_fieldgroup'),
            $this->make_format([])->get_label()
        );
        $this->assertSame('Gesamt', $this->make_format(['label' => 'Gesamt'])->get_label());
    }

    /**
     * A defined fieldgroup format must be registered as a tag so it is both offered in the
     * field tags picker (get_menu) and discovered in templates (search), not shown literally.
     */
    public function test_format_tag_is_registered_and_discoverable(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $numid = (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $dlx->id(), 'name' => 'price', 'type' => 'number', 'description' => '',
        ]);
        $fgid = (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $dlx->id(), 'name' => 'group', 'type' => 'fieldgroup', 'description' => '',
            'param1' => json_encode(["$numid"]), 'param2' => 3, 'param3' => 3, 'param4' => 0,
        ]);
        manager::save_format((object) [
            'dataid' => $dlx->id(), 'name' => 'totals', 'fieldtype' => 'fieldgroup',
            'settings' => json_encode(['aggregation' => 'sum']),
        ]);

        $renderer = $dlx->get_field_from_id($fgid, true)->renderer();

        // The format tag is offered in the field tags picker menu.
        $menutags = [];
        foreach ($renderer->get_menu() as $cat) {
            foreach ($cat as $subcat) {
                $menutags = array_merge($menutags, array_keys($subcat));
            }
        }
        $this->assertContains('[[group:totals]]', $menutags);
        $this->assertContains('[[group]]', $menutags);

        // The format tag is discovered when present in a template.
        $found = $renderer->search('Before [[group:totals]] after');
        $this->assertContains('[[group:totals]]', $found);
    }

    /**
     * The view-edit form validation must not crash on a fieldgroup format tag (e.g. [[group:summe]]).
     * These display-only tags appear in the Fieldgroups menu but are not editable fieldgroup
     * instances, so they must be skipped by the "multiple fieldgroups" validation.
     */
    public function test_view_edit_validation_ignores_format_tag(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $numid = (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $dlx->id(), 'name' => 'price', 'type' => 'number', 'description' => '',
        ]);
        $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $dlx->id(), 'name' => 'kost', 'type' => 'fieldgroup', 'description' => '',
            'param1' => json_encode(["$numid"]), 'param2' => 3, 'param3' => 3, 'param4' => 0,
        ]);
        manager::save_format((object) [
            'dataid' => $dlx->id(), 'name' => 'summe', 'fieldtype' => 'fieldgroup',
            'settings' => json_encode(['aggregation' => 'sum']),
        ]);

        $viewrecord = (object) [
            'dataid' => $dlx->id(), 'type' => 'grid', 'name' => 'Grid', 'description' => '',
            'visible' => 7, 'filter' => 0, 'perpage' => 0, 'groupby' => '', 'param5' => 0, 'param10' => 0,
            'section' => '##entries##',
            'param2' => '<div>[[kost:summe]]</div>',
        ];
        $viewrecord->id = (int) $DB->insert_record('datalynx_views', $viewrecord);
        $view = $dlx->get_view('grid', $viewrecord);

        // Reach validation() directly without building the whole moodleform.
        $reflection = new ReflectionClass(\datalynxview_grid\form::class);
        $form = $reflection->newInstanceWithoutConstructor();
        foreach (['view' => $view, 'dlx' => $dlx] as $prop => $value) {
            $p = $reflection->getProperty($prop);
            $p->setAccessible(true);
            $p->setValue($form, $value);
        }

        // Before the fix this threw "Attempt to read property fieldids on false".
        $errors = $form->validation([
            'name' => 'Grid',
            'eparam2_editor' => ['text' => '<div>[[kost:summe]]</div>'],
        ], []);
        $this->assertIsArray($errors);
        $this->assertArrayNotHasKey('eparam2_editor', $errors);
    }

    /**
     * End-to-end: a fieldgroup rendered with the totals format shows the summed column,
     * and rendering without the format leaves the output unchanged.
     */
    public function test_renderer_shows_totals(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        // Two number subfields.
        $priceid = (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $dlx->id(), 'name' => 'price', 'type' => 'number', 'description' => '',
        ]);
        $qtyid = (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $dlx->id(), 'name' => 'qty', 'type' => 'number', 'description' => '',
        ]);

        // A fieldgroup grouping them, max 3 lines (param2).
        $fgid = (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $dlx->id(), 'name' => 'group', 'type' => 'fieldgroup', 'description' => '',
            'param1' => json_encode(["$priceid", "$qtyid"]), 'param2' => 3, 'param3' => 3, 'param4' => 0,
        ]);

        // A totals format (sum, 2 decimals).
        manager::save_format((object) [
            'dataid' => $dlx->id(), 'name' => 'totals', 'fieldtype' => 'fieldgroup',
            'settings' => json_encode(['aggregation' => 'sum', 'decimals' => 2]),
        ]);
        $format = manager::get_format_by_name($dlx->id(), 'totals');

        // An entry carrying three lines of fieldgroup content.
        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(), 'userid' => 2, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        // Price column 10, 20, 30 (sum 60); qty column 1, 2 with an empty third line (sum 3).
        $entry->{"c{$priceid}_content_fieldgroup"} = ['10', '20', '30'];
        $entry->{"c{$priceid}_id_fieldgroup"} = [1, 2, 3];
        $entry->{"c{$qtyid}_content_fieldgroup"} = ['1', '2', ''];
        $entry->{"c{$qtyid}_id_fieldgroup"} = [4, 5, 6];

        $fgfield = $dlx->get_field_from_id($fgid, true);
        $renderer = $fgfield->renderer();

        // With the format: a totals row with the summed columns.
        $html = $renderer->render_display_mode($entry, ['field_format' => $format, 'edit' => false]);
        $this->assertStringContainsString('datalynx-fieldgroup-totals', $html);
        $this->assertStringContainsString(get_string('fieldformattotal', 'datalynxfield_fieldgroup'), $html);
        $this->assertStringContainsString('60.00', $html);
        $this->assertStringContainsString('3.00', $html);

        // Without the format: no totals row, output is just the lines.
        $plain = $renderer->render_display_mode($entry, ['edit' => false]);
        $this->assertStringNotContainsString('datalynx-fieldgroup-totals', $plain);

        // Subfield values are shown without the field-name label prefix (e.g. "10", not "price: 10").
        $this->assertStringContainsString('>10<', $plain);
        $this->assertStringNotContainsString('price:', $plain);
        $this->assertStringNotContainsString('qty:', $plain);
    }
}
