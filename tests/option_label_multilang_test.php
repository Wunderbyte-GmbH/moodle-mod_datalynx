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
 * Tests that content filters are applied to the option labels of choice fields.
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
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/multilang_test_trait.php');

/**
 * Option labels are authored text, so filter markup in them must be resolved before display.
 *
 * Regression test: labels of select, radiobutton, multiselect and checkbox fields went into the
 * filter and entry widgets straight from param1, so the markup showed raw — most visibly in the
 * custom filter dropdowns of a view.
 *
 * @covers \mod_datalynx\local\field\datalynxfield_renderer::format_option_labels
 */
final class option_label_multilang_test extends advanced_testcase {
    use multilang_test_trait;

    /** @var datalynx The datalynx instance under test. */
    private datalynx $dlx;

    /** @var string[] The choice field types, all sharing one options implementation. */
    private const TYPES = ['select', 'radiobutton', 'multiselect', 'checkbox'];

    /**
     * Set up the fixture.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $this->dlx = new datalynx($instance->id);
        $this->enable_multilang_filter();
    }

    /**
     * Create a choice field whose first option carries multilang markup.
     *
     * @param string $type One of select, radiobutton, multiselect, checkbox.
     * @return \mod_datalynx\local\field\datalynxfield_base
     */
    private function make_field(string $type) {
        global $DB;

        $fieldid = (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $this->dlx->id(),
            'type' => $type,
            'name' => $type . 'field',
            'description' => '',
            'param1' => $this->multilang_markup() . "\nPlain second option",
            'param2' => '', 'param3' => '', 'param4' => '', 'param5' => '',
            'param6' => '', 'param7' => '', 'param8' => '', 'param9' => '', 'param10' => '',
        ]);

        // Force a re-read: get_fields() caches and each test creates its fields one at a time.
        return $this->dlx->get_field_from_id($fieldid, true);
    }

    /**
     * The filter dropdowns of every choice field type must show localised option labels.
     *
     * @return void
     */
    public function test_search_mode_option_labels_are_localised(): void {
        foreach (self::TYPES as $type) {
            $field = $this->make_field($type);

            foreach (['de', 'en'] as $lang) {
                $this->set_current_language($lang);
                $mform = new MoodleQuickForm('testfilter', 'post', 'index.php');
                [$elements] = $field->renderer()->render_search_mode($mform, 0, '');

                $html = '';
                foreach ($elements as $element) {
                    $html .= $element->toHtml();
                }

                $this->assert_localised($lang, $html, "$type search widget");
            }
        }
    }

    /**
     * The selected value shown on an entry must be localised, for single and multiple choice alike.
     *
     * @return void
     */
    public function test_display_mode_option_labels_are_localised(): void {
        global $DB, $USER;

        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $this->dlx->id(),
            'userid' => $USER->id,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        foreach (self::TYPES as $type) {
            $field = $this->make_field($type);
            $entry = (object) ['id' => $entryid, "c{$field->id()}_content" => '1'];

            foreach (['de', 'en'] as $lang) {
                $this->set_current_language($lang);
                $html = $field->renderer()->render_display_mode($entry, []);
                $this->assert_localised($lang, $html, "$type display mode");
            }
        }
    }
}
