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
 * Tests that saving an unchanged option value is no change.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;

/**
 * The input contract types option numbers as int, the stored content is a string. Comparing them
 * strictly reported every saved select or radio button as changed, so rules that only trigger when
 * that field changes ran again on every save of the entry.
 *
 * @covers \mod_datalynx\local\field\datalynxfield_base::update_content
 * @covers \mod_datalynx\local\datalynx_entries::process_entries
 */
final class entries_unchanged_option_test extends advanced_testcase {
    /**
     * Saving the stored option again reports no changed field; a different option does.
     */
    public function test_unchanged_option_is_no_change(): void {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);
        $fieldids = [];
        foreach (['select', 'radiobutton'] as $type) {
            $fieldids[$type] = (int) $DB->insert_record('datalynx_fields', (object) [
                'dataid' => $dlx->id(), 'type' => $type, 'name' => "f_$type", 'description' => '',
                'param1' => "One\nTwo\nThree",
            ]);
        }
        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(), 'userid' => $USER->id, 'groupid' => 0, 'approved' => 1,
            'status' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        foreach ($fieldids as $fieldid) {
            $DB->insert_record('datalynx_contents', (object) ['fieldid' => $fieldid, 'entryid' => $entryid, 'content' => '2']);
        }

        $save = function (int $value) use ($instance, $entryid, $fieldids): array {
            $data = new \stdClass();
            foreach ($fieldids as $fieldid) {
                // As the entry submission service passes it after cleaning by the input contract.
                $data->{"field_{$fieldid}_{$entryid}"} = $value;
            }
            $sink = $this->redirectEvents();
            $entries = new local\datalynx_entries(new datalynx($instance->id));
            $entries->process_entries('update', (string) $entryid, $data, true);
            $changed = [];
            foreach ($sink->get_events() as $event) {
                if ($event instanceof event\entry_updated) {
                    $changed = array_merge($changed, $event->other['changed_field_ids'] ?? []);
                }
            }
            $sink->close();
            sort($changed);
            return $changed;
        };

        $this->assertSame([], $save(2));
        $expected = array_values($fieldids);
        sort($expected);
        $this->assertSame($expected, $save(3));
        foreach ($fieldids as $fieldid) {
            $this->assertSame(
                ['3'],
                array_values($DB->get_fieldset_select(
                    'datalynx_contents',
                    'content',
                    'entryid = ? AND fieldid = ?',
                    [$entryid, $fieldid]
                ))
            );
        }
    }
}
