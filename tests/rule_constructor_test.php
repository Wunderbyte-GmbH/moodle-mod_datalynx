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

use advanced_testcase;
use datalynxrule_eventnotification\rule;
use moodle_exception;

/**
 * A rule can be built from its id, through the instance's rule manager.
 *
 * @package    mod_datalynx
 * @category   test
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\rule\base::__construct
 */
final class rule_constructor_test extends advanced_testcase {
    /** @var datalynx */
    private datalynx $dlx;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $this->dlx = new datalynx($instance->id);
    }

    /**
     * Built from its id, a rule carries its stored record, also when the instance object
     * already loaded its rules before the rule was added.
     */
    public function test_rule_from_id(): void {
        $this->dlx->get_rule_manager()->get_rules();
        $ruleid = (int) $this->dlx->get_rule_manager()->get_rule('eventnotification')
            ->insert_rule((object) ['name' => 'Notify', 'param1' => json_encode(['entry_created'])]);

        $rule = new rule($this->dlx, $ruleid);

        $this->assertSame($ruleid, (int) $rule->get_id());
        $this->assertSame('Notify', $rule->get_name());
        $this->assertSame((int) $this->dlx->id(), (int) $rule->rule->dataid);

        $byinstanceid = new rule($this->dlx->id(), $ruleid);
        $this->assertSame('Notify', $byinstanceid->get_name());
    }

    /**
     * An id that is not a rule of the instance is refused with a readable message.
     */
    public function test_unknown_rule_id(): void {
        try {
            new rule($this->dlx, 999999);
            $this->fail('An unknown rule id must be refused.');
        } catch (moodle_exception $e) {
            $this->assertSame('invalidrule', $e->errorcode);
            $this->assertStringContainsString('999999', $e->getMessage());
            $this->assertStringNotContainsString('[[', $e->getMessage());
        }
    }
}
