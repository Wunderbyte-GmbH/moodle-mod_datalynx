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
use backup;
use backup_controller;
use datalynxfield_identifier\field as identifier_field;
use restore_controller;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Tests for site-wide unique keys of the identifier field.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \datalynxfield_identifier\field
 * @covers \restore_datalynx_activity_structure_step
 */
final class identifier_field_test extends advanced_testcase {
    /**
     * Set up the fixture.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Create a datalynx instance with one field.
     *
     * @param stdClass $course The course to add the instance to.
     * @param string $type The field type.
     * @param array $params Extra param1..param10 values.
     * @return array [datalynx, field id]
     */
    private function create_datalynx_with_field(stdClass $course, string $type = 'identifier', array $params = []): array {
        global $DB;
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $record = (object) [
            'dataid' => $instance->id,
            'type' => $type,
            'name' => 'Field ' . $type,
            'description' => '',
            'required' => 0,
        ];
        for ($i = 1; $i <= 10; $i++) {
            $record->{"param$i"} = $params["param$i"] ?? '';
        }
        $fieldid = (int) $DB->insert_record('datalynx_fields', $record);
        return [new datalynx($instance->id), $fieldid];
    }

    /**
     * Create an entry and store a content for it.
     *
     * @param int $dataid The datalynx instance id.
     * @param int $fieldid The field id.
     * @param string $content The content to store.
     * @return int The content id.
     */
    private function create_entry_with_content(int $dataid, int $fieldid, string $content): int {
        global $DB, $USER;
        $entryid = $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dataid,
            'userid' => $USER->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        return (int) $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid,
            'entryid' => $entryid,
            'content' => $content,
        ]);
    }

    /**
     * Create an entry without contents.
     *
     * @param datalynx $dlx The datalynx instance.
     * @return stdClass The entry record.
     */
    private function create_entry(datalynx $dlx): stdClass {
        global $DB, $USER;
        $entry = (object) [
            'dataid' => $dlx->id(),
            'userid' => $USER->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $entry->id = $DB->insert_record('datalynx_entries', $entry);
        return $entry;
    }

    /**
     * Generate a key for an entry through the field's content update.
     *
     * @param datalynx $dlx The datalynx instance.
     * @param int $fieldid The identifier field id.
     * @param stdClass|null $entry The entry, a new one when omitted.
     * @return string The stored key.
     */
    private function generate_key(datalynx $dlx, int $fieldid, ?stdClass $entry = null): string {
        global $DB;
        $entry = $entry ?? $this->create_entry($dlx);
        $field = new identifier_field($dlx, $fieldid);
        $contentid = $field->update_content($entry, ['1']);
        return $DB->get_field('datalynx_contents', 'content', ['id' => $contentid], MUST_EXIST);
    }

    /**
     * key_exists() reports taken keys of identifier fields only.
     */
    public function test_key_exists(): void {
        $course = $this->getDataGenerator()->create_course();
        [$dlx, $identifierid] = $this->create_datalynx_with_field($course);
        [$textdlx, $textid] = $this->create_datalynx_with_field($course, 'text');

        $contentid = $this->create_entry_with_content($dlx->id(), $identifierid, 'takenkey');
        $this->create_entry_with_content($textdlx->id(), $textid, 'textkey');

        $this->assertTrue(identifier_field::key_exists('takenkey'));
        $this->assertFalse(identifier_field::key_exists('takenkey', $contentid));
        $this->assertFalse(identifier_field::key_exists('unusedkey'));
        $this->assertFalse(identifier_field::key_exists('textkey'));
    }

    /**
     * A generated key that is already taken on the site is replaced by an unused one.
     */
    public function test_generated_key_avoids_taken_keys(): void {
        global $CFG, $DB;
        // The system salt makes the first candidate predictable: md5(entryid_salt).
        $CFG->passwordsaltmain = 'testsalt';
        $course = $this->getDataGenerator()->create_course();
        [$dlx, $fieldid] = $this->create_datalynx_with_field($course, 'identifier', ['param1' => 'system']);
        [$otherdlx, $otherfieldid] = $this->create_datalynx_with_field($course);

        // Occupy the predictable key of the entry in another instance.
        $entry = $this->create_entry($dlx);
        $predictable = md5($entry->id . '_testsalt');
        $this->create_entry_with_content($otherdlx->id(), $otherfieldid, $predictable);

        $key = $this->generate_key($dlx, $fieldid, $entry);

        $this->assertNotSame($predictable, $key);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $key);
        $this->assertSame(1, $DB->count_records_select(
            'datalynx_contents',
            $DB->sql_compare_text('content') . ' = ' . $DB->sql_compare_text(':content'),
            ['content' => $key]
        ));
    }

    /**
     * An unused key from the configured salt is kept as is.
     */
    public function test_generated_key_uses_configured_salt(): void {
        global $CFG, $DB;
        $CFG->passwordsaltmain = 'testsalt';
        $course = $this->getDataGenerator()->create_course();
        [$dlx, $fieldid] = $this->create_datalynx_with_field($course, 'identifier', ['param1' => 'system']);

        $key = $this->generate_key($dlx, $fieldid);

        $entryid = $DB->get_field('datalynx_contents', 'entryid', ['fieldid' => $fieldid], MUST_EXIST);
        $this->assertSame(md5($entryid . '_testsalt'), $key);
    }

    /**
     * The system salt option is stored under its own key.
     */
    public function test_salt_options_include_system(): void {
        global $CFG;
        $CFG->passwordsaltmain = 'testsalt';
        $this->assertArrayHasKey('system', identifier_field::get_salt_options());
    }

    /**
     * replace_duplicate_keys() replaces only keys that are taken elsewhere.
     */
    public function test_replace_duplicate_keys(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        [$dlx, $fieldid] = $this->create_datalynx_with_field($course);
        [$copydlx, $copyfieldid] = $this->create_datalynx_with_field($course);

        $this->create_entry_with_content($dlx->id(), $fieldid, 'sharedkey');
        $duplicateid = $this->create_entry_with_content($copydlx->id(), $copyfieldid, 'sharedkey');
        $uniqueid = $this->create_entry_with_content($copydlx->id(), $copyfieldid, 'uniquekey');

        $this->assertSame(1, identifier_field::replace_duplicate_keys($copydlx->id()));

        $newkey = $DB->get_field('datalynx_contents', 'content', ['id' => $duplicateid]);
        $this->assertNotSame('sharedkey', $newkey);
        $this->assertFalse(identifier_field::key_exists($newkey, $duplicateid));
        $this->assertSame('uniquekey', $DB->get_field('datalynx_contents', 'content', ['id' => $uniqueid]));
        $this->assertSame(0, identifier_field::replace_duplicate_keys($dlx->id()));
    }

    /**
     * Restoring a backup on the same site gives the copied entries new keys.
     */
    public function test_restore_on_same_site_replaces_keys(): void {
        global $DB, $USER;
        $course = $this->getDataGenerator()->create_course();
        [$dlx, $fieldid] = $this->create_datalynx_with_field($course);
        $key = $this->generate_key($dlx, $fieldid);

        // Back up the activity with user data.
        $bc = new backup_controller(
            backup::TYPE_1ACTIVITY,
            $dlx->cm->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(true);
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();

        $backupid = 'identifier_restore_test';
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), make_backup_temp_directory($backupid));

        // Restore it into another course of the same site.
        $target = $this->getDataGenerator()->create_course();
        $rc = new restore_controller(
            $backupid,
            $target->id,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_EXISTING_ADDING
        );
        $rc->get_plan()->get_setting('users')->set_value(true);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $newdataid = $DB->get_field('datalynx', 'id', ['course' => $target->id], MUST_EXIST);
        $newfieldid = $DB->get_field('datalynx_fields', 'id', ['dataid' => $newdataid, 'type' => 'identifier'], MUST_EXIST);
        $restoredkey = $DB->get_field('datalynx_contents', 'content', ['fieldid' => $newfieldid], MUST_EXIST);

        $this->assertNotSame($key, $restoredkey);
        $this->assertSame($key, $DB->get_field('datalynx_contents', 'content', ['fieldid' => $fieldid]));
    }
}
