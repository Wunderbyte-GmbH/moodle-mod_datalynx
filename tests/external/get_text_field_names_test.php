<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_datalynx\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

use advanced_testcase;
use core_external\external_api;

/**
 * External function tests for get_text_field_names.
 *
 * @package    mod_datalynx
 * @category   external
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_datalynx\external\get_text_field_names
 * @runTestsInSeparateProcesses
 */
final class get_text_field_names_test extends advanced_testcase {
    /**
     * Test execute returns text fields sorted by name and excludes other field types.
     *
     * @covers ::execute
     */
    public function test_execute_returns_all_text_fields(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);

        $expectedids = [];
        foreach (['Zulu text', 'Alpha text'] as $fieldname) {
            $expectedids[$fieldname] = (int) $DB->insert_record('datalynx_fields', (object) [
                'dataid' => $instance->id,
                'type' => 'text',
                'name' => $fieldname,
                'description' => '',
                'param1' => '',
                'param2' => '',
                'param3' => '',
                'param4' => '',
                'param5' => '',
                'param6' => '',
                'param7' => '',
                'param8' => '',
                'param9' => '',
                'param10' => '',
            ]);
        }

        $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $instance->id,
            'type' => 'number',
            'name' => 'Ignored field',
            'description' => '',
            'param1' => '',
            'param2' => '',
            'param3' => '',
            'param4' => '',
            'param5' => '',
            'param6' => '',
            'param7' => '',
            'param8' => '',
            'param9' => '',
            'param10' => '',
        ]);

        $result = get_text_field_names::execute($instance->id);
        $result = external_api::clean_returnvalue(get_text_field_names::execute_returns(), $result);

        $this->assertSame(
            [
                ['id' => $expectedids['Alpha text'], 'name' => 'Alpha text'],
                ['id' => $expectedids['Zulu text'], 'name' => 'Zulu text'],
            ],
            $result
        );
    }
}
