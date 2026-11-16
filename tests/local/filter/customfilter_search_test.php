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

namespace mod_datalynx\local\filter;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use advanced_testcase;
use mod_datalynx\datalynx;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\mobile_test_trait;

/**
 * One search for the custom filter of the web and of the Moodle App (mod_datalynx#270).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\filter\customfilter_search
 * @covers     \mod_datalynx\local\filter\datalynx_filter_manager::get_filter_from_customfilterform
 */
final class customfilter_search_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var int[] field ids by name */
    private array $ids = [];

    /** @var int */
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

        [, $dlx] = $this->create_mobile_instance(['approval' => 1]);
        $this->ids['Title'] = $this->add_mobile_field($dlx, 'text', 'Title');
        // A user field that happens to carry the name of an internal one.
        $this->ids['Status'] = $this->add_mobile_field($dlx, 'text', 'Status');
        $this->ids['Count'] = $this->add_mobile_field($dlx, 'number', 'Count');
        $this->ids['When'] = $this->add_mobile_field($dlx, 'time', 'When');
        $this->ids['Colour'] = $this->add_mobile_field($dlx, 'select', 'Colour', ['param1' => "Red\nGreen"]);
        $this->ids['Tags'] = $this->add_mobile_field($dlx, 'multiselect', 'Tags', ['param1' => "Big\nSmall\nOld"]);
        $this->viewid = $this->add_mobile_view(
            $dlx,
            'grid',
            'Search',
            '[[Title]] [[Status]] [[Count]] [[When]] [[Colour]] [[Tags]] ##approve## ##status## ##timecreated##'
        );

        $day = 86400;
        $base = mktime(12, 0, 0, 1, 10, 2026);
        $rows = [
            'Apple' => ['Count' => '3', 'When' => $base, 'Colour' => '1', 'Tags' => '#1#,#2#', 'created' => $base],
            'Banana' => ['Count' => '5', 'When' => $base + 2 * $day, 'Colour' => '2', 'Tags' => '#1#', 'created' => $base + $day],
            'Cherry' => [
                'Count' => '10', 'When' => $base + 5 * $day, 'Colour' => '1', 'Tags' => '#2#,#3#', 'created' => $base + 2 * $day,
            ],
            'Damson' => ['Count' => '12', 'Colour' => '2', 'created' => $base + 3 * $day],
        ];
        foreach ($rows as $title => $row) {
            $contents = [$this->ids['Title'] => $title];
            foreach (['Count', 'When', 'Colour', 'Tags'] as $name) {
                if (isset($row[$name])) {
                    $contents[$this->ids[$name]] = (string) $row[$name];
                }
            }
            $this->entries[$title] = $this->add_mobile_entry($dlx, $USER->id, $contents, $row['created']);
        }
        $DB->set_field('datalynx_entries', 'approved', 0, ['id' => $this->entries['Banana']]);
        $DB->set_field('datalynx_entries', 'status', 1, ['id' => $this->entries['Cherry']]);

        $listed = fn(string $name, bool $sortable = false) => ['name' => $name, 'sortable' => $sortable ? 1 : 0];
        $record = (object) [
            'dataid' => $dlx->id(), 'name' => 'Find', 'description' => '', 'visible' => 1,
            'fulltextsearch' => 1, 'authorsearch' => 1, 'approve' => 1, 'status' => 1,
            'timecreated' => 1, 'timecreatedsortable' => 1, 'timemodified' => 1, 'timemodifiedsortable' => 0,
            'fieldlist' => json_encode([
                $this->ids['Title'] => $listed('Title'),
                $this->ids['Count'] => $listed('Count', true),
                $this->ids['When'] => $listed('When'),
                $this->ids['Colour'] => $listed('Colour'),
                $this->ids['Tags'] => $listed('Tags'),
            ]),
        ];
        $record->id = (int) $DB->insert_record('datalynx_customfilters', $record);
        $this->customfilter = $record;
        $this->dlx = new datalynx($dlx->id());
    }

    /**
     * The view the filter is used in.
     *
     * @return \mod_datalynx\local\view\base
     */
    private function view(): \mod_datalynx\local\view\base {
        return $this->dlx->get_view('grid', $this->viewid, false);
    }

    /**
     * The titles of the entries a set of values finds, in the order shown.
     *
     * @param array $values
     * @return string[]
     */
    private function find(array $values): array {
        $view = $this->view();
        $criteria = (new customfilter_search($this->dlx, $this->customfilter))->criteria($view, $values);
        $view->set_filter([
            'search' => $criteria['search'],
            'customsearch' => $criteria['customsearch'],
            'customsort' => $criteria['customsort'],
        ], true);
        $found = (new datalynx_entries($this->dlx, $view->get_filter()))->get_entries()->entries ?? [];
        $titles = array_flip($this->entries);

        return array_map(fn($id) => $titles[(int) $id], array_keys($found));
    }

    /**
     * The filter offers what it lists and the internal fields it turns on, all by id and in the
     * order of the view; a user field named like an internal one is not taken for it.
     */
    public function test_fields_and_sort_fields(): void {
        $search = new customfilter_search($this->dlx, $this->customfilter);

        // The view shows timemodified as well; the user field "Status" is not listed.
        $this->assertEqualsCanonicalizing(
            [$this->ids['Title'], $this->ids['Count'], $this->ids['When'], $this->ids['Colour'], $this->ids['Tags'],
                'approve', 'status', 'timecreated', 'timemodified'],
            array_keys($search->get_fields($this->view()))
        );
        $this->assertNotContains($this->ids['Status'], array_keys($search->get_fields($this->view())));
        $sortfields = array_keys($search->get_sort_fields($this->view()));
        $this->assertEqualsCanonicalizing([$this->ids['Count'], 'timecreated'], $sortfields);

        // Internal fields only when turned on.
        $off = clone $this->customfilter;
        $off->status = 0;
        $off->approve = 0;
        $this->assertNotContains('status', array_keys((new customfilter_search($this->dlx, $off))->get_fields($this->view())));
        $this->assertNotContains('approve', array_keys((new customfilter_search($this->dlx, $off))->get_fields($this->view())));
    }

    /**
     * Each control searches as the field says; nothing entered, no condition.
     */
    public function test_criteria_per_control(): void {
        $all = ['Apple', 'Banana', 'Cherry', 'Damson'];
        $this->assertEqualsCanonicalizing($all, $this->find([]));
        $this->assertSame([], (new customfilter_search($this->dlx, $this->customfilter))->criteria($this->view(), [
            (string) $this->ids['Count'] => ['from' => '', 'to' => ''],
            (string) $this->ids['When'] => ['from' => 0, 'to' => 0],
            (string) $this->ids['Colour'] => [-1],
            'approve' => '-1',
            'status' => '',
        ])['customsearch']);

        $this->assertSame(['Apple'], $this->find([(string) $this->ids['Title'] => 'ppl']));
        $this->assertSame(['Apple'], $this->find(['search' => 'Apple']));

        // Number ranges, inclusive, either bound alone.
        $count = (string) $this->ids['Count'];
        $this->assertEqualsCanonicalizing(['Banana', 'Cherry'], $this->find([$count => ['from' => '5', 'to' => '10']]));
        $this->assertEqualsCanonicalizing(['Apple', 'Banana'], $this->find([$count => ['from' => '', 'to' => '5']]));
        $this->assertEqualsCanonicalizing(['Cherry', 'Damson'], $this->find([$count => ['from' => '10', 'to' => '']]));

        // Time ranges, inclusive; an entry without a time is not in a range.
        $when = (string) $this->ids['When'];
        $base = mktime(12, 0, 0, 1, 10, 2026);
        $this->assertEqualsCanonicalizing(
            ['Apple', 'Banana'],
            $this->find([$when => ['from' => $base, 'to' => $base + 2 * 86400]])
        );
        $this->assertEqualsCanonicalizing(['Banana', 'Cherry'], $this->find([$when => ['from' => $base + 1, 'to' => 0]]));

        // Options: any of the chosen ones; the empty choice is no option.
        $this->assertEqualsCanonicalizing(['Apple', 'Cherry'], $this->find([(string) $this->ids['Colour'] => [-1, 1]]));
        $tags = (string) $this->ids['Tags'];
        $this->assertEqualsCanonicalizing(['Apple', 'Cherry'], $this->find([$tags => ['ids' => [2], 'mode' => 'any']]));
        $this->assertEqualsCanonicalizing(['Apple'], $this->find([$tags => ['ids' => [1, 2], 'mode' => 'all']]));

        // Internal fields.
        $this->assertSame(['Banana'], $this->find(['approve' => '0']));
        $this->assertSame(['Cherry'], $this->find(['status' => '1']));
        $created = mktime(12, 0, 0, 1, 12, 2026);
        $this->assertEqualsCanonicalizing(['Cherry', 'Damson'], $this->find(['timecreated' => ['from' => $created, 'to' => 0]]));
        $this->assertEqualsCanonicalizing(
            ['Apple', 'Banana', 'Cherry'],
            $this->find(['timecreated' => ['from' => 0, 'to' => $created]])
        );
    }

    /**
     * The chosen sort applies only to a field the filter lets the user sort by.
     */
    public function test_sort(): void {
        $count = (string) $this->ids['Count'];
        $this->assertSame(['Damson', 'Cherry', 'Banana', 'Apple'], $this->find(['sort' => ['field' => $count, 'dir' => 1]]));

        $criteria = (new customfilter_search($this->dlx, $this->customfilter))
            ->criteria($this->view(), ['sort' => ['field' => (string) $this->ids['Title'], 'dir' => 1]]);
        $this->assertSame([], $criteria['customsort']);
    }

    /**
     * The web's custom filter form sends its inputs in its own shapes (lists as arrays, days as
     * timestamps); read by customfilter_controls they make the search the values stand for.
     */
    public function test_form_inputs_make_the_search(): void {
        $count = $this->ids['Count'];
        $when = $this->ids['When'];
        $colour = $this->ids['Colour'];
        $tags = $this->ids['Tags'];
        $from = make_timestamp(2026, 1, 1);
        // As the frontend form submits it (customfilter_controls::PREFIX).
        $formdata = (object) [
            'cf_search' => 'a',
            "cf_{$this->ids['Title']}" => 'an',
            "cf_{$count}_from" => '',
            "cf_{$count}_to" => '10',
            "cf_{$when}_from" => $from,
            "cf_{$when}_to" => 0,
            "cf_{$colour}" => ['-1', '2'],
            "cf_{$tags}" => ['1'],
            "cf_{$tags}_mode" => 'all',
            'cf_approve' => '1',
            'cf_status' => '',
            'cf_timecreated_from' => $from,
            'cf_timecreated_to' => 0,
            'cf_sort' => (string) $count,
            'cf_sortdir' => '1',
        ];
        $values = [
            'search' => 'a',
            (string) $this->ids['Title'] => 'an',
            (string) $count => ['from' => '', 'to' => '10'],
            (string) $when => ['from' => $from, 'to' => 0],
            (string) $colour => ['-1', '2'],
            (string) $tags => ['ids' => ['1'], 'mode' => 'all'],
            'approve' => '1',
            'status' => '',
            'timecreated' => ['from' => $from, 'to' => 0],
            'sort' => ['field' => (string) $count, 'dir' => 1],
        ];

        $filter = new datalynx_filter((object) ['id' => -2, 'dataid' => $this->dlx->id()]);
        $manager = $this->dlx->get_filter_manager();
        $filter = $manager->get_filter_from_customfilterform($filter, $formdata, $this->customfilter, $this->view());
        $criteria = (new customfilter_search($this->dlx, $this->customfilter))->criteria($this->view(), $values);

        $this->assertSame('a', $filter->search);
        $this->assertEquals($criteria['customsearch'], unserialize($filter->customsearch));
        $this->assertEquals($criteria['customsort'], unserialize($filter->customsort));
        $this->assertEqualsCanonicalizing(
            [$this->ids['Title'], $count, $when, $colour, $tags, 'approve', 'timecreated'],
            array_keys($criteria['customsearch'])
        );
    }

    /**
     * The web's custom filter form is built from the controls: one input per control, named as
     * the app names it, and nothing for a field the filter does not offer.
     */
    public function test_form_shows_the_controls(): void {
        $view = $this->view();
        $filter = new datalynx_filter((object) ['id' => -2, 'dataid' => $this->dlx->id()]);
        $form = $this->dlx->get_filter_manager()->get_customfilter_frontend_form($filter, $view, $this->customfilter);
        $html = $form->render();

        $search = new customfilter_search($this->dlx, $this->customfilter);
        foreach (customfilter_controls::describe($search, $view, [])['controls'] as $control) {
            $pattern = '/name="cf_' . preg_quote($control['key'], '/') . '[\[_"]/';
            $this->assertMatchesRegularExpression($pattern, $html, $control['key']);
        }
        $this->assertMatchesRegularExpression('/name="cf_sort"/', $html);
        $this->assertStringNotContainsString("cf_{$this->ids['Status']}", $html);
        $this->assertStringNotContainsString('name="f_', $html);
    }

    /**
     * A tag that no entry carries finds nothing, and its negation everything, without an SQL error.
     */
    public function test_tag_without_match(): void {
        $tagid = $this->add_mobile_field($this->dlx, 'tag', 'Keywords');
        $field = (new datalynx($this->dlx->id()))->get_field_from_id($tagid);

        [$sql, $params] = $field->get_search_sql(['', 'ANY_OF', ['nosuchtag']]);
        $this->assertSame(' 1 = 0 ', $sql);
        [$sql] = $field->get_search_sql(['NOT', 'ANY_OF', ['nosuchtag']]);
        $this->assertSame(' 1 = 1 ', $sql);
        $this->assertSame([], $params);
    }
}
