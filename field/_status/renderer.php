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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package dataformfield
 * @subpackage _status
 * @copyright 2013 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/dataform/field/renderer.php");

/**
 * Implementation of internal field for setting entry submission statuses.
 */
class dataformfield__status_renderer extends dataformfield_renderer {

    /**
     * Performs replacements of supported patterns depending on the given parameters
     * @param  array          $tags    unused
     * @param  dataform_entry $entry   an entry object
     * @param  array          $options array of viewing options. Supported: 'edit'
     * @return array          an array of replacements (either HTML for browse mode or callback info for the edit mode)
     */
    protected function replacements(array $tags = array(), $entry = null, array $options = array()) {

        $edit = !empty($options['edit']) ? $options['edit'] : false;

        $replacements = array();
        if (!$entry or $edit) {
            $replacements['##status##'] = array('', array(array($this, 'display_edit'), array($entry)));
        } else {
            $replacements['##status##'] = array('html', $this->display_browse($entry));
        }

        return $replacements;
    }

    /**
     * Adds the element for the status field to the entry form
     * @param  moodleform     $mform entry form
     * @param  dataform_entry $entry an entry
     * @param  array          $options unused
     */
    public function display_edit(&$mform, $entry, array $options = array()) {
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $status = isset($entry->status) ? $entry->status : dataformfield__status::STATUS_DRAFT;

        $fieldname = "field_{$fieldid}_{$entryid}";
        $mform->addElement('select', $fieldname, get_string('status', 'dataform'), $this->menu_status());
        $mform->setDefault($fieldname, $status);
    }

    /**
     * Creates menu items for submission status
     * @param  boolean $includenotcreated if STATUS_NOT_CREATED should be included in the menu (default false)
     * @return array   (int) statusid => (string) label
     */
    private function menu_status($includenotcreated = false) {
        if ($includenotcreated) {
            return array(
                dataformfield__status::STATUS_NOT_CREATED => get_string('status_notcreated', 'dataform'),
                dataformfield__status::STATUS_DRAFT => get_string('status_draft', 'dataform'),
                dataformfield__status::STATUS_SUBMISSION => get_string('status_submission', 'dataform'),
                dataformfield__status::STATUS_FINAL_SUBMISSION => get_string('status_finalsubmission', 'dataform'));
        } else {
            return array(
                dataformfield__status::STATUS_DRAFT => get_string('status_draft', 'dataform'),
                dataformfield__status::STATUS_SUBMISSION => get_string('status_submission', 'dataform'),
                dataformfield__status::STATUS_FINAL_SUBMISSION => get_string('status_finalsubmission', 'dataform'));
        }
    }

    /**
     * Returns an HTML representation of the value of the internal status field of an entry
     * @param  dataform_entry $entry an entry
     * @param  array          $params unused
     * @return string         HTML representation
     */
    protected function display_browse($entry, $params = array()) {
        $field = $this->_field;
        $menu = $this->menu_status(true);
        if (isset($entry) && isset($entry->status)) {
            return $menu[$entry->status];
        } else {
            return $menu[dataformfield__status::STATUS_NOT_CREATED];
        }

    }

    /**
     * Returns information about supported field patterns
     * @return array (string) pattern => array((boolean) supported, (string) pattern category)
     */
    protected function patterns() {
        $cat = get_string('actions', 'dataform');

        $patterns = array();
        $patterns["##status##"] = array(true, $cat);

        return $patterns;
    }
}
