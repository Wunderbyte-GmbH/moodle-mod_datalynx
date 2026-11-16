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
 * Ids are compared as whole numbers, not as parts of text (mod_datalynx#317).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class id_comparison_test extends advanced_testcase {
    use mobile_test_trait;

    /**
     * A rule for team field 12 does not trigger on team field 2 (or 1, or 120).
     *
     * @covers \datalynxrule_eventnotification\rule
     */
    public function test_team_trigger_matches_the_whole_field_id(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        $record = ['id' => 1, 'dataid' => $dlx->id(), 'type' => 'eventnotification', 'name' => 'Team', 'enabled' => 1];
        for ($i = 1; $i <= 10; $i++) {
            $record["param$i"] = '';
        }
        $record['param1'] = json_encode(['team_updated:12', 'entry_created']);
        $rule = new \datalynxrule_eventnotification\rule($dlx, (object) $record);
        $checkteam = new \ReflectionMethod($rule, 'checkteam');
        $checkteam->setAccessible(true);
        $event = fn(int $fieldid) => \mod_datalynx\event\team_updated::create([
            'context' => $dlx->context, 'objectid' => 1,
            'other' => ['dataid' => $dlx->id(), 'fieldid' => $fieldid, 'name' => 'Team',
                'addedmembers' => '[]', 'removedmembers' => '[]'],
        ]);

        $this->assertTrue($checkteam->invoke($rule, $event(12)));
        foreach ([1, 2, 120] as $other) {
            $this->assertFalse($checkteam->invoke($rule, $event($other)), "field $other");
        }
    }

    /**
     * The quick search for the label of option 2 of a multiple choice field does not find
     * entries that chose option 21.
     *
     * @covers \mod_datalynx\local\filter\datalynx_filter::get_search_sql
     */
    public function test_quick_search_matches_whole_option_ids(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        $labels = array_map(fn($i) => "Option $i", range(1, 21));
        $labels[1] = 'Zebra';
        $fieldid = $this->add_mobile_field($dlx, 'multiselect', 'Tags', ['param1' => implode("\n", $labels)]);
        $viewid = $this->add_mobile_view($dlx, 'grid', 'Entries', '[[Tags]]');
        $dlx = new datalynx($dlx->id());
        $two = $this->add_mobile_entry($dlx, $USER->id, [$fieldid => '#2#']);
        $this->add_mobile_entry($dlx, $USER->id, [$fieldid => '#21#']);

        $view = $dlx->get_view('grid', $viewid, false);
        $view->set_filter(['search' => 'Zebra'], true);
        $found = array_keys((new datalynx_entries($dlx, $view->get_filter()))->get_entries()->entries ?? []);

        $this->assertSame([$two], array_map('intval', $found));
    }

    /**
     * Opening the form for several new entries (##addnewentries##, new=3) is no processed save.
     *
     * @covers \mod_datalynx\local\view\base::process_data
     */
    public function test_opening_several_new_entries(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        $this->add_mobile_field($dlx, 'text', 'Title');
        $viewid = $this->add_mobile_view($dlx, 'grid', 'Add', '[[Title]]', ['section' => '##addnewentries## ##entries##']);
        $dlx = new datalynx($dlx->id());
        $_GET['new'] = '3';
        $_GET['sesskey'] = sesskey();

        $view = $dlx->get_view('grid', $viewid, true);
        $view->process_data();

        $this->assertSame([-3], array_map('intval', $view->get_editentries()));
        unset($_GET['new'], $_GET['sesskey']);
    }
}
