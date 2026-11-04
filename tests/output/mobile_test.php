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

namespace mod_datalynx\output;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use advanced_testcase;
use mod_datalynx\local\mobile\entries_payload_builder;
use mod_datalynx\mobile_test_trait;

/**
 * Tests for the Moodle App content of the activity.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\output\mobile
 */
final class mobile_test extends advanced_testcase {
    use mobile_test_trait;

    /**
     * Call the content method the way tool_mobile_get_content does: every argument a string.
     *
     * @param array $args
     * @return array the result, with otherdata decoded the way the app decodes it
     */
    private function get_content(array $args): array {
        $result = mobile::mobile_course_view(array_map('strval', $args));
        $result['otherdata'] = array_map(fn($value) => json_decode($value, true), $result['otherdata']);

        return $result;
    }

    /**
     * Opening the activity shows its entries, with the state the template needs in otherdata.
     */
    public function test_course_view_first_paint(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $dlx] = $this->create_mobile_instance(['intro' => '<p>Find a ride</p>']);
        $fieldid = $this->add_mobile_field($dlx, 'text', 'Title');
        $viewid = $this->add_mobile_view($dlx, 'grid', 'Rides', '<div class="ride">[[Title]]</div>');
        $entryid = $this->add_mobile_entry($dlx, $USER->id, [$fieldid => 'Wien → Graz']);

        $result = $this->get_content(['cmid' => $dlx->cm->id, 'courseid' => $course->id]);

        $this->assertSame('main', $result['templates'][0]['id']);
        $html = $result['templates'][0]['html'];
        $this->assertStringContainsString('core-format-text', $html);
        $this->assertStringContainsString('CONTENT_OTHERDATA.entries', $html);
        $this->assertStringNotContainsString('<%', $html, 'Mustache delimiters left in the template.');
        $this->assertStringNotContainsString('Mock', $html);
        // Entry content travels as data, never in the template source.
        $this->assertStringNotContainsString('Graz', $html);
        $this->assertSame('', $result['javascript']);

        $data = $result['otherdata'];
        $this->assertSame($viewid, $data['viewid']);
        $this->assertTrue($data['hasview']);
        $this->assertTrue($data['supported']);
        $this->assertSame((int) $course->id, $data['courseid']);
        $this->assertStringContainsString('Find a ride', $data['intro']);
        $this->assertSame([$entryid], array_column($data['entries'], 'id'));
        $this->assertStringContainsString('Wien → Graz', $data['entries'][0]['html']);
        $this->assertSame(mobile::PERPAGE, $data['perpage']);
        $this->assertFalse($data['cangrow']);
    }

    /**
     * Every otherdata value survives the app's JSON parse with its type: strings stay strings.
     */
    public function test_otherdata_keeps_string_values(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $dlx] = $this->create_mobile_instance();
        $fieldid = $this->add_mobile_field($dlx, 'text', 'Title');
        $this->add_mobile_view($dlx, 'grid', 'Rides');
        $this->add_mobile_entry($dlx, $USER->id, [$fieldid => '42']);
        $criteria = json_encode([$fieldid => ['AND' => [['', '=', '42']]]]);

        $result = mobile::mobile_course_view([
            'cmid' => (string) $dlx->cm->id,
            'courseid' => (string) $course->id,
            'search' => '42',
            'criteria' => $criteria,
        ]);

        foreach ($result['otherdata'] as $name => $value) {
            $this->assertIsString($value, "otherdata $name is not a string");
        }
        $data = array_map(fn($value) => json_decode($value, true), $result['otherdata']);
        $this->assertSame('42', $data['search']);
        $this->assertSame($criteria, $data['criteria']);
        $this->assertCount(1, $data['entries']);
    }

    /**
     * Arguments the app does not send do not raise warnings.
     */
    public function test_missing_arguments_are_empty(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $dlx] = $this->create_mobile_instance();
        $this->add_mobile_view($dlx, 'grid', 'Rides');

        $result = $this->get_content(['cmid' => $dlx->cm->id, 'courseid' => $course->id]);

        $this->assertSame('', $result['otherdata']['search']);
        $this->assertSame(0, $result['otherdata']['filterid']);
    }

    /**
     * The list grows in steps until a request would exceed the most one may return.
     */
    public function test_list_growth(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $dlx] = $this->create_mobile_instance();
        $fieldid = $this->add_mobile_field($dlx, 'text', 'Title');
        $this->add_mobile_view($dlx, 'grid', 'Rides');
        for ($i = 0; $i < mobile::PERPAGE + 1; $i++) {
            $this->add_mobile_entry($dlx, $USER->id, [$fieldid => "Ride $i"]);
        }

        $data = $this->get_content(['cmid' => $dlx->cm->id, 'courseid' => $course->id])['otherdata'];
        $this->assertCount(mobile::PERPAGE, $data['entries']);
        $this->assertTrue($data['cangrow']);
        $this->assertSame(2 * mobile::PERPAGE, $data['nextperpage']);

        $data = $this->get_content([
            'cmid' => $dlx->cm->id,
            'courseid' => $course->id,
            'perpage' => $data['nextperpage'],
        ])['otherdata'];
        $this->assertCount(mobile::PERPAGE + 1, $data['entries']);
        $this->assertFalse($data['cangrow']);

        $data = $this->get_content([
            'cmid' => $dlx->cm->id,
            'courseid' => $course->id,
            'perpage' => entries_payload_builder::MAX_PERPAGE,
        ])['otherdata'];
        $this->assertSame(entries_payload_builder::MAX_PERPAGE, $data['nextperpage']);
    }

    /**
     * An activity without a view the user can open says so instead of failing.
     */
    public function test_no_view(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $dlx] = $this->create_mobile_instance();

        $data = $this->get_content(['cmid' => $dlx->cm->id, 'courseid' => $course->id])['otherdata'];

        $this->assertFalse($data['hasview']);
        $this->assertSame([], $data['entries']);
    }

    /**
     * The module must be one the user can access.
     */
    public function test_requires_access(): void {
        $this->resetAfterTest();
        [$course, $dlx] = $this->create_mobile_instance();
        $this->add_mobile_view($dlx, 'grid', 'Rides');
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\require_login_exception::class);
        $this->get_content(['cmid' => $dlx->cm->id, 'courseid' => $course->id]);
    }
}
