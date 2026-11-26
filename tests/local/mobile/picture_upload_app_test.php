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

namespace mod_datalynx\local\mobile;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use advanced_testcase;
use mod_datalynx\datalynx;
use mod_datalynx\mobile_test_trait;
use mod_datalynx\output\mobile;

/**
 * A photo from the Moodle App (mod_datalynx#334): the app's attachments for picture and file
 * fields, uploaded to a draft area whose id the save sends.
 *
 * @package    mod_datalynx
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxfield_file\renderer::get_edit_control
 * @covers     \mod_datalynx\local\entry\entry_edit_form::submission
 */
final class picture_upload_app_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var datalynx */
    protected datalynx $dlx;

    /** @var \stdClass */
    protected \stdClass $course;

    /** @var int */
    protected int $photoid;

    /** @var int */
    protected int $viewid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        [$this->course, $this->dlx] = $this->create_mobile_instance();
        $this->photoid = $this->add_mobile_field($this->dlx, 'picture', 'Parkfoto', ['param1' => 10485760, 'param2' => 1,
            'param3' => 'image', 'param7' => 1600, 'param8' => 1600]);
        $everyone = [datalynx::PERMISSION_MANAGER, datalynx::PERMISSION_TEACHER, datalynx::PERMISSION_STUDENT,
            datalynx::PERMISSION_AUTHOR];
        $this->add_mobile_behavior($this->dlx, 'pflicht', ['required' => 1, 'editableby' => $everyone]);
        $this->viewid = $this->add_mobile_view(
            $this->dlx,
            'grid',
            'Foto',
            '[[Parkfoto|pflicht]] ##edit## ##submit##',
            ['param9' => 1]
        );
        $this->dlx = new datalynx($this->dlx->id());
        $this->setAdminUser();
    }

    /**
     * The page state of the form.
     *
     * @param int $entryid
     * @param array $args more arguments (a save)
     * @return array
     */
    protected function page(int $entryid, array $args = []): array {
        $result = mobile::mobile_edit_entry(array_map('strval', $args + ['cmid' => $this->dlx->cm->id,
            'courseid' => $this->course->id, 'viewid' => $this->viewid, 'entryid' => $entryid]));
        return json_decode($result['otherdata']['state'], true) ?? ['nextstep' => true];
    }

    /**
     * A draft area of the current user with one image, as the app's upload leaves it.
     *
     * @param int $width
     * @return int its item id
     */
    protected function draft(int $width): int {
        global $USER;
        $image = imagecreatetruecolor($width, (int) ($width * 2 / 3));
        ob_start();
        imagejpeg($image);
        $jpeg = ob_get_clean();
        $itemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string(['contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user', 'filearea' => 'draft', 'itemid' => $itemid, 'filepath' => '/',
            'filename' => 'parked.jpg'], $jpeg);
        return $itemid;
    }

    /**
     * The files control, the required check without a draft, and the photo saved with one.
     */
    public function test_photo_from_the_app(): void {
        global $DB;
        $state = $this->page(0);
        $this->assertTrue($state['hasfiles']);
        $this->assertSame([], $state['unsupportedfields']);
        $control = current(array_filter($state['fields'], fn(array $f): bool => (int) $f['fieldid'] === $this->photoid));
        $this->assertSame('files', $control['control']);
        $this->assertSame([1, 'image', []], [$control['maxfiles'], $control['accepted'], $control['files']]);
        $formname = $control['inputs'][0]['formname'];

        // Without a photo: the required field says so, nothing is saved.
        $state = $this->page(0, ['save' => 1]);
        $control = current(array_filter($state['fields'], fn(array $f): bool => (int) $f['fieldid'] === $this->photoid));
        $this->assertNotSame('', $control['error']);
        $this->assertSame(0, $DB->count_records('datalynx_entries', ['dataid' => $this->dlx->id()]));

        // With the draft the app uploaded to: saved, and scaled down like a photo from the web.
        $this->page(0, ['save' => 1, $formname => $this->draft(3000)]);
        $entryid = (int) $DB->get_field('datalynx_entries', 'id', ['dataid' => $this->dlx->id()], MUST_EXIST);
        $files = get_file_storage()->get_area_files($this->dlx->context->id, 'mod_datalynx', 'content', false, 'id', false);
        $this->assertCount(1, $files);
        $this->assertSame(1600, reset($files)->get_imageinfo()['width']);

        // Editing again lists the stored photo; saving without a new draft keeps it.
        $state = $this->page($entryid);
        $control = current(array_filter($state['fields'], fn(array $f): bool => (int) $f['fieldid'] === $this->photoid));
        $this->assertSame('parked.jpg', $control['files'][0]['filename']);
        $this->assertStringContainsString('/webservice/pluginfile.php/', $control['files'][0]['fileurl']);
        $this->page($entryid, ['save' => 1]);
        $this->assertCount(1, get_file_storage()->get_area_files(
            $this->dlx->context->id,
            'mod_datalynx',
            'content',
            false,
            'id',
            false
        ));
    }
}
