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
 * @package datalynxfield_datalynxview
 * @subpackage datalynxview
 * @copyright 2014 onwards by edulabs.org and associated programmers
 * @copyright based on the work by 2013 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_datalynx\local\field\datalynxfield_base;

/**
 * Datalynxview field class for datalynx.
 *
 * @package    datalynxfield_datalynxview
 * @copyright  2025 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datalynxfield_datalynxview extends datalynxfield_base {
    /** @var string Field type. */
    public $type = 'datalynxview';

    /** @var object|null Referenced datalynx instance. */
    public $refdatalynx = null;

    /** @var int|null Referenced view id. */
    public $refview = null;

    /** @var int|null Referenced filter id. */
    public $reffilterid = null;

    /** @var int|null Local view id. */
    public $localview = null;

    /** @var string|null CSS for the field. */
    public $css = null;

    /**
     * Constructs the datalynxview field instance.
     *
     * @param int|object $df
     * @param int|object $field
     */
    public function __construct($df = 0, $field = 0) {
        global $DB;

        parent::__construct($df, $field);

        // Get the datalynx.
        if (
            empty($this->field->param1) &&
                !$DB->record_exists('datalynx', ['id' => $this->field->param1])
        ) {
            return;
        }

        $datalynx = new mod_datalynx\datalynx($this->field->param1);
        // TODO MDL-000000 Add capability check on view entries.

        // Is there a view? Otherwise return.
        if (empty($this->field->param2) || !$viewid = $DB->get_field('datalynx_views', 'id', ['id' => $this->field->param2])) {
            return;
        }
        $this->refdatalynx = $datalynx;
        $this->refview = $viewid;
        $currentview = $this->df->get_current_view();
        $this->localview = $currentview ? $currentview->id() : null;
    }

    /**
     * Returns true to indicate this field is editable.
     *
     * @return bool
     */
    public function is_editable() {
        return true;
    }
}
