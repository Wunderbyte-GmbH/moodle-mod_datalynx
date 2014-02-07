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
 * @subpackage text
 * @copyright 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->dirroot/mod/datalynx/field/field_class.php");

class datalynxfield_text extends datalynxfield_base {

    public $type = 'text';

    public function validate($entryid, $tags, $formdata) {
        $fieldid = $this->id();
        $fieldname = $this->name();

        $formfieldname = "field_{$fieldid}_{$entryid}";
        $tags = $this->renderer()->add_clean_pattern_keys($tags);

        if (array_key_exists("[[*$fieldname]]", $tags) and isset($formdata->$formfieldname)) {
            if (!clean_param($formdata->$formfieldname, PARAM_NOTAGS)) {
                return array($formfieldname => get_string('fieldrequired', 'datalynx'));
            }
        }
        return null;
    }

}
