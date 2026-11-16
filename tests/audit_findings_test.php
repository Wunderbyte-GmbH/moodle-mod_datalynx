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
 * Further findings of the type audit (mod_datalynx#319).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class audit_findings_test extends advanced_testcase {
    use mobile_test_trait;

    /**
     * An edit that resets the approval (approval on update) reports the approval as changed; an
     * approval value posted by a user who may not set it is no change.
     *
     * @covers \mod_datalynx\local\datalynx_entries::process_entries
     */
    public function test_approval_change_is_what_was_saved(): void {
        global $DB;

        $this->resetAfterTest();
        [$course, $dlx] = $this->create_mobile_instance(['approval' => datalynx::APPROVAL_ON_UPDATE]);
        $student = $this->create_mobile_student($course);
        $titleid = $this->add_mobile_field($dlx, 'text', 'Title');
        $entryid = $this->add_mobile_entry($dlx, $student->id, [$titleid => 'Draft']);
        $DB->set_field('datalynx_entries', 'approved', 1, ['id' => $entryid]);
        $this->setUser($student);

        $changed = $this->update($dlx->id(), $entryid, ["field_{$titleid}_{$entryid}" => 'Edited']);

        $this->assertSame('0', $DB->get_field('datalynx_entries', 'approved', ['id' => $entryid]));
        $this->assertContains('approve', $changed);

        // Approved again by a manager; the student posts approved = 0, which is not taken.
        $DB->set_field('datalynx_entries', 'approved', 1, ['id' => $entryid]);
        $DB->set_field('datalynx', 'approval', 0, ['id' => $dlx->id()]);
        $changed = $this->update($dlx->id(), $entryid, ["field_{$titleid}_{$entryid}" => 'Edited again', 'approved' => 0]);
        $this->assertSame('1', $DB->get_field('datalynx_entries', 'approved', ['id' => $entryid]));
        $this->assertNotContains('approve', $changed);
    }

    /**
     * A team member select that was not submitted keeps its team and reports no team change.
     *
     * @covers \datalynxfield_teammemberselect\field::update_content
     */
    public function test_team_not_submitted_reports_no_change(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        $teamid = $this->add_mobile_field($dlx, 'teammemberselect', 'Team', ['param1' => '5', 'param2' => '[1,2,3,4]']);
        $entryid = $this->add_mobile_entry($dlx, $USER->id, [$teamid => json_encode([(int) $USER->id])]);
        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        $entry->{"c{$teamid}_id"} = $DB->get_field('datalynx_contents', 'id', ['entryid' => $entryid]);
        $entry->{"c{$teamid}_content"} = json_encode([(int) $USER->id]);
        $dlx = new datalynx($dlx->id());

        $sink = $this->redirectEvents();
        $dlx->get_field_from_id($teamid)->update_content($entry, []);
        $events = array_filter($sink->get_events(), fn($event) => $event instanceof event\team_updated);
        $sink->close();

        $this->assertSame([], $events);
        $this->assertSame(json_encode([(int) $USER->id]), $DB->get_field('datalynx_contents', 'content', ['entryid' => $entryid]));
    }

    /**
     * A full team offers no subscribe link to others.
     *
     * @covers \datalynxfield_teammemberselect\renderer::render_display_mode
     */
    public function test_full_team_offers_no_subscription(): void {
        global $COURSE;

        $this->resetAfterTest();
        [$course, $dlx] = $this->create_mobile_instance();
        $COURSE = $course;
        $member = $this->create_mobile_student($course);
        $other = $this->create_mobile_student($course);
        $settings = ['param1' => '1', 'param2' => json_encode([datalynx::PERMISSION_STUDENT]), 'param7' => '1'];
        $teamid = $this->add_mobile_field($dlx, 'teammemberselect', 'Team', $settings);
        $dlx = new datalynx($dlx->id());
        $this->setUser($other);
        $renderer = $dlx->get_field_from_id($teamid)->renderer();

        $fullteam = (object) ['id' => 1, "c{$teamid}_content" => json_encode([(int) $member->id])];
        $full = $renderer->render_display_mode($fullteam, ['subscribe' => true]);
        $open = $renderer->render_display_mode((object) ['id' => 2, "c{$teamid}_content" => '[]'], ['subscribe' => true]);

        $this->assertStringNotContainsString('datalynxfield_subscribe', $full);
        $this->assertStringContainsString('datalynxfield_subscribe', $open);
    }

    /**
     * A rule triggered by the cron concerns no entry.
     *
     * @covers \mod_datalynx\local\rule\base::resolve_entryid
     */
    public function test_cron_event_concerns_no_entry(): void {
        $this->resetAfterTest();
        [, $dlx] = $this->create_mobile_instance();
        $record = ['id' => 1, 'dataid' => $dlx->id(), 'type' => 'eventnotification', 'name' => 'Cron', 'enabled' => 1];
        for ($i = 1; $i <= 10; $i++) {
            $record["param$i"] = '';
        }
        $rule = new \datalynxrule_eventnotification\rule($dlx, (object) $record);
        $resolve = new \ReflectionMethod($rule, 'resolve_entryid');
        $resolve->setAccessible(true);

        $event = event\cron_triggered::create(['context' => $dlx->context, 'objectid' => $dlx->id()]);

        $this->assertSame(0, $resolve->invoke($rule, $event));
    }

    /**
     * CSV import matches single choice labels as text.
     *
     * @covers \mod_datalynx\local\field\datalynxfield_option_single::prepare_import_content
     */
    public function test_csv_import_matches_labels_as_text(): void {
        $this->resetAfterTest();
        [, $dlx] = $this->create_mobile_instance();
        $fieldid = $this->add_mobile_field($dlx, 'select', 'Code', ['param1' => "5\n05\n0"]);
        $field = (new datalynx($dlx->id()))->get_field_from_id($fieldid);
        $settings = ['Code' => ['name' => 'code']];

        foreach (['05' => 2, '5' => 1, '0' => 3] as $label => $expected) {
            $data = new \stdClass();
            $field->prepare_import_content($data, $settings, ['code' => $label], -1);
            $this->assertSame($expected, $data->{"field_{$fieldid}_-1"} ?? null, "label $label");
        }
    }

    /**
     * Empty member lists are no team, without a TypeError.
     *
     * @covers \mod_datalynx\datalynx::teammember_can_edit
     */
    public function test_empty_member_list(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        $teamid = $this->add_mobile_field($dlx, 'teammemberselect', 'Team', ['param9' => '1']);
        $dlx = new datalynx($dlx->id());

        $this->assertFalse($dlx->teammember_can_edit((object) ["c{$teamid}_content" => '']));
        $this->assertFalse($dlx->teammember_can_edit((object) ["c{$teamid}_content" => 'null']));
    }

    /**
     * Update an entry as the current user and return the changed field ids.
     *
     * @param int $dataid
     * @param int $entryid
     * @param array $data
     * @return array
     */
    private function update(int $dataid, int $entryid, array $data): array {
        $sink = $this->redirectEvents();
        (new datalynx_entries(new datalynx($dataid)))->process_entries('update', (string) $entryid, (object) $data, true);
        $changed = [];
        foreach ($sink->get_events() as $event) {
            if ($event instanceof event\entry_updated) {
                $changed = array_merge($changed, $event->other['changed_field_ids'] ?? []);
            }
        }
        $sink->close();
        return $changed;
    }
}
