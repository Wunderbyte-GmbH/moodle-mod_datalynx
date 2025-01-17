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
 * @package datalynxview
 * @subpackage report
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_datalynx\view\base;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/classes/view/base.php");

class datalynxview_report extends base {

    protected string $type = 'report';

    protected string $_output = 'report';

    protected array $_editors = ['section'];

    protected ?array $_columns = null;

    /**
     */
    public function __construct($df = 0, $view = 0) {
        parent::__construct($df, $view);
        if (!empty($this->view->param3)) {
            $this->_output = $this->view->param3;
        }
    }

    /**
     */
    protected function apply_entry_group_layout($entriesset, $name = '') {
        global $OUTPUT;

        $elements = [];
        // Open table and wrap header with thead.
        $elements[] = array('html',
                html_writer::start_tag('table', array('class' => 'generaltable')));

        // Flatten the set to a list of elements, wrap with tbody and close table.
        $elements[] = array('html', html_writer::start_tag('tbody'));
        foreach ($entriesset as $entryid => $entrydefinitions) {
            $elements = array_merge($elements, $entrydefinitions);
        }
        $elements[] = array('html', html_writer::end_tag('tbody') . html_writer::end_tag('table'));

        // Add group heading.
        $name = ($name == 'newentry') ? get_string('entrynew', 'datalynx') : $name;
        if ($name) {
            array_unshift($elements, array('html', $OUTPUT->heading($name, 3, 'main')));
        }
        // Wrap with entriesview.
        array_unshift($elements, array('html', html_writer::start_tag('div', array('class' => 'entriesview'))));
        $elements[] = array('html', html_writer::end_tag('div'));

        return $elements;
    }

    /**
     * No new entry with this view.
     * @param $entryid
     * @return array
     */
    protected function new_entry_definition($entryid = -1) {
        return [];
    }

    /**
     * @param $fielddefinitions
     * @return array
     */
    protected function entry_definition($fielddefinitions) {
        $elements = [];
        // Get the columns definition from the view template.
        $columns = $this->get_columns();

        // Generate entry table row.
        $elements[] = array('html', html_writer::start_tag('tr'));
        foreach ($columns as $column) {
            list($tag, , $class) = array_map('trim', $column);
            if (!empty($fielddefinitions[$tag])) {
                $fielddefinition = $fielddefinitions[$tag];
                if ($fielddefinition[0] == 'html') {
                    $elements[] = array('html',
                            html_writer::tag('td', $fielddefinition[1], array('class' => $class))
                    );
                } else {
                    $elements[] = array('html',
                            html_writer::start_tag('td', array('class' => $class))
                    );
                    $elements[] = $fielddefinition;
                    $elements[] = array('html', html_writer::end_tag('td'));
                }
            } else {
                $elements[] = array('html', html_writer::tag('td', '', array('class' => $class)));
            }
        }
        $elements[] = array('html', html_writer::end_tag('tr'));

        return $elements;
    }

    /**
     * Add the patterns we store in param2 to all the other used patterns.
     */
    protected function set__patterns() {
        parent::set__patterns();
    }

    /**
     * @param array $options
     * @return string
     */
    public function display(array $options = []): string {
        echo parent::display(['tohtml' => true]);
        return '';
    }

    /**
     * Do not display entries in report view instead display the report.
     * @return string
     */
    public function display_entries(?array $options = null): string {
        global $DB, $OUTPUT;
        $report = $this->create_report_sql();
        $fieldid = (int) $this->view->param1;
        $countfield = $this->_df->get_field_from_id($fieldid);
        $desiredfieldoptions = $countfield->get_options();

        $output = html_writer::start_tag('div', ['class' => 'table-responsive']);

        if ($this->view->param2 === 'month') {
            // Collect and aggregate data by month
            $monthlydatabymonth = [];
            $overalltotalentriessum = 0;
            $overallmatchingcontentssums = array_fill_keys($desiredfieldoptions, 0);
            $overallnotyeteditedsum = 0;

            foreach ($report as $userid => $data) {
                $user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, email');
                $userinfo = "{$user->firstname} {$user->lastname} ({$user->id})<br>{$user->email}";

                foreach ($data['monthly'] as $month => $monthlydata) {
                    if (!isset($monthlydatabymonth[$month])) {
                        $monthlydatabymonth[$month] = [];
                    }

                    $monthlydatabymonth[$month][$userid] = [
                            'userinfo' => $userinfo,
                            'totalentries' => $monthlydata['totalentries'],
                            'matchingcontents' => $monthlydata['matchingcontents'],
                    ];
                }
            }

            // Sort months in descending order
            krsort($monthlydatabymonth);

            // Create containers for chart output
            $chartoutput = '';
            $overallchartdata = [];
            foreach ($desiredfieldoptions as $label) {
                $overallchartdata[$label] = 0;
            }

            foreach ($monthlydatabymonth as $month => $usersdata) {
                // Initialize sum variables for this month's table
                $totalentriessum = 0;
                $matchingcontentssums = array_fill_keys($desiredfieldoptions, 0);
                $notyetansweredsum = 0;

                foreach ($usersdata as $userid => $user_month_data) {
                    // Total entries for this user in this month
                    $usertotalentries = $user_month_data['totalentries'];
                    $totalentriessum += $usertotalentries;
                    $overalltotalentriessum += $usertotalentries;

                    // Matching contents counts and "Not Yet Answered" for each user
                    $summatchingcontents = 0;
                    foreach ($desiredfieldoptions as $label) {
                        $count = $user_month_data['matchingcontents'][$label] ?? 0;
                        if ($count !== 0) {
                            $summatchingcontents += $count;
                            $matchingcontentssums[$label] += $count;
                            $overallmatchingcontentssums[$label] += $count;
                            $overallchartdata[$label] += $count;
                        }
                    }

                    $notyetanswered = $usertotalentries - $summatchingcontents;
                    $notyetansweredsum += $notyetanswered;
                    $overallnotyeteditedsum += $notyetanswered;
                    $overallchartdata['notyetanswered'] = isset($overallchartdata['notyetanswered']) ?
                            $overallchartdata['notyetanswered'] + $notyetanswered : $notyetanswered;
                }

                // Create the monthly pie chart
                $chart = new \core\chart_pie();
                $chart->set_title(get_string('month') . ": " . $month);
                $labels = array_merge($desiredfieldoptions, [get_string('notyetanswered', 'question')]);
                $values = array_merge(array_values($matchingcontentssums), [$notyetansweredsum]);
                $series = new \core\chart_series(get_string('aggregationsum', 'reportbuilder'), $values);
                $chart->add_series($series);
                $chart->set_labels($labels);
                $chartoutput .= html_writer::start_div('col-md-6 col-lg-4 mb-4');
                $chartoutput .= $OUTPUT->render($chart);
                $chartoutput .= html_writer::end_div();
            }

            // Create the overall pie chart
            $overallchart = new \core\chart_pie();
            $overallchart->set_title(get_string('aggregationsum', 'reportbuilder'));
            $overallseries = new \core\chart_series(get_string('aggregationsum', 'reportbuilder'), array_values($overallchartdata));
            $overallchart->add_series($overallseries);
            $overallchart->set_labels(array_keys($overallchartdata));
            $chartoutput .= html_writer::start_div('row clearfix');
            $chartoutput .= html_writer::start_div('col-12 mb-4');
            $chartoutput .= $OUTPUT->render($overallchart);
            $chartoutput .= html_writer::end_div();
            $chartoutput .= html_writer::end_div();
            // Output the charts in a responsive grid layout
            $output .= html_writer::start_div('container');
            $output .= html_writer::start_div('row');
            $output .= $chartoutput;
            $output .= html_writer::end_div(); // Close row
            $output .= html_writer::end_div(); // Close container

            // Output the tables with the data
            foreach ($monthlydatabymonth as $month => $usersdata) {
                // Initialize sum variables for this month's table
                $totalentriessum = 0;
                $matchingcontentssums = array_fill_keys($desiredfieldoptions, 0);
                $notyetansweredsum = 0;

                // Create a new table for the month
                $output .= html_writer::tag('h3', get_string('month') . ": " . $month);
                $output .= html_writer::start_tag('table', ['class' => 'table table-striped table-bordered']);

                // Table header
                $output .= html_writer::start_tag('thead');
                $output .= html_writer::start_tag('tr');
                $output .= html_writer::tag('th', get_string('user'));
                $output .= html_writer::tag('th', get_string('aggregationsum', 'reportbuilder'));
                foreach ($desiredfieldoptions as $label) {
                    $output .= html_writer::tag('th', $label);
                }
                $output .= html_writer::tag('th', get_string('notyetanswered', 'question'));
                $output .= html_writer::end_tag('tr');
                $output .= html_writer::end_tag('thead');

                $output .= html_writer::start_tag('tbody');

                foreach ($usersdata as $userid => $user_month_data) {
                    $output .= html_writer::start_tag('tr');
                    $output .= html_writer::tag('td', $user_month_data['userinfo']);

                    // Total entries for this user in this month
                    $usertotalentries = $user_month_data['totalentries'];
                    $output .= html_writer::tag('td', $usertotalentries);
                    $totalentriessum += $usertotalentries;

                    // Matching contents counts and "Not Yet Answered" for each user
                    $summatchingcontents = 0;
                    foreach ($desiredfieldoptions as $label) {
                        $count = $user_month_data['matchingcontents'][$label] ?? 0;
                        if ($count !== 0) {
                            $summatchingcontents += $count;
                            $matchingcontentssums[$label] += $count;
                        }
                        $output .= html_writer::tag('td', $count);
                    }

                    $notyetanswered = $usertotalentries - $summatchingcontents;
                    $output .= html_writer::tag('td', $notyetanswered);
                    $notyetansweredsum += $notyetanswered;

                    $output .= html_writer::end_tag('tr');
                }

                // Totals row for this month
                $output .= html_writer::start_tag('tr');
                $output .= html_writer::tag('td', get_string('total'));
                $output .= html_writer::tag('td', $totalentriessum);

                foreach ($matchingcontentssums as $sum) {
                    $output .= html_writer::tag('td', $sum);
                }

                $output .= html_writer::tag('td', $notyetansweredsum);
                $output .= html_writer::end_tag('tr');

                $output .= html_writer::end_tag('tbody');
                $output .= html_writer::end_tag('table');
            }

            // Overall totals
            $output .= html_writer::tag('h3', get_string('aggregationsum', 'reportbuilder'));
            $output .= html_writer::start_tag('table', ['class' => 'table table-striped table-bordered']);

            // Table header
            $output .= html_writer::start_tag('thead');
            $output .= html_writer::start_tag('tr');
            $output .= html_writer::tag('th', get_string('total'));
            $output .= html_writer::tag('th', get_string('aggregationsum', 'reportbuilder'));
            foreach ($desiredfieldoptions as $label) {
                $output .= html_writer::tag('th', $label);
            }
            $output .= html_writer::tag('th', get_string('notyetanswered', 'question'));
            $output .= html_writer::end_tag('tr');
            $output .= html_writer::end_tag('thead');

            $output .= html_writer::start_tag('tbody');
            $output .= html_writer::start_tag('tr');
            $output .= html_writer::tag('td', get_string('total'));
            $output .= html_writer::tag('td', $overalltotalentriessum);

            foreach ($overallmatchingcontentssums as $sum) {
                $output .= html_writer::tag('td', $sum);
            }

            $output .= html_writer::tag('td', $overallnotyeteditedsum);
            $output .= html_writer::end_tag('tr');

        } else {
            $output .= html_writer::start_tag('table', ['class' => 'table table-striped table-bordered']);

            $output .= html_writer::start_tag('thead');
            $output .= html_writer::start_tag('tr');

            // Header row.
            $output .= html_writer::tag('th', get_string('user')); // Assuming you have a lang string for this.
            $output .= html_writer::tag('th', get_string('month'));
            $output .= html_writer::tag('th', get_string('total'));

            foreach ($desiredfieldoptions as $label) {
                $output .= html_writer::tag('th', $label);
            }
            // Additional column for "Not Yet Answered".
            $output .= html_writer::tag('th', get_string('notyetanswered', 'question'));

            $output .= html_writer::end_tag('tr');
            $output .= html_writer::end_tag('thead');

            $output .= html_writer::start_tag('tbody');

            // Data rows.
            foreach ($report as $userid => $data) {
                // Get user info.
                $user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, email, department');

                // Prepare user column content.
                $userinfo = "{$user->firstname} {$user->lastname} ({$user->id})<br>{$user->email} ({$user->department})";

                foreach ($data['monthly'] as $month => $monthlydata) {
                    $output .= html_writer::start_tag('tr');
                    $output .= html_writer::tag('td', $userinfo);
                    $output .= html_writer::tag('td', $month);
                    $output .= html_writer::tag('td', $monthlydata['totalentries']);

                    // Calculate the sum of matching contents.
                    $summatchingcontents = 0;
                    foreach ($desiredfieldoptions as $label) {
                        $count = isset($monthlydata['matchingcontents'][$label]) ? $monthlydata['matchingcontents'][$label] : 0;
                        $summatchingcontents += $count;
                        $output .= html_writer::tag('td', $count);
                    }

                    // Calculate and display "Not Yet Answered".
                    $notyetanswered = $monthlydata['totalentries'] - $summatchingcontents;
                    $output .= html_writer::tag('td', $notyetanswered);

                    $output .= html_writer::end_tag('tr');
                }
            }

        }
        $output .= html_writer::end_tag('tbody');
        $output .= html_writer::end_tag('table');
        $output .= html_writer::end_tag('div');
        return $output;
    }

    /**
     * Create the SQL for the report.
     *
     * @return array
     */
    public function create_report_sql(): array {
        global $DB;
        $dataid = $this->_df->id();
        $entryids = $this->get_report_entryids();
        if (!empty($entryids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($entryids, SQL_PARAMS_NAMED, 'inparam');
            $insqlentries = " AND e.id $insql";
        } else {
            $insql = '';
            $inparams = [];
            $insqlentries = '';
        }
        // Check if the report is for teammemberselect or author.
        $groupingid = (int) $this->view->param4;
        $userarray = [];
        if ($groupingid > 0) {
            // Get all userids that are associated to an entry with the view filter applied.
            $teammemberselectfield = $this->_df->get_field_from_id($groupingid);
            $userarray = $teammemberselectfield->get_all_userids_in_all_entries($insql, $inparams);
        } else if ($groupingid === -1) {
            // Get all user ids of entry authors when filter is already applied.
            $sql = "SELECT *
                FROM {datalynx_entries} e
                WHERE e.dataid = $dataid
                $insqlentries";
            $entries = $DB->get_records_sql($sql, $inparams);
            foreach ($entries as $entry) {
                // Make the array the same as in get_all_userids_in_all_entries.
                $userarray[$entry->userid][] = $entry->id;
            }
        }

        // Get field options and their line number (which is saved in datalynx_contents)
        $fields = $this->_df->get_fields();
        foreach ($fields as $field) {
            if ($field->field->id === (int) $this->view->param1) {
                $fieldoptions = $field->get_options();
            }
        }
        // The field used to count the selected values by the user. Must be an option type field.
        $fieldid = (int) $this->view->param1;
        $countfield = $this->_df->get_field_from_id($fieldid);
        $desiredfieldoptions = $countfield->get_options();
        $report = [];

        foreach ($userarray as $userid => $entryids) {
            $totalentries = count($entryids);

            // Initialize the report array for this user.
            $report[$userid] = [
                    'totalentries' => 0,
                    'monthly' => []
            ];

            foreach ($entryids as $entryid) {
                // Fetch timecreated and count matching content.
                $entry_record = $DB->get_record('datalynx_entries', ['id' => $entryid, 'dataid' => $dataid], 'id, timecreated');
                if ($entry_record) {
                    $month = date('Y-m', $entry_record->timecreated);

                    // Initialize the month data if not set.
                    if (!isset($report[$userid]['monthly'][$month])) {
                        $report[$userid]['monthly'][$month] = [
                                'totalentries' => 0,
                                'matchingcontents' => []
                        ];
                    }

                    // Increment total entries for this month.
                    $report[$userid]['monthly'][$month]['totalentries'] += 1;

                    // Check for each desired field option in datalynx_contents using SQL.
                    foreach ($desiredfieldoptions as $value => $label) {
                        $sql = "SELECT COUNT(1) 
                        FROM {datalynx_contents} 
                        WHERE entryid = :entryid 
                          AND fieldid = :fieldid 
                          AND " . $DB->sql_compare_text('content') . " = " . $DB->sql_compare_text(':value');
                        $params = [
                                'entryid' => $entryid,
                                'fieldid' => $fieldid,
                                'value' => (string)$value // Cast the value to string to ensure proper comparison.
                        ];

                        $matchingcontents_count = $DB->count_records_sql($sql, $params);

                        // Initialize the matching content count for this value if not set.
                        if (!isset($report[$userid]['monthly'][$month]['matchingcontents'][$label])) {
                            $report[$userid]['monthly'][$month]['matchingcontents'][$label] = 0;
                        }

                        // Increment matching content count for this value.
                        $report[$userid]['monthly'][$month]['matchingcontents'][$label] += $matchingcontents_count;
                    }
                }
            }

            // Set total entries for the user..
            $report[$userid]['totalentries'] = $totalentries;
        }
        // Sort the report array by month in descending order.
        foreach ($report as &$data) {
            krsort($data['monthly']); // Sorts the 'monthly' array in descending order by month.
        }
        return $report;
    }

    /**
     * @return array
     */
    public function get_report_entryids(): array {
        // Set content.
        $entries = new datalynx_entries($this->_df, $this->_filter);
        $options = [];
        // Set a filter to take it all.
        $filter = $this->get_filter();
        $filter->perpage = 0;
        $options['filter'] = $filter;
        // Do we need ratings?
        if ($ratingoptions = $this->is_rating()) {
            $options['ratings'] = $ratingoptions;
        }
        // Get the entries.
        $entries->set_content($options);
        $exportentries = $entries->entries();

        // Return empty array if no entries.
        if (!$exportentries) {
            return [];
        }
        return array_keys($exportentries);
    }

    /**
     * Generates the view with default settings.
     */
    public function generate_default_view() {
        // Get all the fields.
        if (!$fields = $this->_df->get_fields()) {
            return; // You shouldn't get that far if there are no user fields.
        }

        // Remove fields that are used in fieldgroup.
        $fields = parent::remove_duplicates($fields);

        // Set views and filters menus and quick search.
        $table = new html_table();
        $table->attributes['cellpadding'] = '2';
        // First row: menus.
        $row1 = new html_table_row();
        $viewsmenu = new html_table_cell('##viewsmenu##');
        $seperator = new html_table_cell('     ');
        $filtersmenu = new html_table_cell('##filtersmenu##');
        $quicksearch = new html_table_cell('##quicksearch##');
        $quickperpage = new html_table_cell('##quickperpage##');
        $row1->cells = array($viewsmenu, $seperator, $filtersmenu, $quicksearch, $quickperpage);
        foreach ($row1->cells as $cell) {
            $cell->style = 'border:0 none;';
        }
        // Don't show addnewentries, it has no functionality..
        $row2 = new html_table_row();
        $addentries = new html_table_cell('');
        $addentries->colspan = 5;
        $row2->cells = array($addentries);
        foreach ($row2->cells as $cell) {
            $cell->style = 'border:0 none;';
        }
        // Third row: paging bar.
        $row3 = new html_table_row();
        $pagingbar = new html_table_cell('##pagingbar##');
        $pagingbar->colspan = 5;
        $row3->cells = array($pagingbar);
        foreach ($row3->cells as $cell) {
            $cell->style = 'border:0 none;';
        }
        // Construct the table.
        $table->data = array($row1, $row2, $row3);
        $sectiondefault = html_writer::table($table);
        $this->view->esection = html_writer::tag('div', $sectiondefault,
                        array('class' => 'mdl-align')) . "<div>##entries##</div>";

        // Set content.
        $this->view->param2 = '';
        foreach ($fields as $field) {
            if (is_numeric($field->field->id) && $field->field->id > 0) {
                $fieldname = $field->name();
                if ($field->type == "userinfo") {
                    $this->view->param2 .= "##author:{$fieldname}##\n";
                } else {
                    $this->view->param2 .= "[[$fieldname]]\n";
                }
            }
        }
    }

    /**
     * Retrieves defined fields based on field patterns in param2.
     * @return array|null
     */
    public function get_columns(): ?array {
        if (empty($this->_columns)) {
            $this->_columns = [];
            $columns = explode("\n", $this->view->param2);
            foreach ($columns as $column) {
                $column = trim($column);
                if (empty($column)) {
                    continue;
                }
                $fieldname = trim($column, '[]#');
                $this->_columns[] = $fieldname;
            }
        }
        return $this->_columns;
    }

}
