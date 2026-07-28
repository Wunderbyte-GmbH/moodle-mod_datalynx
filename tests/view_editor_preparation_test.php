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
 * Tests for preparing a view's editors for output.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;

/**
 * Tests for base::prepare_editors_for_output().
 *
 * @covers \mod_datalynx\local\view\base::prepare_editors_for_output
 */
final class view_editor_preparation_test extends advanced_testcase {
    /** @var datalynx The datalynx instance under test. */
    private datalynx $dlx;

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
        \filter_manager::reset_caches();
    }

    /**
     * Create a grid view.
     *
     * @param string $section View section template.
     * @param string $param2 Entry template.
     * @return \mod_datalynx\local\view\base
     */
    private function make_view(string $section, string $param2) {
        global $DB;

        $record = (object) [
            'dataid' => $this->dlx->id(),
            'type' => 'grid',
            'name' => 'Preparation',
            'description' => '',
            'visible' => 7,
            'filter' => 0,
            'perpage' => 0,
            'groupby' => '',
            'param5' => 0,
            'param10' => 0,
            'section' => $section,
            'param2' => $param2,
        ];
        $record->id = (int) $DB->insert_record('datalynx_views', $record);

        return $this->dlx->get_view('grid', $record, false);
    }

    /**
     * Preparing twice must not filter or rewrite the editors twice.
     *
     * @return void
     */
    public function test_preparation_is_idempotent(): void {
        $view = $this->make_view('<p>##entries##</p>', '<div>{mlang other}Hello{mlang}</div>');

        $view->prepare_editors_for_output();
        $prepared = $view->view->eparam2;
        $this->assertStringNotContainsString('{mlang', $prepared);

        $view->prepare_editors_for_output();
        $this->assertSame($prepared, $view->view->eparam2);
    }

    /**
     * An explicit pluginfile url must win, and a later no-argument call must not rewrite again.
     *
     * @return void
     */
    public function test_explicit_pluginfileurl_is_used_once(): void {
        $view = $this->make_view('##entries##', '<img src="@@PLUGINFILE@@/logo.png">');

        $view->prepare_editors_for_output('/exportfiles/');
        $this->assertStringContainsString('/exportfiles/logo.png', $view->view->eparam2);

        $view->prepare_editors_for_output();
        $this->assertStringContainsString('/exportfiles/logo.png', $view->view->eparam2);
        $this->assertStringNotContainsString('pluginfile.php', $view->view->eparam2);
    }

    /**
     * View tag substitution must still work after the editors were prepared separately.
     *
     * The tag masking happens during preparation, so a filter tag used inside a datalynx tag's
     * arguments must survive it unresolved and only be localised when the tag is built.
     *
     * @return void
     */
    public function test_view_tags_are_still_substituted_after_preparation(): void {
        $view = $this->make_view('<p>{mlang other}Heading{mlang}</p>##entries##', '[[fieldname]]');

        $view->prepare_editors_for_output();
        // The section text is filtered, but the tag itself is untouched.
        $this->assertStringContainsString('Heading', $view->view->esection);
        $this->assertStringNotContainsString('{mlang', $view->view->esection);
        $this->assertStringContainsString('##entries##', $view->view->esection);

        $view->set_view_tags([]);
        // The entries placeholder is never masked or replaced here; display() substitutes it.
        $this->assertStringContainsString('##entries##', $view->view->esection);
        $this->assertStringContainsString('Heading', $view->view->esection);
    }

    /**
     * An empty entry template must stay empty and must not inherit the section text.
     *
     * @return void
     */
    public function test_empty_entry_template_does_not_inherit_section(): void {
        $view = $this->make_view('<p>Section only</p>', '');

        $this->assertSame('', $view->view->eparam2);
    }
}
