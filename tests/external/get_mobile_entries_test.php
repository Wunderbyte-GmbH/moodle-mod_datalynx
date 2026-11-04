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

namespace mod_datalynx\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use core_external\external_api;
use externallib_advanced_testcase;
use invalid_parameter_exception;
use mod_datalynx\datalynx;
use mod_datalynx\mobile_test_trait;
use required_capability_exception;

/**
 * Tests for the Moodle App browse service.
 *
 * @package    mod_datalynx
 * @category   external
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\external\get_mobile_entries
 * @covers     \mod_datalynx\local\mobile\entries_payload_builder
 */
final class get_mobile_entries_test extends externallib_advanced_testcase {
    use mobile_test_trait;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var int */
    private int $titleid;

    /** @var int[] entry ids by title */
    private array $entryids = [];

    #[\Override]
    protected function setUp(): void {
        global $USER;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        [$this->course, $this->dlx] = $this->create_mobile_instance();
        $this->titleid = $this->add_mobile_field($this->dlx, 'text', 'Title');
        $time = time() - 100;
        foreach (['Apple', 'Banana', 'Cherry'] as $i => $title) {
            $this->entryids[$title] = $this->add_mobile_entry($this->dlx, $USER->id, [$this->titleid => $title], $time + $i);
        }
    }

    /**
     * Call the service the way the app does and clean the result against the declared returns.
     *
     * @param array $params
     * @return array
     */
    private function call(array $params): array {
        $result = get_mobile_entries::execute(...($params + ['d' => $this->dlx->id()]));

        return external_api::clean_returnvalue(get_mobile_entries::execute_returns(), $result);
    }

    /**
     * The function is in the Moodle App's service; without that the app cannot call it at all.
     */
    public function test_function_is_in_the_mobile_service(): void {
        global $DB;

        $this->assertTrue($DB->record_exists_sql(
            "SELECT 1
               FROM {external_services_functions} sf
               JOIN {external_services} s ON s.id = sf.externalserviceid
              WHERE s.shortname = :service AND sf.functionname = :function",
            ['service' => MOODLE_OFFICIAL_MOBILE_SERVICE, 'function' => 'mod_datalynx_get_mobile_entries']
        ));
    }

    /**
     * A grid entry is rendered with its own template; action links and scripts do not survive.
     */
    public function test_grid_entry_uses_template_without_actions(): void {
        $viewid = $this->add_mobile_view(
            $this->dlx,
            'grid',
            'Cards',
            '<div class="ride">[[Title]] ##edit## ##delete## ##duplicate## ##select##<script>alert(1)</script></div>'
        );

        $result = $this->call(['viewid' => $viewid, 'search' => 'Banana']);

        $this->assertTrue($result['supported']);
        $this->assertSame('grid', $result['viewtype']);
        $this->assertCount(1, $result['entries']);
        $entry = $result['entries'][0];
        $this->assertSame($this->entryids['Banana'], $entry['id']);
        $this->assertStringContainsString('<div class="ride">', $entry['html']);
        $this->assertStringContainsString('Banana', $entry['html']);
        foreach (['delete=', 'editentries=', 'duplicate=', 'sesskey=', '<script', '<input'] as $needle) {
            $this->assertStringNotContainsString($needle, $entry['html']);
        }
        $this->assertSame([['fieldid' => $this->titleid, 'name' => 'Title']], array_map(
            fn($field) => ['fieldid' => $field['fieldid'], 'name' => $field['name']],
            $entry['fields']
        ));
        $this->assertSame('Banana', $entry['fields'][0]['valuetext']);
        $this->assertSame(fullname(get_admin()), $entry['author']);
    }

    /**
     * A tabular view is listed field by field, without entry HTML.
     */
    public function test_tabular_view_lists_fields(): void {
        $viewid = $this->add_mobile_view($this->dlx, 'tabular', 'Table', '<table><tr><td>[[Title]]</td></tr></table>');

        $result = $this->call(['viewid' => $viewid]);

        $this->assertTrue($result['supported']);
        $this->assertCount(3, $result['entries']);
        foreach ($result['entries'] as $entry) {
            $this->assertSame('', $entry['html']);
            $this->assertCount(1, $entry['fields']);
        }
    }

    /**
     * Without a view id the default view is shown, or the first browsable one when the default is not.
     */
    public function test_default_view_resolution(): void {
        $grid = $this->add_mobile_view($this->dlx, 'grid', 'Grid');
        $tabular = $this->add_mobile_view($this->dlx, 'tabular', 'Table');
        $csv = $this->add_mobile_view($this->dlx, 'csv', 'Export');

        $this->set_mobile_default_view($this->dlx, $tabular);
        $this->assertSame($tabular, $this->call([])['viewid']);

        $this->set_mobile_default_view($this->dlx, $csv);
        $result = $this->call([]);
        $this->assertSame($grid, $result['viewid']);
        $this->assertSame([$grid, $tabular, $csv], array_column($result['views'], 'id'));
        $this->assertSame([true, true, false], array_column($result['views'], 'supported'));
        $this->assertSame([true, false, false], array_column($result['views'], 'active'));
    }

    /**
     * A view type the app cannot show comes back unsupported, with the way to the web.
     */
    public function test_unsupported_view_type(): void {
        $csv = $this->add_mobile_view($this->dlx, 'csv', 'Export');

        $result = $this->call(['viewid' => $csv]);

        $this->assertFalse($result['supported']);
        $this->assertSame([], $result['entries']);
        $this->assertStringContainsString('view=' . $csv, $result['browserurl']);
    }

    /**
     * A view the user cannot see cannot be requested.
     */
    public function test_hidden_view_is_refused(): void {
        $this->add_mobile_view($this->dlx, 'grid', 'Visible');
        $hidden = $this->add_mobile_view($this->dlx, 'grid', 'Managers only', '', ['visible' => 1]);
        $this->setUser($this->create_mobile_student($this->course));

        $this->expectException(invalid_parameter_exception::class);
        $this->call(['viewid' => $hidden]);
    }

    /**
     * Full-text search, criteria and sort each change the result, and come back as applied.
     */
    public function test_search_criteria_and_sort(): void {
        $viewid = $this->add_mobile_view($this->dlx, 'grid', 'Grid');

        $result = $this->call(['viewid' => $viewid, 'search' => 'Cher']);
        $this->assertSame([$this->entryids['Cherry']], $this->mobile_entry_ids($result));

        $criteria = json_encode([$this->titleid => ['OR' => [['', '=', 'Apple'], ['', '=', 'Cherry']]]]);
        $result = $this->call(['viewid' => $viewid, 'criteria' => $criteria, 'sort' => json_encode([$this->titleid => 1])]);
        $this->assertSame([$this->entryids['Cherry'], $this->entryids['Apple']], $this->mobile_entry_ids($result));
        $this->assertSame($criteria, $result['criteria']);
        $this->assertSame(json_encode([$this->titleid => 1]), $result['sort']);

        $result = $this->call(['viewid' => $viewid, 'sort' => json_encode([$this->titleid => 0])]);
        $this->assertSame(array_values($this->entryids), $this->mobile_entry_ids($result));
    }

    /**
     * Invalid criteria are refused, not ignored.
     */
    public function test_invalid_criteria_are_refused(): void {
        $viewid = $this->add_mobile_view($this->dlx, 'grid', 'Grid');

        $this->expectException(invalid_parameter_exception::class);
        $this->call(['viewid' => $viewid, 'criteria' => 'O:8:"stdClass":0:{}']);
    }

    /**
     * Paging: the window size limits the entries, and hasmore tells the app there are more.
     */
    public function test_paging(): void {
        $viewid = $this->add_mobile_view($this->dlx, 'grid', 'Grid');

        $result = $this->call(['viewid' => $viewid, 'perpage' => 2]);
        $this->assertCount(2, $result['entries']);
        $this->assertSame(2, $result['entriescount']);
        $this->assertSame(3, $result['entriesfiltercount']);
        $this->assertSame(2, $result['perpage']);
        $this->assertTrue($result['hasmore']);

        $result = $this->call(['viewid' => $viewid, 'perpage' => 2, 'page' => 1]);
        $this->assertCount(1, $result['entries']);
        $this->assertFalse($result['hasmore']);

        $result = $this->call(['viewid' => $viewid, 'perpage' => 100000]);
        $this->assertSame(200, $result['perpage']);
        $this->assertFalse($result['hasmore']);
    }

    /**
     * eids narrows to the named entries.
     */
    public function test_eids(): void {
        $viewid = $this->add_mobile_view($this->dlx, 'grid', 'Grid');

        $result = $this->call(['viewid' => $viewid, 'eids' => (string) $this->entryids['Banana']]);

        $this->assertSame([$this->entryids['Banana']], $this->mobile_entry_ids($result));
    }

    /**
     * Saved filters: the menu offers what the view permits, and other filters are refused.
     */
    public function test_saved_filters(): void {
        $cherries = $this->add_mobile_filter($this->dlx, 'Cherries', ['search' => 'Cherry']);
        $apples = $this->add_mobile_filter($this->dlx, 'Apples', ['search' => 'Apple']);
        $open = $this->add_mobile_view($this->dlx, 'grid', 'Open', '', ['param5' => 1]);
        $whitelisted = $this->add_mobile_view($this->dlx, 'grid', 'Whitelisted', '', [
            'permittedfilters' => json_encode([$cherries]),
        ]);

        $result = $this->call(['viewid' => $open, 'filterid' => $apples]);
        $this->assertTrue($result['canfilter']);
        $this->assertSame($apples, $result['filterid']);
        $this->assertSame([$this->entryids['Apple']], $this->mobile_entry_ids($result));
        $this->assertEqualsCanonicalizing([$cherries, $apples], array_column($result['filters'], 'id'));

        $result = $this->call(['viewid' => $whitelisted, 'filterid' => $cherries]);
        $this->assertSame([$this->entryids['Cherry']], $this->mobile_entry_ids($result));
        $this->assertSame([$cherries], array_column($result['filters'], 'id'));

        $this->expectException(invalid_parameter_exception::class);
        $this->call(['viewid' => $whitelisted, 'filterid' => $apples]);
    }

    /**
     * A view that forces its filter ignores the client's search, criteria and sort.
     */
    public function test_forced_filter_ignores_client_filtering(): void {
        $apples = $this->add_mobile_filter($this->dlx, 'Apples', ['search' => 'Apple']);
        $forced = $this->add_mobile_view($this->dlx, 'grid', 'Forced', '', ['filter' => $apples]);

        $result = $this->call([
            'viewid' => $forced,
            'search' => 'Banana',
            'criteria' => json_encode([$this->titleid => ['AND' => [['', '=', 'Banana']]]]),
        ]);

        $this->assertFalse($result['canfilter']);
        $this->assertSame([], $result['filters']);
        $this->assertSame('', $result['search']);
        $this->assertSame([$this->entryids['Apple']], $this->mobile_entry_ids($result));
    }

    /**
     * Browsing requires the capability to view entries.
     */
    public function test_requires_viewentry(): void {
        global $DB;

        $this->add_mobile_view($this->dlx, 'grid', 'Grid');
        $student = $this->create_mobile_student($this->course);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('mod/datalynx:viewentry', CAP_PROHIBIT, $roleid, $this->dlx->context->id, true);
        $this->setUser($student);

        $this->expectException(required_capability_exception::class);
        $this->call([]);
    }
}
