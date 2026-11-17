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

namespace mod_datalynx\local\mobile;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use core_external\external_api;
use externallib_advanced_testcase;
use mod_datalynx\datalynx;
use mod_datalynx\external\toggle_entry_approval;
use mod_datalynx\mobile_test_trait;
use moodle_exception;

/**
 * Files, pictures and approval of entries in the Moodle App (mod_datalynx#274).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\mobile\entries_payload_builder
 * @covers     \mod_datalynx\external\toggle_entry_approval
 * @covers     \mod_datalynx\datalynx::user_can_approve_entries
 */
final class entry_files_and_approval_test extends externallib_advanced_testcase {
    use mobile_test_trait;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Store one file as the content of a file or picture field of an entry.
     *
     * @param datalynx $dlx
     * @param int $entryid
     * @param int $fieldid
     * @param string $filename
     * @param string $content
     * @param string $alt the link or alt text (content1)
     */
    private function add_file(datalynx $dlx, int $entryid, int $fieldid, string $filename, string $content, string $alt): void {
        global $DB;

        $contentid = (int) $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid, 'entryid' => $entryid, 'content' => 1, 'content1' => $alt,
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => $dlx->context->id, 'component' => 'mod_datalynx', 'filearea' => 'content',
            'itemid' => $contentid, 'filepath' => '/', 'filename' => $filename,
        ], $content);
    }

    /**
     * A file and a picture reach the app as absolute pluginfile URLs of the activity, which the
     * app's core-format-text fetches with the user's token. Nothing else is needed for the app to
     * show them: no list of files, no URL the app could not authenticate.
     */
    public function test_files_and_pictures_are_pluginfile_urls(): void {
        global $CFG;

        [$course, $dlx] = $this->create_mobile_instance();
        $docid = $this->add_mobile_field($dlx, 'file', 'Document');
        $photoid = $this->add_mobile_field($dlx, 'picture', 'Photo');
        $viewid = $this->add_mobile_view($dlx, 'grid', 'Cards', '<div>[[Document]] [[Photo]]</div>');
        $student = $this->create_mobile_student($course);
        $entryid = $this->add_mobile_entry($dlx, $student->id, []);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==');
        $this->add_file($dlx, $entryid, $docid, 'report.pdf', '%PDF-1.4', 'The report');
        $this->add_file($dlx, $entryid, $photoid, 'apple.png', $png, 'A red apple');

        $this->setUser($student);
        $payload = (new entries_payload_builder(new datalynx($dlx->id())))->build(['viewid' => $viewid]);

        $html = implode('', array_column($payload['entries'][0]['parts'], 'html'));
        $base = $CFG->wwwroot . '/pluginfile.php/' . $dlx->context->id . '/mod_datalynx/content/';
        $this->assertMatchesRegularExpression('~href="' . preg_quote($base, '~') . '\d+/report\.pdf~', $html);
        $this->assertMatchesRegularExpression('~<img[^>]+src="' . preg_quote($base, '~') . '\d+/apple\.png~', $html);
        $this->assertStringContainsString('alt="A red apple"', $html);
        $this->assertStringNotContainsString('@@PLUGINFILE@@', $html);
    }

    /**
     * The approve button is offered to those who may approve, where the view shows ##approve##
     * and approval is on: the same places the web shows the switch.
     */
    public function test_canapprove(): void {
        global $DB;

        [$course, $dlx] = $this->create_mobile_instance(['approval' => 1]);
        $titleid = $this->add_mobile_field($dlx, 'text', 'Title');
        $withtag = $this->add_mobile_view($dlx, 'grid', 'Review', '[[Title]] ##approve##');
        $without = $this->add_mobile_view($dlx, 'grid', 'Plain', '[[Title]]');
        $student = $this->create_mobile_student($course);
        $this->add_mobile_entry($dlx, $student->id, [$titleid => 'Apple']);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $canapprove = function (int $viewid) use ($dlx): bool {
            $payload = (new entries_payload_builder(new datalynx($dlx->id())))->build(['viewid' => $viewid]);
            return $payload['entries'][0]['canapprove'];
        };

        $this->setUser($teacher);
        $this->assertTrue($canapprove($withtag));
        $this->assertFalse($canapprove($without));

        $this->setUser($student);
        $this->assertFalse($canapprove($withtag));

        $DB->set_field('datalynx', 'approval', 0, ['id' => $dlx->id()]);
        $this->setUser($teacher);
        $this->assertFalse($canapprove($withtag));
    }

    /**
     * The service approves and disapproves for those who may, through a view they can see, and
     * refuses everyone else, also when approval is off.
     */
    public function test_service_approves_and_refuses(): void {
        global $DB;

        [$course, $dlx] = $this->create_mobile_instance(['approval' => 1]);
        $titleid = $this->add_mobile_field($dlx, 'text', 'Title');
        $viewid = $this->add_mobile_view($dlx, 'grid', 'Review', '[[Title]] ##approve##');
        $student = $this->create_mobile_student($course);
        $entryid = $this->add_mobile_entry($dlx, $student->id, [$titleid => 'Apple']);
        $DB->set_field('datalynx_entries', 'approved', 0, ['id' => $entryid]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($teacher);
        $result = external_api::clean_returnvalue(
            toggle_entry_approval::execute_returns(),
            toggle_entry_approval::execute($dlx->id(), $viewid, $entryid, 'approve')
        );
        $this->assertTrue($result['approved']);
        $this->assertEquals(1, $DB->get_field('datalynx_entries', 'approved', ['id' => $entryid]));
        toggle_entry_approval::execute($dlx->id(), $viewid, $entryid, 'disapprove');
        $this->assertEquals(0, $DB->get_field('datalynx_entries', 'approved', ['id' => $entryid]));

        // Not an entry of this instance.
        [, $other] = $this->create_mobile_instance(['approval' => 1]);
        $foreign = $this->add_mobile_entry($other, $student->id, []);
        try {
            toggle_entry_approval::execute($dlx->id(), $viewid, $foreign, 'approve');
            $this->fail('An entry of another instance was approved.');
        } catch (\invalid_parameter_exception $e) {
            $this->assertEquals(1, $DB->get_field('datalynx_entries', 'approved', ['id' => $foreign]));
        }

        $this->setUser($student);
        try {
            toggle_entry_approval::execute($dlx->id(), $viewid, $entryid, 'approve');
            $this->fail('A student approved an entry.');
        } catch (moodle_exception $e) {
            $this->assertEquals(0, $DB->get_field('datalynx_entries', 'approved', ['id' => $entryid]));
        }

        $DB->set_field('datalynx', 'approval', 0, ['id' => $dlx->id()]);
        $this->setUser($teacher);
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('entryapprovenotallowed', 'datalynx'));
        toggle_entry_approval::execute($dlx->id(), $viewid, $entryid, 'approve');
    }

    /**
     * The function is in the Moodle App's service.
     */
    public function test_function_is_in_the_mobile_service(): void {
        global $DB;

        $this->assertTrue($DB->record_exists_sql(
            "SELECT 1
               FROM {external_services_functions} sf
               JOIN {external_services} s ON s.id = sf.externalserviceid
              WHERE s.shortname = :service AND sf.functionname = :function",
            ['service' => MOODLE_OFFICIAL_MOBILE_SERVICE, 'function' => 'mod_datalynx_toggle_entry_approval']
        ));
    }
}
