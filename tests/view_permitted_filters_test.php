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
 * Tests for the per-view permitted-filters whitelist.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;

/**
 * Tests for the per-view permitted-filters whitelist.
 *
 * @covers \mod_datalynx\local\view\base::set_filter
 * @covers \mod_datalynx\local\view\base::is_forcing_filter
 * @covers \mod_datalynx\local\view\base::is_filter_whitelist_active
 * @covers \mod_datalynx\local\view\base::get_permitted_filter_ids
 */
final class view_permitted_filters_test extends advanced_testcase {
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
    }

    /**
     * Create a filter that matches every entry.
     *
     * @param string $name Filter name.
     * @return int New filter id.
     */
    private function make_filter(string $name): int {
        global $DB;
        return (int) $DB->insert_record('datalynx_filters', (object) [
            'dataid' => $this->dlx->id(),
            'name' => $name,
            'description' => '',
            'customsort' => '',
            'customsearch' => '',
            'search' => '',
            'groupby' => '',
            'perpage' => 50,
            'selection' => 0,
            'page' => 0,
            'eids' => '',
        ]);
    }

    /**
     * Create a grid view and return the constructed view object (which runs set_filter()).
     *
     * @param string $name View name.
     * @param int $filter Default filter id.
     * @param int[]|null $permitted Permitted-filters whitelist (null = column unset).
     * @param int $param5 "Allow all filters" override flag.
     * @return \mod_datalynx\local\view\base
     */
    private function make_view(string $name, int $filter, ?array $permitted, int $param5) {
        global $DB;
        $record = (object) [
            'dataid' => $this->dlx->id(),
            'type' => 'grid',
            'name' => $name,
            'description' => '',
            'visible' => 7,
            'filter' => $filter,
            'permittedfilters' => $permitted === null ? null : json_encode($permitted),
            'perpage' => 0,
            'groupby' => '',
            'param5' => $param5,
            'param9' => 0,
            'param10' => 0,
            'section' => '',
            'param2' => '',
        ];
        $record->id = (int) $DB->insert_record('datalynx_views', $record);
        return $this->dlx->get_view('grid', $DB->get_record('datalynx_views', ['id' => $record->id]));
    }

    /**
     * Construct a view with the given URL filter parameter and return the resulting filter id.
     *
     * @param string $name View name.
     * @param int $filter Default filter id.
     * @param int[]|null $permitted Whitelist.
     * @param int $param5 Override flag.
     * @param int|null $urlfilter Value of the URL `filter` parameter, or null to omit it.
     * @return int Resolved filter id.
     */
    private function resolve_filter_id(string $name, int $filter, ?array $permitted, int $param5, ?int $urlfilter): int {
        if ($urlfilter !== null) {
            $_POST['filter'] = (string) $urlfilter;
        }
        try {
            $view = $this->make_view($name, $filter, $permitted, $param5);
            return (int) $view->get_filter()->id;
        } finally {
            unset($_POST['filter']);
        }
    }

    /**
     * The helpers combine the default filter and the whitelist, and report when the whitelist is active.
     */
    public function test_helpers(): void {
        $f = $this->make_filter('F');
        $g = $this->make_filter('G');

        $view = $this->make_view('helpers', $f, [$g], 0);
        $this->assertEqualsCanonicalizing([$f, $g], $view->get_permitted_filter_ids());
        $this->assertEquals([$g], $view->get_permitted_filters_setting());
        $this->assertTrue($view->is_filter_whitelist_active());

        // Enabling "allow all filters" deactivates the whitelist restriction.
        $viewall = $this->make_view('helpers-all', $f, [$g], 1);
        $this->assertFalse($viewall->is_filter_whitelist_active());

        // No whitelist configured.
        $viewnone = $this->make_view('helpers-none', $f, null, 0);
        $this->assertFalse($viewnone->is_filter_whitelist_active());
        $this->assertEquals([$f], $viewnone->get_permitted_filter_ids());
    }

    /**
     * A migrated locked view (whitelist = [default], override off) stays locked: it forces its
     * filter and ignores a URL filter parameter, exactly as before the feature.
     */
    public function test_single_entry_whitelist_stays_locked(): void {
        $f = $this->make_filter('F');
        $g = $this->make_filter('G');

        $view = $this->make_view('locked', $f, [$f], 0);
        $this->assertEquals($f, $view->is_forcing_filter());

        // URL asks for G, but the view is still locked to F.
        $this->assertEquals($f, $this->resolve_filter_id('locked2', $f, [$f], 0, $g));
    }

    /**
     * A multi-entry whitelist allows switching among permitted filters but rejects others.
     */
    public function test_multi_entry_whitelist_gates_url_filter(): void {
        $f = $this->make_filter('F');
        $g = $this->make_filter('G');
        $h = $this->make_filter('H');

        // No URL param -> default filter F (backward-compatible selection).
        $this->assertEquals($f, $this->resolve_filter_id('multi-default', $f, [$f, $g], 0, null));

        // Permitted filter G via URL -> honored.
        $this->assertEquals($g, $this->resolve_filter_id('multi-permitted', $f, [$f, $g], 0, $g));

        // Non-permitted filter H via URL -> rejected, falls back to default F.
        $this->assertEquals($f, $this->resolve_filter_id('multi-rejected', $f, [$f, $g], 0, $h));

        // A negative (personal/user) filter id is also rejected.
        $this->assertEquals($f, $this->resolve_filter_id('multi-userfilter', $f, [$f, $g], 0, -1));
    }

    /**
     * "Allow all filters" (override) supersedes the whitelist: any existing filter is honored.
     */
    public function test_allow_all_filters_supersedes_whitelist(): void {
        $f = $this->make_filter('F');
        $g = $this->make_filter('G');
        $h = $this->make_filter('H');

        // H is not on the whitelist, but override is on, so it is honored.
        $this->assertEquals($h, $this->resolve_filter_id('override', $f, [$f, $g], 1, $h));
    }

    /**
     * With no whitelist and override on (legacy behaviour), any filter is honored via the URL.
     */
    public function test_legacy_no_whitelist_override_on(): void {
        $f = $this->make_filter('F');
        $g = $this->make_filter('G');

        $this->assertEquals($g, $this->resolve_filter_id('legacy', $f, null, 1, $g));
    }
}
