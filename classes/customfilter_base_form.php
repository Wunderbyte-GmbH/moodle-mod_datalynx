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
 * Contains class mod_customfilter_base_form
 *
 * @package mod
 * @subpackage datalynx
 * @copyright 2016 Thomas Niedermaier
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die();

global $CFG;

require_once("$CFG->libdir/formslib.php");


/**
 */
abstract class mod_datalynx_customfilter_base_form extends moodleform {

    protected $_customfilter = null;

    /**
     *
     * @var datalynx null
     */
    protected $_df = null;

    /*
     *
     */
    public function __construct($df, $customfilter, $action = null, $customdata = null, $method = 'post',
            $target = '', $attributes = null, $editable = true) {
        $this->_customfilter = $customfilter;
        $this->_df = $df;

        parent::__construct($action, $customdata, $method, $target, $attributes, $editable);
    }
}
