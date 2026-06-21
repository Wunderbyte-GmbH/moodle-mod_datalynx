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
use mod_datalynx\local\field\datalynxfield_layout;

/**
 * Tests for the dependent layout option (Verwende Anzeige-Vorlage wenn Inhalt vorhanden, ansonsten nichts anzeigen).
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_datalynx\local\field\datalynxfield_renderer::replacements
 */
final class field_layout_dependent_test extends advanced_testcase {
    /**
     * Test rendering when the "not editable" option is set to dependent layout (___5___).
     */
    public function test_dependent_layout_rendering(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        // Create a text field.
        $fieldrecord = (object) [
            'dataid' => $dlx->id(),
            'type' => 'text',
            'name' => 'MyField',
            'description' => '',
            'param1' => '',
            'param2' => '',
            'param3' => '',
            'param4' => '',
            'param5' => '',
            'param6' => '',
            'param7' => '',
            'param8' => '',
            'param9' => '',
            'param10' => '',
        ];
        $fieldrecord->id = (int) $DB->insert_record('datalynx_fields', $fieldrecord);

        // Create a custom renderer layout.
        $rendererid = (int) $DB->insert_record('datalynx_renderers', (object) [
            'dataid' => $dlx->id(),
            'type' => 'field',
            'name' => 'MyLayout',
            'description' => '',
            'displaytemplate' => 'Display: #value',
            'noteditabletemplate' => '___5___',
        ]);

        // Create a non-editable behavior.
        $behaviorid = (int) $DB->insert_record('datalynx_behaviors', (object) [
            'dataid' => $dlx->id(),
            'name' => 'MyBehavior',
            'description' => '',
            'visibleto' => serialize(['permissions' => [
                datalynx::PERMISSION_MANAGER,
                datalynx::PERMISSION_TEACHER,
                datalynx::PERMISSION_STUDENT,
                datalynx::PERMISSION_AUTHOR,
                datalynx::PERMISSION_GUEST,
            ]]),
            'editableby' => serialize([]), // Empty editableby array means nobody can edit.
            'required' => 0,
            'editableafterfinal' => 0,
            'conditions' => '',
        ]);

        // Get the field instance and its renderer.
        $field = $dlx->get_field_from_id($fieldrecord->id);
        $renderer = $field->renderer();

        // Create an entry where the field has no content.
        $entryempty = (object) [
            'id' => (int) $DB->insert_record('datalynx_entries', (object) [
                'dataid' => $dlx->id(),
                'userid' => $USER->id,
                'groupid' => 0,
                'approved' => 1,
                'status' => 0,
                'timecreated' => time(),
                'timemodified' => time(),
            ]),
        ];

        // Create an entry where the field has content.
        $entrywithval = (object) [
            'id' => (int) $DB->insert_record('datalynx_entries', (object) [
                'dataid' => $dlx->id(),
                'userid' => $USER->id,
                'groupid' => 0,
                'approved' => 1,
                'status' => 0,
                'timecreated' => time(),
                'timemodified' => time(),
            ]),
        ];
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldrecord->id,
            'entryid' => $entrywithval->id,
            'lineid' => 0,
            'content' => 'Hello World',
        ]);
        // Also populate entry content property.
        $entrywithval->{"c{$fieldrecord->id}_content"} = 'Hello World';

        // The tag format is Fieldname|Behaviorname|Renderername inside double brackets.
        $tag = '[[MyField|MyBehavior|MyLayout]]';

        // 1. Empty entry should render empty string.
        $replacementsempty = $renderer->replacements([$tag], $entryempty, ['edit' => true]);
        $this->assertArrayHasKey($tag, $replacementsempty);
        $this->assertEquals(['html', ''], $replacementsempty[$tag]);

        // 2. Entry with value should render display template (Display: Hello World).
        $replacementsval = $renderer->replacements([$tag], $entrywithval, ['edit' => true]);
        $this->assertArrayHasKey($tag, $replacementsval);
        $replacementspec = $replacementsval[$tag];

        $this->assertEquals('', $replacementspec[0]);
        $callback = $replacementspec[1][0];
        $args = $replacementspec[1][1];

        $this->assertEquals('prerender_edit_mode', $callback[1]);
        $renderedoptions = $args[1];
        $this->assertEquals('Hello World', $renderedoptions['value']);
        $this->assertEquals('Display: #value', $renderedoptions['template']);
    }
}
