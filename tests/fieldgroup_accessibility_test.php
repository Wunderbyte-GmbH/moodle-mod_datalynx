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
 * Fieldgroup rows in edit mode are named for screen readers and the add button has a unique id (#299).
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \datalynxfield_fieldgroup\renderer::render_edit_mode
 * @covers \datalynxfield_fieldgroup\renderer::group_label
 * @covers \datalynxfield_fieldgroup\renderer::add_button_text
 */
final class fieldgroup_accessibility_test extends advanced_testcase {
    /** @var datalynx */
    private datalynx $dlx;

    /** @var int id of the text subfield */
    private int $subfieldid;

    /**
     * Create a datalynx instance with a text field to use in fieldgroups.
     */
    protected function setUp(): void {
        global $CFG, $DB;
        parent::setUp();
        require_once($CFG->libdir . '/formslib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $this->dlx = new datalynx($instance->id);
        $this->subfieldid = (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $this->dlx->id(), 'type' => 'text', 'name' => 'kost_pos', 'description' => '',
        ]);
    }

    /**
     * Add a fieldgroup with two rows and render it in edit mode.
     *
     * @param string $name field name
     * @param array $options render options (e.g. prefix)
     * @param string $addtext add button text (param5)
     * @return array [html, fieldgroup id]
     */
    private function render_group(string $name, array $options = [], string $addtext = ''): array {
        global $DB;
        $groupid = (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $this->dlx->id(), 'type' => 'fieldgroup', 'name' => $name, 'description' => '',
            'param1' => json_encode([(string) $this->subfieldid]), 'param2' => '2', 'param3' => '2', 'param4' => '0',
            'param5' => $addtext,
        ]);
        $group = (new datalynx($this->dlx->id()))->get_field_from_id($groupid, true);
        $mform = new \MoodleQuickForm('fga11y' . $groupid, 'post', 'index.php');
        $group->renderer()->render_edit_mode($mform, (object) ['id' => -1], $options + ['edit' => true]);
        return [$mform->toHtml(), $groupid];
    }

    /**
     * Rows and delete buttons are named after the layout's label and the row number.
     */
    public function test_rows_and_delete_buttons_are_named(): void {
        $prefix = '<div class="mb-4"><label class="fw-semibold">Financing plan</label>';
        [$html] = $this->render_group('Kosten', ['prefix' => $prefix]);

        $this->assertStringContainsString('role="group" aria-label="Financing plan, row 1"', $html);
        $this->assertStringContainsString('role="group" aria-label="Financing plan, row 2"', $html);
        $deletelabel = 'aria-label="' . get_string('delete') . ': Financing plan, row 2"';
        $this->assertMatchesRegularExpression('~<button[^>]+data-removeline="2"[^>]+' . $deletelabel . '~', $html);
        $this->assertMatchesRegularExpression('~class="fieldgroup-status[^"]*" role="status" aria-live="polite"~', $html);
    }

    /**
     * Without a label or heading in the layout, the field name names the group.
     */
    public function test_group_label_falls_back_to_field_name(): void {
        [$html] = $this->render_group('Receipt · Beleg', ['prefix' => '<div class="alert">No heading here</div>']);
        $this->assertStringContainsString('aria-label="Receipt · Beleg, row 1"', $html);

        [$html] = $this->render_group('Guests', ['prefix' => '<h4 class="h5">Guest lecturers &amp; speakers</h4>']);
        $this->assertStringContainsString('aria-label="Guest lecturers &amp; speakers, row 1"', $html);
    }

    /**
     * Two fieldgroups on one page get different add button ids; the button text is configurable.
     */
    public function test_add_button_id_is_unique_and_text_configurable(): void {
        [$html1, $id1] = $this->render_group('Kosten');
        [$html2, $id2] = $this->render_group('Belege', [], 'Add receipt');

        $this->assertStringContainsString('id="id_addline_' . $id1 . '"', $html1);
        $this->assertStringContainsString('id="id_addline_' . $id2 . '"', $html2);
        $this->assertStringNotContainsString('id="id_addline"', $html1 . $html2);
        $this->assertStringContainsString('>' . get_string('addline', 'datalynx', 'Kosten') . '</button>', $html1);
        $this->assertStringContainsString('>Add receipt</button>', $html2);
    }

    /**
     * A subfield whose own layout labels its input keeps that label; the fieldgroup's label is not put on top.
     */
    public function test_subfield_keeps_its_own_layout_label(): void {
        global $DB;
        $DB->insert_record('datalynx_renderers', (object) [
            'dataid' => $this->dlx->id(), 'type' => '', 'name' => 'r_pos', 'description' => '',
            'notvisibletemplate' => '___0___', 'displaytemplate' => '#value', 'novaluetemplate' => '___0___',
            'edittemplate' => '<label>Item · Position</label>#input', 'noteditabletemplate' => '___1___',
        ]);
        $groupid = (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $this->dlx->id(), 'type' => 'fieldgroup', 'name' => 'Kosten', 'description' => '',
            'param1' => json_encode([$this->subfieldid . ':||r_pos']), 'param2' => '2', 'param3' => '2', 'param4' => '0',
        ]);
        $group = (new datalynx($this->dlx->id()))->get_field_from_id($groupid, true);
        $mform = new \MoodleQuickForm('fgnested', 'post', 'index.php');
        $group->renderer()->prerender_edit_mode($mform, (object) ['id' => 7], [
            'editable' => true,
            'template' => '<div><label>Financing plan</label>#input</div>',
        ]);
        $html = $mform->toHtml();

        $this->assertSame(2, substr_count($html, '>Item · Position</label>'));
        $inputid = 'id_field_' . $this->subfieldid . '_7_fieldgroup_' . $groupid;
        $this->assertSame(2, preg_match_all('~<label for="' . $inputid . '_[01]">Item~', $html));
        $this->assertStringNotContainsString('aria-label="Financing plan"', $html);
        $this->assertStringContainsString('<label>Financing plan</label>', $html);
    }
}
