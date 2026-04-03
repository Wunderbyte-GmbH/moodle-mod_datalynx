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
 * @package datalynxfield_url
 * @subpackage url
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_datalynx\local\field\datalynxfield_base;

defined('MOODLE_INTERNAL') || die();



/**
 * URL field class.
 */
class datalynxfield_url extends datalynxfield_base {
    /** @var string CSS class */
    public $class;

    /** @var string Link target */
    public $target;

    /** @var string Field type */
    public $type = 'url';

    /**
     * Can this field be used in fieldgroups?
     * @var bool
     */
    protected $forfieldgroup = true;

    /**
     * Constructor.
     *
     * @param int|object $df Datalynx ID or object
     * @param int|object $field Field ID or object
     */
    public function __construct($df = 0, $field = 0) {
        parent::__construct($df, $field);
        $this->class = isset($field->param3) ? $field->param3 : '';
        $this->target = isset($field->param4) ? $field->param4 : '';
    }

    /**
     * Get the content names of the field.
     *
     * @return array
     */
    protected function content_names() {
        return ['url', 'alt'];
    }

    /**
     * Format the field content for storage.
     *
     * @param stdClass $entry
     * @param array $values
     * @return array
     */
    protected function format_content($entry, array $values = null) {
        $fieldid = $this->field->id;
        $oldcontents = [];
        $contents = [];
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
                            // TODO: MDL-0000 Is this really the place to validate for empty fields?
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
        return [$contents, $oldcontents];
    }

    /**
     * Get the content parts of the field.
     *
     * @return array
     */
    public function get_content_parts() {
        return ['content', 'content1'];
    }

    /**
     * Get supported search operators.
     *
     * @return array
     */
    public function get_supported_search_operators() {
        return ['' => get_string('empty', 'datalynx'), '=' => get_string('equal', 'datalynx'),
                'LIKE' => get_string('contains', 'datalynx')];
    }

    /**
     * Prepare field content for import.
     *
     * @param stdClass $data
     * @param array $importsettings
     * @param array $csvrecord
     * @param int $entryid
     * @return bool
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
