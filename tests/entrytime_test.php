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

/**
 * Tests for entrytime field renderer, formats, and timesubmitted database field.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;


use advanced_testcase;
use datalynxfield_status\field as datalynxfield_status;
use mod_datalynx\local\datalynx_entries;
use stdClass;

/**
 * Class entrytime_test
 *
 * @package    mod_datalynx
 * @covers     \mod_datalynx\local\datalynx_entries
 * @covers     \datalynxfield_entrytime\renderer
 * @covers     \datalynxfield_entrytime\field
 */
final class entrytime_test extends advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Test timesubmitted default of null when inserting a new entry in draft status.
     */
    public function test_timesubmitted_defaults_to_null_on_insert(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);
        $entriesclass = new datalynx_entries($dlx);

        $entry = new stdClass();
        $entry->id = 0;
        $entry->status = datalynxfield_status::STATUS_DRAFT;

        $entryid = $entriesclass->update_entry($entry);
        $this->assertNotEmpty($entryid);

        $record = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        $this->assertNull($record->timesubmitted);
    }

    /**
     * Test timesubmitted stamps with current time when inserting directly in final status.
     */
    public function test_timesubmitted_stamps_on_insert_final(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);
        $entriesclass = new datalynx_entries($dlx);

        $entry = new stdClass();
        $entry->id = 0;
        $entry->status = datalynxfield_status::STATUS_FINAL_SUBMISSION;

        $entryid = $entriesclass->update_entry($entry);
        $this->assertNotEmpty($entryid);

        $record = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        $this->assertNotNull($record->timesubmitted);
        $this->assertLessThanOrEqual(time(), $record->timesubmitted);
        $this->assertGreaterThan(time() - 60, $record->timesubmitted);
    }

    /**
     * Test timesubmitted stamps with current time when updating draft entry to final status.
     */
    public function test_timesubmitted_stamps_on_update_to_final(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);
        $entriesclass = new datalynx_entries($dlx);

        // Insert draft entry.
        $entry = new stdClass();
        $entry->id = 0;
        $entry->status = datalynxfield_status::STATUS_DRAFT;
        $entryid = $entriesclass->update_entry($entry);

        $record = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        $this->assertNull($record->timesubmitted);

        // Update to final.
        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        $entry->status = datalynxfield_status::STATUS_FINAL_SUBMISSION;
        $entriesclass->update_entry($entry);

        $record = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        $this->assertNotNull($record->timesubmitted);
        $this->assertLessThanOrEqual(time(), $record->timesubmitted);
        $this->assertGreaterThan(time() - 60, $record->timesubmitted);
    }

    /**
     * Test timesubmitted is preserved on update (never overwritten/reset once stamped).
     */
    public function test_timesubmitted_preserved_on_update(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);
        $entriesclass = new datalynx_entries($dlx);

        // Insert final entry.
        $entry = new stdClass();
        $entry->id = 0;
        $entry->status = datalynxfield_status::STATUS_FINAL_SUBMISSION;
        $entryid = $entriesclass->update_entry($entry);

        $record = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        $firsttimestamp = $record->timesubmitted;
        $this->assertNotNull($firsttimestamp);

        // Set manually back in time in DB, and assert it is preserved on update.
        $DB->set_field('datalynx_entries', 'timesubmitted', 123456789, ['id' => $entryid]);

        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        $entry->status = datalynxfield_status::STATUS_FINAL_SUBMISSION; // Keep final.
        $entriesclass->update_entry($entry);

        $record = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        $this->assertEquals(123456789, $record->timesubmitted);

        // Even if status changes to draft, we preserve the original submission date.
        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        $entry->status = datalynxfield_status::STATUS_DRAFT;
        $entriesclass->update_entry($entry);

        $record = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        $this->assertEquals(123456789, $record->timesubmitted);
    }

    /**
     * Test entrytime rendering when the timestamp is null or empty.
     */
    public function test_entrytime_rendering_empty(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        // Create the entrytime field object for timesubmitted.
        $fieldobjects = \datalynxfield_entrytime\field::get_field_objects($dlx->id());
        $timesubmittedfield = $fieldobjects['timesubmitted'];
        $timesubmittedfield->id = 'timesubmitted';
        $field = new \datalynxfield_entrytime\field($dlx, $timesubmittedfield);
        $renderer = new \datalynxfield_entrytime\renderer($field);

        // Create entry with null timesubmitted.
        $entry = (object) [
            'id' => 1,
            'timesubmitted' => null,
        ];

        $replacements = $renderer->replacements(['##timesubmitted##', '##timesubmitted:date##'], $entry);
        $this->assertEquals('', $replacements['##timesubmitted##']);
        $this->assertEquals('', $replacements['##timesubmitted:date##']);
    }

    /**
     * Test entrytime rendering when formatted with custom formats and checking legacy modifiers.
     */
    public function test_entrytime_rendering_formatted(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $fieldobjects = \datalynxfield_entrytime\field::get_field_objects($dlx->id());
        $timesubmittedfield = $fieldobjects['timesubmitted'];
        $timesubmittedfield->id = 'timesubmitted';
        $field = new \datalynxfield_entrytime\field($dlx, $timesubmittedfield);
        $renderer = new \datalynxfield_entrytime\renderer($field);

        $timestamp = 1719500000; // 2024-06-27 14:53:20 UTC
        $entry = (object) [
            'id' => 1,
            'timesubmitted' => $timestamp,
        ];

        // 1. Render default (no suffix) should output Moodle default date format.
        $replacements = $renderer->replacements(['##timesubmitted##'], $entry);
        $this->assertEquals(userdate($timestamp), $replacements['##timesubmitted##'][1]);

        // 2. Render legacy suffix (e.g. :date) - should render as empty string since legacy modifiers are removed.
        $replacements = $renderer->replacements(['##timesubmitted:date##'], $entry);
        $this->assertEquals('', $replacements['##timesubmitted:date##']);

        // 3. Render user-defined format.
        // Define a field format via datalynx_field_formats table.
        $formatrecord = (object) [
            'dataid' => $dlx->id(),
            'fieldtype' => 'entrytime',
            'name' => 'customdate',
            'settings' => json_encode(['dateformat' => '%Y-%m-%d']),
        ];
        $DB->insert_record('datalynx_field_formats', $formatrecord);

        $replacements = $renderer->replacements(['##timesubmitted:customdate##'], $entry);
        $this->assertEquals(userdate($timestamp, '%Y-%m-%d'), $replacements['##timesubmitted:customdate##'][1]);

        // 4. Render user-defined format with timestamp.
        $formatrecordts = (object) [
            'dataid' => $dlx->id(),
            'fieldtype' => 'entrytime',
            'name' => 'rawts',
            'settings' => json_encode(['dateformat' => 'timestamp']),
        ];
        $DB->insert_record('datalynx_field_formats', $formatrecordts);

        $replacements = $renderer->replacements(['##timesubmitted:rawts##'], $entry);
        $this->assertEquals((string)$timestamp, $replacements['##timesubmitted:rawts##'][1]);
    }

    /**
     * Test entrytime patterns only return base field and active field formats, with no legacy modifiers.
     */
    public function test_entrytime_patterns(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $fieldobjects = \datalynxfield_entrytime\field::get_field_objects($dlx->id());
        $timesubmittedfield = $fieldobjects['timesubmitted'];
        $timesubmittedfield->id = 'timesubmitted';
        $field = new \datalynxfield_entrytime\field($dlx, $timesubmittedfield);
        $renderer = new \datalynxfield_entrytime\renderer($field);

        $method = new \ReflectionMethod($renderer, 'patterns');
        $method->setAccessible(true);

        // 1. Without any formats, patterns should only list ##timesubmitted## (no legacy modifiers).
        $patterns = $method->invoke($renderer);
        $this->assertArrayHasKey('##timesubmitted##', $patterns);
        $this->assertCount(1, $patterns);

        // 2. With a format, patterns should list ##timesubmitted## and ##timesubmitted:customformat##.
        $formatrecord = (object) [
            'dataid' => $dlx->id(),
            'fieldtype' => 'entrytime',
            'name' => 'customformat',
            'settings' => json_encode(['dateformat' => '%Y']),
        ];
        $DB->insert_record('datalynx_field_formats', $formatrecord);

        // Reset the manager instance cache so the new database format is loaded.
        $rc = new \ReflectionClass(\mod_datalynx\local\field_format\manager::class);
        $property = $rc->getProperty('instancecache');
        $property->setAccessible(true);
        $property->setValue(null, []);

        $patterns = $method->invoke($renderer);
        $this->assertArrayHasKey('##timesubmitted##', $patterns);
        $this->assertArrayHasKey('##timesubmitted:customformat##', $patterns);
        $this->assertCount(2, $patterns);
    }
}
