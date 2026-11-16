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

/**
 * The quick search finds entries by the labels of their options and the names of their team members,
 * which are stored as ids. Team members are found whether or not they are still enrolled, and
 * whether the member list holds their ids as numbers or strings.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\filter\datalynx_filter::get_search_sql
 */
final class filter_quick_search_test extends advanced_testcase {
    use mobile_test_trait;

    /**
     * Search terms match single choice labels, multiple choice labels and team member names.
     */
    public function test_quick_search_matches_labels_and_names(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $member = $this->getDataGenerator()->create_user(['firstname' => 'Quirinus', 'lastname' => 'Member']);
        [, $dlx] = $this->create_mobile_instance();
        $radioid = $this->add_mobile_field($dlx, 'radiobutton', 'Colour', ['param1' => "Crimson\nTeal"]);
        $selectid = $this->add_mobile_field($dlx, 'select', 'Size', ['param1' => "Gigantic\nTiny"]);
        $multiid = $this->add_mobile_field($dlx, 'multiselect', 'Tags', ['param1' => "Velvet\nSilk"]);
        $teamid = $this->add_mobile_field($dlx, 'teammemberselect', 'Team', ['param2' => json_encode([1, 2, 3, 4, 5])]);
        $viewid = $this->add_mobile_view($dlx, 'grid', 'Entries');

        $crimson = $this->add_mobile_entry($dlx, $USER->id, [$radioid => '1']);
        $gigantic = $this->add_mobile_entry($dlx, $USER->id, [$selectid => '1']);
        $velvet = $this->add_mobile_entry($dlx, $USER->id, [$multiid => '#1#']);
        $team = $this->add_mobile_entry($dlx, $USER->id, [$teamid => json_encode([(string) $member->id])]);
        $teamints = $this->add_mobile_entry($dlx, $USER->id, [$teamid => json_encode([(int) $member->id])]);
        $this->add_mobile_entry($dlx, $USER->id, [$radioid => '2', $selectid => '2', $multiid => '#2#']);

        $searches = [
            'Crimson' => [$crimson],
            'Gigantic' => [$gigantic],
            'Velvet' => [$velvet],
            'Quirinus Mem' => [$team, $teamints],
        ];
        foreach ($searches as $search => $expected) {
            $view = $dlx->get_view('grid', $viewid, false);
            $view->set_filter(['search' => $search], true);
            $entries = new datalynx_entries($dlx, $view->get_filter());

            $found = array_map('intval', array_keys($entries->get_entries()->entries ?? []));
            sort($found);
            $this->assertSame($expected, $found, $search);
        }
    }
}
