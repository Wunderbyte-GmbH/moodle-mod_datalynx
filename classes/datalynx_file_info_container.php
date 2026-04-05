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

namespace mod_datalynx;
use file_browser;
use file_info;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/portfolio/caller.php");
require_once("$CFG->dirroot/mod/datalynx/classes/datalynx.php");
require_once($CFG->libdir . '/filelib.php');

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
        return ['contextid' => $this->context->id, 'component' => $this->component,
                'filearea' => $this->filearea, 'itemid' => null, 'filepath' => null, 'filename' => null];
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

        $children = [];
        $itemids = $DB->get_records(
            'files',
            ['contextid' => $this->context->id, 'component' => $this->component,
                        'filearea' => $this->filearea,
                ],
            'itemid DESC',
            "DISTINCT itemid"
        );
        foreach ($itemids as $itemid => $unused) {
            if (
                    $child = $this->browser->get_file_info(
                        $this->context,
                        'mod_datalynx',
                        $this->filearea,
                        $itemid
                    )
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
