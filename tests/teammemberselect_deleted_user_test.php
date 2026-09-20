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

namespace mod_datalynx;

use advanced_testcase;
use datalynxfield_teammemberselect\field;

/**
 * Deleted users are removed from team member select fields (see issue #172).
 *
 * @package mod_datalynx
 * @copyright 2026 onwards The datalynx contributors
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class teammemberselect_deleted_user_test extends advanced_testcase {
    /** @var int datalynx instance id */
    private int $dataid;

    /** @var int team member select field id */
    private int $fieldid;

    public function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $this->dataid = (int) $instance->id;
        $this->fieldid = (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $this->dataid,
            'type' => 'teammemberselect',
            'name' => 'Team',
            'description' => '',
        ]);
    }

    /**
     * Create an entry with the given stored member list.
     *
     * @param array $members
     * @return int content id
     */
    private function create_team(array $members): int {
        global $DB, $USER;
        $now = time();
        $entryid = $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $this->dataid,
            'userid' => $USER->id,
            'groupid' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
            'approved' => 1,
            'status' => 0,
        ]);
        return (int) $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $this->fieldid,
            'entryid' => $entryid,
            'content' => json_encode($members),
        ]);
    }

    /**
     * Read the stored member list of a content row.
     *
     * @param int $contentid
     * @return array
     */
    private function get_team(int $contentid): array {
        global $DB;
        return json_decode($DB->get_field('datalynx_contents', 'content', ['id' => $contentid]), true);
    }

    /**
     * Deleting a user removes them from every team, in integer and legacy string storage, and
     * leaves the other members and unrelated teams alone.
     *
     * @covers \mod_datalynx\observer::user_deleted
     * @covers \datalynxfield_teammemberselect\field::remove_users_from_teams
     */
    public function test_deleting_a_user_removes_them_from_teams(): void {
        $gone = $this->getDataGenerator()->create_user();
        $stays = $this->getDataGenerator()->create_user();

        $intteam = $this->create_team([(int) $gone->id, (int) $stays->id]);
        $stringteam = $this->create_team([(string) $stays->id, (string) $gone->id]);
        $onlygone = $this->create_team([(int) $gone->id]);
        // An id that merely contains the deleted user's id must survive.
        $lookalike = (int) ($gone->id . '0' . $gone->id);
        $lookaliketeam = $this->create_team([$lookalike]);

        delete_user($gone);

        $this->assertSame([(int) $stays->id], $this->get_team($intteam));
        $this->assertSame([(int) $stays->id], $this->get_team($stringteam));
        $this->assertSame([], $this->get_team($onlygone));
        $this->assertSame([$lookalike], $this->get_team($lookaliketeam));
    }

    /**
     * The upgrade clean-up removes users deleted before the observer existed, and members that
     * have no user record at all.
     *
     * @covers \datalynxfield_teammemberselect\field::remove_users_from_teams
     */
    public function test_cleanup_removes_already_deleted_users(): void {
        global $DB;

        $gone = $this->getDataGenerator()->create_user();
        $stays = $this->getDataGenerator()->create_user();
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {user}') + 1000;
        $team = $this->create_team([(int) $gone->id, (int) $stays->id, $missing]);
        $untouched = $this->create_team([(int) $stays->id]);

        // Simulate a deletion that happened while no observer was listening.
        $DB->set_field('user', 'deleted', 1, ['id' => $gone->id]);

        $this->assertSame(1, field::remove_users_from_teams(null));

        $this->assertSame([(int) $stays->id], $this->get_team($team));
        $this->assertSame([(int) $stays->id], $this->get_team($untouched));
    }

    /**
     * The instance restriction is honoured.
     *
     * @covers \datalynxfield_teammemberselect\field::remove_users_from_teams
     */
    public function test_removal_can_be_restricted_to_one_instance(): void {
        $user = $this->getDataGenerator()->create_user();
        $team = $this->create_team([(int) $user->id]);

        $this->assertSame(0, field::remove_users_from_teams([$user->id], $this->dataid + 1));
        $this->assertSame([(int) $user->id], $this->get_team($team));

        $this->assertSame(1, field::remove_users_from_teams([$user->id], $this->dataid));
        $this->assertSame([], $this->get_team($team));
    }
}
