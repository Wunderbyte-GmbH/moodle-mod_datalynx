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
use mod_datalynx\local\field\datalynxfield_behavior;
use mod_datalynx\local\ride\board_builder;
use mod_datalynx\local\ride\schedule;

/**
 * The rules of a ride offer (mod_datalynx#325): licence plate, seats, editable until departure.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxfield_text\field
 * @covers     \datalynxfield_number\renderer
 * @covers     \mod_datalynx\local\field\datalynxfield_behavior
 * @covers     \mod_datalynx\local\ride\board_builder
 */
final class ride_offer_rules_test extends advanced_testcase {
    use mobile_test_trait;

    /**
     * A licence plate is checked and stored trimmed, in upper case.
     */
    public function test_licence_plate(): void {
        global $DB;
        $this->resetAfterTest();
        [, $dlx] = $this->create_mobile_instance();
        $plateid = $this->add_mobile_field($dlx, 'text', 'Kennzeichen', ['param4' => 'licenceplate']);
        $field = $dlx->get_field_from_id($plateid, true);
        $name = "field_{$plateid}_1";

        $this->assertSame([], $field->renderer()->validate(1, [], (object) [$name => 'W-12345 A']));
        $this->assertSame([], $field->renderer()->validate(1, [], (object) [$name => 'gö 4711']));
        $this->assertArrayHasKey($name, $field->renderer()->validate(1, [], (object) [$name => 'W.123']));
        $this->assertArrayHasKey($name, $field->renderer()->validate(1, [], (object) [$name => 'ABCDEFGHIJKLM']));

        $entryid = $this->add_mobile_entry($dlx, get_admin()->id, []);
        $field->update_content((object) ['id' => $entryid], ['  w-12345   a ']);
        $stored = $DB->get_field('datalynx_contents', 'content', ['entryid' => $entryid, 'fieldid' => $plateid]);
        $this->assertSame('W-12345 A', $stored);
    }

    /**
     * A number field takes a smallest and a largest value.
     */
    public function test_number_range(): void {
        $this->resetAfterTest();
        [, $dlx] = $this->create_mobile_instance();
        $seatsid = $this->add_mobile_field($dlx, 'number', 'Plätze', ['param5' => 0, 'param6' => 8]);
        $renderer = $dlx->get_field_from_id($seatsid, true)->renderer();
        $name = "field_{$seatsid}_1";
        $this->assertSame([], $renderer->validate(1, [], (object) [$name => '0']));
        $this->assertSame([], $renderer->validate(1, [], (object) [$name => '8']));
        $this->assertArrayHasKey($name, $renderer->validate(1, [], (object) [$name => '9']));
        $this->assertArrayHasKey($name, $renderer->validate(1, [], (object) [$name => '-1']));
    }

    /**
     * Fields of an offer can be changed until its departure; then only managers of entries can.
     */
    public function test_editable_until_departure(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $dlx] = $this->create_mobile_instance();
        $terminid = $this->add_mobile_field($dlx, 'schedule', 'Termin');
        $noteid = $this->add_mobile_field($dlx, 'text', 'Notiz');
        $behaviorid = $DB->insert_record('datalynx_behaviors', (object) [
            'dataid' => $dlx->id(), 'name' => 'bisabfahrt', 'description' => '',
            'visibleto' => serialize(['permissions' => [1, 2, 4, 16]]),
            'editableby' => serialize([1, 2, 4, 16]), 'required' => 0, 'editableafterfinal' => 0,
            'conditions' => json_encode(['editableuntil' => $terminid]),
        ]);
        local\definition_cache::invalidate($dlx->id());
        $driver = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $once = fn(string $date) => (new schedule(schedule::MODE_ONCE, $date, [], 8 * 60))->to_json();
        $past = $this->add_mobile_entry($dlx, $driver->id, [$terminid => $once(date('Y-m-d', time() - 2 * DAYSECS))]);
        $future = $this->add_mobile_entry($dlx, $driver->id, [$terminid => $once(date('Y-m-d', time() + 2 * DAYSECS))]);
        // A weekly schedule has no single departure.
        $weekly = $this->add_mobile_entry($dlx, $driver->id, [$terminid => (new schedule(schedule::MODE_WEEKLY, '', [1], 480))
            ->to_json()]);

        $dlx = new datalynx($dlx->id());
        $behavior = datalynxfield_behavior::from_name('bisabfahrt', $dlx->id(), $dlx);
        $this->assertSame($terminid, $behavior->get_editable_until_fieldid());
        $this->setUser($driver);
        $this->assertTrue($behavior->is_past_editable_until((object) ['id' => $past]));
        $this->assertFalse($behavior->is_past_editable_until((object) ['id' => $future]));
        $this->assertFalse($behavior->is_past_editable_until((object) ['id' => $weekly]));

        // The editability the edit form uses.
        $renderer = $dlx->get_field_from_id($noteid, true)->renderer();
        $tag = '[[Notiz|bisabfahrt]]';
        $this->assertFalse($renderer->is_tag_editable($tag, (object) ['id' => $past, 'userid' => $driver->id]));
        $this->assertTrue($renderer->is_tag_editable($tag, (object) ['id' => $future, 'userid' => $driver->id]));
        $this->setAdminUser();
        $this->assertTrue(
            $renderer->is_tag_editable($tag, (object) ['id' => $past, 'userid' => $driver->id]),
            'managers of entries are not bound'
        );
        $this->assertNotEmpty($behaviorid);
    }

    /**
     * The billing board: seats as a number 0 to 8, plate required on offers, offers editable until
     * departure, and only rides with a free seat in the search and the matching.
     */
    public function test_billing_board(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $builder = new board_builder($this->getDataGenerator()->create_course(), board_builder::VARIANT_BILLING);
        $builder->build();
        $ids = $builder->get_fieldids_by_name();
        $dataid = $builder->get_dataid();

        $seats = $DB->get_record('datalynx_fields', ['id' => $ids['Freie Plätze']]);
        $this->assertSame(['number', '0', '8'], [$seats->type, $seats->param5, $seats->param6]);
        $this->assertSame('licenceplate', $DB->get_field('datalynx_fields', 'param4', ['id' => $ids['Kennzeichen']]));
        $step3 = $DB->get_field('datalynx_views', 'param2', ['dataid' => $dataid, 'name' => board_builder::VIEWS['step3']]);
        $this->assertStringContainsString('[[Kennzeichen|angebotpflicht|', $step3);

        $conditions = json_decode($DB->get_field('datalynx_behaviors', 'conditions', ['dataid' => $dataid,
            'name' => 'angebotpflicht']), true);
        $this->assertSame($ids['Termin'], $conditions['editableuntil']);

        $offers = local\serialized::to_array($DB->get_field(
            'datalynx_filters',
            'customsearch',
            ['dataid' => $dataid, 'name' => 'Nur Angebote']
        ));
        $this->assertSame([['', 'FREEPLACES', '']], $offers[$ids['Passagiere']]['AND']);
        $rule = json_decode($DB->get_field('datalynx_rules', 'param9', ['dataid' => $dataid,
            'name' => 'Passende Fahrt gefunden (2)']), true);
        $this->assertSame([['', 'FREEPLACES', '']], $rule['_matchcriteria']['counterpart'][$ids['Passagiere']]['AND']);
    }
}
