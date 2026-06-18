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

    /** @var ?array Active temporal scope: field, mode, year, month, fromdate, todate, from, to. */
    protected ?array $reportscope = null;

    /**
     * Constructor for datalynxview_report.
     *
     * @param mixed $dlx Datalynx instance or ID.
     * @param mixed $view View record or ID.
     * @param bool $filteroptions Whether to apply filter options.
     */
    public function __construct($dlx = 0, $view = 0, $filteroptions = true) {
        parent::__construct($dlx, $view, $filteroptions);
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
        $inlinefieldview = !empty($options['fieldview']);
        $browsemode = !$inlinefieldview && !$this->returntoentriesform && !$this->user_is_editing() &&
            !optional_param('new', 0, PARAM_INT) && !$this->entriesprocessedsuccessfully;

        $this->set_view_tags($options);
        $browserregion = $browsemode
            ? $this->render_view_browser_region('mod-datalynx-report-entries', 'report-view-browser')
            : html_writer::tag(
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
            'data-id' => $this->dlx->id(),
            'data-viewid' => $this->view->id,
        ]);

        if ($browsemode) {
            $this->initialise_view_browser(
                'report-view-browser',
                'mod_datalynx_get_report_view_data',
                'mod_datalynx/report_view_browser'
            );
            $this->initialise_report_controls(
                'report-view-browser',
                'mod_datalynx_get_report_view_data',
                'mod_datalynx/report_view_browser'
            );
        }

        if ($tohtml) {
            return $output;
        }

        echo $output;

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
     * Register the AMD bootstrap for the date-scope controls.
     *
     * The controls re-fetch and re-render the browser region when changed.
     *
     * @param string $region
     * @param string $methodname
     * @param string $template
     * @return void
     */
    protected function initialise_report_controls(string $region, string $methodname, string $template): void {
        global $PAGE;

        $PAGE->requires->js_call_amd('mod_datalynx/report_controls', 'init', [
            $this->get_view_browser_selector($region),
            [
                'methodname' => $methodname,
                'template' => $template,
                'args' => $this->get_view_browser_arguments(),
                'errormessage' => get_string('ajaxviewloaderror', 'datalynx'),
            ],
        ]);
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
            'datalynxid' => $this->dlx->id(),
            'viewid' => (int) $this->id(),
            'viewname' => $this->name(),
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
            'hascharts' => false,
            'charts' => [],
            'scope' => $this->get_report_scope_payload(),
            'emptycontent' => $this->display_no_entries(),
        ];

        $countfieldid = (int) ($this->view->param1 ?? 0);
        $countfield = $countfieldid ? $this->dlx->get_field_from_id($countfieldid) : null;
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

        if ($payload['hasdata']) {
            $payload['charts'] = $this->build_report_charts(
                $optionlabels,
                $entryrecords,
                $userentryids,
                $users,
                $optioncountsbyentry
            );
            $payload['hascharts'] = !empty($payload['charts']);
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

        $exporter = new report_view_browser_renderable($this->get_report_payload(), $this->dlx->context);
        $this->reporthtml = $OUTPUT->render_from_template(
            'mod_datalynx/report_view_browser',
            $exporter->export($OUTPUT)
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
                    'user' => $this->export_report_user($users[$userid]),
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
                    'user' => $this->export_report_user($users[$userid]),
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
     * Build the structured user column for the report table.
     *
     * @param stdClass $user
     * @return array
     */
    protected function export_report_user(stdClass $user): array {
        return [
            'fullname' => fullname($user),
            'email' => !empty($user->email) ? $user->email : '',
            'hasemail' => !empty($user->email),
        ];
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
        $where = "id $insql";

        $scope = $this->get_report_scope();
        // The scope field is whitelisted in set_report_scope(), never interpolated from raw input.
        if (!empty($scope['from'])) {
            $where .= " AND {$scope['field']} >= :tfrom";
            $params['tfrom'] = (int) $scope['from'];
        }
        if (!empty($scope['to'])) {
            $where .= " AND {$scope['field']} <= :tto";
            $params['tto'] = (int) $scope['to'];
        }

        return $DB->get_records_select(
            'datalynx_entries',
            $where,
            $params,
            'timecreated ASC, id ASC',
            'id,userid,timecreated,timemodified'
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
            $groupfield = $this->dlx->get_field_from_id($groupingid);
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
        $entries = new datalynx_entries($this->dlx, $this->filter);
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
     * Build the native charts (pie + bar) for the report from the aggregated data.
     *
     * @param array $optionlabels Option id => label for the counted field.
     * @param stdClass[] $entryrecords Entry records keyed by entry id.
     * @param array $userentryids User/group id => entry ids.
     * @param stdClass[] $users User records keyed by id.
     * @param array $optioncountsbyentry Entry id => [label => count].
     * @return array One descriptor per chart: uniqid, title, chartdata, withtable.
     */
    protected function build_report_charts(
        array $optionlabels,
        array $entryrecords,
        array $userentryids,
        array $users,
        array $optioncountsbyentry
    ): array {
        $charts = [];
        $labels = array_values(array_map('format_string', $optionlabels));

        // Option distribution (pie): total hits per option across all entries.
        $optiontotals = array_fill_keys(array_values($optionlabels), 0);
        foreach ($optioncountsbyentry as $counts) {
            foreach ($counts as $label => $count) {
                $optiontotals[$label] = ($optiontotals[$label] ?? 0) + $count;
            }
        }
        if (array_sum($optiontotals) > 0) {
            $pie = new \core\chart_pie();
            $pie->set_labels($labels);
            $pie->add_series(new \core\chart_series(
                get_string('optiondistribution', 'datalynxview_report'),
                array_values($optiontotals)
            ));
            $charts[] = $this->wrap_chart('optiondist', get_string('optiondistribution', 'datalynxview_report'), $pie);
        }

        // Entries over time (bar): entry counts per month.
        $bymonth = [];
        foreach ($entryrecords as $entry) {
            $month = userdate((int) $entry->timecreated, '%Y-%m');
            $bymonth[$month] = ($bymonth[$month] ?? 0) + 1;
        }
        if (!empty($bymonth)) {
            ksort($bymonth);
            $timebar = new \core\chart_bar();
            $timebar->set_labels(array_keys($bymonth));
            $timebar->add_series(new \core\chart_series(
                get_string('entriesovertime', 'datalynxview_report'),
                array_values($bymonth)
            ));
            $charts[] = $this->wrap_chart('overtime', get_string('entriesovertime', 'datalynxview_report'), $timebar);
        }

        // Per-user / per-group totals (bar): entry count per user or grouping value.
        $usertotals = [];
        foreach ($userentryids as $userid => $entryids) {
            if (empty($users[$userid])) {
                continue;
            }
            $count = 0;
            foreach ($entryids as $entryid) {
                if (!empty($entryrecords[$entryid])) {
                    $count++;
                }
            }
            if ($count > 0) {
                $usertotals[fullname($users[$userid])] = $count;
            }
        }
        if (!empty($usertotals)) {
            arsort($usertotals);
            $userbar = new \core\chart_bar();
            $userbar->set_labels(array_keys($usertotals));
            $userbar->add_series(new \core\chart_series(
                get_string('usertotals', 'datalynxview_report'),
                array_values($usertotals)
            ));
            $charts[] = $this->wrap_chart('usertotals', get_string('usertotals', 'datalynxview_report'), $userbar);
        }

        return $charts;
    }

    /**
     * Wrap a chart instance into a template descriptor with a deterministic id.
     *
     * @param string $key Stable chart key (combined with the view id).
     * @param string $title Chart heading.
     * @param \core\chart_base $chart The chart instance.
     * @return array
     */
    protected function wrap_chart(string $key, string $title, \core\chart_base $chart): array {
        return [
            'uniqid' => 'dlreport' . (int) $this->id() . $key,
            'title' => $title,
            'chartdata' => json_encode($chart),
            'withtable' => true,
        ];
    }

    /**
     * Set the active temporal scope from a raw selection and compute its bounds.
     *
     * @param array $scope Keys: field, mode, year, month, fromdate, todate.
     * @return void
     */
    public function set_report_scope(array $scope): void {
        $field = (($scope['field'] ?? '') === 'timemodified') ? 'timemodified' : 'timecreated';
        $mode = $scope['mode'] ?? 'all';
        if (!in_array($mode, ['all', 'year', 'month', 'range'], true)) {
            $mode = 'all';
        }
        $year = (int) ($scope['year'] ?? 0);
        $month = (int) ($scope['month'] ?? 0);
        $fromdate = (string) ($scope['fromdate'] ?? '');
        $todate = (string) ($scope['todate'] ?? '');

        [$from, $to] = $this->compute_scope_bounds($mode, $year, $month, $fromdate, $todate);

        $this->reportscope = [
            'field' => $field,
            'mode' => $mode,
            'year' => $year,
            'month' => $month,
            'fromdate' => $fromdate,
            'todate' => $todate,
            'from' => $from,
            'to' => $to,
        ];
        // Invalidate cached payload/markup so the new scope takes effect.
        $this->reportpayload = null;
        $this->reporthtml = null;
    }

    /**
     * Get the active temporal scope, defaulting to an unbounded created-time scope.
     *
     * @return array
     */
    protected function get_report_scope(): array {
        return $this->reportscope ?? [
            'field' => 'timecreated',
            'mode' => 'all',
            'year' => 0,
            'month' => 0,
            'fromdate' => '',
            'todate' => '',
            'from' => 0,
            'to' => 0,
        ];
    }

    /**
     * Compute the [from, to] timestamp bounds for a scope selection.
     *
     * @param string $mode all|year|month|range
     * @param int $year
     * @param int $month
     * @param string $fromdate YYYY-MM-DD
     * @param string $todate YYYY-MM-DD
     * @return array{0:int,1:int} From and to timestamps (0 means unbounded).
     */
    protected function compute_scope_bounds(string $mode, int $year, int $month, string $fromdate, string $todate): array {
        switch ($mode) {
            case 'year':
                if ($year <= 0) {
                    return [0, 0];
                }
                return [
                    make_timestamp($year, 1, 1, 0, 0, 0),
                    make_timestamp($year, 12, 31, 23, 59, 59),
                ];
            case 'month':
                if ($year <= 0 || $month < 1 || $month > 12) {
                    return [0, 0];
                }
                $lastday = (int) date('t', make_timestamp($year, $month, 1, 12, 0, 0));
                return [
                    make_timestamp($year, $month, 1, 0, 0, 0),
                    make_timestamp($year, $month, $lastday, 23, 59, 59),
                ];
            case 'range':
                $from = $this->parse_scope_date($fromdate, false);
                $to = $this->parse_scope_date($todate, true);
                return [$from, $to];
            default:
                return [0, 0];
        }
    }

    /**
     * Parse a YYYY-MM-DD scope date into a day-start or day-end timestamp.
     *
     * @param string $date
     * @param bool $endofday Whether to return the end-of-day second.
     * @return int Timestamp, or 0 when the date is empty/invalid.
     */
    protected function parse_scope_date(string $date, bool $endofday): int {
        if (!preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', trim($date), $m)) {
            return 0;
        }
        $year = (int) $m[1];
        $month = (int) $m[2];
        $day = (int) $m[3];
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return 0;
        }
        return $endofday
            ? make_timestamp($year, $month, $day, 23, 59, 59)
            : make_timestamp($year, $month, $day, 0, 0, 0);
    }

    /**
     * Build the date-scoping control payload (labels, current selection, options).
     *
     * @return array
     */
    protected function get_report_scope_payload(): array {
        $scope = $this->get_report_scope();

        $createdlabel = get_string('timecreated', 'datalynxview_report');
        $modifiedlabel = get_string('timemodified', 'datalynxview_report');
        $fields = [
            $this->scope_option('timecreated', $createdlabel, $scope['field'] === 'timecreated'),
            $this->scope_option('timemodified', $modifiedlabel, $scope['field'] === 'timemodified'),
        ];

        $modes = [];
        foreach (['all', 'year', 'month', 'range'] as $modekey) {
            $modes[] = $this->scope_option(
                $modekey,
                get_string('scopemode_' . $modekey, 'datalynxview_report'),
                $scope['mode'] === $modekey
            );
        }

        $years = [];
        foreach ($this->get_report_available_years($scope['field']) as $year) {
            $years[] = $this->scope_option((string) $year, (string) $year, $scope['year'] === $year);
        }

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $label = userdate(make_timestamp(2000, $m, 1, 12, 0, 0), '%B');
            $months[] = $this->scope_option((string) $m, $label, $scope['month'] === $m);
        }

        return [
            'fieldlabel' => get_string('scopefield', 'datalynxview_report'),
            'modelabel' => get_string('scopemode', 'datalynxview_report'),
            'yearlabel' => get_string('year'),
            'monthlabel' => get_string('month'),
            'fromlabel' => get_string('scopefrom', 'datalynxview_report'),
            'tolabel' => get_string('scopeto', 'datalynxview_report'),
            'field' => $scope['field'],
            'mode' => $scope['mode'],
            'from' => $scope['from'],
            'to' => $scope['to'],
            'fromdate' => $scope['fromdate'],
            'todate' => $scope['todate'],
            'showyear' => in_array($scope['mode'], ['year', 'month'], true),
            'showmonth' => $scope['mode'] === 'month',
            'showrange' => $scope['mode'] === 'range',
            'fields' => $fields,
            'modes' => $modes,
            'years' => $years,
            'months' => $months,
        ];
    }

    /**
     * Build one select-option descriptor for the scope controls.
     *
     * @param string $value
     * @param string $label
     * @param bool $selected
     * @return array
     */
    protected function scope_option(string $value, string $label, bool $selected): array {
        return ['value' => $value, 'label' => $label, 'selected' => $selected];
    }

    /**
     * Get the list of years covered by the filtered entries for the given time field.
     *
     * @param string $field timecreated|timemodified
     * @return int[] Years in descending order.
     */
    protected function get_report_available_years(string $field): array {
        global $DB;

        $field = $field === 'timemodified' ? 'timemodified' : 'timecreated';
        $entryids = $this->get_report_entryids();
        if (empty($entryids)) {
            return [(int) userdate(time(), '%Y')];
        }

        [$insql, $params] = $DB->get_in_or_equal($entryids, SQL_PARAMS_NAMED);
        $bounds = $DB->get_record_select(
            'datalynx_entries',
            "id $insql AND $field > 0",
            $params,
            "MIN($field) AS mintime, MAX($field) AS maxtime"
        );

        $currentyear = (int) userdate(time(), '%Y');
        if (empty($bounds) || empty($bounds->maxtime)) {
            return [$currentyear];
        }

        $minyear = (int) userdate((int) $bounds->mintime, '%Y');
        $maxyear = max($currentyear, (int) userdate((int) $bounds->maxtime, '%Y'));

        $years = range($maxyear, $minyear);
        return array_map('intval', $years);
    }

    /**
     * Generates the view with default settings.
     */
    public function generate_default_view() {
        if (!$fields = $this->dlx->get_fields()) {
            return;
        }

        $fields = parent::remove_duplicates($fields);

        // Set views and filters menus and quick search.
        $this->view->esection = $this->get_default_esection_html('report');
    }
}
