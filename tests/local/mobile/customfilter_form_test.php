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

namespace mod_datalynx\local\mobile;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use advanced_testcase;
use invalid_parameter_exception;
use mod_datalynx\datalynx;
use mod_datalynx\local\filter\customfilter_controls;
use mod_datalynx\local\filter\customfilter_search;
use mod_datalynx\local\filter\datalynx_filter;
use mod_datalynx\mobile_test_trait;
use mod_datalynx\output\mobile;

/**
 * The custom filter in the Moodle App (mod_datalynx#270).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\mobile\customfilter_form
 * @covers     \mod_datalynx\local\filter\customfilter_controls
 * @covers     \mod_datalynx\local\mobile\entries_payload_builder
 * @covers     \mod_datalynx\output\mobile::mobile_course_view
 */
final class customfilter_form_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var int[] field ids by name */
    private array $ids = [];

    /** @var int a view with the custom filter */
    private int $viewid;

    /** @var int[] entry ids by title */
    private array $entries = [];

    /** @var \stdClass */
    private \stdClass $customfilter;

    #[\Override]
    protected function setUp(): void {
        global $DB, $USER;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        [$this->course, $dlx] = $this->create_mobile_instance();
        $this->ids['Title'] = $this->add_mobile_field($dlx, 'text', 'Title');
        $this->ids['Count'] = $this->add_mobile_field($dlx, 'number', 'Count');
        $this->ids['Colour'] = $this->add_mobile_field($dlx, 'select', 'Colour', ['param1' => "Red\nGreen"]);
        $this->ids['Tags'] = $this->add_mobile_field($dlx, 'multiselect', 'Tags', ['param1' => "Big\nSmall\nOld"]);
        $this->ids['Place'] = $this->add_mobile_field($dlx, 'itinerary', 'Place');
        $this->viewid = $this->add_mobile_view(
            $dlx,
            'grid',
            'Search',
            '<p>Fruit: [[Title]] [[Count]] [[Colour]] [[Tags]] [[Place]]</p>',
            ['section' => '##customfilter:Find## ##entries##']
        );
        foreach (['Apple' => [3, 1, '#1#,#2#'], 'Banana' => [5, 2, '#1#'], 'Cherry' => [10, 1, '#2#,#3#']] as $title => $row) {
            [$count, $colour, $tags] = $row;
            $this->entries[$title] = $this->add_mobile_entry($dlx, $USER->id, [
                $this->ids['Title'] => $title, $this->ids['Count'] => (string) $count,
                $this->ids['Colour'] => (string) $colour, $this->ids['Tags'] => $tags,
            ]);
        }

        $listed = fn(string $name, bool $sortable = false) => ['name' => $name, 'sortable' => $sortable ? 1 : 0];
        $this->customfilter = (object) [
            'dataid' => $dlx->id(), 'name' => 'Find', 'description' => '', 'visible' => 1,
            'fulltextsearch' => 1, 'authorsearch' => 0, 'approve' => 0, 'status' => 0,
            'timecreated' => 0, 'timecreatedsortable' => 0, 'timemodified' => 0, 'timemodifiedsortable' => 0,
            'fieldlist' => json_encode([
                $this->ids['Title'] => $listed('Title'),
                $this->ids['Count'] => $listed('Count', true),
                $this->ids['Colour'] => $listed('Colour'),
                $this->ids['Tags'] => $listed('Tags'),
                $this->ids['Place'] => $listed('Place'),
            ]),
        ];
        $this->customfilter->id = (int) $DB->insert_record('datalynx_customfilters', $this->customfilter);
        \mod_datalynx\local\definition_cache::purge();
        $this->dlx = new datalynx($dlx->id());
    }

    /**
     * Open the browse page as the app does; arguments are strings.
     *
     * @param array $args
     * @return array the page state
     */
    private function page(array $args): array {
        $result = mobile::mobile_course_view(array_map('strval', $args + [
            'cmid' => $this->dlx->cm->id, 'courseid' => $this->course->id, 'viewid' => $this->viewid,
        ]));

        return json_decode($result['otherdata']['state'], true);
    }

    /**
     * The titles of the entries of a page state.
     *
     * @param array $state
     * @return string[]
     */
    private function titles(array $state): array {
        $titles = array_flip($this->entries);
        return array_map(fn($entry) => $titles[$entry['id']], $state['entries']);
    }

    /**
     * The app's form and the web's form send the same inputs, the app as text ("1,2"), the web
     * in its own shapes (arrays); both make the same search.
     */
    public function test_app_and_web_make_the_same_search(): void {
        $view = $this->dlx->get_view('grid', $this->viewid, false);
        $search = new customfilter_search($this->dlx, $this->customfilter);
        [$title, $count, $colour, $tags] = [$this->ids['Title'], $this->ids['Count'], $this->ids['Colour'], $this->ids['Tags']];

        $app = $search->criteria($view, customfilter_controls::values($search, $view, [
            'search' => 'a', (string) $title => 'an', "{$count}_from" => '4', "{$count}_to" => '',
            (string) $colour => '1,2', (string) $tags => '1,3', "{$tags}_mode" => 'all',
            'sort' => (string) $count, 'sortdir' => '1',
        ]));
        $web = $this->dlx->get_filter_manager()->get_filter_from_customfilterform(
            new datalynx_filter((object) ['id' => -2, 'dataid' => $this->dlx->id()]),
            (object) [
                'cf_search' => 'a', "cf_{$title}" => 'an', "cf_{$count}_from" => '4', "cf_{$count}_to" => '',
                "cf_{$colour}" => ['1', '2'], "cf_{$tags}" => ['1', '3'], "cf_{$tags}_mode" => 'all',
                'cf_sort' => (string) $count, 'cf_sortdir' => '1',
            ],
            $this->customfilter,
            $view
        );

        $this->assertSame('a', $app['search']);
        $this->assertSame($web->search, $app['search']);
        $this->assertEquals(unserialize($web->customsearch), $app['customsearch']);
        $this->assertEquals(unserialize($web->customsort), $app['customsort']);
    }

    /**
     * The page offers the view's custom filter; applying it filters, and the values travel with
     * every later request of the page.
     */
    public function test_apply_and_carry(): void {
        $state = $this->page([]);
        $this->assertSame([['id' => $this->customfilter->id, 'name' => 'Find', 'active' => false]], $state['customfilters']);
        $this->assertNull($state['customfilter']);
        $this->assertCount(3, $state['entries']);

        // Opening the filter: its form, nothing filtered yet.
        $state = $this->page(['customfilterid' => $this->customfilter->id]);
        $this->assertTrue($state['customfilters'][0]['active']);
        $controls = array_column($state['customfilter']['controls'], 'control', 'key');
        $this->assertSame([
            'search' => 'text',
            (string) $this->ids['Title'] => 'text',
            (string) $this->ids['Count'] => 'range',
            (string) $this->ids['Colour'] => 'select',
            (string) $this->ids['Tags'] => 'multiselect',
            (string) $this->ids['Place'] => 'unsupported',
        ], $controls);
        $this->assertSame(['Place'], $state['customfilter']['unsupported']);
        $this->assertSame([['value' => (string) $this->ids['Count'], 'label' => 'Count']], $state['customfilter']['sortfields']);
        $this->assertCount(3, $state['entries']);

        // Applying it: the form's inputs.
        $count = $this->ids['Count'];
        $state = $this->page([
            'customfilterid' => $this->customfilter->id, 'cfapply' => 1,
            "cf_{$count}_from" => '4', 'cf_sort' => (string) $count, 'cf_sortdir' => '1', 'cf_unknown' => 'x',
        ]);
        $this->assertSame(['Cherry', 'Banana'], $this->titles($state));
        $this->assertSame('4', $state['customfilter']['controls'][2]['from']);

        // A later request of the page (search, more entries) carries the values on.
        $carried = $state['customvalues'];
        $this->assertNotSame('', $carried);
        $state = $this->page(['customfilterid' => $this->customfilter->id, 'customvalues' => $carried, 'perpage' => 40]);
        $this->assertSame(['Cherry', 'Banana'], $this->titles($state));

        // Clearing: applying with nothing entered.
        $state = $this->page(['customfilterid' => $this->customfilter->id, 'cfapply' => 1]);
        $this->assertCount(3, $state['entries']);
        $this->assertSame('{}', $state['customvalues']);
    }

    /**
     * Option lists from the app (comma separated) filter like the web's selections.
     */
    public function test_options_any_and_all(): void {
        $tags = $this->ids['Tags'];
        $apply = fn(array $inputs) => $this->titles(
            $this->page(['customfilterid' => $this->customfilter->id, 'cfapply' => 1] + $inputs)
        );

        $this->assertEqualsCanonicalizing(['Apple', 'Cherry'], $apply(["cf_{$this->ids['Colour']}" => '1']));
        $this->assertEqualsCanonicalizing(['Apple', 'Cherry'], $apply(["cf_{$tags}" => '2', "cf_{$tags}_mode" => 'any']));
        $this->assertSame(['Apple'], $apply(["cf_{$tags}" => '1,2', "cf_{$tags}_mode" => 'all']));
        $this->assertSame(['Apple'], $apply(['cf_search' => 'Apple']));
    }

    /**
     * Days from the app's date inputs are whole days in the user's timezone.
     */
    public function test_days(): void {
        $timeid = $this->add_mobile_field($this->dlx, 'time', 'When');
        $view = $this->dlx->get_view('grid', $this->viewid, false);
        $record = clone $this->customfilter;
        $record->fieldlist = json_encode([$timeid => ['name' => 'When', 'sortable' => 0]]);
        $search = new customfilter_search(new datalynx($this->dlx->id()), $record);
        $view->view->param2 .= ' [[When]]';
        $view = (new datalynx($this->dlx->id()))->get_view('grid', $view->view, false);

        $values = customfilter_controls::values($search, $view, ["{$timeid}_from" => '2026-01-10', "{$timeid}_to" => '2026-01-10']);
        $tz = \core_date::get_user_timezone_object();
        $this->assertSame((new \DateTime('2026-01-10 00:00:00', $tz))->getTimestamp(), $values[(string) $timeid]['from']);
        $this->assertSame((new \DateTime('2026-01-10 23:59:59', $tz))->getTimestamp(), $values[(string) $timeid]['to']);
        $this->assertSame(
            ['from' => 0, 'to' => 0],
            customfilter_controls::values($search, $view, ["{$timeid}_from" => 'tomorrow'])[(string) $timeid]
        );
    }

    /**
     * A view without the tag offers no custom filter, and a custom filter it does not offer is refused.
     */
    public function test_only_the_views_custom_filters(): void {
        $other = $this->add_mobile_view($this->dlx, 'grid', 'Plain', '[[Title]]');

        $state = $this->page(['viewid' => $other]);
        $this->assertSame([], $state['customfilters']);

        $this->expectException(invalid_parameter_exception::class);
        $this->page(['viewid' => $other, 'customfilterid' => $this->customfilter->id]);
    }
}
