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
 * Tests for turning ride matching rules into event notification rules.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use datalynxrule_eventnotification\rule;
use mod_datalynx\local\rule\match_compiler;

/**
 * The ride matching rule type is gone, so any rule of that type has to become a working pair of
 * event notification rules - and a leftover row of the old type would make the rule index throw.
 *
 * @group mod_datalynx
 * @covers ::mod_datalynx_migrate_ridematch_rules
 */
final class rule_ridematch_migration_test extends advanced_testcase {
    /** @var datalynx */
    private datalynx $dlx;

    /**
     * Set up an instance with the fields a ride matching rule referred to.
     */
    public function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        require_once($CFG->dirroot . '/mod/datalynx/db/upgrade.php');

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $this->dlx = new datalynx($instance->id);
    }

    /**
     * Insert a field and return its id.
     *
     * @param string $type
     * @param string $name
     * @param string $param1
     * @return int
     */
    private function make_field(string $type, string $name, string $param1 = ''): int {
        global $DB;
        return (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $this->dlx->id(), 'type' => $type, 'name' => $name, 'description' => '',
            'param1' => $param1, 'param2' => '', 'param3' => '', 'param4' => '', 'param5' => '',
            'param6' => '', 'param7' => '', 'param8' => '', 'param9' => '', 'param10' => '',
        ]);
    }

    /**
     * Insert an old ride matching rule configured the way its form wrote it.
     *
     * @param int $itineraryfieldid
     * @param int $typefieldid
     * @return int rule id
     */
    private function make_old_rule(int $itineraryfieldid, int $typefieldid): int {
        global $DB;
        return (int) $DB->insert_record('datalynx_rules', (object) [
            'dataid' => $this->dlx->id(),
            'type' => 'ridematch',
            'name' => 'Match rides',
            'description' => 'Tell both sides',
            'enabled' => 1,
            'param1' => json_encode(['entry_created', 'entry_updated']),
            'param2' => $itineraryfieldid,
            'param3' => $typefieldid,
            'param4' => 'Offer a ride',
            'param5' => 'Looking for a ride',
            'param6' => 10,
            'param7' => 24,
        ]);
    }

    /**
     * The old rule becomes one rule per side, both of them configured to match and to notify.
     */
    public function test_a_ride_matching_rule_becomes_two_notification_rules(): void {
        global $DB;

        $itinerary = $this->make_field('itinerary', 'Journey');
        $typefield = $this->make_field('select', 'Ride type', "Offer a ride\nLooking for a ride");
        $oldid = $this->make_old_rule($itinerary, $typefield);

        mod_datalynx_migrate_ridematch_rules();

        $this->assertEquals(0, $DB->count_records('datalynx_rules', ['type' => 'ridematch']));
        $rules = array_values($DB->get_records('datalynx_rules', ['dataid' => $this->dlx->id()], 'id'));
        $this->assertCount(2, $rules);

        $directions = [];
        $sides = [];
        foreach ($rules as $record) {
            $this->assertEquals('eventnotification', $record->type);
            $this->assertEquals(1, $record->enabled);
            $this->assertEquals(json_encode(['entry_created', 'entry_updated']), $record->param1);

            // Both authors are told about each other, which is all the old rule ever did.
            $recipients = json_decode($record->param3, true);
            $this->assertEquals(1, $recipients['matchauthors']);
            $this->assertEquals(1, $recipients['subjectauthorpermatch']);

            $param9 = json_decode($record->param9, true);
            $config = $param9[rule::MATCH_KEY];
            $this->assertEquals('ridematch' . $oldid, $config['dedupe'], 'both sides share one ledger');
            $this->assertEquals(1, $config['requireapproved']);
            $this->assertEquals(1, $config['forgetstale']);

            // The ride type has to differ, the routes have to fit, the departures have to be close.
            $this->assertCount(3, $config['criteria']);
            $this->assertEquals(
                [$typefield, match_compiler::OP_DIFFERENT],
                [$config['criteria'][0]['fieldid'], $config['criteria'][0]['op']]
            );
            $this->assertEquals(
                [$itinerary, match_compiler::OP_ROUTE, 10.0],
                [$config['criteria'][1]['fieldid'], $config['criteria'][1]['op'], $config['criteria'][1]['radius']]
            );
            $this->assertEquals(
                [$itinerary, match_compiler::OP_WITHIN, 24.0, HOURSECS],
                [
                    $config['criteria'][2]['fieldid'],
                    $config['criteria'][2]['op'],
                    $config['criteria'][2]['tolerance'],
                    $config['criteria'][2]['unit'],
                ]
            );
            $directions[] = $config['criteria'][1]['direction'];

            // The labels the old rule was configured with become a trigger condition on the
            // position the select field actually stores.
            $sides[] = $param9[$typefield]['AND'][0][2][0];
        }

        $this->assertEqualsCanonicalizing(
            [match_compiler::DIRECTION_REVERSE, match_compiler::DIRECTION_FORWARD],
            $directions
        );
        $this->assertEqualsCanonicalizing(['1', '2'], $sides, 'labels resolved to option positions');
    }

    /**
     * The pairs already announced are carried over, so nobody is introduced twice.
     */
    public function test_announced_pairs_are_carried_over(): void {
        global $DB;

        if (!$DB->get_manager()->table_exists('datalynx_ride_matches')) {
            $this->markTestSkipped('The old ride matching table has already been uninstalled.');
        }

        $itinerary = $this->make_field('itinerary', 'Journey');
        $typefield = $this->make_field('select', 'Ride type', "Offer a ride\nLooking for a ride");
        $oldid = $this->make_old_rule($itinerary, $typefield);

        $DB->insert_record('datalynx_ride_matches', (object) [
            'ruleid' => $oldid, 'offerentryid' => 42, 'requestentryid' => 17, 'timenotified' => 1234,
        ]);

        mod_datalynx_migrate_ridematch_rules();

        $carried = $DB->get_record('datalynx_rule_matches', ['scope' => 'ridematch' . $oldid]);
        $this->assertNotEmpty($carried);
        // A pair is held with the lower id first, so it is the same pair from either side.
        $this->assertEquals(17, $carried->entrylow);
        $this->assertEquals(42, $carried->entryhigh);
        $this->assertEquals(1234, $carried->timenotified);
    }

    /**
     * A rule that was never finished configuring carries nothing over and simply goes.
     */
    public function test_an_unconfigured_rule_is_dropped(): void {
        global $DB;

        $DB->insert_record('datalynx_rules', (object) [
            'dataid' => $this->dlx->id(),
            'type' => 'ridematch',
            'name' => 'Half done',
            'description' => '',
            'enabled' => 1,
            'param1' => json_encode(['entry_created']),
        ]);

        mod_datalynx_migrate_ridematch_rules();

        $this->assertEquals(0, $DB->count_records('datalynx_rules', ['dataid' => $this->dlx->id()]));
    }
}
