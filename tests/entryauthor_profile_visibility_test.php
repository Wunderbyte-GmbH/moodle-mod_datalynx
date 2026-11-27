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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/user/profile/lib.php');

/**
 * ##author:<profilefield>## follows the visibility of the profile field.
 *
 * Core defines the PROFILE_VISIBLE_* constants as strings; comparing them strictly with the
 * integer visibility hid every profile field from everyone but the author and managers, e.g. the
 * student ID from the head of section reviewing an application (DocSchool).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxfield_entryauthor\renderer::may_see_profile_field
 */
final class entryauthor_profile_visibility_test extends advanced_testcase {
    /**
     * The value of each author profile field as seen by teacher, another student and the author.
     */
    public function test_profile_fields_follow_their_visibility(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();

        $visibility = [
            'pfall' => PROFILE_VISIBLE_ALL,
            'pfteachers' => PROFILE_VISIBLE_TEACHERS,
            'pfprivate' => PROFILE_VISIBLE_PRIVATE,
        ];
        foreach ($visibility as $shortname => $visible) {
            $gen->create_custom_profile_field([
                'shortname' => $shortname, 'name' => $shortname, 'datatype' => 'text', 'visible' => $visible,
            ]);
        }
        $course = $gen->create_course();
        $instance = $gen->create_module('datalynx', ['course' => $course->id]);
        foreach (array_keys($visibility) as $shortname) {
            \mod_datalynx\local\field_format\manager::save_format((object) [
                'dataid' => $instance->id,
                'name' => $shortname,
                'fieldtype' => 'entryauthor',
                'settings' => json_encode(['option' => $shortname, 'editable' => 0, 'mandatory' => 0]),
            ]);
        }

        $author = $gen->create_user(['profile_field_pfall' => 'A1', 'profile_field_pfteachers' => 'T1',
            'profile_field_pfprivate' => 'P1']);
        $teacher = $gen->create_user();
        $other = $gen->create_user();
        $gen->enrol_user($author->id, $course->id, 'student');
        $gen->enrol_user($other->id, $course->id, 'student');
        $gen->enrol_user($teacher->id, $course->id, 'teacher');
        $entryid = $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $instance->id, 'userid' => $author->id, 'groupid' => 0, 'status' => 2, 'approved' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid]);

        $seen = function (\stdClass $viewer) use ($instance, $entry, $visibility): array {
            $this->setUser($viewer);
            $dlx = new datalynx($instance->id);
            $values = [];
            foreach ($dlx->get_fields(null, false, true) as $field) {
                if ($field->type === 'entryauthor' && isset($visibility[(string) $field->id()])) {
                    $method = new \ReflectionMethod($field->renderer(), 'display_profile_value');
                    $values[(string) $field->id()] = trim(strip_tags($method->invoke($field->renderer(), $entry)));
                }
            }
            ksort($values);
            return $values;
        };

        $this->assertSame(['pfall' => 'A1', 'pfprivate' => '', 'pfteachers' => 'T1'], $seen($teacher));
        $this->assertSame(['pfall' => 'A1', 'pfprivate' => '', 'pfteachers' => ''], $seen($other));
        $this->assertSame(['pfall' => 'A1', 'pfprivate' => 'P1', 'pfteachers' => 'T1'], $seen($author));
    }
}
