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
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/field_class.php");

/**
 * Field class for the datalynxview field type.
 */
class datalynxfield_datalynxview extends datalynxfield_base {
    /** @var string The field type identifier. */
    public $type = 'datalynxview';

    /** @var mod_datalynx\datalynx|null The referenced datalynx instance. */
    public $refdatalynx = null;

    /** @var int|null The ID of the referenced view. */
    public $refview = null;

    /** @var int|null The ID of the reference filter. */
    public $reffilterid = null;

    /** @var int|null The ID of the local view. */
    public $localview = null;

    /** @var string|null Custom CSS for the field. */
    public $css = null;

    /**
     * Constructor for the datalynxview field.
     *
     * @param int $df The datalynx id.
     * @param int $field The field id.
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
        // A capability check on view entries should be added here.

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
     * Returns true because this field supports editing.
     *
     * @return bool True always.
     */
    public function is_editable() {
        return true;
    }
}
