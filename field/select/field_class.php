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
 * @subpackage select
 * @copyright 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once ("$CFG->dirroot/mod/datalynx/field/field_class.php");


class datalynxfield_select extends datalynxfield_option_single {

    public $type = 'select';

    /**
     * Computes which values of this field have already been chosen by the given user and
     * determines which ones have reached their limit
     * 
     * @param int $userid ID of the user modifying an entry; if not specified defaults to $USER->id
     * @return array an array of disabled values
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
        
        $params = array('userid' => $userid, 'dataid' => $this->df->id(), 
            'fieldid1' => $this->field->id, 'fieldid2' => $this->field->id
        );
        
        $results = $DB->get_records_sql($sql, $params);
        
        return array_keys($results);
    }

    /**
     */
    protected function get_sql_compare_text($column = 'content') {
        global $DB;
        return $DB->sql_compare_text("c{$this->field->id}.$column", 255);
    }

    /**
     */
    public function prepare_import_content(&$data, $importsettings, $csvrecord = null, $entryid = null) {
        // import only from csv
        if ($csvrecord) {
            $fieldid = $this->field->id;
            $fieldname = $this->name();
            $csvname = $importsettings[$fieldname]['name'];
            $label = !empty($csvrecord[$csvname]) ? $csvrecord[$csvname] : null;
            
            if ($label) {
                $options = $this->options_menu();
                if ($optionkey = array_search($label, $options)) {
                    $data->{"field_{$fieldid}_{$entryid}_selected"} = $optionkey;
                }
            }
        }
        
        return true;
    }

    public function validate($entryid, $tags, $formdata) {
        $fieldid = $this->id();
        
        global $DB;
        $query = "SELECT dc.content
                    FROM {datalynx_contents} dc
                   WHERE dc.entryid = :entryid
                     AND dc.fieldid = :fieldid";
        $params = array('entryid' => $entryid, 'fieldid' => $fieldid
        );
        
        $oldcontent = $DB->get_field_sql($query, $params);
        
        $formfieldname = "field_{$fieldid}_{$entryid}_selected";
        
        if (isset($this->field->param5)) {
            $disabled = $this->get_disabled_values_for_user();
            $content = clean_param($formdata->{$formfieldname}, PARAM_INT);
            if ($content != $oldcontent && array_search($content, $disabled) !== false) {
                $menu = $this->options_menu();
                return array(
                    $formfieldname => get_string('limitchoice_error', 'datalynx', $menu[$content])
                );
            }
        } else {
            return array();
        }
    }
}
