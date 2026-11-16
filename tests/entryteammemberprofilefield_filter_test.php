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
use core_external\external_api;
use mod_datalynx\external\get_grid_view_data;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\filter\search_criteria;

/**
 * Filters on the profile of the team members (institution, department), mod_datalynx#305.
 *
 * Team member lists are stored as JSON arrays of integer user ids, as every writer stores them.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxfield_entryteammemberprofilefield\field
 */
final class entryteammemberprofilefield_filter_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var int */
    private int $teamid;

    /** @var int */
    private int $viewid;

    /** @var int[] entry ids by name */
    private array $entries = [];

    /** @var \stdClass[] users by name */
    private array $users = [];

    #[\Override]
    protected function setUp(): void {
        global $USER;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $this->users['nurse'] = $generator->create_user(['department' => 'Ward 1', 'institution' => 'North']);
        $this->users['other'] = $generator->create_user(['department' => 'Ward 2', 'institution' => 'South']);
        $this->users['lead'] = $generator->create_user(['department' => 'Ward 1']);
        $this->users['nodepartment'] = $generator->create_user(['department' => '']);
        $this->users['gone'] = $generator->create_user(['department' => 'Ward 3']);

        [, $this->dlx] = $this->create_mobile_instance();
        $this->teamid = $this->add_mobile_field($this->dlx, 'teammemberselect', 'Team', ['param2' => json_encode([1, 2, 3, 4])]);
        $this->viewid = $this->add_mobile_view($this->dlx, 'grid', 'Entries', '[[Team]]');
        $team = fn(string ...$names): string => json_encode(array_map(fn($name) => (int) $this->users[$name]->id, $names));
        $this->entries['ward1'] = $this->add_mobile_entry($this->dlx, $USER->id, [$this->teamid => $team('nurse')]);
        $this->entries['ward2'] = $this->add_mobile_entry($this->dlx, $USER->id, [$this->teamid => $team('other')]);
        $this->entries['mixed'] = $this->add_mobile_entry($this->dlx, $USER->id, [$this->teamid => $team('other', 'nurse')]);
        $this->entries['noteam'] = $this->add_mobile_entry($this->dlx, $USER->id, []);
        $this->entries['ward3'] = $this->add_mobile_entry($this->dlx, $USER->id, [$this->teamid => $team('gone')]);
        delete_user($this->users['gone']);
    }

    /**
     * Every team member select field brings an institution and a department field.
     */
    public function test_fields_exist_for_team_member_fields(): void {
        $fields = $this->dlx->get_fields();

        $this->assertArrayHasKey("entryteammemberprofilefield_{$this->teamid}_institution", $fields);
        $this->assertArrayHasKey("entryteammemberprofilefield_{$this->teamid}_department", $fields);
        $department = $fields["entryteammemberprofilefield_{$this->teamid}_department"];
        $this->assertSame('Team ' . get_string('department'), $department->name());
    }

    /**
     * Criteria and the entries they select, as the user named.
     *
     * @return array
     */
    public static function criteria_provider(): array {
        return [
            'my department' => ['lead', 'department', ['', 'MY_PROFILE_FIELD', ''], ['ward1', 'mixed']],
            'not my department' => ['lead', 'department', ['NOT', 'MY_PROFILE_FIELD', ''], ['ward2', 'noteam', 'ward3']],
            'literal department' => ['lead', 'department', ['', 'LITERAL_VALUE', 'Ward 2'], ['ward2', 'mixed']],
            'literal institution' => ['lead', 'institution', ['', 'LITERAL_VALUE', 'North'], ['ward1', 'mixed']],
            'own value empty' => ['nodepartment', 'department', ['', 'MY_PROFILE_FIELD', ''], []],
            'own institution empty' => ['lead', 'institution', ['', 'MY_PROFILE_FIELD', ''], []],
            'deleted members do not count' => ['lead', 'department', ['', 'LITERAL_VALUE', 'Ward 3'], []],
        ];
    }

    /**
     * The criteria select the entries whose team holds a matching user, through the entries
     * manager and through the browse web service (strict criteria).
     *
     * @dataProvider criteria_provider
     * @param string $user who searches
     * @param string $profilefield institution or department
     * @param array $criterion [not, operator, value]
     * @param string[] $expected entry names
     */
    public function test_criteria_select_entries(string $user, string $profilefield, array $criterion, array $expected): void {
        $this->setUser($this->users[$user]);
        $key = "entryteammemberprofilefield_{$this->teamid}_{$profilefield}";
        $json = json_encode([$key => ['AND' => [$criterion]]]);
        $expected = array_map(fn($name) => $this->entries[$name], $expected);
        sort($expected);

        $view = $this->dlx->get_view('grid', $this->viewid, false);
        $view->set_filter(['customsearch' => search_criteria::decode_search($json, $this->dlx)], true);
        $entries = new datalynx_entries($this->dlx, $view->get_filter());
        $found = array_map('intval', array_keys($entries->get_entries()->entries ?? []));
        sort($found);
        $this->assertSame($expected, $found, 'entries manager');

        $this->setAdminUser();
        $this->setUser($this->users[$user]);
        $this->getDataGenerator()->enrol_user($this->users[$user]->id, $this->dlx->course->id, 'editingteacher');
        $result = get_grid_view_data::execute($this->dlx->id(), $this->viewid, 0, 0, 0, '', '', '', '', 0, '', $json);
        $result = external_api::clean_returnvalue(get_grid_view_data::execute_returns(), $result);
        $found = [];
        foreach ($result['groups'] as $group) {
            foreach ($group['entries'] as $entry) {
                $found[] = (int) $entry['id'];
            }
        }
        sort($found);
        $this->assertSame($expected, $found, 'browse service');
    }
}
