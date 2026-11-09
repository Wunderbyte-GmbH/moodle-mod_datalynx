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

/**
 * Tests for editing rights of team members.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;

/**
 * A view only loads the contents of the fields it displays. Team members must still be allowed to
 * edit an entry in a view that does not show the team field.
 *
 * @covers \mod_datalynx\datalynx::teammember_can_edit
 */
final class teammember_can_edit_test extends advanced_testcase {
    /**
     * The team is read from the database when the entry object does not carry it.
     */
    public function test_team_member_can_edit_without_loaded_team_content(): void {
        global $DB;
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $author = $gen->create_user();
        $member = $gen->create_user();
        $other = $gen->create_user();
        $instance = $gen->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $teamfieldid = (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $dlx->id(), 'type' => 'teammemberselect', 'name' => 'team', 'description' => '',
            'param1' => '5', 'param2' => '[1,2]', 'param9' => '1',
        ]);
        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(), 'userid' => $author->id, 'groupid' => 0, 'approved' => 1,
            'status' => 2, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'entryid' => $entryid, 'fieldid' => $teamfieldid, 'content' => json_encode([(int) $member->id]), 'lineid' => 0,
        ]);
        $dlx = new datalynx($instance->id);
        $entry = (object) ['id' => $entryid, 'userid' => $author->id, 'status' => 2];

        $this->setUser($member);
        $this->assertTrue($dlx->teammember_can_edit($entry));

        $this->setUser($other);
        $this->assertFalse($dlx->teammember_can_edit($entry));

        // Content that was loaded by the view still takes precedence.
        $this->setUser($member);
        $entry->{"c{$teamfieldid}_content"} = json_encode([(int) $other->id]);
        $this->assertFalse($dlx->teammember_can_edit($entry));
    }
}
