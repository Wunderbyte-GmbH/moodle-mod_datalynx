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
 * External function tests for toggle_behavior.
 *
 * @package    mod_datalynx
 * @category   external
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_datalynx\external\toggle_behavior
 * @runTestsInSeparateProcesses
 */
final class toggle_behavior_test extends advanced_testcase {
    /**
     * Test execute toggles required, visibleto, and editableby states.
     *
     * @covers ::execute
     */
    public function test_execute_toggles_behavior_states(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);

        $behaviorid = (int) $DB->insert_record('datalynx_behaviors', (object) [
            'dataid' => $instance->id,
            'name' => 'Toggle test',
            'description' => '',
            'visibleto' => serialize(['permissions' => [1, 2], 'users' => [], 'teammember' => []]),
            'editableby' => serialize([1, 2]),
            'required' => 0,
        ]);

        $requiredresult = toggle_behavior::execute($behaviorid, 'required');
        $requiredresult = external_api::clean_returnvalue(toggle_behavior::execute_returns(), $requiredresult);
        $this->assertTrue($requiredresult['enabled']);
        $this->assertSame(1, (int) $DB->get_field('datalynx_behaviors', 'required', ['id' => $behaviorid]));

        $visibleresult = toggle_behavior::execute($behaviorid, 'visibleto', 3);
        $visibleresult = external_api::clean_returnvalue(toggle_behavior::execute_returns(), $visibleresult);
        $this->assertTrue($visibleresult['enabled']);

        $visibleto = unserialize($DB->get_field('datalynx_behaviors', 'visibleto', ['id' => $behaviorid]));
        $this->assertContains(3, $visibleto['permissions']);

        $editableresult = toggle_behavior::execute($behaviorid, 'editableby', 2);
        $editableresult = external_api::clean_returnvalue(toggle_behavior::execute_returns(), $editableresult);
        $this->assertFalse($editableresult['enabled']);

        $editableby = unserialize($DB->get_field('datalynx_behaviors', 'editableby', ['id' => $behaviorid]));
        $this->assertSame([1], array_values($editableby));
    }
}
