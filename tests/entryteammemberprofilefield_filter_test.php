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
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use advanced_testcase;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\filter\search_criteria;

/**
 * Filters on the profile of the team members (institution, department).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxfield_entryteammemberprofilefield\field
 */
final class entryteammemberprofilefield_filter_test extends advanced_testcase {
    use mobile_test_trait;

    /**
     * Every team member select field brings an institution and a department field.
     */
    public function test_fields_exist_for_team_member_fields(): void {
        $this->resetAfterTest();
        [, $dlx] = $this->create_mobile_instance();
        $teamid = $this->add_mobile_field($dlx, 'teammemberselect', 'Team', ['param2' => json_encode([1, 2, 3, 4])]);

        $fields = $dlx->get_fields();

        $this->assertArrayHasKey("entryteammemberprofilefield_{$teamid}_institution", $fields);
        $this->assertArrayHasKey("entryteammemberprofilefield_{$teamid}_department", $fields);
        $this->assertSame('Team ' . get_string('department'), $fields["entryteammemberprofilefield_{$teamid}_department"]->name());
    }

    /**
     * "My profile field" on the department selects the entries whose team holds a member of the
     * user's department, and so does a literal value; through the criteria the browse services decode.
     */
    public function test_department_criteria_select_entries(): void {
        global $USER;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $nurse = $generator->create_user(['department' => 'Ward 1']);
        $other = $generator->create_user(['department' => 'Ward 2']);
        $lead = $generator->create_user(['department' => 'Ward 1']);
        [, $dlx] = $this->create_mobile_instance();
        $teamid = $this->add_mobile_field($dlx, 'teammemberselect', 'Team', ['param2' => json_encode([1, 2, 3, 4])]);
        $viewid = $this->add_mobile_view($dlx, 'grid', 'Entries');
        $this->setAdminUser();
        $ward1 = $this->add_mobile_entry($dlx, $USER->id, [$teamid => json_encode([(string) $nurse->id])]);
        $this->add_mobile_entry($dlx, $USER->id, [$teamid => json_encode([(string) $other->id])]);
        $key = "entryteammemberprofilefield_{$teamid}_department";

        $this->setUser($lead);
        foreach ([['', 'MY_PROFILE_FIELD', ''], ['', 'LITERAL_VALUE', 'Ward 1']] as $criterion) {
            $criteria = search_criteria::decode_search(json_encode([$key => ['AND' => [$criterion]]]), $dlx);
            $view = $dlx->get_view('grid', $viewid, false);
            $view->set_filter(['customsearch' => $criteria], true);
            $entries = new datalynx_entries($dlx, $view->get_filter());

            $this->assertSame([$ward1], array_map('intval', array_keys($entries->get_entries()->entries)), $criterion[1]);
        }
    }
}
