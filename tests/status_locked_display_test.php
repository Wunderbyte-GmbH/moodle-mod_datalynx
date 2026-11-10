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
 * Tests for the read-only display of a status that can no longer be changed.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use datalynxfield_status\field as datalynxfield_status;

/**
 * After final submission only users with mod/datalynx:editfinalsubmission can change the status,
 * so everyone else sees the status as text instead of the draft/final switch.
 *
 * @covers \datalynxfield_status\renderer::replacements
 */
final class status_locked_display_test extends advanced_testcase {
    /** @var datalynx */
    private $dlx;

    /** @var \stdClass */
    private $student;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->student = $generator->create_user();
        $generator->enrol_user($this->student->id, $course->id, 'student');
        $instance = $generator->create_module('datalynx', ['course' => $course->id]);
        $this->dlx = new datalynx($instance->id);
    }

    /**
     * A student sees the switch for a draft and read-only text after final submission.
     */
    public function test_student_sees_text_after_final_submission(): void {
        $this->setUser($this->student);
        $draft = $this->replacement(datalynxfield_status::STATUS_DRAFT);
        $this->assertIsArray($draft[1], 'draft: deferred form element (switch)');

        $final = $this->replacement(datalynxfield_status::STATUS_FINAL_SUBMISSION);
        $this->assertSame('html', $final[0]);
        $this->assertStringContainsString(get_string('statusfinalsubmission', 'datalynx'), $final[1]);
        $this->assertStringContainsString(get_string('statuslockeddesc', 'datalynx'), $final[1]);
    }

    /**
     * Users who may change a final status keep the switch.
     */
    public function test_editfinalsubmission_keeps_switch(): void {
        $this->setAdminUser();
        $final = $this->replacement(datalynxfield_status::STATUS_FINAL_SUBMISSION);
        $this->assertIsArray($final[1]);
    }

    /**
     * Edit-mode replacement of ##status## for an entry of the student with the given status.
     *
     * @param int $status
     * @return array
     */
    private function replacement(int $status): array {
        $field = null;
        foreach ($this->dlx->get_fields() as $candidate) {
            if ($candidate->type === 'status') {
                $field = $candidate;
            }
        }
        $this->assertNotNull($field, 'internal status field');
        $entry = (object) ['id' => 7, 'userid' => $this->student->id, 'status' => $status];
        $replacements = $field->renderer()->replacements(['##status##'], $entry, ['edit' => true]);
        return $replacements['##status##'];
    }
}
