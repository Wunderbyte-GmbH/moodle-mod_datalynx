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

namespace mod_datalynx\local\filter;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use advanced_testcase;
use backup;
use backup_controller;
use mod_datalynx\datalynx;
use mod_datalynx\mobile_test_trait;
use restore_controller;

/**
 * The stored form of the criteria of saved filters, and of team member lists (mod_datalynx#305).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\filter\stored_criteria
 * @covers     \datalynxfield_teammemberselect\field::normalize_stored_content
 */
final class stored_criteria_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var int */
    private int $teamid;

    /** @var int */
    private int $textid;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $this->dlx] = $this->create_mobile_instance();
        $this->teamid = $this->add_mobile_field($this->dlx, 'teammemberselect', 'Team', ['param2' => json_encode([1, 2, 3, 4])]);
        $this->textid = $this->add_mobile_field($this->dlx, 'text', 'Title');
    }

    /**
     * Stored criteria as older versions and the filter forms wrote them.
     *
     * @return array
     */
    private function legacy_criteria(): array {
        return [
            $this->teamid => ['AND' => [
                ['', 'USER', '6'],
                ['NOT', 'OTHER_USER', ['5', '0', 5, -999]],
                ['', 'ANY_OF', ['', '0']],
                ['', '', 'x'],
            ]],
            "entryteammemberprofilefield_{$this->teamid}_department" => ['AND' => [
                ['', 'MY_PROFILE_FIELD', '2'],
                ['', 'LITERAL_VALUE', '  Ward 1 '],
                ['', 'LITERAL_VALUE', ''],
                ['', '', ''],
            ]],
            'entryteammemberprofilefield_999999_department' => ['AND' => [['', 'MY_PROFILE_FIELD', '']]],
            "entryteammemberprofilefield_{$this->teamid}_email" => ['AND' => [['', 'LITERAL_VALUE', 'x']]],
            $this->textid => ['AND' => [['', 'LIKE', 'Graz']]],
            999999 => ['AND' => [['', 'LIKE', 'deleted field']]],
            0 => ['AND' => [['', '', false]]],
            'status' => ['AND' => [['', '=', 1]]],
        ];
    }

    /**
     * The criteria in the stored form of legacy_criteria().
     *
     * @return array
     */
    private function stored_criteria(): array {
        return [
            $this->teamid => ['AND' => [
                ['', 'USER', ''],
                ['NOT', 'OTHER_USER', [5]],
                ['', '', ''],
            ]],
            "entryteammemberprofilefield_{$this->teamid}_department" => ['AND' => [
                ['', 'MY_PROFILE_FIELD', ''],
                ['', 'LITERAL_VALUE', 'Ward 1'],
            ]],
            $this->textid => ['AND' => [['', 'LIKE', 'Graz']]],
            'status' => ['AND' => [['', '=', 1]]],
        ];
    }

    /**
     * Criteria come out in the stored form: no user for "I am a team member", user ids as
     * numbers, no criteria on fields that do not exist.
     */
    public function test_normalise_search(): void {
        $types = stored_criteria::field_types($this->dlx->id());

        $this->assertSame($this->stored_criteria(), stored_criteria::normalise_search($this->legacy_criteria(), $types));
        $this->assertSame(
            [$this->textid => 1, 'timecreated' => 0],
            stored_criteria::normalise_sort([$this->textid => 1, 999999 => 0, 'timecreated' => 0], $types)
        );
    }

    /**
     * The upgrade and restore bring stored filters into the stored form, once.
     */
    public function test_normalize_stored_filters(): void {
        global $DB;

        $filterid = $this->add_mobile_filter($this->dlx, 'Legacy', [
            'customsearch' => serialize($this->legacy_criteria()),
            'customsort' => serialize([999999 => 1, $this->textid => 0]),
        ]);

        $this->assertSame(1, stored_criteria::normalize_stored_filters($this->dlx->id()));
        $this->assertSame(0, stored_criteria::normalize_stored_filters($this->dlx->id()), 'idempotent');
        $filter = $DB->get_record('datalynx_filters', ['id' => $filterid]);
        $this->assertSame($this->stored_criteria(), \mod_datalynx\local\serialized::to_array($filter->customsearch));
        $this->assertSame([$this->textid => 0], \mod_datalynx\local\serialized::to_array($filter->customsort));
    }

    /**
     * Saving a filter stores the criteria in the stored form, whatever wrote them.
     */
    public function test_saving_a_filter_stores_the_stored_form(): void {
        global $DB;

        $manager = new datalynx_filter_manager($this->dlx);
        $filter = (object) ['id' => 0, 'dataid' => $this->dlx->id(), 'name' => 'Saved', 'description' => '',
            'visible' => 1, 'perpage' => 0, 'selection' => 0, 'groupby' => '', 'search' => '',
            'customsort' => '', 'customsearch' => serialize($this->legacy_criteria())];

        $saved = $manager->save_filter($filter);

        $this->assertSame(
            $this->stored_criteria(),
            \mod_datalynx\local\serialized::to_array($DB->get_field('datalynx_filters', 'customsearch', ['id' => $saved->id]))
        );
    }

    /**
     * Team member lists become JSON arrays of positive integer user ids.
     */
    public function test_team_member_lists(): void {
        global $DB, $USER;

        $rows = [
            '["5","7"]' => '[5,7]',
            '[5,"7"]' => '[5,7]',
            '{"-1":"-999","0":"5"}' => '[5]',
            '["-999"]' => '[]',
            '[5,7]' => '[5,7]',
        ];
        $ids = [];
        foreach ($rows as $stored => $expected) {
            $entryid = $this->add_mobile_entry($this->dlx, $USER->id, [$this->teamid => $stored]);
            $ids[$entryid] = $expected;
        }

        \datalynxfield_teammemberselect\field::normalize_stored_content($this->dlx->id());

        foreach ($ids as $entryid => $expected) {
            $this->assertSame($expected, $DB->get_field('datalynx_contents', 'content', ['entryid' => $entryid]));
        }
    }

    /**
     * Restoring maps the field ids of the criteria, also in the key of a profile field criterion,
     * keeps the users on the same site, and stores the stored form.
     */
    public function test_restore(): void {
        global $DB, $USER;

        $member = $this->getDataGenerator()->create_user();
        $this->add_mobile_filter($this->dlx, 'Legacy', [
            'customsearch' => serialize([
                $this->teamid => ['AND' => [['', 'USER', '6'], ['', 'ANY_OF', [(string) $member->id]]]],
                "entryteammemberprofilefield_{$this->teamid}_department" => ['AND' => [['', 'MY_PROFILE_FIELD', '2']]],
                999999 => ['AND' => [['', 'LIKE', 'deleted field']]],
            ]),
        ]);

        $bc = new backup_controller(
            backup::TYPE_1ACTIVITY,
            $this->dlx->cm->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id
        );
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();
        $backupid = 'stored_criteria_test';
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), make_backup_temp_directory($backupid));
        $target = $this->getDataGenerator()->create_course();
        $rc = new restore_controller(
            $backupid,
            $target->id,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_EXISTING_ADDING
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $newdataid = $DB->get_field('datalynx', 'id', ['course' => $target->id], MUST_EXIST);
        $newteamid = $DB->get_field('datalynx_fields', 'id', ['dataid' => $newdataid, 'type' => 'teammemberselect'], MUST_EXIST);
        $this->assertSame(
            [
                $newteamid => ['AND' => [['', 'USER', ''], ['', 'ANY_OF', [(int) $member->id]]]],
                "entryteammemberprofilefield_{$newteamid}_department" => ['AND' => [['', 'MY_PROFILE_FIELD', '']]],
            ],
            \mod_datalynx\local\serialized::to_array($DB->get_field('datalynx_filters', 'customsearch', ['dataid' => $newdataid]))
        );
    }

    /**
     * A team member select field whose profile fields a filter searches cannot be deleted.
     */
    public function test_profile_criterion_blocks_deleting_the_team_field(): void {
        $this->add_mobile_filter($this->dlx, 'Department', [
            'customsearch' => serialize([
                "entryteammemberprofilefield_{$this->teamid}_department" => ['AND' => [['', 'MY_PROFILE_FIELD', '']]],
            ]),
        ]);
        $dlx = new datalynx($this->dlx->id());

        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('deleteblocked');
        $dlx->process_fields('delete', (string) $this->teamid, true);
    }
}
