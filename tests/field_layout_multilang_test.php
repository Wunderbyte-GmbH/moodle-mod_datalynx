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
 * Tests that field layout templates are run through the content filters.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/multilang_test_trait.php');

/**
 * Field layouts are inserted after the view template has been filtered, so filter markup in a
 * layout template (labels, help texts) used to show raw in both display and edit mode.
 *
 * @covers \mod_datalynx\local\field\datalynxfield_renderer::replacements
 * @covers \mod_datalynx\local\field\datalynxfield_renderer::filter_layout_template
 * @covers \datalynxfield_fieldgroup\renderer::render_edit_mode
 */
final class field_layout_multilang_test extends advanced_testcase {
    use multilang_test_trait;

    /** @var datalynx The datalynx instance under test. */
    private datalynx $dlx;

    /** @var \mod_datalynx\local\field\datalynxfield_base The text field under test. */
    private $field;

    /** @var \stdClass An entry with a value for the field. */
    private \stdClass $entry;

    /**
     * Set up a text field, an entry and a layout whose templates carry multilang markup.
     */
    public function setUp(): void {
        global $DB, $USER;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $this->dlx = new datalynx($instance->id);

        $fieldid = (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $this->dlx->id(), 'type' => 'text', 'name' => 'title', 'description' => '',
            'param1' => '', 'param2' => '', 'param3' => '', 'param4' => '', 'param5' => '',
            'param6' => '', 'param7' => '', 'param8' => '', 'param9' => '', 'param10' => '',
        ]);
        $this->field = $this->dlx->get_field_from_id($fieldid, true);

        $label = '<label>' . $this->multilang_markup() . '</label>';
        $DB->insert_record('datalynx_renderers', (object) [
            'dataid' => $this->dlx->id(),
            'type' => '',
            'name' => 'mllayout',
            'description' => '',
            'notvisibletemplate' => '___0___',
            'displaytemplate' => '<div>' . $label . '#value</div>',
            'novaluetemplate' => '<div>' . $label . '</div>',
            'edittemplate' => '<div>' . $label . '#input</div>',
            'noteditabletemplate' => '___1___',
        ]);

        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $this->dlx->id(), 'userid' => $USER->id, 'groupid' => 0, 'approved' => 1,
            'status' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->entry = (object) [
            'id' => $entryid, 'userid' => $USER->id, 'status' => 0,
            "c{$fieldid}_id" => 1, "c{$fieldid}_content" => 'Some value',
        ];

        $this->enable_multilang_filter();
    }

    /**
     * Display mode: the layout's label is localised and the value is still inserted.
     */
    public function test_display_template_is_filtered(): void {
        $tag = '[[title||mllayout]]';
        foreach (['de', 'en'] as $lang) {
            $this->set_current_language($lang);
            $replacements = $this->field->renderer()->replacements([$tag], $this->entry, ['edit' => false]);
            [$type, $html] = $replacements[$tag];

            $this->assertSame('html', $type);
            $this->assertStringContainsString('Some value', $html);
            $this->assert_localised($lang, $html, 'display template');
        }
    }

    /**
     * Display mode without a value: the no-value template is localised too.
     */
    public function test_novalue_template_is_filtered(): void {
        $entry = clone $this->entry;
        $entry->{"c{$this->field->id()}_content"} = '';
        $tag = '[[title||mllayout]]';
        foreach (['de', 'en'] as $lang) {
            $this->set_current_language($lang);
            [, $html] = $this->field->renderer()->replacements([$tag], $entry, ['edit' => false])[$tag];
            $this->assert_localised($lang, $html, 'no value template');
        }
    }

    /**
     * Edit mode: the template handed to prerender_edit_mode() is localised and keeps #input.
     */
    public function test_edit_template_is_filtered(): void {
        $tag = '[[title||mllayout]]';
        foreach (['de', 'en'] as $lang) {
            $this->set_current_language($lang);
            $replacements = $this->field->renderer()->replacements([$tag], $this->entry, ['edit' => true]);
            [, [$callback, [, $options]]] = $replacements[$tag];

            $this->assertSame('prerender_edit_mode', $callback[1]);
            $this->assertStringContainsString('#input', $options['template']);
            $this->assert_localised($lang, $options['template'], 'edit template');
        }
    }

    /**
     * Edit mode inside a fieldgroup: the subfield's layout is filtered too.
     */
    public function test_fieldgroup_subfield_edit_template_is_filtered(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/formslib.php');
        $groupid = (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $this->dlx->id(), 'type' => 'fieldgroup', 'name' => 'rows', 'description' => '',
            'param1' => json_encode([$this->field->id() . ':||mllayout']), 'param2' => '2', 'param3' => '1', 'param4' => '0',
        ]);
        $group = (new datalynx($this->dlx->id()))->get_field_from_id($groupid, true);

        foreach (['de', 'en'] as $lang) {
            $this->set_current_language($lang);
            $mform = new \MoodleQuickForm('fgtest' . $lang, 'post', 'index.php');
            $group->renderer()->render_edit_mode($mform, (object) ['id' => -1], ['edit' => true]);
            $this->assert_localised($lang, $mform->toHtml(), 'fieldgroup subfield edit template');
        }
    }
}
