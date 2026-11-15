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
require_once($CFG->libdir . '/formslib.php');

use advanced_testcase;
use datalynxfield_status\field as status_field;
use MoodleQuickForm;

/**
 * What a field tag allows for an entry is resolved in one place (#266).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\field\datalynxfield_renderer::resolve_render_options
 * @covers     \mod_datalynx\local\field\datalynxfield_renderer::render_edit_mode
 */
final class render_options_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var int */
    private int $titleid;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        // Ids repeat from test to test; the per-request condition cache must not carry over.
        \mod_datalynx\local\field\datalynxfield_behavior::reset_condition_cache();

        [$this->course, $this->dlx] = $this->create_mobile_instance();
        $this->titleid = $this->add_mobile_field($this->dlx, 'text', 'Title');
    }

    /**
     * Resolve a tag of the title field.
     *
     * @param string $tag
     * @param \stdClass|null $entry
     * @param array|null $options
     * @return array the resolved options
     */
    private function resolve(string $tag, ?\stdClass $entry, ?array $options = null): array {
        $field = $this->dlx->get_field_from_id($this->titleid);

        return $field->renderer()->resolve_render_options($tag, $entry, $options)[0];
    }

    /**
     * Without options or entry the defaults apply, and the default behavior allows everything.
     */
    public function test_defaults_without_options_or_entry(): void {
        $options = $this->resolve('[[Title]]', null);

        $this->assertTrue($options['visible']);
        $this->assertTrue($options['editable']);
        $this->assertFalse($options['required']);
        $this->assertFalse($options['edit']);
        $this->assertFalse($options['internal']);
    }

    /**
     * Required comes from the behavior, and only while its availability conditions are met.
     */
    public function test_required_follows_behavior_and_conditions(): void {
        global $USER;
        $this->add_mobile_behavior($this->dlx, 'Required', ['required' => 1]);
        $switchid = $this->add_mobile_field($this->dlx, 'text', 'Switch');
        $this->add_mobile_behavior($this->dlx, 'Conditional', [
            'required' => 1,
            'conditions' => ['match' => 'all', 'rules' => [
                ['sourcefieldid' => $switchid, 'not' => '', 'operator' => '=', 'value' => 'on'],
            ]],
        ]);
        $on = $this->add_mobile_entry($this->dlx, $USER->id, [$switchid => 'on']);
        $off = $this->add_mobile_entry($this->dlx, $USER->id, [$switchid => 'off']);

        $this->assertTrue($this->resolve('[[Title|Required]]', (object) ['id' => $off])['required']);

        $met = $this->resolve('[[Title|Conditional]]', (object) ['id' => $on]);
        $this->assertTrue($met['visible'] && $met['editable'] && $met['required']);
        $unmet = $this->resolve('[[Title|Conditional]]', (object) ['id' => $off]);
        $this->assertFalse($unmet['visible'] || $unmet['editable'] || $unmet['required']);
    }

    /**
     * After final submission a field is read-only for its author unless the behavior allows it.
     */
    public function test_final_submission_locks_the_author(): void {
        $this->add_mobile_behavior($this->dlx, 'After final', ['editableafterfinal' => 1]);
        $student = $this->create_mobile_student($this->course);
        $this->setUser($student);
        $entry = (object) ['id' => 5, 'userid' => $student->id, 'status' => status_field::STATUS_FINAL_SUBMISSION];

        $this->assertFalse($this->resolve('[[Title]]', $entry)['editable']);
        $this->assertTrue($this->resolve('[[Title|After final]]', $entry)['editable']);
        $entry->status = status_field::STATUS_DRAFT;
        $this->assertTrue($this->resolve('[[Title]]', $entry)['editable']);
    }

    /**
     * The given options are kept, and the resolved keys always win over them.
     */
    public function test_given_options_are_kept(): void {
        $this->add_mobile_behavior($this->dlx, 'Read only', ['editable' => 0, 'editableby' => []]);

        $options = $this->resolve('[[Title|Read only]]', null, ['edit' => true, 'editable' => true]);

        $this->assertTrue($options['edit']);
        $this->assertFalse($options['editable']);
    }

    /**
     * The generic edit element carries the name process_entries() reads back.
     */
    public function test_base_edit_element_uses_the_canonical_name(): void {
        $field = $this->dlx->get_field_from_id($this->titleid);
        $renderer = new class ($field) extends \mod_datalynx\local\field\datalynxfield_renderer {
        };
        $mform = new MoodleQuickForm('test', 'post', '');

        $renderer->render_edit_mode($mform, (object) ['id' => -1], ['disabled' => false]);

        $this->assertTrue($mform->elementExists("field_{$this->titleid}_-1"));
    }

    /**
     * A field editable only by the author is editable in a new entry, which the current user is
     * filling in, and in the user's own entries - not in other users' entries.
     */
    public function test_author_permission_applies_to_new_entries(): void {
        $this->add_mobile_behavior($this->dlx, 'Author only', ['editableby' => [datalynx::PERMISSION_AUTHOR]]);
        $student = $this->create_mobile_student($this->course);
        $other = $this->create_mobile_student($this->course);
        $this->setUser($student);
        $tag = '[[Title|Author only]]';

        $this->assertTrue($this->resolve($tag, (object) ['id' => -1], ['edit' => true])['editable']);
        $this->assertTrue($this->resolve($tag, (object) ['id' => -2], ['edit' => true])['editable']);
        $this->assertTrue($this->resolve($tag, (object) ['id' => 5, 'userid' => $student->id], ['edit' => true])['editable']);
        $this->assertFalse($this->resolve($tag, (object) ['id' => 5, 'userid' => $other->id], ['edit' => true])['editable']);
    }
}
