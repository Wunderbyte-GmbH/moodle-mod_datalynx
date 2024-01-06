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
 * @package datalynxfield
 * @subpackage multiselect
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/field_class.php");

class datalynxfield_multiselect extends datalynxfield_option_multiple {

    public $type = 'multiselect';

    /**
     * Can this field be used in fieldgroups? Override if yes.
     * @var boolean
     */
    protected $forfieldgroup = true;

    /**
     * @var array
     */
    public $separators = array(array('name' => 'New line', 'chr' => '<br />'),
            array('name' => 'Space', 'chr' => '&#32;'),
            array('name' => ',', 'chr' => '&#44;'),
            array('name' => ', (with space)', 'chr' => '&#44;&#32;'),
            array('name' => 'Unordered list', 'chr' => '</li><li>')
    );

    /**
     */
    public function prepare_import_content(&$data, $importsettings, $csvrecord = null, $entryid = null) {
        // Import only from csv.
        if ($csvrecord) {
            $fieldid = $this->field->id;
            $fieldname = $this->name();
            $csvname = $importsettings[$fieldname]['name'];
            $labels = !empty($csvrecord[$csvname]) ? explode('<br />', trim($csvrecord[$csvname])) : null;

            if ($labels) {
                $options = $this->options_menu();
                $selected = array();
                foreach ($labels as $label) {
                    if ($optionkey = array_search($label, $options)) {
                        $selected[] = $optionkey;
                    }
                }
                if ($selected) {
                    $data->{"field_{$fieldid}_{$entryid}"} = $selected;
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
            if ($default && $key = array_search($default, $options)) {
                $defaults[] = $key;
            }
        }
        return $defaults;
    }

    /**
     * Is $value a valid content or do we see an empty input?
     * @return bool
     */
    public static function is_fieldvalue_empty($value) {
        // If array > 1 entry we see actual input from the user, next to -999.
        // TODO: This needs to consider alternative renderers with the select field.
        if (count($value) < 2) {
            return true;
        }
        return false;
    }

    /**
     * @param stdClass $entry
     * @param array|null $values
     * @return bool|int
     * @throws dml_exception
     */
    public function update_content(stdClass $entry, array $values = null) {

        // Check if all values are known in field definition.
        $knownvalues = explode("\n", $this->field->param1);

        // Our values start counting at 1, correct knownvalues array keys.
        $knownvalues = array_combine(range(1, count($knownvalues)), $knownvalues);

        $addoption = null;
        if (!empty($values[''])) {
            foreach ($values[''] as $key => $value) {

                // When left empty multiselect passes 0, catch this.
                if (!$value || array_key_exists($value, $knownvalues)) {
                    continue;
                }

                // Add new value to the field definitions known values.
                $addoption = count($knownvalues) + 1;
                $knownvalues[$addoption] = $value;

                // Change $values to work with update_content.
                unset($values[''][$key]);
                $values[''][] = $addoption;
            }
        }

        // In case we have spotted some addoptions, update field definition.
        if ($addoption) {
            global $DB;
            $update = new \stdClass;
            $update->id = $this->field->id;
            $update->param1 = implode("\n", $knownvalues);
            $DB->update_record('datalynx_fields', $update);
        }
        return parent::update_content($entry, $values);
    }

    /**
     * {@inheritDoc}
     * @see datalynxfield_base::get_search_sql()
     */
    public function get_search_sql(array $search): array {

        // If we only see the andor and no value just skip.
        if (is_array($search[2]) && count($search[2]) == 0) {
            return array('', '', '');
        }
        if (is_array($search[2]) && count($search[2]) == 1 && isset($search[2]['andor'])) {
            return array('', '', '');
        }

        return parent::get_search_sql($search);
    }

}
