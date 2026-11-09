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
 * Tests for the input handling of the URL field.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use datalynxfield_url\field as url_field;

/**
 * The URL input is no longer prefilled with "http://"; input without a scheme gets "https://".
 *
 * @covers \datalynxfield_url\field::normalise_url
 * @covers \datalynxfield_url\field::is_fieldvalue_empty
 * @covers \datalynxfield_url\renderer::validate
 * @covers \datalynxfield_url\renderer::render_edit_mode
 */
final class url_field_input_test extends advanced_testcase {
    /**
     * Normalisation of typical inputs.
     */
    public function test_normalise_url(): void {
        $this->assertSame('', url_field::normalise_url(''));
        $this->assertSame('', url_field::normalise_url('  http://  '));
        $this->assertSame('', url_field::normalise_url('https://'));
        $this->assertSame('https://ustream.univie.ac.at/video/1', url_field::normalise_url('ustream.univie.ac.at/video/1'));
        $this->assertSame('http://example.org', url_field::normalise_url('http://example.org'));
        $this->assertSame('mailto:a@example.org', url_field::normalise_url('mailto:a@example.org'));
        $this->assertTrue(url_field::is_fieldvalue_empty('http://'));
        $this->assertFalse(url_field::is_fieldvalue_empty('example.org'));
    }

    /**
     * Edit mode shows an empty input with a placeholder; validation accepts scheme-less input.
     */
    public function test_edit_mode_and_validation(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/formslib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $fieldid = (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $instance->id, 'type' => 'url', 'name' => 'Link', 'description' => '',
        ]);
        $field = (new datalynx($instance->id))->get_field_from_id($fieldid, true);

        $mform = new \MoodleQuickForm('urltest', 'post', 'index.php');
        $field->renderer()->render_edit_mode($mform, (object) ['id' => -1], []);
        $html = $mform->toHtml();
        $this->assertStringNotContainsString('value="http://"', $html);
        $this->assertStringContainsString('placeholder="https://"', $html);

        $name = "field_{$fieldid}_-1_url";
        $grp = "field_{$fieldid}_-1_grp";
        $validate = fn(string $value) => $field->renderer()->validate(-1, ['[[Link]]'], (object) [$name => $value]);
        $this->assertSame([], $validate('example.org/page'));
        $this->assertSame([], $validate(''));
        $this->assertArrayHasKey($grp, $validate('not a url'));
    }
}
