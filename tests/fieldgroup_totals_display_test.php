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
 * Tests the display of a fieldgroup with a totals format.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use mod_datalynx\local\field_format\manager;

/**
 * The fieldgroup's totals format used to be passed on to every subfield, so a date subfield
 * rendered nothing and empty amounts showed as 0.00.
 *
 * @covers \datalynxfield_fieldgroup\renderer::render_display_mode
 * @covers \datalynxfield_number\renderer::render_display_mode
 */
final class fieldgroup_totals_display_test extends advanced_testcase {
    /**
     * Subfields keep their own rendering when the fieldgroup tag uses a totals format.
     */
    public function test_subfields_ignore_fieldgroup_format(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dataid = (int) $instance->id;
        $mk = function (string $type, string $name, array $params = []) use ($DB, $dataid): int {
            $record = (object) ['dataid' => $dataid, 'type' => $type, 'name' => $name, 'description' => ''];
            for ($i = 1; $i <= 10; $i++) {
                $record->{"param$i"} = $params["param$i"] ?? '';
            }
            return (int) $DB->insert_record('datalynx_fields', $record);
        };
        $dateid = $mk('time', 'Invoicedate', ['param1' => '1']);
        $amountid = $mk('number', 'Amount', ['param1' => '2', 'param4' => '1']);
        $groupid = $mk('fieldgroup', 'Costs', [
            'param1' => json_encode(["$dateid:||", "$amountid:||"]), 'param2' => '2', 'param3' => '2', 'param4' => '0',
        ]);
        manager::save_format((object) [
            'dataid' => $dataid, 'name' => 'total', 'fieldtype' => 'fieldgroup',
            'settings' => json_encode(['aggregation' => 'sum', 'decimals' => 2, 'label' => 'Total']),
        ]);
        $format = manager::get_format_by_name($dataid, 'total');
        $this->assertNotNull($format);

        $date = make_timestamp(2026, 11, 9, 12, 0, 0);
        $entry = (object) [
            'id' => 1, 'userid' => get_admin()->id, 'status' => 0,
            "c{$dateid}_content_fieldgroup" => [(string) $date, '0'],
            "c{$dateid}_id_fieldgroup" => [11, 12],
            "c{$amountid}_content_fieldgroup" => ['350', ''],
            "c{$amountid}_id_fieldgroup" => [21, 22],
        ];

        $group = (new datalynx($dataid))->get_field_from_id($groupid, true);
        $html = $group->renderer()->render_display_mode($entry, ['field_format' => $format]);

        $this->assertStringContainsString(userdate($date, get_string('strftimedate')), $html, 'date of the first row');
        $this->assertStringContainsString('350.00', $html, 'amount of the first row');
        // The empty second row must not show 0.00 (every "0.00" belongs to a "350.00": row and total).
        $this->assertSame(substr_count($html, '350.00'), substr_count($html, '0.00'), 'empty amount rendered as 0.00');
    }
}
