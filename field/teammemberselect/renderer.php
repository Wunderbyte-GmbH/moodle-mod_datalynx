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
 * @package datalynxfield
 * @subpackage teammemberselect
 * @copyright 2013 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die;

require_once("$CFG->dirroot/mod/datalynx/field/renderer.php");

/**
 * Renderer class for teammemberselect datalynx field
 */
class datalynxfield_teammemberselect_renderer extends datalynxfield_renderer {

    /**
     * [display_edit description]
     * @param  MoodleQuickForm $mform form to display element in
     * @param  [type] $entry   [description]
     * @param  [type] $options [description]
     * @return [type]          [description]
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options = null) {
        global $PAGE, $USER;

        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_$entryid";
        $fieldnamedropdown = "field_{$fieldid}_{$entryid}_dropdown";
        $classname = "teammemberselect_{$fieldid}_{$entryid}";
        $required = !empty($options['required']);

        $selected = !empty($entry->{"c{$fieldid}_content"}) ? json_decode($entry->{"c{$fieldid}_content"}, true) : array();
        $authorid = isset($entry->userid) ? $entry->userid : $USER->id;
        $menu = $field->options_menu(true, false, $authorid);

        $selectgroup = array();
        $dropdowngroup = array();
        for ($i = 0; $i < $field->teamsize; $i++) {
            if (!isset($selected[$i]) || !isset($menu[$selected[$i]])) {
                $selected[$i] = 0;
            }
            $select = $mform->createElement('select', "{$fieldname}[{$i}]", null, $menu,
                array('class' => "datalynxfield_teammemberselect_select $classname"));
            $mform->setType("{$fieldname}[{$i}]", PARAM_INT);
            $text = $mform->createElement('text', "{$fieldnamedropdown}[{$i}]", null,
                array('class' => "datalynxfield_teammemberselect_dropdown $classname"));
            $mform->setType("{$fieldnamedropdown}[{$i}]", PARAM_TEXT);

            $select->setSelected($selected[$i]);
            $text->setValue($menu[$selected[$i]]);
            $selectgroup[] = $select;
            $dropdowngroup[] = $text;
        }
        $mform->addGroup($dropdowngroup, "{$fieldname}_dropdown_grp", null, null, false);
        $mform->addGroup($selectgroup, "{$fieldname}_grp", null, null, false);
        if ($required) {
            $mform->addGroupRule("{$fieldname}_dropdown_grp", '', 'required', null, 0, 'client');
        }
        $PAGE->requires->strings_for_js(array('minteamsize_error_form', 'moreresults'), 'datalynx');
        $PAGE->requires->js_init_call(
                'M.datalynxfield_teammemberselect.init_entry_form',
                array($field->options_menu(false, false, $authorid), $fieldid, $entryid, $field->minteamsize),
                false,
                $this->get_js_module());
    }

    public static function compare_different_ignore_zero_callback($data) {
        $count = array_fill(0, max($data) + 1, 0);

        foreach ($data as $id) {
            $count[$id]++;
        }

        for ($id = 1; $id < count($count); $id++) {
            if ($count[$id] > 1) {
                return false;
            }
        }

        return true;
    }

    private function get_js_module() {
        $jsmodule = array(
            'name' => 'datalynxfield_teammemberselect',
            'fullpath' => '/mod/datalynx/field/teammemberselect/teammemberselect.js',
            'requires' => array('node', 'event', 'node-event-delegate', 'autocomplete', 'autocomplete-filters', 'autocomplete-highlighters', 'event-outside'),
            );
        return $jsmodule;
    }

    /**
     *
     */
    public function display_search(&$mform, $i = 0, $value = '') {
        global $PAGE;

        $field = $this->_field;
        $fieldid = $field->id();
        $fieldname = "f_{$i}_{$fieldid}";
        $fieldnamedropdown = "{$fieldname}_dropdown";
        $menu = $field->options_menu(true, false, 0);

        $elements = array();
        $elements[] = $mform->createElement('hidden', "{$fieldname}", null);
        $mform->setType("{$fieldname}", PARAM_INT);
        $elements[] = $mform->createElement('text', "{$fieldnamedropdown}", null);
        $mform->setType("{$fieldnamedropdown}", PARAM_TEXT);
        $mform->disabledIf($fieldnamedropdown, "searchoperator{$i}", 'eq', '');
        $mform->disabledIf($fieldnamedropdown, "searchoperator{$i}", 'eq', 'USER');

        $PAGE->requires->strings_for_js(array('moreresults'), 'datalynx');
        $PAGE->requires->js_init_call(
                'M.datalynxfield_teammemberselect.init_filter_search_form',
                array($menu, $fieldid),
                false,
                $this->get_js_module());

        return array($elements, null);
    }


    /**
     *
     */
    public function render_display_mode(stdClass $entry, array $params) {
        $field = $this->_field;
        $fieldid = $field->id();
        $str = '';

        if (isset($entry->{"c{$fieldid}_content"})) {
            $selected = json_decode($entry->{"c{$fieldid}_content"}, true);
            $options = $field->options_menu(false, true, 0, true);

            $str = array();
            foreach ($selected as $id) {
                if ($id > 0) {
                    if (isset($options[$id])) {
                        $str[] = $options[$id];
                    }
                }
            }

            switch ($field->listformat) {
                case datalynxfield_teammemberselect::TEAMMEMBERSELECT_FORMAT_NEWLINE:
                    $str = implode('<br />', $str);
                    break;
                case datalynxfield_teammemberselect::TEAMMEMBERSELECT_FORMAT_SPACE:
                    $str = implode(' ', $str);
                    break;
                case datalynxfield_teammemberselect::TEAMMEMBERSELECT_FORMAT_COMMA:
                    $str = implode(',', $str);
                    break;
                case datalynxfield_teammemberselect::TEAMMEMBERSELECT_FORMAT_COMMA_SPACE:
                    $str = implode(', ', $str);
                    break;
                case datalynxfield_teammemberselect::TEAMMEMBERSELECT_FORMAT_UL:
                default:
                    if (count($str) > 0) {
                        $str = '<ul><li>' . implode('</li><li>', $str) . '</li></ul>';
                    } else {
                        $str = '';
                    }
                    break;

            }
        }

        return $str;
    }

    /**
     * Array of patterns this field supports
     */
    protected function patterns() {
        $fieldname = $this->_field->name();

        $patterns = parent::patterns();
        $patterns["[[$fieldname]]"] = array(true);

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
