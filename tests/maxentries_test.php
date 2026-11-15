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

namespace mod_datalynx;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/lib.php');

use advanced_testcase;

/**
 * The maximum number of entries of an instance stays within the site's limit.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::datalynx_limit_maxentries
 * @covers     ::datalynx_add_instance
 */
final class maxentries_test extends advanced_testcase {
    /**
     * Site limits, instance settings and the setting an instance ends up with.
     *
     * @return array
     */
    public static function limit_provider(): array {
        return [
            'no site limit, unlimited' => [-1, -1, -1],
            'no site limit, own limit' => [-1, 5, 5],
            'no site limit, no entries' => [-1, 0, 0],
            'no site limit, not given' => [-1, null, -1],
            'site allows none' => [0, 5, 0],
            'within the site limit' => [10, 3, 3],
            'above the site limit' => [10, 20, 10],
            'unlimited under a site limit' => [10, -1, 10],
        ];
    }

    /**
     * The instance keeps its setting within the site's limit.
     *
     * @dataProvider limit_provider
     * @param int $sitemax
     * @param int|null $requested
     * @param int $expected
     */
    public function test_limit(int $sitemax, ?int $requested, int $expected): void {
        $this->resetAfterTest();
        set_config('datalynx_maxentries', $sitemax);

        $this->assertSame($expected, datalynx_limit_maxentries($requested));
    }

    /**
     * Adding an instance keeps its own setting instead of taking the site's.
     */
    public function test_add_instance_keeps_its_setting(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('datalynx_maxentries', -1);
        $course = $this->getDataGenerator()->create_course();

        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id, 'maxentries' => 3]);

        $this->assertEquals(3, $DB->get_field('datalynx', 'maxentries', ['id' => $instance->id]));
    }
}
