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
use datalynxfield_teammemberselect\membership;
use mod_datalynx\external\team_subscription;

/**
 * Team member select: joining by request, approved or declined by the entry author (mod_datalynx#341).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxfield_teammemberselect\membership
 * @covers     \mod_datalynx\external\team_subscription
 * @covers     \datalynxfield_teammemberselect\field
 */
final class teammemberselect_requests_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var datalynx */
    protected datalynx $dlx;

    /** @var \stdClass */
    protected \stdClass $course;

    /** @var int */
    protected int $seatsid;

    /** @var int */
    protected int $teamid;

    /** @var \stdClass The driver. */
    protected \stdClass $driver;

    /** @var \stdClass[] Passengers. */
    protected array $passengers = [];

    /** @var int A ride with two seats. */
    protected int $ride;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        [$this->course, $this->dlx] = $this->create_mobile_instance();
        $this->seatsid = $this->add_mobile_field($this->dlx, 'number', 'Plätze');
        $this->teamid = $this->add_mobile_field($this->dlx, 'teammemberselect', 'Passagiere', [
            'param1' => 8,
            'param2' => json_encode([datalynx::PERMISSION_STUDENT]),
            'param8' => 1,
            'param10' => json_encode(['limitfield' => $this->seatsid, 'approval' => 1]),
        ]);
        $this->driver = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        for ($i = 0; $i < 3; $i++) {
            $this->passengers[] = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        }
        $this->dlx = new datalynx($this->dlx->id());
        $this->ride = $this->add_mobile_entry($this->dlx, $this->driver->id, [$this->seatsid => '2']);
    }

    /**
     * Run an action through the web service as a user.
     *
     * @param \stdClass $actor
     * @param string $action
     * @param \stdClass|null $subject the user acted on; the actor when null
     * @return array
     */
    protected function act(\stdClass $actor, string $action, ?\stdClass $subject = null): array {
        $this->setUser($actor);
        return team_subscription::execute($this->dlx->id(), $this->ride, $this->teamid, ($subject ?? $actor)->id, $action);
    }

    /**
     * The team of the ride.
     *
     * @return membership
     */
    protected function team(): membership {
        return (new datalynx($this->dlx->id()))->get_field_from_id($this->teamid, true)->membership($this->ride);
    }

    /**
     * Request, approve: a member. Request, decline: no second request. Withdraw.
     */
    public function test_request_approve_decline_withdraw(): void {
        [$anna, $ben, $cleo] = $this->passengers;

        $result = $this->act($anna, 'request');
        $this->assertTrue($result['success']);
        $this->assertSame(membership::REQUESTED, $result['status']);
        $this->assertSame([], $this->team()->members(), 'a request does not take a seat');

        $this->assertTrue($this->act($this->driver, 'approve', $anna)['success']);
        $this->assertSame([(int) $anna->id], $this->team()->members());
        $this->assertSame(membership::MEMBER, $this->team()->status((int) $anna->id));

        $this->act($ben, 'request');
        $this->assertTrue($this->act($this->driver, 'decline', $ben)['success']);
        $this->assertSame(membership::DECLINED, $this->team()->status((int) $ben->id));
        $again = $this->act($ben, 'request');
        $this->assertFalse($again['success']);
        $this->assertSame(get_string('requestdeclined', 'datalynxfield_teammemberselect'), $again['error']);

        $this->act($cleo, 'request');
        $this->assertTrue($this->act($cleo, 'withdraw')['success']);
        $this->assertSame(membership::NONE, $this->team()->status((int) $cleo->id));

        // A member leaves.
        $this->assertTrue($this->act($anna, 'withdraw')['success']);
        $this->assertSame([], $this->team()->members());
    }

    /**
     * Approving when the seats are taken is refused; requests are not taken on a full ride.
     */
    public function test_approve_when_full(): void {
        global $DB;
        [$anna, $ben, $cleo] = $this->passengers;
        $DB->set_field('datalynx_contents', 'content', '1', ['entryid' => $this->ride, 'fieldid' => $this->seatsid]);
        $this->act($anna, 'request');
        $this->act($ben, 'request');
        $this->assertTrue($this->act($this->driver, 'approve', $anna)['success']);

        $full = $this->act($this->driver, 'approve', $ben);
        $this->assertFalse($full['success']);
        $this->assertSame(get_string('teamfull', 'datalynxfield_teammemberselect'), $full['error']);
        $this->assertFalse($this->act($cleo, 'request')['success'], 'no request on a full ride');
    }

    /**
     * Only the author and who manages entries decide; nobody acts for someone else.
     */
    public function test_who_decides(): void {
        [$anna, $ben] = $this->passengers;
        $this->act($anna, 'request');

        try {
            $this->act($anna, 'approve', $anna);
            $this->fail('a user approved their own request');
        } catch (\moodle_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
        try {
            $this->act($ben, 'decline', $anna);
            $this->fail('another passenger declined a request');
        } catch (\moodle_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
        try {
            $this->setUser($ben);
            team_subscription::execute($this->dlx->id(), $this->ride, $this->teamid, $anna->id, 'withdraw');
            $this->fail('a user withdrew the request of someone else');
        } catch (\moodle_exception $e) {
            $this->assertSame('invaliduserid', $e->errorcode);
        }

        $manager = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->assertTrue($this->act($manager, 'approve', $anna)['success'], 'a manager of entries may decide');
        $this->assertTrue($this->act($this->driver, 'remove', $anna)['success'], 'the author may remove a member');
        $this->assertSame([], $this->team()->members());
    }

    /**
     * Events carry the person concerned; a notification rule reaches the author and the requester.
     */
    public function test_events_and_notifications(): void {
        global $DB;
        [$anna] = $this->passengers;
        foreach ([['team_request_created', ['author' => 1]], ['team_request_approved', ['relateduser' => 1]]] as [$event, $to]) {
            $DB->insert_record('datalynx_rules', (object) [
                'dataid' => $this->dlx->id(), 'type' => 'eventnotification', 'name' => $event, 'description' => '', 'enabled' => 1,
                'param1' => json_encode(["$event:{$this->teamid}"]), 'param2' => 1, 'param3' => json_encode($to),
                'param4' => '[]', 'param6' => "Subject $event", 'param7' => '[]',
            ]);
        }
        local\definition_cache::invalidate($this->dlx->id());

        $events = $this->redirectEvents();
        $this->act($anna, 'request');
        $this->act($this->driver, 'approve', $anna);
        $fired = array_map(fn($e) => [get_class($e), (int) $e->relateduserid], $events->get_events());
        $events->close();
        $this->assertContains([event\team_request_created::class, (int) $anna->id], $fired);
        $this->assertContains([event\team_request_approved::class, (int) $anna->id], $fired);

        // Again with the messages: the observers only run while events are not redirected.
        $this->act($anna, 'withdraw');
        $DB->set_field('datalynx_contents', 'content1', null, ['entryid' => $this->ride, 'fieldid' => $this->teamid]);
        $sink = $this->redirectMessages();
        $this->act($anna, 'request');
        $this->act($this->driver, 'approve', $anna);
        // The rule sends through an ad-hoc task.
        ob_start();
        $this->run_all_adhoc_tasks();
        ob_end_clean();
        $messages = array_map(fn($m) => [$m->subject, (int) $m->useridto], $sink->get_messages());
        $sink->close();
        $this->assertContains(['Subject team_request_created', (int) $this->driver->id], $messages);
        $this->assertContains(['Subject team_request_approved', (int) $anna->id], $messages);
    }

    /**
     * Search "I have requested".
     */
    public function test_requested_search(): void {
        global $DB;
        [$anna, $ben] = $this->passengers;
        $other = $this->add_mobile_entry($this->dlx, $this->driver->id, [$this->seatsid => '2']);
        $this->act($anna, 'request');
        $this->setUser($anna);
        $field = (new datalynx($this->dlx->id()))->get_field_from_id($this->teamid, true);
        [$sql, $params] = $field->get_search_sql(['', 'REQUESTED', '']);
        $found = $DB->get_fieldset_sql(
            "SELECT e.id FROM {datalynx_entries} e WHERE e.dataid = :dataid AND $sql",
            ['dataid' => $this->dlx->id()] + $params
        );
        $this->assertSame([$this->ride], array_map('intval', $found));
        $this->setUser($ben);
        [$sql, $params] = $field->get_search_sql(['', 'REQUESTED', '']);
        $this->assertSame([], $DB->get_fieldset_sql("SELECT e.id FROM {datalynx_entries} e WHERE $sql", $params));
        $this->assertNotEmpty($other);
    }

    /**
     * Without the approval setting, a request joins directly, as self-subscription always did.
     */
    public function test_without_approval(): void {
        global $DB;
        $DB->set_field('datalynx_fields', 'param10', json_encode(['limitfield' => $this->seatsid]), ['id' => $this->teamid]);
        local\definition_cache::invalidate($this->dlx->id());
        [$anna] = $this->passengers;
        $result = $this->act($anna, 'subscribe');
        $this->assertSame(membership::MEMBER, $result['status']);
        $this->assertSame([(int) $anna->id], $this->team()->members());
    }

    /**
     * A request is personal data of the requester: found, and removed on deletion.
     */
    public function test_privacy(): void {
        [$anna] = $this->passengers;
        $this->act($anna, 'request');
        $contexts = privacy\provider::get_contexts_for_userid($anna->id)->get_contextids();
        $this->assertContains((string) $this->dlx->context->id, array_map('strval', $contexts));

        $this->setAdminUser();
        $list = new \core_privacy\local\request\approved_contextlist($anna, 'mod_datalynx', [$this->dlx->context->id]);
        privacy\provider::delete_data_for_user($list);
        $this->assertSame([], $this->team()->requests());
        $this->assertSame(membership::NONE, $this->team()->status((int) $anna->id));
    }

    /**
     * The display shows the request button to a passenger and the open requests to the driver.
     */
    public function test_display(): void {
        global $DB;
        [$anna] = $this->passengers;
        local\field_format\manager::save_format((object) ['dataid' => $this->dlx->id(), 'name' => 'mitfahren',
            'fieldtype' => 'teammemberselect', 'settings' => json_encode(['subscribe' => 1, 'label' => 'Mitfahrt beantragen'])]);
        $render = function (\stdClass $user): string {
            $this->setUser($user);
            $dlx = new datalynx($this->dlx->id());
            $field = $dlx->get_field_from_id($this->teamid, true);
            $format = local\field_format\manager::get_format_by_name($dlx->id(), 'mitfahren');
            return $field->renderer()->render_display_mode((object) ['id' => $this->ride], ['field_format' => $format]);
        };

        $this->assertStringContainsString('Mitfahrt beantragen', $render($anna));
        $this->act($anna, 'request');
        $this->assertStringContainsString(get_string('withdrawrequest', 'datalynxfield_teammemberselect'), $render($anna));
        $driverview = $render($this->driver);
        $this->assertStringContainsString(fullname($anna), $driverview);
        $this->assertStringContainsString('data-dlx-team-action="approve"', $driverview);
    }
}
