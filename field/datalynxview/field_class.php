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
 * @package datalynxfield
 * @subpackage datalynxview
 * @copyright 2014 onwards by edulabs.org and associated programmers
 * @copyright based on the work by 2013 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/field_class.php");

class datalynxfield_datalynxview extends datalynxfield_base {

    public $type = 'datalynxview';

    public $refdatalynx = null;

    public $refview = null;

    public $reffilterid = null;

    public $localview = null;

    public $css = null;

    public function __construct($df = 0, $field = 0) {
        global $DB;

        parent::__construct($df, $field);

        // Get the datalynx.
        if (empty($this->field->param1) or
                !$data = $DB->get_record('datalynx', array('id' => $this->field->param1))
        ) {
            return;
        }

        $datalynx = new mod_datalynx\datalynx($data, null);
        // TODO Add capability check on view entries.

        // Is there a view? Otherwise return.
        if (empty($this->field->param2) || !$viewid = $DB->get_field('datalynx_views', 'id', array('id' => $this->field->param2))) {
            return;
        }
        $this->refdatalynx = $datalynx;
        $this->refview = $viewid;
        $currentview = $this->df->get_current_view();
        $this->localview = $currentview ? $currentview->id() : null;
    }

    public function is_editable() {
        return true;
    }
}

