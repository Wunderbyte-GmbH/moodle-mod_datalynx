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
use mod_datalynx\local\view\datalynxview_entries_form;
use moodle_url;
use ReflectionClass;

/**
 * Tests for entry form field scoping and validation.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_datalynx\local\view\base::get_entry_form_patterns
 * @covers \mod_datalynx\local\view\base::get_entry_form_fields
 * @covers \mod_datalynx\local\view\datalynxview_entries_form::validation
 * @covers \datalynxfield_userinfo\renderer::validate
 */
final class entry_form_validation_test extends advanced_testcase {
    /**
     * Build a grid fixture with a text field in the new-entry template and a userinfo field elsewhere in the view.
     *
     * @return array
     */
    private function create_grid_fixture(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $view = (object) [
            'dataid' => $dlx->id(),
            'type' => 'grid',
            'name' => 'Validation Grid',
            'description' => '',
            'visible' => 7,
            'filter' => 0,
            'perpage' => 0,
            'groupby' => '',
            'param5' => 0,
            'param10' => 0,
            'section' => '##author:Driver##',
            'param2' => '<div>[[Title]]</div>',
        ];
        $view->id = (int) $DB->insert_record('datalynx_views', $view);

        $textfield = (object) [
            'dataid' => $dlx->id(),
            'type' => 'text',
            'name' => 'Title',
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
        $textfield->id = (int) $DB->insert_record('datalynx_fields', $textfield);

        $userinfofield = (object) [
            'dataid' => $dlx->id(),
            'type' => 'userinfo',
            'name' => 'Driver',
            'description' => '',
            'param1' => 0,
            'param2' => 'driverinfo',
            'param3' => 'text',
            'param4' => '',
            'param5' => '',
            'param6' => 1,
            'param7' => 1,
            'param8' => '',
            'param9' => '',
            'param10' => '',
        ];
        $userinfofield->id = (int) $DB->insert_record('datalynx_fields', $userinfofield);

        return [$dlx, $view, $textfield, $userinfofield];
    }

    /**
     * Prepare a view object for new-entry form rendering.
     *
     * @param \mod_datalynx\local\view\base $view
     * @return datalynxview_entries_form
     */
    private function create_new_entry_form(\mod_datalynx\local\view\base $view): datalynxview_entries_form {
        $reflection = new ReflectionClass($view);

        $editentries = $reflection->getProperty('editentries');
        $editentries->setAccessible(true);
        $editentries->setValue($view, [-1]);

        $setdisplaydefinition = $reflection->getMethod('set_display_definition');
        $setdisplaydefinition->setAccessible(true);
        $setdisplaydefinition->invoke($view);

        return new datalynxview_entries_form(
            new moodle_url('/mod/datalynx/view.php'),
            ['view' => $view, 'update' => '-1']
        );
    }

    /**
     * Only fields present in the new-entry template should be validated for that form.
     */
    public function test_get_entry_form_fields_ignores_fields_outside_new_entry_template(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$dlx, $viewrecord, $textfield, $userinfofield] = $this->create_grid_fixture();

        $view = $dlx->get_view('grid', $viewrecord);

        $patterns = $view->get_entry_form_patterns();
        $fields = $view->get_entry_form_fields();

        $this->assertArrayHasKey($textfield->id, $patterns);
        $this->assertArrayNotHasKey($userinfofield->id, $patterns);
        $this->assertArrayHasKey($textfield->id, $fields);
        $this->assertArrayNotHasKey($userinfofield->id, $fields);
    }

    /**
     * New-entry validation must not touch userinfo fields omitted from the template.
     */
    public function test_new_entry_validation_skips_userinfo_field_not_in_template(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$dlx, $viewrecord, $textfield] = $this->create_grid_fixture();

        $view = $dlx->get_view('grid', $viewrecord);
        $form = $this->create_new_entry_form($view);

        $errors = $form->validation([
            'new' => 1,
            "field_{$textfield->id}_-1" => 'Test title',
        ], []);

        $this->assertSame([], $errors);
    }

    /**
     * Userinfo validation should safely no-op if the form field was never rendered.
     */
    public function test_userinfo_validation_ignores_missing_form_property(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$dlx, , , $userinfofield] = $this->create_grid_fixture();

        $field = $dlx->get_field_from_id($userinfofield->id);

        $errors = $field->renderer()->validate(-1, ["##author:{$userinfofield->name}##"], (object) []);

        $this->assertSame([], $errors);
    }
}
