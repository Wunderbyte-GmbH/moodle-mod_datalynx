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
        // rules support
        $tags = $this->add_clean_pattern_keys($tags);

        foreach ($tags as $tag => $cleantag) {
            if (!$entry or $edit) {
                if ($cleantag == "##status##") {
                    $required = $this->is_required($tag);
                    $replacements[$tag] = array('', array(array($this, 'display_edit'), array($entry, array('required' => $required))));
                }
            } else {
                if ($cleantag == "##status##") {
                    $replacements[$tag] = array('html', $this->display_browse($entry));
                }
            }
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
        $status = isset($entry->status) ? $entry->status : dataformfield__status::STATUS_NOT_SET;
        $required = !empty($options['required']);

        $fieldname = "field_{$fieldid}_{$entryid}";
        $menu = $this->menu_status(!isset($entry->status));
        $select = &$mform->addElement('select', $fieldname, '', $menu);
        $mform->setDefault($fieldname, $status);
        if ($required) {
            $mform->addRule($fieldname, get_string('statusrequired', 'dataform'), 'nonzero', null, 'client');
        }
    }

    /**
     * Creates menu items for submission status
     * @param  boolean $includenotcreated if STATUS_NOT_SET should be included in the menu (default false)
     * @return array   (int) statusid => (string) label
     */
    private function menu_status($newentry = false) {
        if (!$newentry) {
            return array(
                dataformfield__status::STATUS_NOT_SET => get_string('status_notcreated', 'dataform'),
                dataformfield__status::STATUS_DRAFT => get_string('status_draft', 'dataform'),
                dataformfield__status::STATUS_FINAL_SUBMISSION => get_string('status_finalsubmission', 'dataform'));
        } else {
            return array(
                dataformfield__status::STATUS_NOT_SET => get_string('choosedots'),
                dataformfield__status::STATUS_DRAFT => get_string('status_draft', 'dataform'),
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
        $menu = $this->menu_status();
        if (isset($entry) && isset($entry->status)) {
            return $menu[$entry->status];
        } else {
            return $menu[dataformfield__status::STATUS_NOT_SET];
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

    /**
     * Array of patterns this field supports
     */
    protected function supports_rules() {
        return array(
            self::RULE_REQUIRED
        );
    }
}
