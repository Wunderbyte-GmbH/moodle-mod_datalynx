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

namespace mod_datalynx;

use advanced_testcase;

/**
 * Form elements rendered in edit mode always get an accessible name (#298).
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_datalynx\local\field\datalynxfield_renderer::prerender_edit_mode
 * @covers \mod_datalynx\local\field\datalynxfield_renderer::name_edit_elements
 * @covers \mod_datalynx\local\field\datalynxfield_renderer::element_needs_name
 */
final class edit_element_accessible_name_test extends advanced_testcase {
    /** @var datalynx */
    private datalynx $dlx;

    /**
     * Create a datalynx instance.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->libdir . '/formslib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $this->dlx = new datalynx($instance->id);
    }

    /**
     * Add a field of the given type and return it.
     *
     * @param string $type field type
     * @param string $name field name
     * @param string $description field description
     * @param array $params field params, e.g. ['param1' => options for select and checkbox]
     * @return mixed the field object
     */
    private function add_field(string $type, string $name, string $description = '', array $params = []) {
        global $DB;
        $id = (int) $DB->insert_record('datalynx_fields', (object) ([
            'dataid' => $this->dlx->id(), 'type' => $type, 'name' => $name, 'description' => $description,
        ] + $params));
        return $this->dlx->get_field_from_id($id, true);
    }

    /**
     * Render one field in edit mode with the given layout template and return the form html.
     *
     * @param mixed $field the field
     * @param string|null $template edit template, null for none
     * @return string
     */
    private function render_field($field, ?string $template): string {
        $mform = new \MoodleQuickForm('a11ytest', 'post', 'index.php');
        $options = ['editable' => true];
        if ($template !== null) {
            $options['template'] = $template;
        }
        $field->renderer()->prerender_edit_mode($mform, (object) ['id' => 7], $options);
        return $mform->toHtml();
    }

    /**
     * The first <label> of a custom layout is attached to the input with for.
     */
    public function test_layout_label_is_attached_to_input(): void {
        $field = $this->add_field('text', 'antrag_zweck');
        $id = 'id_field_' . $field->id() . '_7';
        $html = $this->render_field($field, '<label class="form-label fw-semibold">Purpose · Zweck</label>'
            . '<div class="form-text">Hint</div>#input');

        $this->assertStringContainsString('<label for="' . $id . '" class="form-label fw-semibold">Purpose · Zweck</label>', $html);
        $this->assertMatchesRegularExpression('~<input[^>]+id="' . $id . '"~', $html);
        $this->assertStringNotContainsString('aria-label=', $html);
    }

    /**
     * A layout label that already has for= is left alone; the input then gets the label text as aria-label.
     */
    public function test_existing_for_is_kept(): void {
        $field = $this->add_field('text', 'tel');
        $html = $this->render_field($field, '<label for="custom">Phone · Telefon</label>#input');

        $this->assertStringContainsString('<label for="custom">Phone · Telefon</label>', $html);
        $this->assertMatchesRegularExpression('~<input[^>]+aria-label="Phone · Telefon"~', $html);
    }

    /**
     * Without a layout label the select gets the field description, or the field name, as aria-label.
     */
    public function test_fallback_to_description_and_name(): void {
        $select = $this->add_field('select', 'sektion', 'DocSchool section', ['param1' => "A\nB"]);
        $html = $this->render_field($select, '<div class="mb-3">#input</div>');
        $this->assertMatchesRegularExpression('~<select[^>]+aria-label="DocSchool section"~', $html);

        $text = $this->add_field('text', 'pd_telefon');
        $html = $this->render_field($text, null);
        $this->assertMatchesRegularExpression('~<input[^>]+aria-label="pd_telefon"~', $html);
    }

    /**
     * Elements that name themselves (checkbox options) are not touched.
     */
    public function test_checkbox_options_keep_their_text(): void {
        $field = $this->add_field('checkbox', 'confirm', '', ['param1' => 'I confirm the information.']);
        $html = $this->render_field($field, '<label>Confirmation</label>#input');

        $this->assertStringContainsString('I confirm the information.', $html);
        $this->assertStringNotContainsString('aria-label="Confirmation"', $html);
        $this->assertStringNotContainsString('<label for=', substr($html, 0, strpos($html, 'Confirmation') + 20));
    }

    /**
     * The url and link text inputs of a url field sit in a group; both are named, the second with its placeholder.
     */
    public function test_group_parts_are_named(): void {
        $field = $this->add_field('url', 'bericht_link', '', ['param5' => 1]);
        $prefix = 'field_' . $field->id() . '_7';
        $html = $this->render_field($field, '<label>Report link · Link zum Bericht</label>#input');

        $this->assertStringContainsString('<label for="id_' . $prefix . '_url">Report link · Link zum Bericht</label>', $html);
        $linktext = get_string('linktext', 'datalynxfield_url');
        $arialabel = 'aria-label="Report link · Link zum Bericht: ' . preg_quote($linktext) . '"';
        $this->assertMatchesRegularExpression('~<input[^>]+name="' . $prefix . '_alt"[^>]+' . $arialabel . '~', $html);
    }

    /**
     * The masked day, month and year menus of a time field are told apart by their mask option.
     */
    public function test_masked_time_menus_get_their_mask_as_hint(): void {
        $field = $this->add_field('time', 'reisedatum', '', ['param1' => 1, 'param5' => 1]);
        $html = $this->render_field($field, '<label>Travel date · Reisedatum</label>#input');

        $this->assertMatchesRegularExpression('~<label for="id_field_' . $field->id() . '_7_day"~', $html);
        $this->assertStringContainsString('aria-label="Travel date · Reisedatum: ' . get_string('month') . '"', $html);
        $this->assertStringContainsString('aria-label="Travel date · Reisedatum: ' . get_string('year') . '"', $html);
    }

    /**
     * The parts of a standard date selector have their own labels and stay untouched.
     */
    public function test_date_selector_parts_are_untouched(): void {
        $field = $this->add_field('time', 'termin', '', ['param1' => 1]);
        $html = $this->render_field($field, '<label>Date · Datum</label>#input');

        $this->assertStringNotContainsString('aria-label="Date · Datum', $html);
        $this->assertStringNotContainsString('<label for=', substr($html, 0, strpos($html, 'Date · Datum') + 20));
    }
}
