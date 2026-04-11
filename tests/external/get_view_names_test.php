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
 * External function tests for get_view_names.
 *
 * @package    mod_datalynx
 * @category   external
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_datalynx\external\get_view_names
 * @runTestsInSeparateProcesses
 */
final class get_view_names_test extends advanced_testcase {
    /**
     * Test execute returns all datalynx views sorted by name.
     *
     * @covers ::execute
     */
    public function test_execute_returns_all_views(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);

        $viewrecords = [
            (object) [
                'dataid' => $instance->id,
                'type' => 'tabular',
                'name' => 'Zulu',
                'description' => '',
                'visible' => 7,
                'filter' => 0,
                'perpage' => 0,
                'groupby' => '',
                'section' => '',
                'param5' => 0,
                'param10' => 0,
            ],
            (object) [
                'dataid' => $instance->id,
                'type' => 'tabular',
                'name' => 'Alpha',
                'description' => '',
                'visible' => 7,
                'filter' => 0,
                'perpage' => 0,
                'groupby' => '',
                'section' => '',
                'param5' => 0,
                'param10' => 0,
            ],
        ];

        $expectedids = [];
        foreach ($viewrecords as $viewrecord) {
            $expectedids[$viewrecord->name] = (int) $DB->insert_record('datalynx_views', $viewrecord);
        }

        $result = get_view_names::execute($instance->id);
        $result = external_api::clean_returnvalue(get_view_names::execute_returns(), $result);

        $filtered = array_values(array_filter($result, static function (array $view): bool {
            return in_array($view['name'], ['Alpha', 'Zulu'], true);
        }));

        $this->assertSame(
            [
                ['id' => $expectedids['Alpha'], 'name' => 'Alpha'],
                ['id' => $expectedids['Zulu'], 'name' => 'Zulu'],
            ],
            $filtered
        );
    }
}
