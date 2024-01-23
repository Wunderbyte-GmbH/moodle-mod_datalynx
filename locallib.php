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
 * @package mod_datalynx
 * @copyright 2013 onwards Ivan Šakić, Thomas Niedermaier
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/portfolio/caller.php");
require_once("$CFG->dirroot/mod/datalynx/classes/datalynx.php");
require_once($CFG->libdir . '/filelib.php');

/**
 * The class to handle entry exports of a datalynx module
 */
class datalynx_portfolio_caller extends portfolio_module_caller_base {

    const CONTENT_NOFILES = 0;

    const CONTENT_WITHFILES = 1;

    const CONTENT_FILESONLY = 2;

    /**
     * the required callback arguments for export
     *
     * @return array
     */
    public static function expected_callbackargs() {
        return array('id' => true, 'vid' => true, 'fid' => true, 'eids' => false,
                'ecount' => false);
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
        return array();
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

        // TODO by file sizes.
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
        return sha1(serialize(array($this->id, $this->vid, $this->fid, $this->eids)));
    }

    /**
     * Prepare the package for export
     *
     * @return nothing
     */
    public function prepare_package() {
        // Set the exported view content.
        $df = new mod_datalynx\datalynx(null, $this->id);
        $view = $df->get_view_from_id($this->vid);
        $view->set_filter(array('filterid' => $this->fid, 'eids' => $this->eids));
        $view->set_content();

        // Export to spreadsheet.
        if ($this->exporter->get('formatclass') == PORTFOLIO_FORMAT_SPREADSHEET) {
            $content = $view->display(array('controls' => false, 'tohtml' => true));
            $filename = clean_filename(
                    $view->name() . '-full.' . $this->get_export_config('spreadsheettype'));
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
                // TODO the user may choose to export without files.
                $content = $view->display(
                        array('controls' => false, 'tohtml' => true,
                                'pluginfileurl' => $this->exporter->get('format')->get_file_directory()
                        ));
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
     */
    public function export_config_form(&$mform, $instance) {
        if (!$this->has_export_config()) {
            return;
        }

        // Spreadsheet selection.
        $types = array('csv', 'ods', 'xls');
        $options = array_combine($types, $types);
        $mform->addElement('select', 'caller_spreadsheettype',
                get_string('spreadsheettype', 'datalynx'), $options);
        $mform->setDefault('caller_spreadsheettype', 'csv');
        $mform->disabledIf('caller_spreadsheettype', 'format', 'neq', PORTFOLIO_FORMAT_SPREADSHEET);

        // Export content.
        $options = array(self::CONTENT_NOFILES => 'Exclude embedded files',
                self::CONTENT_WITHFILES => 'Include embedded files',
                self::CONTENT_FILESONLY => 'embedded files only');
        $mform->addElement('select', 'caller_contentformat',
                get_string('exportcontent', 'datalynx'), $options);
        $mform->setDefault('caller_contentformat', self::CONTENT_NOFILES);
        $mform->disabledIf('caller_contentformat', 'format', 'neq', PORTFOLIO_FORMAT_RICHHTML);

        // Each entry in a separate file.
        $mform->addElement('selectyesno', 'caller_separateentries',
                get_string('separateentries', 'datalynx'));
    }

    /**
     */
    public function get_allowed_export_config() {
        return array('spreadsheettype', 'documenttype', 'contentformat', 'separateentries');
    }

    /**
     */
    public function get_return_url() {
        global $CFG;

        $returnurl = new moodle_url('/mod/datalynx/view.php',
                array('id' => $this->id, 'view' => $this->vid, 'filter' => $this->fid));
        return $returnurl->out(false);
    }
}

/**
 * Class representing the virtual node with all itemids in the file browser
 *
 * @category files
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datalynx_file_info_container extends file_info {

    /**
     * @var file_browser
     */
    protected $browser;

    /**
     * @var stdClass
     */
    protected $course;

    /**
     * @var stdClass
     */
    protected $cm;

    /**
     * @var string
     */
    protected $component;

    /**
     * @var stdClass
     */
    protected $context;

    /**
     * @var array
     */
    protected $areas;

    /**
     * @var string
     */
    protected $filearea;

    /**
     * Constructor (in case you did not realize it ;-)
     *
     * @param file_browser $browser
     * @param stdClass $course
     * @param stdClass $cm
     * @param stdClass $context
     * @param array $areas
     * @param string $filearea
     */
    public function __construct($browser, $course, $cm, $context, $areas, $filearea) {
        parent::__construct($browser, $context);
        $this->browser = $browser;
        $this->course = $course;
        $this->cm = $cm;
        $this->component = 'mod_datalynx';
        $this->context = $context;
        $this->areas = $areas;
        $this->filearea = $filearea;
    }

    /**
     *
     * @return array with keys contextid, filearea, itemid, filepath and filename
     */
    public function get_params() {
        return array('contextid' => $this->context->id, 'component' => $this->component,
                'filearea' => $this->filearea, 'itemid' => null, 'filepath' => null, 'filename' => null);
    }

    /**
     * Can new files or directories be added via the file browser
     *
     * @return bool
     */
    public function is_writable() {
        return false;
    }

    /**
     * Should this node be considered as a folder in the file browser
     *
     * @return bool
     */
    public function is_directory() {
        return true;
    }

    /**
     * Returns localised visible name of this node
     *
     * @return string
     */
    public function get_visible_name() {
        return $this->areas[$this->filearea];
    }

    /**
     * Returns list of children nodes
     *
     * @return array of file_info instances
     */
    public function get_children() {
        global $DB;

        $children = array();
        $itemids = $DB->get_records('files',
                array('contextid' => $this->context->id, 'component' => $this->component,
                        'filearea' => $this->filearea
                ), 'itemid DESC', "DISTINCT itemid");
        foreach ($itemids as $itemid => $unused) {
            if ($child = $this->browser->get_file_info($this->context, 'mod_datalynx',
                    $this->filearea, $itemid)
            ) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * Returns parent file_info instance
     *
     * @return file_info or null for root
     */
    public function get_parent() {
        return $this->browser->get_file_info($this->context);
    }
}
