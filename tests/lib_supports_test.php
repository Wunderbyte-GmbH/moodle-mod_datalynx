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

use basic_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/lib.php');

/**
 * Tests for datalynx_supports().
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::datalynx_supports
 */
final class lib_supports_test extends basic_testcase {
    /**
     * Moodle refuses to install or upgrade a module that declares the removed groupmembersonly feature.
     *
     * The upgrade check passes the plain string, and the constant is deprecated, so this does too.
     */
    public function test_does_not_declare_groupmembersonly(): void {
        $this->assertNotTrue(datalynx_supports('groupmembersonly'));
    }

    /**
     * The features the module relies on stay declared.
     */
    public function test_declares_group_features(): void {
        $this->assertTrue(datalynx_supports(FEATURE_GROUPS));
        $this->assertTrue(datalynx_supports(FEATURE_GROUPINGS));
    }
}
