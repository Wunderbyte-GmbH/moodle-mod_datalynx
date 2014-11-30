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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.
 
/**
 * @package datalynxfield
 * @subpackage checkbox
 * @copyright 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->dirroot/mod/datalynx/field/multiselect/field_class.php");

class datalynxfield_checkbox extends datalynxfield_option_multiple {

    public $type = 'checkbox';

    public $separators = array(
        array('name' => 'New line', 'chr' => '<br />'),
        array('name' => 'Space', 'chr' => '&#32;'),
        array('name' => ',', 'chr' => '&#44;'),
        array('name' => ', (with space)', 'chr' => '&#44;&#32;'),
        array('name' => 'Unordered list', 'chr' => '</li><li>')
    );

    /**
     *
     */
    public function format_search_value($searchparams) {
        list($not, $operator, $value) = $searchparams;
        if (is_array($value)){
            $selected = implode(', ', $value['selected']);
            $allrequired = '('. ($value['allrequired'] ? get_string('requiredall') : get_string('requirednotall', 'datalynx')). ')';
            return $not. ' '. $operator. ' '. $selected. ' '. $allrequired;
        } else {
            return false;
        }
    }

    /**
     *
     */
    public function get_search_sql($search) {
        global $DB;

        // TODO Handle search for empty field

        list($not, , $value) = $search;

        static $i=0;
        $i++;
        $name = "df_{$this->field->id}_{$i}_";
        $params = array();

        $allrequired = $value['allrequired'];
        $selected    = $value['selected'];
        $content = "c{$this->field->id}.content";

        if ($selected) {
            $conditions = array();
            foreach ($selected as $key => $sel) {
                $xname = $name. $key;
                $likesel = str_replace('%', '\%', $sel);

                $conditions[] = $DB->sql_like($content, ":{$xname}");
                $params[$xname] = "%#$likesel#%";
            }
            if ($allrequired) {
                return array(" $not (".implode(" AND ", $conditions).") ", $params, true);
            } else {
                return array(" $not (".implode(" OR ", $conditions).") ", $params, true);
            }
        } else {
            return array(" ", $params);
        }
    }

    /**
     *
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
     *
     */
    public function default_values() {
        $rawdefaults = explode("\n",$this->field->param2);
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
    
    /**
     *
     */
    protected function format_content($entry, array $values = null) {
        $fieldid = $this->field->id;
        $contents = array();
        $oldcontents = array();

        // old contents
        if (isset($entry->{"c{$fieldid}_content"})) {
            $oldcontents[] = $entry->{"c{$fieldid}_content"};
        }

        $value = reset($values);
        // new contents
        if (!empty($value)) {
            $contents[] = '#' . implode('#', $value) . '#';
        }

        return array($contents, $oldcontents);
    }

    /**
     *
     */
    public function parse_search($formdata, $i) {
        $selected = array();
        
        $fieldname = "f_{$i}_{$this->field->id}";
        foreach (array_keys($this->options_menu()) as $cb) {
            if (!empty($formdata->{"{$fieldname}_$cb"})) {
                $selected[] = $cb;
            }
        }
        if ($selected) {
            if (!empty($formdata->{"{$fieldname}_allreq"})) {
                $allrequired = $formdata->{"{$fieldname}_allreq"};
            } else {
                $allrequired = '';
            }
            return array('selected'=>$selected, 'allrequired'=>$allrequired);
        } else {
            return false;
        }
    }

}
