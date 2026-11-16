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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use advanced_testcase;
use mod_datalynx\local\entry\entry_submission_service;
use ReflectionClass;

/**
 * Team members the field no longer offers (e.g. no longer enrolled) are removed when the entry is
 * saved; the edit form warns about it and saving names who was removed (mod_datalynx#309).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxfield_teammemberselect\field
 * @covers     \datalynxfield_teammemberselect\renderer::render_edit_mode
 */
final class teammemberselect_not_offered_test extends advanced_testcase {
    use mobile_test_trait;

    /**
     * The stored team keeps an unenrolled user until the entry is saved: the form warns, saving
     * removes them and says so.
     */
    public function test_unenrolled_member_is_announced_and_removed(): void {
        global $COURSE, $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        // The member menu is cached per field id, and ids repeat between tests.
        foreach ((new ReflectionClass(\datalynxfield_teammemberselect\field::class))->getStaticProperties() as $name => $value) {
            if (is_array($value)) {
                (new ReflectionClass(\datalynxfield_teammemberselect\field::class))->setStaticPropertyValue($name, []);
            }
        }

        [$course, $dlx] = $this->create_mobile_instance();
        $COURSE = $course;
        $enrolled = $this->getDataGenerator()->create_user(['firstname' => 'Erna', 'lastname' => 'Enrolled']);
        $gone = $this->getDataGenerator()->create_user(['firstname' => 'Gerd', 'lastname' => 'Gone']);
        $this->getDataGenerator()->enrol_user($enrolled->id, $course->id, 'student');

        $teamid = $this->add_mobile_field($dlx, 'teammemberselect', 'Lehrende', [
            'param1' => '5', 'param2' => json_encode([datalynx::PERMISSION_STUDENT]),
        ]);
        $titleid = $this->add_mobile_field($dlx, 'text', 'Title');
        $viewid = $this->add_mobile_view($dlx, 'grid', 'Edit', '[[Title]] [[Lehrende]] ##edit##', [
            'section' => '##addnewentry## ##entries##',
        ]);
        $dlx = new datalynx($dlx->id());
        $team = json_encode([(int) $enrolled->id, (int) $gone->id]);
        $entryid = $this->add_mobile_entry($dlx, $USER->id, [$titleid => 'Planning', $teamid => $team]);
        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        $field = $dlx->get_field_from_id($teamid);

        // The field names who it no longer offers.
        $this->assertSame([(int) $gone->id => 'Gerd Gone'], $field->get_members_not_offered($entry, [$enrolled->id, $gone->id]));

        // The edit form warns at the field.
        $entry->{"c{$teamid}_content"} = json_encode([(int) $enrolled->id, (int) $gone->id]);
        $mform = new \MoodleQuickForm('test', 'post', '');
        $field->renderer()->render_edit_mode($mform, $entry, []);
        $html = $mform->toHtml();
        $this->assertStringContainsString('Gerd Gone', $html);
        $this->assertStringContainsString('alert-warning', $html);

        // Saving another field of the entry removes the user the form could not keep, and says so.
        \core\notification::fetch();
        $service = new entry_submission_service($dlx->get_view('grid', $viewid, false));
        $result = $service->save([$entryid], (object) [
            "field_{$titleid}_{$entryid}" => 'Planning, corrected',
            "field_{$teamid}_{$entryid}" => [(int) $enrolled->id],
        ]);
        $this->assertSame([], $result['errors']);
        $this->assertSame(
            [(int) $enrolled->id],
            json_decode($DB->get_field('datalynx_contents', 'content', ['entryid' => $entryid, 'fieldid' => $teamid]), true)
        );
        $messages = array_map(fn($n) => $n->get_message(), \core\notification::fetch());
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('Gerd Gone', $messages[0]);
        $this->assertStringContainsString('Lehrende', $messages[0]);
    }
}
