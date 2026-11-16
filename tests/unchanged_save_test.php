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

/**
 * Saving an entry again with the same value reports no change of the field; another value does
 * (mod_datalynx#314). Rules that only trigger when a field changes rely on this.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\field\datalynxfield_base::update_content
 * @covers     \mod_datalynx\local\field\datalynxfield_base::same_contents
 */
final class unchanged_save_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var int */
    private int $viewid;

    /**
     * Field types with their settings, and two different values as the entry form posts them
     * after the input contract: element name suffix => value.
     *
     * @return array
     */
    public static function field_provider(): array {
        $options = ['param1' => "One\nTwo\nThree"];
        $editor = fn(string $text): array => ['_editor' => ['text' => $text, 'format' => FORMAT_HTML, 'itemid' => 0]];
        return [
            'text' => ['text', [], ['' => 'First'], ['' => 'Second']],
            'textarea' => ['textarea', [], ['' => 'First'], ['' => 'Second']],
            'number' => ['number', [], ['' => '5'], ['' => '6']],
            'editor' => ['editor', [], $editor('<p>First</p>'), $editor('<p>Second</p>')],
            'url' => ['url', [], ['_url' => 'https://example.org/a'], ['_url' => 'https://example.org/b']],
            'url with link text' => ['url', ['param5' => '1'], ['_url' => 'https://example.org/a', '_alt' => 'A'],
                ['_url' => 'https://example.org/a', '_alt' => 'B']],
            'youtube' => ['youtube', [], ['' => 'https://www.youtube.com/watch?v=abcdef1'],
                ['' => 'https://youtu.be/ghijkl2']],
            'time' => ['time', [], ['' => 1700000000], ['' => 1700003600]],
            'duration' => ['duration', [], ['' => 3600], ['' => 7200]],
            'select' => ['select', $options, ['' => 2], ['' => 3]],
            'radiobutton' => ['radiobutton', $options, ['' => 2], ['' => 3]],
            'multiselect' => ['multiselect', $options, ['' => [1, 3]], ['' => [2]]],
            'checkbox' => ['checkbox', $options, ['' => [1, 3]], ['' => [2]]],
            'tag' => ['tag', [], ['' => ['red', 'blue']], ['' => ['green']]],
            'location' => ['location', [], ['_address' => 'Graz', '_lat' => '47.07', '_lng' => '15.44'],
                ['_address' => 'Wien', '_lat' => '48.2', '_lng' => '16.37']],
            // The course of the instance is set in the field; GROUP1/GROUP2 are groups of it.
            'course group, fixed course' => ['coursegroup', ['param1' => 'COURSE'], ['_groupid' => 'GROUP1'],
                ['_groupid' => 'GROUP2']],
        ];
    }

    /**
     * The same value saved again is no change; another value is.
     *
     * @dataProvider field_provider
     * @param string $type
     * @param array $params field settings
     * @param array $first
     * @param array $second
     */
    public function test_unchanged_value_is_no_change(string $type, array $params, array $first, array $second): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $dlx] = $this->create_mobile_instance();
        $placeholders = ['COURSE' => (string) $course->id];
        foreach (['GROUP1', 'GROUP2'] as $group) {
            $placeholders[$group] = (string) $this->getDataGenerator()->create_group(['courseid' => $course->id])->id;
        }
        $replace = fn(array $values): array => array_map(
            fn($value) => is_string($value) ? ($placeholders[$value] ?? $value) : $value,
            $values
        );
        [$params, $first, $second] = [$replace($params), $replace($first), $replace($second)];
        $fieldid = $this->add_mobile_field($dlx, $type, 'Value', $params);
        $this->viewid = $this->add_mobile_view($dlx, 'grid', 'Edit', '[[Value]] ##edit##', [
            'section' => '##addnewentry## ##entries##',
        ]);
        $this->dlx = new datalynx($dlx->id());

        $entryid = $this->save(-1, $fieldid, $first)[0];

        $this->assertSame([], $this->save($entryid, $fieldid, $first)[1], 'the same value again');
        $this->assertSame([(int) $fieldid], $this->save($entryid, $fieldid, $second)[1], 'another value');
        $this->assertSame([], $this->save($entryid, $fieldid, $second)[1], 'that value again');
    }

    /**
     * A file field saved again with the same files is no change; another file is.
     *
     * @covers \datalynxfield_file\field::update_content
     */
    public function test_unchanged_files_are_no_change(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        $fieldid = $this->add_mobile_field($dlx, 'file', 'Value', ['param2' => '-1']);
        $this->viewid = $this->add_mobile_view($dlx, 'grid', 'Edit', '[[Value]] ##edit##', [
            'section' => '##addnewentry## ##entries##',
        ]);
        $this->dlx = new datalynx($dlx->id());
        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);
        $upload = function (string $content) use ($fs, $usercontext): int {
            $draftid = file_get_unused_draft_itemid();
            $fs->create_file_from_string(['contextid' => $usercontext->id, 'component' => 'user', 'filearea' => 'draft',
                'itemid' => $draftid, 'filepath' => '/', 'filename' => 'report.txt'], $content);
            return $draftid;
        };

        $entryid = $this->save(-1, $fieldid, ['_filemanager' => $upload('First')])[0];
        $contentid = (int) $DB->get_field('datalynx_contents', 'id', ['fieldid' => $fieldid, 'entryid' => $entryid]);
        // The edit form prepares a draft area with the stored files.
        $prepared = function () use ($contentid): int {
            $draftid = 0;
            file_prepare_draft_area($draftid, $this->dlx->context->id, 'mod_datalynx', 'content', $contentid);
            return $draftid;
        };

        $this->assertSame([], $this->save($entryid, $fieldid, ['_filemanager' => $prepared()])[1], 'the same files again');
        $this->assertSame([$fieldid], $this->save($entryid, $fieldid, ['_filemanager' => $upload('Second')])[1], 'another file');
        $this->assertSame([], $this->save($entryid, $fieldid, ['_filemanager' => $prepared()])[1], 'that file again');
    }

    /**
     * Save a value through the submission service.
     *
     * @param int $entryid -1 for a new entry
     * @param int $fieldid
     * @param array $values element name suffix => value
     * @return array [entry id, changed field ids]
     */
    private function save(int $entryid, int $fieldid, array $values): array {
        $data = new \stdClass();
        foreach ($values as $suffix => $value) {
            $data->{"field_{$fieldid}_{$entryid}{$suffix}"} = $value;
        }
        $sink = $this->redirectEvents();
        $service = new entry_submission_service($this->dlx->get_view('grid', $this->viewid, false));
        $result = $service->save([$entryid], $data);
        $changed = [];
        foreach ($sink->get_events() as $event) {
            if ($event instanceof event\entry_updated) {
                $changed = array_merge($changed, $event->other['changed_field_ids'] ?? []);
            }
        }
        $sink->close();
        $this->assertSame([], $result['errors']);

        return [(int) reset($result['entryids']), array_values(array_map('intval', $changed))];
    }
}
