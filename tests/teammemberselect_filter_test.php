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
 * Tests for mod_datalynx teammemberselect field filtering.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\filter\datalynx_filter;

/**
 * Tests for teammemberselect field filter.
 *
 * @covers \datalynxfield_teammemberselect\field::get_search_sql
 */
final class teammemberselect_filter_test extends advanced_testcase {
    /**
     * Set up the fixture.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test filtering by "I am team member" (USER) operator.
     */
    public function test_teammemberselect_filter_user(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        // Create some users.
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();

        // Add a teammemberselect field.
        $fieldrecord = (object) [
            'dataid' => $dlx->id(),
            'type' => 'teammemberselect',
            'name' => 'TeamSelectField',
            'description' => '',
            'param1' => '20',
            'param2' => json_encode([0, 1, 2, 3]),
            'param3' => '0',
            'param4' => '4',
            'param5' => '0',
            'param6' => '0',
            'param7' => '0',
            'param8' => '0',
            'param9' => '',
            'param10' => '',
        ];
        $fieldid = (int) $DB->insert_record('datalynx_fields', $fieldrecord);

        // Entry A: stored as integer array [user1->id].
        $entryaid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(), 'userid' => $user1->id, 'approved' => 1, 'status' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid, 'entryid' => $entryaid, 'content' => json_encode([(int) $user1->id]),
        ]);

        // Entry B: stored as integer array [user2->id].
        $entrybid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(), 'userid' => $user2->id, 'approved' => 1, 'status' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid, 'entryid' => $entrybid, 'content' => json_encode([(int) $user2->id]),
        ]);

        // Entry C: stored as integer array [user1->id, user2->id].
        $entrycid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(), 'userid' => $user1->id, 'approved' => 1, 'status' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid, 'entryid' => $entrycid, 'content' => json_encode([(int) $user1->id, (int) $user2->id]),
        ]);

        // Entry D: stored as string array ["user1->id"].
        $entrydid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(), 'userid' => $user1->id, 'approved' => 1, 'status' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid, 'entryid' => $entrydid, 'content' => json_encode([(string) $user1->id]),
        ]);

        // Filter: teammemberselect has "I am team member" (USER) operator.
        $customsearch = [$fieldid => ['AND' => [['', 'USER', '']]]];
        $filterrecord = (object) [
            'dataid' => $dlx->id(),
            'name' => 'MyTeamEntries',
            'description' => '',
            'customsort' => '',
            'customsearch' => serialize($customsearch),
            'search' => '',
            'groupby' => '',
            'perpage' => 50,
            'selection' => 0,
            'page' => 0,
            'eids' => '',
        ];
        $filterid = (int) $DB->insert_record('datalynx_filters', $filterrecord);
        $filter = new datalynx_filter($DB->get_record('datalynx_filters', ['id' => $filterid]));
        $filter->contentfields = [$fieldid];

        // 1. Set USER to user1.
        $this->setUser($user1);
        $entriesclass = new datalynx_entries($dlx, $filter);
        $result = $entriesclass->get_entries();

        // User 1 is a team member in Entry A (integer), Entry C (integer array), and Entry D (string).
        // Entry B (integer with only user2) should NOT be returned.
        $this->assertCount(3, $result->entries);
        $this->assertArrayHasKey($entryaid, $result->entries);
        $this->assertArrayHasKey($entrycid, $result->entries);
        $this->assertArrayHasKey($entrydid, $result->entries);
        $this->assertArrayNotHasKey($entrybid, $result->entries);

        // 2. Set USER to user2.
        $this->setUser($user2);
        $entriesclass = new datalynx_entries($dlx, $filter);
        $result = $entriesclass->get_entries();

        // User 2 is a team member in Entry B (integer) and Entry C (integer array).
        // Entry A and D (user 1 only) should NOT be returned.
        $this->assertCount(2, $result->entries);
        $this->assertArrayHasKey($entrybid, $result->entries);
        $this->assertArrayHasKey($entrycid, $result->entries);
        $this->assertArrayNotHasKey($entryaid, $result->entries);
        $this->assertArrayNotHasKey($entrydid, $result->entries);
    }

    /**
     * Test simple search matching for teammemberselect field.
     */
    public function test_teammemberselect_simple_search(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        // Create and enrol users.
        $user1 = $generator->create_user(['firstname' => 'Alice', 'lastname' => 'Smith']);
        $user2 = $generator->create_user(['firstname' => 'Bob', 'lastname' => 'Jones']);

        $generator->enrol_user($user1->id, $course->id, 'student');
        $generator->enrol_user($user2->id, $course->id, 'student');

        // Add a teammemberselect field.
        $fieldrecord = (object) [
            'dataid' => $dlx->id(),
            'type' => 'teammemberselect',
            'name' => 'TeamSelectField',
            'description' => '',
            'param1' => '20',
            'param2' => json_encode([3]), // Student role.
            'param3' => '0',
            'param4' => '4',
            'param5' => '0',
            'param6' => '0',
            'param7' => '0',
            'param8' => '0',
            'param9' => '',
            'param10' => '',
        ];
        $fieldid = (int) $DB->insert_record('datalynx_fields', $fieldrecord);

        // Entry A: Alice (integer).
        $entryaid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(), 'userid' => $user1->id, 'approved' => 1, 'status' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid, 'entryid' => $entryaid, 'content' => json_encode([(int) $user1->id]),
        ]);

        // Entry B: Bob (integer).
        $entrybid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(), 'userid' => $user2->id, 'approved' => 1, 'status' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid, 'entryid' => $entrybid, 'content' => json_encode([(int) $user2->id]),
        ]);

        // Filter: simple search for "Alice".
        $filterrecord = (object) [
            'dataid' => $dlx->id(),
            'name' => 'SimpleSearchAlice',
            'description' => '',
            'customsort' => '',
            'customsearch' => '',
            'search' => 'Alice',
            'groupby' => '',
            'perpage' => 50,
            'selection' => 0,
            'page' => 0,
            'eids' => '',
        ];
        $filterid = (int) $DB->insert_record('datalynx_filters', $filterrecord);
        $filter = new datalynx_filter($DB->get_record('datalynx_filters', ['id' => $filterid]));
        $filter->contentfields = [$fieldid];

        $entriesclass = new datalynx_entries($dlx, $filter);
        $result = $entriesclass->get_entries();

        // Simple search for "Alice" should only return Entry A (Alice).
        $this->assertCount(1, $result->entries);
        $this->assertArrayHasKey($entryaid, $result->entries);
        $this->assertArrayNotHasKey($entrybid, $result->entries);
    }

    /**
     * Test that normalize_stored_content() canonicalises legacy/mixed member lists to integer JSON.
     *
     * @covers \datalynxfield_teammemberselect\field::normalize_stored_content
     */
    public function test_normalize_stored_content(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);
        $other = $generator->create_module('datalynx', ['course' => $course->id]);
        $otherdlx = new datalynx($other->id);

        $makefield = static function (datalynx $d) use ($DB): int {
            return (int) $DB->insert_record('datalynx_fields', (object) [
                'dataid' => $d->id(), 'type' => 'teammemberselect', 'name' => 'Team', 'description' => '',
                'param1' => '20', 'param2' => json_encode([0, 1, 2, 3]), 'param3' => '0', 'param4' => '4',
                'param5' => '0', 'param6' => '0', 'param7' => '0', 'param8' => '0', 'param9' => '', 'param10' => '',
            ]);
        };
        $makeentry = static function (datalynx $d, int $fieldid, string $content) use ($DB): int {
            $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
                'dataid' => $d->id(), 'userid' => 2, 'approved' => 1, 'status' => 0,
                'timecreated' => time(), 'timemodified' => time(),
            ]);
            $DB->insert_record('datalynx_contents', (object) [
                'fieldid' => $fieldid, 'entryid' => $entryid, 'content' => $content,
            ]);
            return (int) $DB->get_field(
                'datalynx_contents',
                'id',
                ['fieldid' => $fieldid, 'entryid' => $entryid]
            );
        };

        $fieldid = $makefield($dlx);
        $cstr = $makeentry($dlx, $fieldid, json_encode(['5', '12'])); // Legacy string form.
        $cint = $makeentry($dlx, $fieldid, json_encode([7, 9]));       // Already canonical.
        $cdummy = $makeentry($dlx, $fieldid, json_encode([-999, '3'])); // Sentinel + string.
        $cempty = $makeentry($dlx, $fieldid, json_encode([]));          // Empty stays empty.

        // A row in another instance, to prove scoping.
        $otherfieldid = $makefield($otherdlx);
        $cother = $makeentry($otherdlx, $otherfieldid, json_encode(['8']));

        // Scoped to this instance only.
        $updated = \datalynxfield_teammemberselect\field::normalize_stored_content($dlx->id());

        $this->assertSame('[5,12]', $DB->get_field('datalynx_contents', 'content', ['id' => $cstr]));
        $this->assertSame('[7,9]', $DB->get_field('datalynx_contents', 'content', ['id' => $cint]));
        $this->assertSame('[3]', $DB->get_field('datalynx_contents', 'content', ['id' => $cdummy]));
        $this->assertSame('[]', $DB->get_field('datalynx_contents', 'content', ['id' => $cempty]));
        // The already-canonical and empty rows must not be counted as updated.
        $this->assertSame(2, $updated);
        // The other instance is untouched while scoped.
        $this->assertSame('["8"]', $DB->get_field('datalynx_contents', 'content', ['id' => $cother]));

        // Global run normalises the remaining instance and is idempotent on the first.
        $this->assertSame(1, \datalynxfield_teammemberselect\field::normalize_stored_content());
        $this->assertSame('[8]', $DB->get_field('datalynx_contents', 'content', ['id' => $cother]));
        $this->assertSame(0, \datalynxfield_teammemberselect\field::normalize_stored_content());
    }
}
