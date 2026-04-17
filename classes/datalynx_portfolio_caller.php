<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_datalynx;
use mod_datalynx;
use moodle_url;
use moodleform;
use portfolio_caller_exception;
use portfolio_module_caller_base;

/**
 * The class to handle entry exports of a datalynx module
 *
 * @package mod_datalynx
 * @copyright 2025 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datalynx_portfolio_caller extends portfolio_module_caller_base {
    /**
     * Content with no files
     */
    const CONTENT_NOFILES = 0;

    /**
     * Content with files
     */
    const CONTENT_WITHFILES = 1;

    /**
     * Content files only
     */
    const CONTENT_FILESONLY = 2;

    /**
     * the required callback arguments for export
     *
     * @return array
     */
    public static function expected_callbackargs() {
        return ['id' => true, 'vid' => true, 'fid' => true, 'eids' => false,
                'ecount' => false];
    }

    /**
     *
     * @return string
     */
    public static function display_name() {
        return get_string('modulename', 'datalynx');
    }

    /**
     * base supported formats before we know anything about the export
     */
    public static function base_supported_formats() {
        return [];
    }

    /**
     * get module
     *
     * @throws portfolio_caller_exception
     */
    public function load_data() {
        $this->cm = get_coursemodule_from_id('datalynx', $this->id);
        if (!$this->cm) {
            throw new portfolio_caller_exception('invalidid', 'datalynx');
        }
    }

    /**
     * How long we think the export will take
     *
     * @return string
     */
    public function expected_time() {
        // By number of exported entries.
        if (!empty($this->eids)) {
            $dbtime = portfolio_expected_time_db(count(explode(',', $this->eids)));
        } else {
            if (!empty($this->ecount)) {
                $dbtime = portfolio_expected_time_db($this->ecount);
            } else {
                $dbtime = PORTFOLIO_TIME_HIGH;
            }
        }

        // TODO: MDL-66151 by file sizes.
        // (only if export includes embedded files but this is in config and not yet accessible here ...).
        $filetime = PORTFOLIO_TIME_HIGH;

        return ($filetime > $dbtime) ? $filetime : $dbtime;
    }

    /**
     * Calculate the sha1 of this export
     * Dependent on the export format.
     *
     * @return string
     */
    public function get_sha1() {
        return sha1(serialize([$this->id, $this->vid, $this->fid, $this->eids]));
    }

    /**
     * Prepare the package for export
     *
     * @return void
     */
    public function prepare_package() {
        // Set the exported view content.
        $df = new mod_datalynx\datalynx(0, $this->id);
        $view = $df->get_view_from_id($this->vid);
        $view->set_filter(['filterid' => $this->fid, 'eids' => $this->eids]);
        $view->set_content();

        // Export to spreadsheet.
        if ($this->exporter->get('formatclass') == PORTFOLIO_FORMAT_SPREADSHEET) {
            $content = $view->display(['controls' => false, 'tohtml' => true]);
            $filename = clean_filename(
                $view->name() . '-full.' . $this->get_export_config('spreadsheettype')
            );
            $this->exporter->write_new_file($content, $filename);
            return;
        }

        // Export to html.
        if ($this->exporter->get('formatclass') == PORTFOLIO_FORMAT_RICHHTML) {
            $exportfiles = $this->get_export_config('contentformat');

            // Collate embedded files (view and field).
            if ($exportfiles) {
                if ($files = $view->get_embedded_files()) {
                    foreach ($files as $file) {
                        $this->exporter->copy_existing_file($file);
                    }
                }
            }

            // Export content.
            if ($exportfiles != self::CONTENT_FILESONLY) {
                // TODO: MDL-66151 the user may choose to export without files.
                $content = $view->display(
                    ['controls' => false, 'tohtml' => true,
                                'pluginfileurl' => $this->exporter->get('format')->get_file_directory(),
                        ]
                );
                $filename = clean_filename($view->name() . '-full.htm');
                $this->exporter->write_new_file($content, $filename);
            }
            return;
        }
    }

    /**
     * verify the user can export the requested entries
     *
     * @return bool
     */
    public function check_permissions() {
        // Verification is done in the view so just return true.
        return true;
    }

    /**
     *
     * @return bool
     */
    public function has_export_config() {
        return true;
    }

    /**
     * Define the export config form
     *
     * @param moodleform $mform
     * @param object $instance
     */
    public function export_config_form(&$mform, $instance) {
        if (!$this->has_export_config()) {
            return;
        }

        // Spreadsheet selection.
        $types = ['csv', 'ods', 'xls'];
        $options = array_combine($types, $types);
        $mform->addElement(
            'select',
            'caller_spreadsheettype',
            get_string('spreadsheettype', 'datalynx'),
            $options
        );
        $mform->setDefault('caller_spreadsheettype', 'csv');
        $mform->disabledIf('caller_spreadsheettype', 'format', 'neq', PORTFOLIO_FORMAT_SPREADSHEET);

        // Export content.
        $options = [self::CONTENT_NOFILES => 'Exclude embedded files',
                self::CONTENT_WITHFILES => 'Include embedded files',
                self::CONTENT_FILESONLY => 'embedded files only'];
        $mform->addElement(
            'select',
            'caller_contentformat',
            get_string('exportcontent', 'datalynx'),
            $options
        );
        $mform->setDefault('caller_contentformat', self::CONTENT_NOFILES);
        $mform->disabledIf('caller_contentformat', 'format', 'neq', PORTFOLIO_FORMAT_RICHHTML);

        // Each entry in a separate file.
        $mform->addElement(
            'selectyesno',
            'caller_separateentries',
            get_string('separateentries', 'datalynx')
        );
    }

    /**
     * Get allowed export config
     *
     * @return array
     */
    public function get_allowed_export_config() {
        return ['spreadsheettype', 'documenttype', 'contentformat', 'separateentries'];
    }

    /**
     * Get return URL
     *
     * @return string
     */
    public function get_return_url() {
        $returnurl = new moodle_url(
            '/mod/datalynx/view.php',
            ['id' => $this->id, 'view' => $this->vid, 'filter' => $this->fid]
        );
        return $returnurl->out(false);
    }
}
