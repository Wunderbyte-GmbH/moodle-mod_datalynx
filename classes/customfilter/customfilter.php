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

/**
 * Customfilter class
 */
class customfilter {
    /** @var int The customfilter id. */
    public $id;

    /** @var int The datalynx instance id. */
    public $dataid;

    /** @var string The customfilter name. */
    public $name;

    /** @var string The customfilter description. */
    public $description;

    /** @var int Whether the customfilter is visible. */
    public $visible;

    /** @var int Whether full text search is enabled. */
    public $fulltextsearch;

    /** @var int Timestamp when the customfilter was created. */
    public $timecreated;

    /** @var int Timestamp when the customfilter was last modified. */
    public $timemodified;

    /** @var int Whether author search is enabled. */
    public $authorsearch;

    /** @var int Whether approval filtering is enabled. */
    public $approve;

    /** @var int The customfilter status. */
    public $status;

    /** @var mixed JSON-encoded list of fields to include in the filter. */
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
     * Get a stdClass object representing this customfilter's data.
     *
     * @return stdClass
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
