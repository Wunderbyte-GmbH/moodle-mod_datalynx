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
 * Tests that content filters reach both the view section and the entry template.
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
 * The view section and the entry template are both authored text and must both be filtered.
 *
 * @covers \mod_datalynx\local\view\base::get_prepared_entry_template
 * @covers \mod_datalynx\local\view\base::set_view_tags
 */
final class view_text_filter_test extends advanced_testcase {
    use multilang_test_trait;

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
        $this->enable_multilang_filter();
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
            'name' => 'Filtered',
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
     * The section must still be filtered when resolving its tags rebuilds this very view.
     *
     * Regression test: a ##viewlink## pointing at its own view makes get_replacements() construct
     * another view object from the same cached record, whose constructor resets the editors to the
     * raw source. Preparing the section before that point left the substitution running on raw
     * text, so every piece of filter markup in the section was shown verbatim.
     *
     * @return void
     */
    public function test_section_is_filtered_even_when_replacements_rebuild_the_view(): void {
        $view = $this->make_view(
            '<p>' . $this->multilang_markup() . '</p>##viewlink:Filtered;Link;;##',
            ''
        );

        $view->set_view_tags([]);

        $this->assert_localised('en', $view->view->esection, 'view section');
        $this->assertStringContainsString('Link', $view->view->esection);
    }

    /**
     * Both render paths must produce the same, filtered entry template.
     *
     * @return void
     */
    public function test_entry_template_is_filtered_in_both_render_paths(): void {
        global $DB, $USER;

        $field = (object) [
            'dataid' => $this->dlx->id(),
            'type' => 'text',
            'name' => 'Title',
            'description' => '',
            'param1' => '', 'param2' => '', 'param3' => '', 'param4' => '', 'param5' => '',
            'param6' => '', 'param7' => '', 'param8' => '', 'param9' => '', 'param10' => '',
        ];
        $field->id = (int) $DB->insert_record('datalynx_fields', $field);

        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $this->dlx->id(),
            'userid' => $USER->id,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $field->id,
            'entryid' => $entryid,
            'lineid' => 0,
            'content' => 'Entry value',
        ]);

        $view = $this->make_view('##entries##', '<div>' . $this->multilang_markup() . ' [[Title]]</div>');
        $view->get_filter()->contentfields = [$field->id];
        $view->set_content();

        $entries = new local\datalynx_entries($this->dlx, $view->get_filter());
        $entries->set_content();
        $records = $entries->entries();
        $entry = reset($records);

        foreach (['de', 'en'] as $lang) {
            $this->set_current_language($lang);

            // The AJAX managers render one entry at a time through this method.
            $ajaxhtml = $view->render_entry_html($entry, ['edit' => false, 'manage' => false]);
            $this->assert_localised($lang, $ajaxhtml, 'render_entry_html');
            $this->assertStringContainsString('Entry value', $ajaxhtml);

            // The classic path assembles the same template through display_entries().
            $view->set_display_definition([]);
            $classichtml = $view->display_entries([]);
            $this->assert_localised($lang, $classichtml, 'display_entries');
            $this->assertStringContainsString('Entry value', $classichtml);
        }
    }

    /**
     * Preparing the template twice must not filter or rewrite it twice.
     *
     * @return void
     */
    public function test_prepared_entry_template_is_stable(): void {
        $view = $this->make_view('##entries##', '<div><img src="@@PLUGINFILE@@/logo.png">' .
            $this->multilang_markup() . '</div>');
        $method = new \ReflectionMethod($view, 'get_prepared_entry_template');
        $method->setAccessible(true);

        $first = $method->invoke($view);
        $this->assertSame($first, $method->invoke($view));
        $this->assertStringNotContainsString('@@PLUGINFILE@@', $first);
        $this->assertSame(1, substr_count($first, 'pluginfile.php'));
    }
}
