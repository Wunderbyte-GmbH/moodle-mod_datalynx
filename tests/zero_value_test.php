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
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\entry\entry_submission_service;

/**
 * "0" is a value; nothing is no value (mod_datalynx#315).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\field\datalynxfield_base
 */
final class zero_value_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var int[] field ids by type */
    private array $fieldids = [];

    /** @var int */
    private int $viewid;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        $this->fieldids['text'] = $this->add_mobile_field($dlx, 'text', 'Text');
        $this->fieldids['textarea'] = $this->add_mobile_field($dlx, 'textarea', 'Textarea');
        $this->fieldids['select'] = $this->add_mobile_field($dlx, 'select', 'Select', ['param1' => "One\nTwo"]);
        $this->fieldids['radiobutton'] = $this->add_mobile_field($dlx, 'radiobutton', 'Radio', ['param1' => "One\nTwo"]);
        $this->fieldids['duration'] = $this->add_mobile_field($dlx, 'duration', 'Duration');
        $this->add_mobile_behavior($dlx, 'Required', ['required' => 1]);
        $this->viewid = $this->add_mobile_view(
            $dlx,
            'grid',
            'Edit',
            '[[Text]] [[Textarea]] [[Select]] [[Radio]] [[Duration]] ##edit##',
            ['section' => '##addnewentry## ##entries##']
        );
        $this->dlx = new datalynx($dlx->id());
    }

    /**
     * A text or text area "0" is stored, shown and offered for editing.
     */
    public function test_zero_text_is_kept(): void {
        global $DB;

        $entryid = $this->save([
            "field_{$this->fieldids['text']}_-1" => '0',
            "field_{$this->fieldids['textarea']}_-1" => '0',
        ]);

        foreach (['text', 'textarea'] as $type) {
            $this->assertSame('0', $DB->get_field('datalynx_contents', 'content', [
                'fieldid' => $this->fieldids[$type], 'entryid' => $entryid,
            ]), $type);
        }
        $entry = (object) ['id' => $entryid, "c{$this->fieldids['text']}_content" => '0'];
        $field = $this->dlx->get_field_from_id($this->fieldids['text']);
        $this->assertStringContainsString('0', $field->renderer()->render_display_mode($entry, []));
        $mform = new \MoodleQuickForm('test', 'post', '');
        $field->renderer()->render_edit_mode($mform, $entry, []);
        $this->assertSame('0', $mform->getElement("field_{$this->fieldids['text']}_{$entryid}")->getValue());
    }

    /**
     * A single choice left on "Choose..." stores nothing and is found by "is empty"; a stored 0
     * from earlier saves counts as empty too.
     */
    public function test_single_choice_without_choice_is_empty(): void {
        global $DB, $USER;

        $entryid = $this->save([
            "field_{$this->fieldids['text']}_-1" => 'x',
            "field_{$this->fieldids['select']}_-1" => '',
            "field_{$this->fieldids['radiobutton']}_-1" => '',
        ]);
        $legacy = $this->add_mobile_entry($this->dlx, $USER->id, [$this->fieldids['select'] => '0']);
        $chosen = $this->add_mobile_entry($this->dlx, $USER->id, [$this->fieldids['select'] => '2']);

        foreach (['select', 'radiobutton'] as $type) {
            $this->assertFalse($DB->record_exists('datalynx_contents', [
                'fieldid' => $this->fieldids[$type], 'entryid' => $entryid,
            ]), $type);
        }
        $this->assertSame([$entryid, $legacy], $this->search($this->fieldids['select'], ['', '', '']));
        $this->assertSame([$chosen], $this->search($this->fieldids['select'], ['NOT', '', '']));
    }

    /**
     * A required duration left at 0 is refused.
     */
    public function test_required_duration_of_zero_is_refused(): void {
        $field = $this->fieldids['duration'];
        $this->dlx = new datalynx($this->dlx->id());
        $viewid = $this->add_mobile_view($this->dlx, 'grid', 'Required', '[[Duration|Required]] ##edit##', [
            'section' => '##addnewentry## ##entries##',
        ]);
        $service = new entry_submission_service($this->dlx->get_view('grid', $viewid, false));

        $this->assertArrayHasKey("field_{$field}_-1", $service->validate([-1], ["field_{$field}_-1" => 0]));
        $this->assertSame([], $service->validate([-1], ["field_{$field}_-1" => 3600]));
    }

    /**
     * The quick search finds "0".
     */
    public function test_quick_search_for_zero(): void {
        global $USER;

        $zero = $this->add_mobile_entry($this->dlx, $USER->id, [$this->fieldids['text'] => '0']);
        $this->add_mobile_entry($this->dlx, $USER->id, [$this->fieldids['text'] => '5']);

        $view = $this->dlx->get_view('grid', $this->viewid, false);
        $view->set_filter(['search' => '0'], true);
        $entries = new datalynx_entries($this->dlx, $view->get_filter());
        $found = array_map('intval', array_keys($entries->get_entries()->entries ?? []));

        $this->assertSame([$zero], $found);
    }

    /**
     * An option labelled "0" can be added, and saving the settings keeps choices of options whose
     * labels are numerically equal ("5" and "05").
     *
     * @covers \mod_datalynx\local\field\datalynxfield_option::set_field
     */
    public function test_option_labels_are_text(): void {
        global $DB, $USER;

        $fieldid = $this->add_mobile_field($this->dlx, 'select', 'Numbers', ['param1' => "5\n05"]);
        $dlx = new datalynx($this->dlx->id());
        $entryid = $this->add_mobile_entry($dlx, $USER->id, [$fieldid => '2']);
        $field = $dlx->get_field_from_id($fieldid);
        $field->get_options(); // As the settings form does before it is saved.

        $field->set_field((object) ['id' => $fieldid, 'name' => 'Numbers', 'addoptions' => '0']);
        $field->update_field();

        $this->assertSame("5\n05\n0", $DB->get_field('datalynx_fields', 'param1', ['id' => $fieldid]));
        $this->assertSame('2', $DB->get_field('datalynx_contents', 'content', ['fieldid' => $fieldid, 'entryid' => $entryid]));
    }

    /**
     * Save a new entry through the submission service.
     *
     * @param array $data
     * @return int the entry id
     */
    private function save(array $data): int {
        $service = new entry_submission_service($this->dlx->get_view('grid', $this->viewid, false));
        $result = $service->save([-1], (object) $data);
        $this->assertSame([], $result['errors']);
        return (int) reset($result['entryids']);
    }

    /**
     * The entries a criterion on a field selects.
     *
     * @param int $fieldid
     * @param array $criterion
     * @return int[]
     */
    private function search(int $fieldid, array $criterion): array {
        $view = $this->dlx->get_view('grid', $this->viewid, false);
        $view->set_filter(['customsearch' => [$fieldid => ['AND' => [$criterion]]]], true);
        $entries = new datalynx_entries($this->dlx, $view->get_filter());
        $ids = array_map('intval', array_keys($entries->get_entries()->entries ?? []));
        sort($ids);
        return $ids;
    }
}
