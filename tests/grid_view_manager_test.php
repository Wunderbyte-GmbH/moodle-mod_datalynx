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
use mod_datalynx\local\view\manager\grid_view_manager;
use stdClass;

/**
 * Tests for the Grid view browse payload manager.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_datalynx\local\view\manager\grid_view_manager
 */
final class grid_view_manager_test extends advanced_testcase {
    /**
     * Build a minimal datalynx fixture with a Grid view, one text field, and one entry.
     *
     * @param string $param2 Optional entry template for the Grid view.
     * @return array
     */
    private function create_grid_fixture(string $param2 = ''): array {
        global $DB, $USER;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $view = (object) [
            'dataid' => $dlx->id(),
            'type' => 'grid',
            'name' => 'Pilot Grid',
            'description' => '',
            'visible' => 7,
            'filter' => 0,
            'perpage' => 0,
            'groupby' => '',
            'param5' => 0,
            'param10' => 0,
            'section' => '',
            'param2' => $param2,
        ];
        $view->id = (int) $DB->insert_record('datalynx_views', $view);

        $field = (object) [
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
        $field->id = (int) $DB->insert_record('datalynx_fields', $field);

        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(),
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
            'content' => 'Hello Grid',
        ]);

        return [$dlx, $view, $field, $entryid];
    }

    /**
     * The manager should return a structured browse payload for one Grid entry.
     *
     * @covers ::get_browse_payload
     */
    public function test_get_browse_payload_returns_structured_grid_entry(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$dlx, $view, , $entryid] = $this->create_grid_fixture();

        $manager = new grid_view_manager();
        $payload = $manager->get_browse_payload($dlx->id(), $view->id);

        $this->assertSame($dlx->id(), $payload['datalynxid']);
        $this->assertSame($view->id, $payload['viewid']);
        $this->assertTrue($payload['hasentries']);
        $this->assertCount(1, $payload['groups']);
        $this->assertCount(1, $payload['groups'][0]['entries']);
        $this->assertSame($entryid, $payload['groups'][0]['entries'][0]['id']);
        $this->assertSame('Title', $payload['groups'][0]['entries'][0]['fields'][0]['name']);
        $this->assertStringContainsString('Hello Grid', $payload['groups'][0]['entries'][0]['fields'][0]['valuehtml']);
        $this->assertStringContainsString('editentries=' . $entryid, $payload['groups'][0]['entries'][0]['edithtml']);
        $this->assertStringContainsString('eids=' . $entryid, $payload['groups'][0]['entries'][0]['edithtml']);
    }

    /**
     * A custom (non-empty) entry template must be honoured when rendering browse entries, and a
     * subsequent edit of that template must be reflected in the rendered output.
     *
     * Regression test: previously a "tag only" entry template (one that contains nothing but field
     * and action tags) was discarded in favour of a generic field loop that rendered *all* fields
     * regardless of the template, so editing such a template appeared to have no effect.
     *
     * @covers ::get_browse_payload
     * @covers ::requires_rendered_entry_html
     */
    public function test_custom_entry_template_is_honoured_and_reflects_edits(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$dlx, $view, , $entryid] = $this->create_grid_fixture();

        // Add a second field (with content) so we can prove the template controls which fields render.
        $second = (object) [
            'dataid' => $dlx->id(),
            'type' => 'text',
            'name' => 'Subtitle',
            'description' => '',
            'param1' => '', 'param2' => '', 'param3' => '', 'param4' => '', 'param5' => '',
            'param6' => '', 'param7' => '', 'param8' => '', 'param9' => '', 'param10' => '',
        ];
        $second->id = (int) $DB->insert_record('datalynx_fields', $second);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $second->id,
            'entryid' => $entryid,
            'lineid' => 0,
            'content' => 'Second value',
        ]);

        $manager = new grid_view_manager();

        // A "tag only" entry template that references only the first field. Such a template contains
        // nothing but a field tag, which is exactly the case that used to be discarded.
        $DB->set_field('datalynx_views', 'param2', '[[Title]]', ['id' => $view->id]);
        $payload = $manager->get_browse_payload($dlx->id(), $view->id);
        $entryhtml = $payload['groups'][0]['entries'][0]['entryhtml'];

        $this->assertNotSame('', $entryhtml, 'A non-empty entry template must be rendered as entryhtml.');
        $this->assertStringContainsString('Hello Grid', $entryhtml);
        // The Subtitle field is not part of the template, so its content must not appear.
        $this->assertStringNotContainsString('Second value', $entryhtml);

        // Edit the template to reference the other field; the rendered output must follow the edit.
        $DB->set_field('datalynx_views', 'param2', '[[Subtitle]]', ['id' => $view->id]);
        $payload = $manager->get_browse_payload($dlx->id(), $view->id);
        $entryhtml = $payload['groups'][0]['entries'][0]['entryhtml'];

        $this->assertStringContainsString('Second value', $entryhtml, 'Editing the entry template must be reflected on render.');
        $this->assertStringNotContainsString('Hello Grid', $entryhtml, 'The stale template must not survive the edit.');
    }

    /**
     * Test wrapper settings payload output.
     *
     * @covers ::get_browse_payload
     */
    public function test_get_browse_payload_with_wrapper_settings(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$dlx, $view, , ] = $this->create_grid_fixture();

        // 1. Default (legacy) wrapper setting when param3 is empty.
        $manager = new grid_view_manager();
        $payload = $manager->get_browse_payload($dlx->id(), $view->id);
        $this->assertFalse($payload['nowrapper']);
        $this->assertSame('entry', $payload['entrywrapperclass']);
        $this->assertSame('', $payload['groupclass']);

        // 2. Bootstrap row-cols setting.
        $DB->set_field('datalynx_views', 'param3', 'col', ['id' => $view->id]);
        $payload = $manager->get_browse_payload($dlx->id(), $view->id);
        $this->assertFalse($payload['nowrapper']);
        $this->assertSame('col', $payload['entrywrapperclass']);
        $this->assertSame('row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4', $payload['groupclass']);

        // 3. Custom class setting with col- definition.
        $DB->set_field('datalynx_views', 'param3', 'custom', ['id' => $view->id]);
        $DB->set_field('datalynx_views', 'param4', 'col-12 col-md-6 col-lg-3', ['id' => $view->id]);
        $payload = $manager->get_browse_payload($dlx->id(), $view->id);
        $this->assertFalse($payload['nowrapper']);
        $this->assertSame('col-12 col-md-6 col-lg-3', $payload['entrywrapperclass']);
        $this->assertSame('row g-4', $payload['groupclass']);

        // 4. Custom class setting without col- definition.
        $DB->set_field('datalynx_views', 'param3', 'custom', ['id' => $view->id]);
        $DB->set_field('datalynx_views', 'param4', 'my-custom-class', ['id' => $view->id]);
        $payload = $manager->get_browse_payload($dlx->id(), $view->id);
        $this->assertFalse($payload['nowrapper']);
        $this->assertSame('my-custom-class', $payload['entrywrapperclass']);
        $this->assertSame('', $payload['groupclass']);

        // 5. No wrapper setting.
        $DB->set_field('datalynx_views', 'param3', 'none', ['id' => $view->id]);
        $payload = $manager->get_browse_payload($dlx->id(), $view->id);
        $this->assertTrue($payload['nowrapper']);
        $this->assertSame('', $payload['entrywrapperclass']);
        $this->assertSame('', $payload['groupclass']);
    }

    /**
     * Content filters must be applied to the entry template in the AJAX browse payload.
     *
     * Regression test: the browse payload is built by this manager, which never goes through
     * base::set_view_tags(). The entry template therefore reached the browser unfiltered and
     * {mlang ...} markup showed up raw on every Grid view.
     *
     * @covers ::get_browse_payload
     */
    public function test_browse_payload_applies_content_filters_to_entry_template(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->enable_multilang_filter();

        $template = '<div>{mlang de}GERMANLABEL{mlang}{mlang other}ENGLISHLABEL{mlang} [[Title]]</div>';
        [$dlx, $view] = $this->create_grid_fixture($template);

        $this->set_current_language('de');
        $entryhtml = $this->get_first_entry_html($dlx->id(), $view->id);
        $this->assertStringNotContainsString('{mlang', $entryhtml);
        $this->assertStringContainsString('GERMANLABEL', $entryhtml);
        $this->assertStringNotContainsString('ENGLISHLABEL', $entryhtml);
        // The datalynx tag must still have been resolved against the entry content.
        $this->assertStringContainsString('Hello Grid', $entryhtml);

        $this->set_current_language('en');
        $entryhtml = $this->get_first_entry_html($dlx->id(), $view->id);
        $this->assertStringNotContainsString('{mlang', $entryhtml);
        $this->assertStringContainsString('ENGLISHLABEL', $entryhtml);
        $this->assertStringNotContainsString('GERMANLABEL', $entryhtml);
    }

    /**
     * Files embedded in the entry template must be rewritten in the AJAX browse payload too.
     *
     * @covers ::get_browse_payload
     */
    public function test_browse_payload_rewrites_pluginfile_urls_in_entry_template(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$dlx, $view] = $this->create_grid_fixture('<div><img src="@@PLUGINFILE@@/logo.png"> [[Title]]</div>');

        $entryhtml = $this->get_first_entry_html($dlx->id(), $view->id);
        $this->assertStringNotContainsString('@@PLUGINFILE@@', $entryhtml);
        $this->assertStringContainsString('pluginfile.php', $entryhtml);
    }

    /**
     * Fetch the rendered entry HTML of the first entry in a Grid browse payload.
     *
     * @param int $datalynxid
     * @param int $viewid
     * @return string
     */
    private function get_first_entry_html(int $datalynxid, int $viewid): string {
        $manager = new grid_view_manager();
        $payload = $manager->get_browse_payload($datalynxid, $viewid);

        return $payload['groups'][0]['entries'][0]['entryhtml'];
    }

    /**
     * Enable the multilang2 content filter globally for the current test.
     */
    private function enable_multilang_filter(): void {
        filter_set_global_state('multilang2', TEXTFILTER_ON);
        \filter_manager::reset_caches();
    }

    /**
     * Force the current language for the request.
     *
     * Sets $SESSION->forcelang directly (rather than force_current_language(), which is a no-op
     * when the language pack is not installed in the test environment) and resets the caches that
     * would otherwise hand back a result filtered for the previous language.
     *
     * @param string $lang the language code to force, e.g. 'de' or 'en'.
     */
    private function set_current_language(string $lang): void {
        global $SESSION;
        $SESSION->forcelang = $lang;
        \filter_multilang2\text_filter::reset_parentcache();
        \filter_manager::reset_caches();
    }
}
