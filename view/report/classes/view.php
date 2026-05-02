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
 *
 * @package datalynxview_report
 * @subpackage report
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxview_report;

use html_table;
use html_table_cell;
use html_table_row;
use html_writer;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\view\base;
use mod_datalynx\output\report_view_browser as report_view_browser_renderable;
use stdClass;

/**
 * Report view class for datalynx.
 *
 * @package    datalynxview_report
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view extends base {
    /** @var string View type identifier. */
    protected string $type = 'report';

    /** @var string Output format type. */
    protected string $output = 'report';

    /** @var array List of editors. */
    protected array $editors = ['section'];

    /** @var ?array Cached report payload. */
    protected ?array $reportpayload = null;

    /** @var ?string Cached rendered report browser HTML. */
    protected ?string $reporthtml = null;

    /**
     * Constructor for datalynxview_report.
     *
     * @param mixed $df Datalynx instance or ID.
     * @param mixed $view View record or ID.
     * @param bool $filteroptions Whether to apply filter options.
     */
    public function __construct($df = 0, $view = 0, $filteroptions = true) {
        parent::__construct($df, $view, $filteroptions);
        if (!empty($this->view->param3)) {
            $this->output = $this->view->param3;
        }
    }

    /**
     * Report browse rendering is handled by display_entries().
     *
     * @param array $entriesset
     * @param string $name
     * @return array
     */
    protected function apply_entry_group_layout($entriesset, $name = '') {
        return [];
    }

    /**
     * Report views do not provide a new entry template.
     *
     * @param int $entryid
     * @return array
     */
    protected function new_entry_definition($entryid = -1) {
        return [];
    }

    /**
     * Ensure the report view always has a view-tag bucket, even with an empty section template.
     */
    protected function set__patterns() {
        parent::set__patterns();
        if (!isset($this->tags['view'])) {
            $this->tags['view'] = [];
        }
        if (!isset($this->tags['field'])) {
            $this->tags['field'] = [];
        }
    }

    /**
     * Display the report view.
     *
     * @param array $options
     * @return string
     */
    public function display(array $options = []): string {
        global $PAGE;

        $tohtml = $options['tohtml'] ?? false;
        $browsemode = !$this->returntoentriesform && !$this->user_is_editing() &&
            !optional_param('new', 0, PARAM_INT) && !$this->entriesprocessedsuccessfully;

        $this->set_view_tags($options);
        $browserregion = html_writer::tag(
            'div',
            $this->render_report_browser(),
            ['class' => 'mod-datalynx-report-entries', 'data-region' => 'report-view-browser']
        );
        $section = !empty($this->view->esection) ? $this->view->esection : '##entries##';
        $output = $this->print_notifications() . str_replace('##entries##', $browserregion, $section);
        $viewname = 'datalynxview-' . preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $this->name()));
        $output = html_writer::tag('div', $output, [
            'class' => $viewname,
            'data-viewname' => $this->name(),
            'data-id' => $this->dl->id(),
            'data-viewid' => $this->view->id,
        ]);

        if ($tohtml) {
            return $output;
        }

        echo $output;

        if ($browsemode) {
            $selector = '[data-id="' . $this->dl->id() . '"][data-viewid="' . $this->id() .
                '"] [data-region="report-view-browser"]';
            $args = [
                'd' => (int) $this->dl->id(),
                'view' => (int) $this->id(),
                'filterid' => (int) ($this->filter->id ?? 0),
                'page' => (int) ($this->filter->page ?? 0),
            ];
            if (!empty($this->filter->perpage)) {
                $args['perpage'] = (int) $this->filter->perpage;
            }
            if (!empty($this->filter->eids)) {
                $args['eids'] = is_array($this->filter->eids) ? implode(',', $this->filter->eids) : (string) $this->filter->eids;
            }
            if (!empty($this->filter->customsort)) {
                $args['customsort'] = $this->filter->customsort;
            }
            if (!empty($this->filter->customsearch)) {
                $args['customsearch'] = $this->filter->customsearch;
            }
            if (!empty($this->filter->search)) {
                $args['search'] = (string) $this->filter->search;
            }
            if (!empty($this->filter->selection)) {
                $args['selection'] = (int) $this->filter->selection;
            }
            if (!empty($this->filter->groupby)) {
                $args['groupby'] = (string) $this->filter->groupby;
            }
            if (!empty($this->filter->users)) {
                $args['users'] = is_array($this->filter->users)
                    ? implode(',', $this->filter->users)
                    : (string) $this->filter->users;
            }
            if (!empty($this->filter->groups)) {
                $args['groups'] = is_array($this->filter->groups)
                    ? implode(',', $this->filter->groups)
                    : (string) $this->filter->groups;
            }

            $PAGE->requires->js_call_amd('mod_datalynx/viewbrowser', 'init', [$selector, [
                'methodname' => 'mod_datalynx_get_report_view_data',
                'template' => 'mod_datalynx/report_view_browser',
                'args' => $args,
            ]]);
        }

        return '';
    }

    /**
     * Render the report content.
     *
     * @param ?array $options Display options.
     * @return string
     */
    public function display_entries(?array $options = null): string {
        return $this->render_report_browser();
    }

    /**
     * Build the structured report payload.
     *
     * @return array
     */
    public function get_report_payload(): array {
        global $DB;

        if ($this->reportpayload !== null) {
            return $this->reportpayload;
        }

        $payload = [
            'datalynxid' => $this->dl->id(),
            'viewid' => (int) $this->id(),
            'viewname' => format_string($this->name()),
            'viewtype' => $this->type(),
            'ismonthly' => $this->view->param2 === 'month',
            'hasdata' => false,
            'hasrows' => false,
            'hasmonthlysections' => false,
            'hasoverall' => false,
            'userlabel' => get_string('user'),
            'monthlabel' => get_string('month'),
            'totallabel' => get_string('total'),
            'notyetansweredlabel' => get_string('notyetanswered', 'question'),
            'aggregationsumlabel' => get_string('aggregationsum', 'reportbuilder'),
            'optioncolumns' => [],
            'rows' => [],
            'monthlysections' => [],
            'overall' => [
                'heading' => get_string('aggregationsum', 'reportbuilder'),
                'totalentries' => 0,
                'optioncells' => [],
                'notyetanswered' => 0,
            ],
            'emptycontent' => $this->display_no_entries(),
        ];

        $countfieldid = (int) ($this->view->param1 ?? 0);
        $countfield = $countfieldid ? $this->dl->get_field_from_id($countfieldid) : null;
        if (!$countfield || !method_exists($countfield, 'get_options')) {
            $this->reportpayload = $payload;
            return $this->reportpayload;
        }

        $optionlabels = $countfield->get_options();
        if (empty($optionlabels)) {
            $this->reportpayload = $payload;
            return $this->reportpayload;
        }

        foreach ($optionlabels as $label) {
            $payload['optioncolumns'][] = ['label' => format_string($label)];
        }
        $payload['overall']['optioncells'] = $this->normalise_option_counts([], $optionlabels);

        $entryrecords = $this->get_report_entries();
        if (empty($entryrecords)) {
            $this->reportpayload = $payload;
            return $this->reportpayload;
        }

        $userentryids = $this->get_report_user_entry_ids($entryrecords);
        if (empty($userentryids)) {
            $this->reportpayload = $payload;
            return $this->reportpayload;
        }

        $users = $DB->get_records_list(
            'user',
            'id',
            array_keys($userentryids),
            '',
            'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename,email'
        );
        $optioncountsbyentry = $this->get_report_option_counts_by_entry(array_keys($entryrecords), $countfieldid, $optionlabels);

        if ($payload['ismonthly']) {
            [$sections, $overall] = $this->build_monthly_sections(
                $userentryids,
                $entryrecords,
                $users,
                $optionlabels,
                $optioncountsbyentry
            );
            $payload['monthlysections'] = $sections;
            $payload['hasmonthlysections'] = !empty($sections);
            $payload['overall'] = $overall;
            $payload['hasoverall'] = $overall['totalentries'] > 0;
            $payload['hasdata'] = $payload['hasmonthlysections'];
        } else {
            $payload['rows'] = $this->build_rows(
                $userentryids,
                $entryrecords,
                $users,
                $optionlabels,
                $optioncountsbyentry
            );
            $payload['hasrows'] = !empty($payload['rows']);
            $payload['hasdata'] = $payload['hasrows'];
        }

        $this->reportpayload = $payload;
        return $this->reportpayload;
    }

    /**
     * Render the report browser template using the structured payload.
     *
     * @return string
     */
    protected function render_report_browser(): string {
        global $OUTPUT;

        if ($this->reporthtml !== null) {
            return $this->reporthtml;
        }

        $renderable = new report_view_browser_renderable($this->get_report_payload());
        $this->reporthtml = $OUTPUT->render_from_template(
            'mod_datalynx/report_view_browser',
            $renderable->export_for_template($OUTPUT)
        );

        return $this->reporthtml;
    }

    /**
     * Build the aggregated user/month rows.
     *
     * @param array $userentryids
     * @param stdClass[] $entryrecords
     * @param stdClass[] $users
     * @param array $optionlabels
     * @param array $optioncountsbyentry
     * @return array
     */
    protected function build_rows(
        array $userentryids,
        array $entryrecords,
        array $users,
        array $optionlabels,
        array $optioncountsbyentry
    ): array {
        $rows = [];

        foreach ($userentryids as $userid => $entryids) {
            if (empty($users[$userid])) {
                continue;
            }

            $monthlysummary = $this->build_user_monthly_summary($entryids, $entryrecords, $optionlabels, $optioncountsbyentry);
            foreach ($monthlysummary as $month => $summary) {
                $rows[] = [
                    'userhtml' => $this->format_report_user($users[$userid]),
                    'month' => $month,
                    'totalentries' => $summary['totalentries'],
                    'optioncells' => $this->normalise_option_counts($summary['matchingcontents'], $optionlabels),
                    'notyetanswered' => $this->calculate_notyetanswered(
                        $summary['totalentries'],
                        $summary['matchingcontents']
                    ),
                ];
            }
        }

        return $rows;
    }

    /**
     * Build the monthly grouped report sections and overall totals.
     *
     * @param array $userentryids
     * @param stdClass[] $entryrecords
     * @param stdClass[] $users
     * @param array $optionlabels
     * @param array $optioncountsbyentry
     * @return array
     */
    protected function build_monthly_sections(
        array $userentryids,
        array $entryrecords,
        array $users,
        array $optionlabels,
        array $optioncountsbyentry
    ): array {
        $sectionsbymonth = [];
        $overalltotals = $this->initialise_summary($optionlabels);

        foreach ($userentryids as $userid => $entryids) {
            if (empty($users[$userid])) {
                continue;
            }

            $monthlysummary = $this->build_user_monthly_summary($entryids, $entryrecords, $optionlabels, $optioncountsbyentry);
            foreach ($monthlysummary as $month => $summary) {
                if (empty($sectionsbymonth[$month])) {
                    $sectionsbymonth[$month] = [
                        'heading' => get_string('month') . ': ' . $month,
                        'rows' => [],
                        'totals' => $this->initialise_summary($optionlabels),
                    ];
                }

                $notyetanswered = $this->calculate_notyetanswered($summary['totalentries'], $summary['matchingcontents']);
                $sectionsbymonth[$month]['rows'][] = [
                    'userhtml' => $this->format_report_user($users[$userid]),
                    'totalentries' => $summary['totalentries'],
                    'optioncells' => $this->normalise_option_counts($summary['matchingcontents'], $optionlabels),
                    'notyetanswered' => $notyetanswered,
                ];
                $sectionsbymonth[$month]['totals'] = $this->merge_summaries($sectionsbymonth[$month]['totals'], $summary);
                $overalltotals = $this->merge_summaries($overalltotals, $summary);
            }
        }

        krsort($sectionsbymonth);
        $sections = [];
        foreach ($sectionsbymonth as $section) {
            $sections[] = [
                'heading' => $section['heading'],
                'rows' => $section['rows'],
                'totalentries' => $section['totals']['totalentries'],
                'optioncells' => $this->normalise_option_counts($section['totals']['matchingcontents'], $optionlabels),
                'notyetanswered' => $this->calculate_notyetanswered(
                    $section['totals']['totalentries'],
                    $section['totals']['matchingcontents']
                ),
            ];
        }

        $overall = [
            'heading' => get_string('aggregationsum', 'reportbuilder'),
            'totalentries' => $overalltotals['totalentries'],
            'optioncells' => $this->normalise_option_counts($overalltotals['matchingcontents'], $optionlabels),
            'notyetanswered' => $this->calculate_notyetanswered(
                $overalltotals['totalentries'],
                $overalltotals['matchingcontents']
            ),
        ];

        return [$sections, $overall];
    }

    /**
     * Build the per-user monthly summary buckets.
     *
     * @param int[] $entryids
     * @param stdClass[] $entryrecords
     * @param array $optionlabels
     * @param array $optioncountsbyentry
     * @return array
     */
    protected function build_user_monthly_summary(
        array $entryids,
        array $entryrecords,
        array $optionlabels,
        array $optioncountsbyentry
    ): array {
        $monthlysummary = [];

        foreach ($entryids as $entryid) {
            if (empty($entryrecords[$entryid])) {
                continue;
            }

            $month = userdate((int) $entryrecords[$entryid]->timecreated, '%Y-%m');
            if (empty($monthlysummary[$month])) {
                $monthlysummary[$month] = $this->initialise_summary($optionlabels);
            }

            $monthlysummary[$month]['totalentries']++;
            foreach ($optioncountsbyentry[$entryid] ?? [] as $label => $count) {
                $monthlysummary[$month]['matchingcontents'][$label] += $count;
            }
        }

        krsort($monthlysummary);
        return $monthlysummary;
    }

    /**
     * Initialise a summary accumulator.
     *
     * @param array $optionlabels
     * @return array
     */
    protected function initialise_summary(array $optionlabels): array {
        return [
            'totalentries' => 0,
            'matchingcontents' => array_fill_keys(array_values($optionlabels), 0),
        ];
    }

    /**
     * Merge one summary into another.
     *
     * @param array $target
     * @param array $source
     * @return array
     */
    protected function merge_summaries(array $target, array $source): array {
        $target['totalentries'] += $source['totalentries'];
        foreach ($source['matchingcontents'] as $label => $count) {
            $target['matchingcontents'][$label] += $count;
        }
        return $target;
    }

    /**
     * Convert option counts to template cells in the configured option order.
     *
     * @param array $counts
     * @param array $optionlabels
     * @return array
     */
    protected function normalise_option_counts(array $counts, array $optionlabels): array {
        $cells = [];
        foreach ($optionlabels as $label) {
            $cells[] = ['count' => (int) ($counts[$label] ?? 0)];
        }
        return $cells;
    }

    /**
     * Calculate the not-yet-answered total.
     *
     * @param int $totalentries
     * @param array $counts
     * @return int
     */
    protected function calculate_notyetanswered(int $totalentries, array $counts): int {
        return $totalentries - array_sum($counts);
    }

    /**
     * Format one user cell for the report table.
     *
     * @param stdClass $user
     * @return string
     */
    protected function format_report_user(stdClass $user): string {
        $name = fullname($user);
        if (!empty($user->email)) {
            return $name . '<br><small>' . s($user->email) . '</small>';
        }
        return $name;
    }

    /**
     * Fetch the filtered entries that should contribute to the report.
     *
     * @return stdClass[]
     */
    protected function get_report_entries(): array {
        global $DB;

        $entryids = $this->get_report_entryids();
        if (empty($entryids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($entryids, SQL_PARAMS_NAMED);
        return $DB->get_records_select(
            'datalynx_entries',
            "id $insql",
            $params,
            'timecreated ASC, id ASC',
            'id,userid,timecreated'
        );
    }

    /**
     * Group entry ids by the configured report user dimension.
     *
     * @param stdClass[] $entryrecords
     * @return array
     */
    protected function get_report_user_entry_ids(array $entryrecords): array {
        global $DB;

        $groupingid = (int) ($this->view->param4 ?? 0);
        if ($groupingid > 0) {
            $groupfield = $this->dl->get_field_from_id($groupingid);
            if (!$groupfield || !method_exists($groupfield, 'get_all_userids_in_all_entries')) {
                return [];
            }

            [$insql, $params] = $DB->get_in_or_equal(array_keys($entryrecords), SQL_PARAMS_NAMED, 'entry');
            $userentryids = $groupfield->get_all_userids_in_all_entries($insql, $params);
            foreach ($userentryids as $userid => $entryids) {
                $userentryids[$userid] = array_values(array_unique(array_map('intval', $entryids)));
            }
            ksort($userentryids);
            return $userentryids;
        }

        if ($groupingid !== -1) {
            return [];
        }

        $userentryids = [];
        foreach ($entryrecords as $entry) {
            $userentryids[(int) $entry->userid][] = (int) $entry->id;
        }
        ksort($userentryids);
        return $userentryids;
    }

    /**
     * Collect the counted field option hits for each report entry.
     *
     * @param int[] $entryids
     * @param int $fieldid
     * @param array $optionlabels
     * @return array
     */
    protected function get_report_option_counts_by_entry(array $entryids, int $fieldid, array $optionlabels): array {
        global $DB;

        if (empty($entryids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($entryids, SQL_PARAMS_NAMED, 'entry');
        $params['fieldid'] = $fieldid;
        $records = $DB->get_records_select(
            'datalynx_contents',
            "fieldid = :fieldid AND entryid $insql",
            $params,
            '',
            'entryid,content'
        );

        $counts = [];
        foreach ($records as $record) {
            $optionid = (int) $record->content;
            if (!array_key_exists($optionid, $optionlabels)) {
                continue;
            }
            $label = (string) $optionlabels[$optionid];
            $counts[(int) $record->entryid][$label] = ($counts[(int) $record->entryid][$label] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Get entry ids for the report across the full filtered result set.
     *
     * @return int[]
     */
    protected function get_report_entryids(): array {
        $entries = new datalynx_entries($this->dl, $this->filter);
        $options = [];
        $filter = clone $this->get_filter();
        $filter->perpage = 0;
        $options['filter'] = $filter;
        if ($ratingoptions = $this->is_rating()) {
            $options['ratings'] = $ratingoptions;
        }

        $entries->set_content($options);
        $exportentries = $entries->entries();

        if (!$exportentries) {
            return [];
        }

        return array_map('intval', array_keys($exportentries));
    }

    /**
     * Generates the view with default settings.
     */
    public function generate_default_view() {
        if (!$fields = $this->dl->get_fields()) {
            return;
        }

        $fields = parent::remove_duplicates($fields);

        $table = new html_table();
        $table->attributes['cellpadding'] = '2';

        $row1 = new html_table_row();
        $viewsmenu = new html_table_cell('##viewsmenu##');
        $seperator = new html_table_cell('     ');
        $filtersmenu = new html_table_cell('##filtersmenu##');
        $quicksearch = new html_table_cell('##quicksearch##');
        $quickperpage = new html_table_cell('##quickperpage##');
        $row1->cells = [$viewsmenu, $seperator, $filtersmenu, $quicksearch, $quickperpage];
        foreach ($row1->cells as $cell) {
            $cell->style = 'border:0 none;';
        }

        $row2 = new html_table_row();
        $addentries = new html_table_cell('');
        $addentries->colspan = 5;
        $row2->cells = [$addentries];
        foreach ($row2->cells as $cell) {
            $cell->style = 'border:0 none;';
        }

        $row3 = new html_table_row();
        $pagingbar = new html_table_cell('##pagingbar##');
        $pagingbar->colspan = 5;
        $row3->cells = [$pagingbar];
        foreach ($row3->cells as $cell) {
            $cell->style = 'border:0 none;';
        }

        $table->data = [$row1, $row2, $row3];
        $sectiondefault = html_writer::table($table);
        $this->view->esection = html_writer::tag(
            'div',
            $sectiondefault,
            ['class' => 'mdl-align']
        ) . '<div class="mod-datalynx-report-entries" data-region="report-view-browser">##entries##</div>';
    }
}
