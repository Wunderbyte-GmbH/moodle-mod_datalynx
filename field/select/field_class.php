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
 * @subpackage select
 * @copyright 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->dirroot/mod/datalynx/field/field_class.php");

class datalynxfield_select extends datalynxfield_base {

    public $type = 'select';
    
    protected $_options = array();

    /**
     * Class constructor
     *
     * @param var $df       datalynx id or class object
     * @param var $field    field id or DB record
     */
    public function __construct($df = 0, $field = 0) {
        parent::__construct($df, $field);

        // Set the options
        $this->options_menu();
    }

    /**
     * Update a field in the database
     */
    public function update_field($fromform = null) {
        global $DB;
        
        // before we update get the current options
        $oldoptions = $this->options_menu();
        // update
        parent::update_field($fromform);       

        // adjust content if necessary
        $adjustments = array();
        // Get updated options
        $newoptions = $this->options_menu(true);
        foreach ($newoptions as $newkey => $value) {
            if (!isset($oldoptions[$newkey]) or $value != $oldoptions[$newkey]) {
                if ($key = array_search($value, $oldoptions) or $key !== false) {
                    $adjustments[$key] = $newkey;
                }
            }
        }

        if (!empty($adjustments)) {
            // fetch all contents of the field whose content in keys
            list($incontent, $params) = $DB->get_in_or_equal(array_keys($adjustments));
            array_unshift($params, $this->field->id);
            $contents = $DB->get_records_select_menu('datalynx_contents',
                                        " fieldid = ? AND content $incontent ",
                                        $params,
                                        '',
                                        'id,content'); 
            if ($contents) {
                if (count($contents) == 1) {
                    list($id, $content) = each($contents);
                    $DB->set_field('datalynx_contents', 'content', $adjustments[$content], array('id' => $id));
                } else { 
                    $params = array();
                    $sql = "UPDATE {datalynx_contents} SET content = CASE id ";
                    foreach ($contents as $id => $content) {
                        $newcontent = $adjustments[$content];
                        $sql .= " WHEN ? THEN ? ";
                        $params[] = $id;
                        $params[] = $newcontent;
                    }
                    list($inids, $paramids) = $DB->get_in_or_equal(array_keys($contents));
                    $sql .= " END WHERE id $inids ";
                    $params = array_merge($params, $paramids);
                    $DB->execute($sql, $params);
                }
            }
        }
        return true;
    }

    /**
     *
     */
    protected function content_names() {
        return array('selected', 'newvalue');
    }

    /**
     * Computes which values of this field have already been chosen by the given user and
     * determines which ones have reached their limit
     * @param  int      $userid  ID of the user modifying an entry; if not specified defaults to $USER->id
     * @return array    an array of disabled values
     */
    public function get_disabled_values_for_user($userid = 0) {
        global $DB, $USER;

        if ($userid == 0) {
            $userid = $USER->id;
        }

        $countsql = "SELECT COUNT(dc2.id)
                       FROM {datalynx_contents} dc2
                 INNER JOIN {datalynx_fields} df2 ON dc2.fieldid = df2.id
                 INNER JOIN {datalynx_entries} de2 ON dc2.entryid = de2.id
                      WHERE dc2.fieldid = :fieldid1
                        AND dc2.content = dc.content";

        $sql = "SELECT dc.content, ({$countsql}) AS count
                  FROM {datalynx_contents} dc
            INNER JOIN {datalynx_entries} de ON dc.entryid = de.id
                 WHERE de.userid = :userid
                   AND de.dataid = :dataid
                   AND dc.fieldid = :fieldid2
                HAVING count >= 1";

        $params = array('userid'    => $userid,
                        'dataid'    => $this->df->id(),
                        'fieldid1'  => $this->field->id,
                        'fieldid2'  => $this->field->id);

        $results = $DB->get_records_sql($sql, $params);

        return array_keys($results);
    }

    /**
     *
     */
    protected function format_content($entry, array $values = null) {
        $fieldid = $this->field->id;
        // old contents
        $oldcontents = array();
        if (isset($entry->{"c{$fieldid}_content"})) {
            $oldcontents[] = $entry->{"c{$fieldid}_content"};
        }
        // new contents
        $contents = array();

        $selected = $newvalue = null;
        if (!empty($values)) {
            foreach ($values as $name => $value) {
                $value = (string) $value;
                if (!empty($name) and !empty($value)) {
                    ${$name} = $value;
                }
            }
        }        
        // update new value in the field type
        if ($newvalue = s($newvalue)) {
            $options = $this->options_menu();
            if (!$selected = (int) array_search($newvalue, $options)) {
                $selected = count($options) + 1;
                $this->field->param1 = trim($this->field->param1). "\n$newvalue";
                $this->update_field();
            }
        }
        // add the content
        if (!is_null($selected)) {
            $contents[] = $selected;
        }

        return array($contents, $oldcontents);
    }

    /**
     * 
     */
    protected function get_sql_compare_text($column = 'content') {
        global $DB;
        return $DB->sql_compare_text("c{$this->field->id}.$column", 255);
    }

    /**
     *
     */
    public function get_search_value($value) {
        $options = $this->options_menu();
        if ($key = array_search($value, $options)) {
            return $key;
        } else {
            return '';
        }
    }

    /**
     * 
     */
    public function options_menu($forceget = false) {
        global $DB, $USER;
        if (!$this->_options or $forceget) {
            if (!empty($this->field->param1)) {
                $rawoptions = explode("\n", $this->field->param1);
                foreach ($rawoptions as $key => $option) {
                    $option = trim($option);
                    if ($option != '') {
                        $this->_options[$key + 1] = $option;
                    }
                }
            }
        }
        return $this->_options;
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
            $allownew = !empty($importsettings[$fieldname]['allownew']) ? true : false;
            $label = !empty($csvrecord[$csvname]) ? $csvrecord[$csvname] : null;
            
            if ($label) {
                $options = $this->options_menu();
                if ($optionkey = array_search($label, $options)) {
                    $data->{"field_{$fieldid}_{$entryid}_selected"} = $optionkey;
                } else if ($allownew) {
                    $data->{"field_{$fieldid}_{$entryid}_newvalue"} = $label;
                }                    
            }
        }
    
        return true;
    }

}
