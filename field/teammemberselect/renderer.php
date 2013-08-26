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
 * @subpackage teammemberselect
 * @copyright 2013 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die;

require_once("$CFG->dirroot/mod/dataform/field/renderer.php");

/**
 * Renderer class for teammemberselect dataform field
 */
class dataformfield_teammemberselect_renderer extends dataformfield_renderer {

    /**
     * Returns array of replacements for the field patterns
     * The label pattern should always be first where applicable
     * so that it is processed first in view templates
     * so that i turn patterns it may contain could be processed.
     *
     * @return array pattern => array(visible in menu, category)
     */
    protected function replacements(array $tags = null, $entry = null, array $options = null) {
        $field = $this->_field;
        $fieldname = $field->name();
        $edit = !empty($options['edit']) ? $options['edit'] : false;

        $replacements = array_fill_keys($tags, '');
        // rules support
        $tags = $this->add_clean_pattern_keys($tags);
        foreach ($tags as $tag => $cleantag) {
            if ($edit) {
                $params = array('required' => $this->is_required($tag));
                $replacements[$tag] = array('', array(array($this, 'display_edit'), array($entry, $params)));
                break;
            } else {
                $replacements[$tag] = array('html', $this->display_browse($entry));
            }
        }

        return $replacements;
    }

    /**
     * [display_edit description]
     * @param  MoodleQuickForm $mform form to display element in
     * @param  [type] $entry   [description]
     * @param  [type] $options [description]
     * @return [type]          [description]
     */
    public function display_edit(MoodleQuickForm &$mform, $entry, array $options = null) {
        global $PAGE;

        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_$entryid";
        $fieldnamedropdown = "field_{$fieldid}_{$entryid}_dropdown";
        $classname = "teammemberselect_{$fieldid}_{$entryid}";

        $selected = !empty($entry->{"c{$fieldid}_content"}) ? json_decode($entry->{"c{$fieldid}_content"}, true) : array();
        $menu = $field->options_menu(true);

        for ($i = 0; $i < $field->teamsize; $i++) {
            if (!isset($selected[$i])) {
                $selected[$i] = 0;
            }
            $select = $mform->addElement('select', "{$fieldname}[{$i}]", null, $menu,
                array('class' => "dataformfield_teammemberselect_select $classname"));
            $mform->setType("{$fieldname}[{$i}]", PARAM_INT);
            $text = $mform->addElement('text', "{$fieldnamedropdown}[{$i}]", null,
                array('class' => "dataformfield_teammemberselect_dropdown $classname"));
            $mform->setType("{$fieldnamedropdown}[{$i}]", PARAM_TEXT);

            $select->setSelected($selected[$i]);
            $text->setValue($menu[$selected[$i]]);
        }

        $PAGE->requires->js_init_call(
                'M.dataformfield_teammemberselect.init_entry_form',
                array($field->options_menu()),
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
            'name' => 'dataformfield_teammemberselect',
            'fullpath' => '/mod/dataform/field/teammemberselect/teammemberselect.js',
            'requires' => array('node', 'event', 'node-event-delegate', 'autocomplete', 'autocomplete-filters', 'autocomplete-highlighters', 'event-outside'),
            );
        return $jsmodule;
    }

    /**
     *
     */
    public function display_browse($entry, $params = null) {
        $field = $this->_field;
        $fieldid = $field->id();
        $str = '';

        if (isset($entry->{"c{$fieldid}_content"})) {
            $selected = json_decode($entry->{"c{$fieldid}_content"}, true);
            $options = $field->options_menu(false, true);

            $str = array();
            foreach ($selected as $id) {
                if ($id > 0) {
                    $str[] = $options[$id];
                }
            }

            switch ($field->listformat) {
                case dataformfield_teammemberselect::TEAMMEMBERSELECT_FORMAT_NEWLINE:
                    $str = implode('<br />', $str);
                    break;
                case dataformfield_teammemberselect::TEAMMEMBERSELECT_FORMAT_SPACE:
                    $str = implode(' ', $str);
                    break;
                case dataformfield_teammemberselect::TEAMMEMBERSELECT_FORMAT_COMMA:
                    $str = implode(',', $str);
                    break;
                case dataformfield_teammemberselect::TEAMMEMBERSELECT_FORMAT_COMMA_SPACE:
                    $str = implode(', ', $str);
                    break;
                case dataformfield_teammemberselect::TEAMMEMBERSELECT_FORMAT_UL:
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
}
