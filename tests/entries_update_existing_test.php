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
 * Tests updating existing entries that are not loaded by a view.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;

/**
 * process_entries('update') for an existing entry that was not loaded through set_content() (API,
 * scripts, imports) passed the ids as an array to the filter, and get_entries() exploded it.
 *
 * @covers \mod_datalynx\local\datalynx_entries::process_entries
 */
final class entries_update_existing_test extends advanced_testcase {
    /**
     * Updating an existing entry without a loaded view stores the new content.
     */
    public function test_update_existing_entry_without_loaded_view(): void {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);
        $fieldid = (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $dlx->id(), 'type' => 'text', 'name' => 'title', 'description' => '',
        ]);
        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(), 'userid' => $USER->id, 'groupid' => 0, 'approved' => 1,
            'status' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);

        $entries = new local\datalynx_entries(new datalynx($instance->id));
        $data = (object) ["field_{$fieldid}_{$entryid}" => 'Updated title'];
        $entries->process_entries('update', (string) $entryid, $data, true);

        $this->assertSame(
            'Updated title',
            $DB->get_field('datalynx_contents', 'content', ['entryid' => $entryid, 'fieldid' => $fieldid])
        );
    }
}
