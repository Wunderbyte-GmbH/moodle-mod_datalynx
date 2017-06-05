<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @package datalynxfield
 * @subpackage multiselect
 * @copyright 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once("$CFG->dirroot/mod/datalynx/field/field_class.php");

class datalynxfield_multiselect extends datalynxfield_option_multiple {

    public $type = 'multiselect';

    public $separators = array(array('name' => 'New line', 'chr' => '<br />'),
            array('name' => 'Space', 'chr' => '&#32;'),
            array('name' => ',', 'chr' => '&#44;'),
            array('name' => ', (with space)', 'chr' => '&#44;&#32;'),
            array('name' => 'Unordered list', 'chr' => '</li><li>')
    );

    /**
     */
    public function prepare_import_content(&$data, $importsettings, $csvrecord = null, $entryid = null) {
        // import only from csv
        if ($csvrecord) {
            $fieldid = $this->field->id;
            $fieldname = $this->name();
            $csvname = $importsettings[$fieldname]['name'];
            $labels = !empty($csvrecord[$csvname]) ? explode('#', trim('#', $csvrecord[$csvname])) : null;

            if ($labels) {
                $options = $this->options_menu();
                $selected = array();
                foreach ($labels as $label) {
                    if ($optionkey = array_search($label, $options)) {
                        $selected[] = $optionkey;
                    }
                }
                if ($selected) {
                    $data->{"field_{$fieldid}_{$entryid}_selected"} = $selected;
                }
            }
        }

        return true;
    }

    /**
     */
    public function default_values() {
        $rawdefaults = explode("\n", $this->field->param2);
        $options = $this->options_menu();

        $defaults = array();
        foreach ($rawdefaults as $default) {
            $default = trim($default);
            if ($default and $key = array_search($default, $options)) {
                $defaults[] = $key;
            }
        }
        return $defaults;
    }
}
