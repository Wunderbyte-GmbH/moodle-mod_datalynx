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
 * @subpackage _status
 * @copyright 2013 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/renderer.php");

/**
 * Implementation of internal field for setting entry submission statuses.
 */
class datalynxfield__status_renderer extends datalynxfield_renderer {

    /**
     * Performs replacements of supported patterns depending on the given parameters
     *
     * @param array $tags unused
     * @param datalynx_entry $entry an entry object
     * @param array $options array of viewing options. Supported: 'edit'
     * @return array an array of replacements (either HTML for browse mode or callback info for the
     *         edit mode)
     */
    public function replacements(array $tags = null, $entry = null, array $options = null) {
        $edit = !empty($options['edit']) ? $options['edit'] : false;

        $replacements = array();

        foreach ($tags as $tag) {
            if (!$entry or $edit) {
                if (trim($tag, '@') == "##status##" || trim($tag, '@') == "##*status##") {
                    $required = trim($tag, '@') === "##*status##";
                    $replacements[$tag] = array('',
                            array(array($this, 'display_edit'
                            ), array($entry, array('required' => $required
                            )
                            )
                            )
                    );
                }
            } else {
                if (trim($tag, '@') == "##status##" || trim($tag, '@') == "##*status##") {
                    $replacements[$tag] = array('html', $this->display_browse($entry)
                    );
                }
            }
        }
        return $replacements;
    }

    /**
     * Adds the element for the status field to the entry form
     *
     * @param moodleform $mform entry form
     * @param datalynx_entry $entry an entry
     * @param array $options unused
     */
    public function display_edit(&$mform, $entry, array $options = array()) {
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $status = isset($entry->status) ? $entry->status : datalynxfield__status::STATUS_NOT_SET;
        $required = !empty($options['required']);

        $fieldname = "field_{$fieldid}_{$entryid}";
        $menu = $this->menu_status();
        $select = &$mform->addElement('select', $fieldname, '', $menu);
        $mform->setDefault($fieldname, $status);
        if ($required) {
            $mform->addRule($fieldname, get_string('statusrequired', 'datalynx'), 'nonzero', null,
                    'client');
        }
    }

    /**
     * Creates menu items for submission status
     *
     * @param boolean $includenotcreated if STATUS_NOT_SET should be included in the menu (default
     *        false)
     * @return array (int) statusid => (string) label
     */
    private function menu_status($shownotset = false) {
        if ($shownotset) {
            return array(
                    datalynxfield__status::STATUS_NOT_SET => get_string('status_notcreated', 'datalynx'),
                    datalynxfield__status::STATUS_DRAFT => get_string('status_draft', 'datalynx'),
                    datalynxfield__status::STATUS_FINAL_SUBMISSION => get_string(
                            'status_finalsubmission', 'datalynx')
            );
        } else {
            return array(datalynxfield__status::STATUS_NOT_SET => get_string('choosedots'),
                    datalynxfield__status::STATUS_DRAFT => get_string('status_draft', 'datalynx'),
                    datalynxfield__status::STATUS_FINAL_SUBMISSION => get_string(
                            'status_finalsubmission', 'datalynx')
            );
        }
    }

    /**
     * Returns an HTML representation of the value of the internal status field of an entry
     *
     * @param datalynx_entry $entry an entry
     * @param array $params unused
     * @return string HTML representation
     */
    protected function display_browse($entry, $params = array()) {
        $field = $this->_field;
        $menu = $this->menu_status(true);
        if (isset($entry) && isset($entry->status)) {
            return $menu[$entry->status];
        } else {
            return $menu[datalynxfield__status::STATUS_NOT_SET];
        }
    }

    /**
     *
     * @param MoodleQuickForm $mform
     * @param int $i
     * @param string $value
     * @return array
     */
    public function render_search_mode(MoodleQuickForm &$mform, $i = 0, $value = '') {
        $fieldid = $this->_field->id();
        $fieldname = "f_{$i}_$fieldid";

        $statusmenu = array(-1 => get_string('status_notcreated', 'datalynx'),
                datalynxfield__status::STATUS_DRAFT => get_string('status_draft', 'datalynx'),
                datalynxfield__status::STATUS_FINAL_SUBMISSION => get_string('status_finalsubmission',
                        'datalynx'));

        $select = &$mform->createElement('select', $fieldname, null, $statusmenu, '');
        $select->setValue($value);

        $mform->disabledIf($fieldname, "searchoperator$i", 'eq', '');
        return [[$select
        ], null
        ];
    }

    /**
     * Returns information about supported field patterns
     *
     * @return array (string) pattern => array((boolean) supported, (string) pattern category)
     */
    protected function patterns() {
        $cat = get_string('actions', 'datalynx');

        $patterns = array();
        $patterns["##status##"] = array(true, $cat);
        $patterns["##*status##"] = array(true, $cat);

        return $patterns;
    }
}
