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
use mod_datalynx\local\view\manager\email_view_manager;

/**
 * Tests for the Email view payload manager.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_datalynx\local\view\manager\email_view_manager
 */
final class email_view_manager_test extends advanced_testcase {
    /**
     * Create a datalynx instance for testing.
     *
     * @return datalynx
     */
    private function create_test_datalynx(): datalynx {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->create_module('datalynx', ['course' => $course->id]);

        return new datalynx($instance->id);
    }

    /**
     * Insert an Email view record for the supplied datalynx instance.
     *
     * @param datalynx $df
     * @param string $param2
     * @return \stdClass
     */
    private function create_email_view_record(datalynx $df, string $param2): \stdClass {
        global $DB;

        $view = (object) [
            'dataid' => $df->id(),
            'type' => 'email',
            'name' => 'Email view',
            'description' => '',
            'visible' => 1,
            'filter' => 0,
            'perpage' => 0,
            'groupby' => '',
            'param5' => 0,
            'param10' => 0,
            'section' => '##entries##',
            'param2' => $param2,
        ];
        $view->id = (int) $DB->insert_record('datalynx_views', $view);

        return $view;
    }

    /**
     * Create a minimal entry for the supplied datalynx instance.
     *
     * @param datalynx $df
     * @return int
     */
    private function create_entry(datalynx $df): int {
        global $DB, $USER;

        return (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $df->id(),
            'userid' => $USER->id,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * The manager should return the rendered body for the selected entry only.
     *
     * @covers ::get_entry_payload
     */
    public function test_get_entry_payload_returns_single_entry_body(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $df = $this->create_test_datalynx();
        $view = $this->create_email_view_record($df, '<p>##notificationentrylink##</p><p>##entryid##</p>');
        $entryid = $this->create_entry($df);
        $otherentryid = $this->create_entry($df);

        $manager = new email_view_manager();
        $payload = $manager->get_entry_payload($df->id(), $view->id, $entryid, [
            'notificationentrylink' => '<a href="https://example.invalid/entry">Entry</a>',
        ]);

        $this->assertSame($df->id(), $payload['datalynxid']);
        $this->assertSame($view->id, $payload['viewid']);
        $this->assertSame('email', $payload['viewtype']);
        $this->assertTrue($payload['hascontent']);
        $this->assertStringContainsString('<a href="https://example.invalid/entry">Entry</a>', $payload['bodyhtml']);
        $this->assertStringContainsString('<p>' . $entryid . '</p>', $payload['bodyhtml']);
        $this->assertStringNotContainsString('<p>' . $otherentryid . '</p>', $payload['bodyhtml']);
    }

    /**
     * The manager should return an empty payload when the entry does not exist in the Email view.
     *
     * @covers ::get_entry_payload
     */
    public function test_get_entry_payload_returns_empty_payload_for_missing_entry(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $df = $this->create_test_datalynx();
        $view = $this->create_email_view_record($df, '<p>##entryid##</p>');

        $manager = new email_view_manager();
        $payload = $manager->get_entry_payload($df->id(), $view->id, 99999);

        $this->assertFalse($payload['hascontent']);
        $this->assertSame('', $payload['bodyhtml']);
    }
}
