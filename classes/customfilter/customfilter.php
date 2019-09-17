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
 * Contains class mod_datalynx_customfilter
 *
 * @package mod_datalynx
 * @copyright 2016 Thomas Niedermaier
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx\customfilter;
use stdClass;
defined('MOODLE_INTERNAL') or die();

/**
 * Customfilter class
 */
class customfilter {

    public $id;

    public $dataid;

    public $name;

    public $description;

    public $visible;

    public $fulltextsearch;

    public $timecreated;

    public $timemodified;

    public $authorsearch;

    public $approve;

    public $status;

    public $fieldlist;

    /**
     * constructor
     */
    public function __construct($filterdata) {
        $this->id = empty($filterdata->id) ? 0 : $filterdata->id;
        $this->dataid = $filterdata->dataid;
        $this->name = empty($filterdata->name) ? '' : $filterdata->name;
        $this->description = empty($filterdata->description) ? '' : $filterdata->description;
        $this->visible = !isset($filterdata->visible) ? 0 : $filterdata->visible;
        $this->fulltextsearch = !isset($filterdata->fulltextsearch) ? 0 : $filterdata->fulltextsearch;
        $this->timecreated = empty($filterdata->timecreated) ? 0 : $filterdata->timecreated;
        $this->timemodified = empty($filterdata->timemodified) ? 0 : $filterdata->timemodified;
        $this->authorsearch = !isset($filterdata->authorsearch) ? 0 : $filterdata->authorsearch;
        $this->approve = empty($filterdata->approve) ? 0 : $filterdata->approve;
        $this->status = empty($filterdata->status) ? 0 : $filterdata->status;
        $this->fieldlist = empty($filterdata->fieldlist) ? 0 : $filterdata->fieldlist;
    }

    /**
     */
    public function get_filter_obj() {
        $filter = new stdClass();
        $filter->id = $this->id;
        $filter->dataid = $this->dataid;
        $filter->name = $this->name;
        $filter->description = $this->description;
        $filter->visible = $this->visible;
        $filter->fulltextsearch = $this->fulltextsearch;
        $filter->timecreated = $this->timecreated;
        $filter->timemodified = $this->timemodified;
        $filter->authorsearch = $this->authorsearch;
        $filter->approve = $this->approve;
        $filter->status = $this->status;
        $filter->fieldlist = $this->fieldlist;

        return $filter;
    }

}

