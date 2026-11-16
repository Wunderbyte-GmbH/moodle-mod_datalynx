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
use mod_datalynx\external\team_subscription;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\entry\entry_submission_service;
use mod_datalynx\local\field\datalynxfield_behavior;

/**
 * Inside a fieldgroup a team member select field is a plain list of people: the team features
 * that act on the entry as a whole do not apply (mod_datalynx#312).
 *
 * Each test sets up the same team field twice: one inside a fieldgroup, one on its own.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxfield_teammemberselect\field::is_fieldgroup_subfield
 */
final class teammemberselect_fieldgroup_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass the team member */
    private \stdClass $member;

    /** @var int team member select field inside a fieldgroup */
    private int $ingroup;

    /** @var int team member select field on its own */
    private int $alone;

    /** @var int */
    private int $groupid;

    #[\Override]
    protected function setUp(): void {
        global $COURSE;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        datalynxfield_behavior::reset_condition_cache();

        [$this->course, $this->dlx] = $this->create_mobile_instance();
        $COURSE = $this->course;
        $this->member = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->member->id, $this->course->id, 'student');
        $this->getDataGenerator()->enrol_user($this->member->id, $this->course->id, 'editingteacher');

        // Every team option set: members may edit (9), add themselves (7), unsubscribe (8), notify (6).
        $settings = [
            'param1' => '5', 'param2' => json_encode([datalynx::PERMISSION_STUDENT, datalynx::PERMISSION_TEACHER]),
            'param6' => '1', 'param7' => '1', 'param8' => '1', 'param9' => '1',
        ];
        $this->ingroup = $this->add_mobile_field($this->dlx, 'teammemberselect', 'Line people', $settings);
        $this->alone = $this->add_mobile_field($this->dlx, 'teammemberselect', 'Team', $settings);
        $this->groupid = $this->add_mobile_field($this->dlx, 'fieldgroup', 'Lines', [
            'param1' => json_encode([(string) $this->ingroup]), 'param2' => '3', 'param3' => '1',
        ]);
        $this->dlx = new datalynx($this->dlx->id());
    }

    /**
     * Only the field inside the fieldgroup counts as a subfield.
     */
    public function test_is_fieldgroup_subfield(): void {
        $this->assertTrue($this->dlx->get_field_from_id($this->ingroup)->is_fieldgroup_subfield());
        $this->assertFalse($this->dlx->get_field_from_id($this->alone)->is_fieldgroup_subfield());
    }

    /**
     * "Members may edit" grants edit rights only through the field outside the fieldgroup.
     */
    public function test_members_may_edit(): void {
        $this->setUser($this->member);
        $members = json_encode([(int) $this->member->id]);

        $this->assertFalse($this->dlx->teammember_can_edit((object) ["c{$this->ingroup}_content" => $members]));
        $this->assertTrue($this->dlx->teammember_can_edit((object) ["c{$this->alone}_content" => $members]));
    }

    /**
     * A behavior "visible to team members" ignores the field inside the fieldgroup.
     */
    public function test_visible_to_team_members(): void {
        $members = json_encode([(int) $this->member->id]);
        $behaviors = [];
        foreach ([$this->ingroup, $this->alone] as $fieldid) {
            $id = $this->add_mobile_behavior($this->dlx, "Team {$fieldid}", ['visibletoteammember' => [$fieldid]]);
            $behaviors[$fieldid] = datalynxfield_behavior::from_id($id);
        }

        $this->setUser($this->member);
        $entry = (object) ["c{$this->ingroup}_content" => $members, "c{$this->alone}_content" => $members];
        $this->assertFalse($behaviors[$this->ingroup]->is_visible_to_teammember($entry));
        $this->assertTrue($behaviors[$this->alone]->is_visible_to_teammember($entry));
    }

    /**
     * Nobody subscribes to a line: the web service refuses it and changes nothing.
     */
    public function test_self_subscription(): void {
        global $DB;

        $entryid = $this->add_mobile_entry($this->dlx, get_admin()->id, [$this->ingroup => '[]', $this->alone => '[]']);
        $this->setUser($this->member);

        $subscribe = fn(int $fieldid): array => team_subscription::execute(
            $this->dlx->id(),
            $entryid,
            $fieldid,
            $this->member->id,
            'subscribe'
        );
        $refused = $subscribe($this->ingroup);
        $this->assertFalse($refused['success']);
        $this->assertSame(get_string('teamsubscriptionfieldgroup', 'datalynxfield_teammemberselect'), $refused['error']);
        $stored = fn(int $fieldid) => $DB->get_field(
            'datalynx_contents',
            'content',
            ['entryid' => $entryid, 'fieldid' => $fieldid]
        );
        $this->assertSame('[]', $stored($this->ingroup));

        $this->assertTrue($subscribe($this->alone)['success']);
        $this->assertSame(
            json_encode([(int) $this->member->id]),
            $DB->get_field('datalynx_contents', 'content', ['entryid' => $entryid, 'fieldid' => $this->alone])
        );
    }

    /**
     * The renderer offers no subscribe link for the field inside the fieldgroup.
     *
     * @covers \datalynxfield_teammemberselect\renderer::render_display_mode
     */
    public function test_no_subscribe_link(): void {
        $this->setUser($this->member);
        $entry = (object) ['id' => 1, "c{$this->ingroup}_content" => '[]', "c{$this->alone}_content" => '[]'];

        $ingroup = $this->dlx->get_field_from_id($this->ingroup)->renderer()->render_display_mode($entry, ['subscribe' => true]);
        $alone = $this->dlx->get_field_from_id($this->alone)->renderer()->render_display_mode($entry, ['subscribe' => true]);

        $this->assertStringNotContainsString('datalynxfield_subscribe', $ingroup);
        $this->assertStringContainsString('datalynxfield_subscribe', $alone);
    }

    /**
     * A team field (reference field set) inside a fieldgroup creates no entries for the members.
     */
    public function test_team_field_creates_no_entries(): void {
        global $DB;

        $DB->set_field('datalynx_fields', 'param5', -1, ['id' => $this->ingroup]);
        $dlx = new datalynx($this->dlx->id());
        $entryid = $this->add_mobile_entry($dlx, get_admin()->id, [$this->ingroup => json_encode([(int) $this->member->id])]);
        $before = $DB->count_records('datalynx_entries', ['dataid' => $dlx->id()]);

        $this->assertSame([], (new datalynx_entries($dlx))->create_approved_entries_for_team([$entryid]));
        $this->assertSame($before, $DB->count_records('datalynx_entries', ['dataid' => $dlx->id()]));
    }

    /**
     * Saving several lines with members notifies nobody and stores aligned lines; empty lines go.
     */
    public function test_saving_lines(): void {
        global $DB;

        $viewid = $this->add_mobile_view($this->dlx, 'grid', 'Edit', '[[Lines]] [[Team]] ##edit##', [
            'section' => '##addnewentry## ##entries##',
        ]);
        $name = fn(int $line): string => "field_{$this->ingroup}_-1_fieldgroup_{$this->groupid}_{$line}";
        $sink = $this->redirectEvents();
        $teamupdated = fn(): int => count(array_filter(
            $sink->get_events(),
            fn($event) => $event instanceof \mod_datalynx\event\team_updated
        ));

        $result = (new entry_submission_service($this->dlx->get_view('grid', $viewid, false)))->save([-1], (object) [
            $name(0) => [(int) $this->member->id],
            $name(1) => [(int) get_admin()->id],
            $name(2) => [],
        ]);

        $this->assertSame([], $result['errors']);
        $entryid = (int) reset($result['entryids']);
        $this->assertSame(
            [json_encode([(int) $this->member->id]), json_encode([(int) get_admin()->id])],
            array_values($DB->get_fieldset_select(
                'datalynx_contents',
                'content',
                'entryid = ? AND fieldid = ? ORDER BY id',
                [$entryid, $this->ingroup]
            ))
        );
        $this->assertSame(0, $teamupdated(), 'no member notifications from a fieldgroup line');

        // The same team field outside a fieldgroup notifies the added member.
        (new entry_submission_service($this->dlx->get_view('grid', $viewid, false)))->save([-1], (object) [
            "field_{$this->alone}_-1" => [(int) $this->member->id],
        ]);
        $this->assertSame(1, $teamupdated());
        $sink->close();
    }
}
