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
 * Tests for the inline user profile field editor of the entryauthor field.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use MoodleQuickForm;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * The inline profile editor (##author:<format>##) used to label its input with the profile
 * field's shortname, so next to the label of the view template a raw "street" appeared.
 *
 * @covers \datalynxfield_entryauthor\renderer::display_edit_profile
 */
final class entryauthor_profile_editor_test extends advanced_testcase {
    /**
     * The input has no visible label of its own but keeps the profile field name as aria-label.
     */
    public function test_profile_editor_input_has_no_shortname_label(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->getDataGenerator()->create_custom_profile_field([
            'shortname' => 'street', 'name' => 'Private address street', 'datatype' => 'text',
        ]);
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        // Use the manager API: it clears its per-instance cache, which a direct insert would
        // leave stale (instance ids repeat across tests).
        \mod_datalynx\local\field_format\manager::save_format((object) [
            'dataid' => $instance->id,
            'name' => 'Street',
            'fieldtype' => 'entryauthor',
            'settings' => json_encode(['option' => 'street', 'editable' => 1, 'mandatory' => 1]),
        ]);

        $dlx = new datalynx($instance->id);
        $field = null;
        foreach ($dlx->get_fields(null, false, true) as $candidate) {
            if ($candidate->type === 'entryauthor' && (string) $candidate->id() === 'Street') {
                $field = $candidate;
            }
        }
        $this->assertNotNull($field, 'profile editor pseudo field for the Street format');

        $mform = new MoodleQuickForm('profileeditor', 'post', 'index.php');
        $entry = (object) ['id' => -1, 'userid' => $USER->id];
        $field->renderer()->display_edit_profile($mform, $entry);
        $html = $mform->toHtml();

        $this->assertStringContainsString('aria-label="Private address street"', $html);
        $this->assertStringContainsString('datalynx-no-fitem-wrapper', $html);
        $this->assertDoesNotMatchRegularExpression('/<label[^>]*>\s*street\s*</', $html);
    }
}
