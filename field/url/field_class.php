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
 * @subpackage url
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/field_class.php");

class datalynxfield_url extends datalynxfield_base {

    public $class;

    public $target;

    public $type = 'url';

    /**
     * Can this field be used in fieldgroups?
     * @var boolean
     */
    protected $forfieldgroup = true;

    public function __construct($df = 0, $field = 0) {
        parent::__construct($df, $field);
        $this->class = isset($field->param3) ? $field->param3 : '';
        $this->target = isset($field->param4) ? $field->param4 : '';
    }

    /**
     */
    protected function content_names() {
        return array('url', 'alt');
    }

    protected function format_content($entry, array $values = null) {
        $fieldid = $this->field->id;
        $oldcontents = array();
        $contents = array();
        // Old contents.
        if (isset($entry->{"c{$fieldid}_content"})) {
            $oldcontents[] = isset($entry->{"c$fieldid" . '_content'}) ? $entry->{"c$fieldid" .
            '_content'} : null;
            $oldcontents[] = isset($entry->{"c$fieldid" . '_content1'}) ? $entry->{"c$fieldid" .
            '_content1'} : null;
        }
        // New contents.
        $url = $linktext = null;
        if (!empty($values)) {
            foreach ($values as $name => $value) {
                if ($name) { // Update from form.

                    switch ($name) {
                        case 'url':
                            // TODO: Is this really the place to validate for empty fields?
                            $url = clean_param($value, PARAM_URL);
                            break;
                        case 'alt':
                            $linktext = clean_param($value, PARAM_NOTAGS);
                            break;
                    }

                } else { // Update from import.
                    if (strpos($value, '##') !== false) {
                        $value = explode('##', $value);
                        $url = clean_param($value[0], PARAM_URL);
                        $linktext = clean_param($value[1], PARAM_NOTAGS);
                    } else {
                        $url = clean_param($value, PARAM_URL);
                    }
                    // There should be only one from import, so break.
                    break;
                }
            }
        }
        if (!is_null($url)) {
            $contents[] = $url;
            $contents[] = $linktext;
        }
        return array($contents, $oldcontents);
    }

    /**
     */
    public function get_content_parts() {
        return array('content', 'content1');
    }

    public function get_supported_search_operators() {
        return array('' => get_string('empty', 'datalynx'), '=' => get_string('equal', 'datalynx'),
                'LIKE' => get_string('contains', 'datalynx'));
    }

    /**
     */
    public function prepare_import_content(&$data, $importsettings, $csvrecord = null, $entryid = null) {
        // Import only from csv.
        if ($csvrecord) {
            $fieldid = $this->field->id;
            $fieldname = $this->name();
            // This _url is needed so we can import from simple csv files.
            $data->{"field_{$fieldid}_{$entryid}_url"} = $csvrecord[$fieldname];
        }
        return true;
    }

    /**
     * Is $value a valid content or do we see an empty input?
     * @return bool
     */
    public static function is_fieldvalue_empty($value) {
        if ($value == '' || $value == 'http://') {
            return true;
        }
        return false;
    }
}
