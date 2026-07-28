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

/**
 * Option labels are authored text, so {mlang ...} markup in them must be resolved before display.
 *
 * Regression tests: the labels of select, radiobutton, multiselect and checkbox fields went into
 * the filter and entry widgets straight from param1, so language markup showed up raw — most
 * visibly in the custom filter dropdowns of a view.
 *
 * @covers \mod_datalynx\local\field\datalynxfield_renderer::format_option_labels
 */
final class option_label_multilang_test extends advanced_testcase {
    /** @var datalynx The datalynx instance under test. */
    private datalynx $dlx;

    /** @var string Option list carrying language markup. */
    private const OPTIONS = "{mlang de}GERMANONE{mlang}{mlang other}ENGLISHONE{mlang}\n" .
        "{mlang de}GERMANTWO{mlang}{mlang other}ENGLISHTWO{mlang}";

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

        filter_set_global_state('multilang2', TEXTFILTER_ON);
        filter_set_applies_to_strings('multilang2', true);
        \filter_manager::reset_caches();
    }

    /**
     * Create a choice field whose options carry language markup.
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
            'param1' => self::OPTIONS,
            'param2' => '', 'param3' => '', 'param4' => '', 'param5' => '',
            'param6' => '', 'param7' => '', 'param8' => '', 'param9' => '', 'param10' => '',
        ]);

        // Force a re-read: get_fields() caches, and each test creates its fields one at a time.
        return $this->dlx->get_field_from_id($fieldid, true);
    }

    /**
     * Force the current language for the request.
     *
     * @param string $lang the language code to force, e.g. 'de' or 'en'.
     */
    private function set_current_language(string $lang): void {
        global $SESSION;
        $SESSION->forcelang = $lang;
        \filter_multilang2\text_filter::reset_parentcache();
        \filter_manager::reset_caches();
    }

    /**
     * Render a field in search mode and return the resulting HTML.
     *
     * @param string $type Field type.
     * @return string
     */
    private function render_search_html(string $type): string {
        $field = $this->make_field($type);
        $mform = new MoodleQuickForm('testfilter', 'post', 'index.php');
        [$elements] = $field->renderer()->render_search_mode($mform, 0, '');

        $html = '';
        foreach ($elements as $element) {
            $html .= $element->toHtml();
        }

        return $html;
    }

    /**
     * The filter dropdowns of every choice field type must show localised option labels.
     *
     * @return void
     */
    public function test_search_mode_option_labels_are_localised(): void {
        foreach (['select', 'radiobutton', 'multiselect', 'checkbox'] as $type) {
            $this->set_current_language('de');
            $html = $this->render_search_html($type);
            $this->assertStringNotContainsString('{mlang', $html, "raw mlang in $type search widget");
            $this->assertStringContainsString('GERMANONE', $html, "missing german label in $type search widget");
            $this->assertStringNotContainsString('ENGLISHONE', $html, "english label leaked into $type search widget");

            $this->set_current_language('en');
            $html = $this->render_search_html($type);
            $this->assertStringNotContainsString('{mlang', $html, "raw mlang in $type search widget");
            $this->assertStringContainsString('ENGLISHONE', $html, "missing english label in $type search widget");
            $this->assertStringNotContainsString('GERMANONE', $html, "german label leaked into $type search widget");
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

        foreach (['select', 'radiobutton', 'multiselect', 'checkbox'] as $type) {
            $field = $this->make_field($type);
            $entry = (object) ['id' => $entryid, "c{$field->id()}_content" => '1'];

            $this->set_current_language('de');
            $html = $field->renderer()->render_display_mode($entry, []);
            $this->assertStringNotContainsString('{mlang', $html, "raw mlang in $type display mode");
            $this->assertStringContainsString('GERMANONE', $html, "missing german label in $type display mode");

            $this->set_current_language('en');
            $html = $field->renderer()->render_display_mode($entry, []);
            $this->assertStringContainsString('ENGLISHONE', $html, "missing english label in $type display mode");
        }
    }
}
