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
 * @package datalynxfield
 * @subpackage fieldgroup
 * @copyright 2018 michael pollak <moodle@michaelpollak.org>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once(dirname(__FILE__) . '/../field_class.php');

class datalynxfield_fieldgroup extends datalynxfield_base {

    public $type = 'fieldgroup';

    /**
     * Fieldids of the fields belonging to the fieldgroup#
     * @var integer[]
     */
    public $fieldids = array();

    public function __construct($df = 0, $field = 0) {
        parent::__construct($df, $field);

        $this->fieldids = json_decode($this->field->param1, true);
    }

    protected function content_names() {
        return array('');
    }

    public function supports_group_by() {
        return false;
    }

    public function get_supported_search_operators() {
        return false;
    }

    public static function is_customfilterfield() {
        return false;
    }

    public function get_select_sql() {
        return '';
    }

    public function get_sort_sql() {
        return '';
    }

    protected function filearea($suffix = null) {
        return false;
    }
}
