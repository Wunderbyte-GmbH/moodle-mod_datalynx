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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use advanced_testcase;
use mod_datalynx\external\team_subscription;

/**
 * Team member select: the team size of an entry taken from a field of the entry (mod_datalynx#326).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxfield_teammemberselect\field
 * @covers     \mod_datalynx\external\team_subscription
 */
final class teammemberselect_limit_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var datalynx */
    protected datalynx $dlx;

    /** @var \stdClass */
    protected \stdClass $course;

    /** @var int Field id of the seats (number). */
    protected int $seatsid;

    /** @var int Field id of the passengers (team member select). */
    protected int $teamid;

    /** @var \stdClass[] Students. */
    protected array $students = [];

    /**
     * A board with seats and passengers, and four students.
     *
     * @param array $teamparams params of the team field
     */
    protected function make_board(array $teamparams = []): void {
        $this->resetAfterTest();
        [$this->course, $this->dlx] = $this->create_mobile_instance();
        $this->seatsid = $this->add_mobile_field($this->dlx, 'number', 'Plätze');
        $this->teamid = $this->add_mobile_field($this->dlx, 'teammemberselect', 'Passagiere', $teamparams + [
            'param1' => 5,
            'param2' => json_encode([datalynx::PERMISSION_STUDENT]),
            'param8' => 1,
            'param10' => json_encode(['limitfield' => $this->seatsid]),
        ]);
        for ($i = 0; $i < 4; $i++) {
            $this->students[] = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        }
        $this->dlx = new datalynx($this->dlx->id());
    }

    /**
     * Subscribe a user to an entry through the web service.
     *
     * @param \stdClass $user
     * @param int $entryid
     * @param string $action
     * @return array
     */
    protected function subscribe(\stdClass $user, int $entryid, string $action = 'subscribe'): array {
        $this->setUser($user);
        return team_subscription::execute($this->dlx->id(), $entryid, $this->teamid, $user->id, $action);
    }

    /**
     * The team field.
     *
     * @return \datalynxfield_teammemberselect\field
     */
    protected function team(): \datalynxfield_teammemberselect\field {
        return (new datalynx($this->dlx->id()))->get_field_from_id($this->teamid, true);
    }

    /**
     * Two seats take two passengers; the third is refused. No seats take nobody.
     */
    public function test_subscription_respects_the_seats(): void {
        $this->make_board();
        $two = $this->add_mobile_entry($this->dlx, $this->students[0]->id, [$this->seatsid => '2']);
        $none = $this->add_mobile_entry($this->dlx, $this->students[0]->id, [$this->seatsid => '0']);
        $empty = $this->add_mobile_entry($this->dlx, $this->students[0]->id, []);

        $this->assertTrue($this->subscribe($this->students[1], $two)['success']);
        $this->assertTrue($this->subscribe($this->students[2], $two)['success']);
        $third = $this->subscribe($this->students[3], $two);
        $this->assertFalse($third['success']);
        $this->assertSame(get_string('teamfull', 'datalynxfield_teammemberselect'), $third['error']);
        $this->assertSame([(int) $this->students[1]->id, (int) $this->students[2]->id], $this->team()->get_members($two));

        $this->assertFalse($this->subscribe($this->students[1], $none)['success']);
        $this->assertFalse($this->subscribe($this->students[1], $empty)['success'], 'an empty limit counts as 0');

        // Leaving frees the place.
        $this->assertTrue($this->subscribe($this->students[1], $two, 'unsubscribe')['success']);
        $this->assertTrue($this->subscribe($this->students[3], $two)['success']);
    }

    /**
     * Without a limit field, the team size of the field applies to every entry, as before.
     */
    public function test_without_limit_field(): void {
        $this->make_board(['param1' => 2, 'param10' => '']);
        $entryid = $this->add_mobile_entry($this->dlx, $this->students[0]->id, [$this->seatsid => '0']);
        $this->assertSame(2, $this->team()->get_member_limit($entryid));
        $this->assertTrue($this->subscribe($this->students[1], $entryid)['success']);
        $this->assertTrue($this->subscribe($this->students[2], $entryid)['success']);
        $this->assertFalse($this->subscribe($this->students[3], $entryid)['success']);
    }

    /**
     * A select field with numbers as options works as a limit field too; the team size stays the upper limit.
     */
    public function test_select_limit_field(): void {
        $this->make_board(['param1' => 3]);
        $selectid = $this->add_mobile_field($this->dlx, 'select', 'Freie Plätze', ['param1' => implode("\n", range(0, 8))]);
        global $DB;
        $DB->set_field('datalynx_fields', 'param10', json_encode(['limitfield' => $selectid]), ['id' => $this->teamid]);
        local\definition_cache::invalidate($this->dlx->id());
        // Option 2 is "1"; option 9 is "8", above the team size of 3.
        $one = $this->add_mobile_entry($this->dlx, $this->students[0]->id, [$selectid => '2']);
        $eight = $this->add_mobile_entry($this->dlx, $this->students[0]->id, [$selectid => '9']);
        $this->assertSame(1, $this->team()->get_member_limit($one));
        $this->assertSame(3, $this->team()->get_member_limit($eight));
    }

    /**
     * The web service checks the entry, the role and whether leaving is allowed.
     */
    public function test_service_checks(): void {
        $this->make_board(['param8' => 0]);
        $entryid = $this->add_mobile_entry($this->dlx, $this->students[0]->id, [$this->seatsid => '2']);
        $this->assertTrue($this->subscribe($this->students[1], $entryid)['success']);
        $this->assertFalse($this->subscribe($this->students[1], $entryid, 'unsubscribe')['success'], 'leaving is not allowed');

        // A teacher is not an admissible role of this field.
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->assertFalse($this->subscribe($teacher, $entryid)['success']);

        // An entry of another instance.
        [, $other] = $this->create_mobile_instance(['course' => $this->course->id]);
        $foreign = $this->add_mobile_entry($other, $this->students[0]->id, []);
        $this->expectException(\moodle_exception::class);
        $this->subscribe($this->students[2], $foreign);
    }

    /**
     * The edit form refuses more members than the seats, and seats below the members.
     */
    public function test_form_validation(): void {
        $this->make_board();
        $entryid = $this->add_mobile_entry($this->dlx, $this->students[0]->id, [
            $this->seatsid => '2',
            $this->teamid => json_encode([(int) $this->students[1]->id, (int) $this->students[2]->id]),
        ]);
        $team = $this->team();
        $seats = $this->dlx->get_field_from_id($this->seatsid, true);
        $three = array_map(fn($user) => (int) $user->id, array_slice($this->students, 1, 3));

        // Three members on two seats; the seats submitted in the same form count.
        $errors = $team->renderer()->validate($entryid, [], (object) ["field_{$this->teamid}_{$entryid}" => $three]);
        $this->assertArrayHasKey("field_{$this->teamid}_{$entryid}", $errors);
        $errors = $team->renderer()->validate($entryid, [], (object) [
            "field_{$this->teamid}_{$entryid}" => $three,
            "field_{$this->seatsid}_{$entryid}" => '3',
        ]);
        $this->assertSame([], $errors);

        // Lowering the seats below the two members.
        $errors = $seats->renderer()->validate($entryid, [], (object) ["field_{$this->seatsid}_{$entryid}" => '1']);
        $this->assertSame([
            "field_{$this->seatsid}_{$entryid}" => get_string('limitbelowmembers', 'datalynxfield_teammemberselect', 2),
        ], $errors);
        $this->assertSame([], $seats->renderer()->validate($entryid, [], (object) ["field_{$this->seatsid}_{$entryid}" => '2']));
    }

    /**
     * "Has free places" finds the entries with fewer members than places.
     */
    public function test_free_places_search(): void {
        global $DB;
        $this->make_board();
        $member = json_encode([(int) $this->students[1]->id]);
        $free = $this->add_mobile_entry($this->dlx, $this->students[0]->id, [$this->seatsid => '2', $this->teamid => $member]);
        $full = $this->add_mobile_entry($this->dlx, $this->students[0]->id, [$this->seatsid => '1', $this->teamid => $member]);
        $none = $this->add_mobile_entry($this->dlx, $this->students[0]->id, [$this->seatsid => '0']);
        $nobody = $this->add_mobile_entry($this->dlx, $this->students[0]->id, [$this->seatsid => '3']);

        $this->assertEqualsCanonicalizing([$free, $nobody], $this->team()->get_entryids_with_free_places());

        [$sql, $params] = $this->team()->get_search_sql(['', 'FREEPLACES', '']);
        $found = $DB->get_fieldset_sql(
            "SELECT e.id FROM {datalynx_entries} e WHERE e.dataid = :dataid AND $sql",
            ['dataid' => $this->dlx->id()] + $params
        );
        $this->assertEqualsCanonicalizing([$free, $nobody], $found);
        [$sql, $params] = $this->team()->get_search_sql(['NOT', 'FREEPLACES', '']);
        $found = $DB->get_fieldset_sql(
            "SELECT e.id FROM {datalynx_entries} e WHERE e.dataid = :dataid AND $sql",
            ['dataid' => $this->dlx->id()] + $params
        );
        $this->assertEqualsCanonicalizing([$full, $none], $found);
    }

    /**
     * A copy of the activity keeps the limit field, with its new id.
     */
    public function test_backup_restore(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        $this->make_board();
        $this->setAdminUser();
        $cm = get_coursemodule_from_instance('datalynx', $this->dlx->id(), 0, false, MUST_EXIST);
        $copy = duplicate_module($this->course, $cm);

        $team = $DB->get_record('datalynx_fields', ['dataid' => $copy->instance, 'name' => 'Passagiere']);
        $seats = $DB->get_field('datalynx_fields', 'id', ['dataid' => $copy->instance, 'name' => 'Plätze']);
        $this->assertSame((int) $seats, json_decode($team->param10, true)['limitfield']);
    }
}
