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
 * @subpackage select
 * @copyright 2014 Ivan Šakić
 * @copyright 2016 David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once(dirname(__FILE__) . "/../renderer.php");

/**
 * Class datalynxfield_select_renderer Renderer for select field type
 */
class datalynxfield_select_renderer extends datalynxfield_renderer {

    /**
     *
     * @var datalynxfield_select
     */
    protected $_field = null;

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_renderer::render_edit_mode()
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $menuoptions = $field->options_menu();
        $fieldname = "field_{$fieldid}_$entryid";
        $required = !empty($options['required']);
        $selected = !empty($entry->{"c{$fieldid}_content"}) ? (int) $entry->{"c{$fieldid}_content"} : 0;
        $autocomplete = $field->get('param6');

        // Check for default value.
        if (!$selected and $defaultval = $field->get('param2')) {
            $selected = (int) array_search($defaultval, $menuoptions);
        }

        // Render as autocomplete field (param6 not empty) or select field.
        if ($autocomplete) {
            $select = &$mform->addElement('autocomplete', $fieldname, null);
        } else {
            $select = &$mform->addElement('select', $fieldname, null);
        }

        if (isset($this->_field->field->param5) && $this->_field->field->param5 > 0) {
            $disabled = $this->_field->get_disabled_values_for_user();
        } else {
            $disabled = array();
        }

        foreach ($menuoptions as $id => $name) {
            $option = new stdClass();
            $option->id = $id;
            $option->name = $name;
            $menuoptions[$id] = $option;
        }
        // Sort the options alphabetically.
        $sortalphbetically = $field->field->param4;
        if ($sortalphbetically) {
            usort($menuoptions, function($a, $b) {
                return strcmp($a->name, $b->name);
            });
        }
        $choosedots = new stdClass();
        $choosedots->id = '';
        $choosedots->name = get_string('choosedots');
        $menuoptions = array('' => $choosedots) + $menuoptions;
        foreach ($menuoptions as $option) {
            if (array_search($option->id, $disabled) === false || $option->id == $selected) {
                $select->addOption($option->name, $option->id);
            } else {
                $select->addOption($option->name, $option->id, array('disabled' => 'disabled'));
            }
        }

        $select->setSelected($selected);

        if ($required) {
            $mform->addRule($fieldname, null, 'required', null, 'client');
        }
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_renderer::render_display_mode()
     */
    public function render_display_mode(stdClass $entry, array $params) {
        $field = $this->_field;
        $fieldid = $field->id();

        if (isset($entry->{"c{$fieldid}_content"})) {
            $selected = (int) $entry->{"c{$fieldid}_content"};
            $options = $field->options_menu();

            if (!empty($params['options'])) {
                $str = array();
                foreach ($options as $key => $option) {
                    $isselected = (int) ($key == $selected);
                    $str[] = "$isselected $option";
                }
                $str = implode(',', $str);
                return $str;
            }

            if (!empty($params['key'])) {
                if ($selected) {
                    return $selected;
                } else {
                    return '';
                }
            }

            if ($selected and $selected <= count($options)) {
                return $options[$selected];
            }
        }

        return '';
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_renderer::render_search_mode()
     */
    public function render_search_mode(MoodleQuickForm &$mform, $i = 0, $value = '') {
        $field = $this->_field;
        $fieldid = $field->id();
        $fieldname = "f_{$i}_{$fieldid}";
        $menu = array(-1 => '') + $field->options_menu();
        $options = array('multiple' => true);
        $elements = array();
        $elements[] = $mform->createElement('autocomplete', $fieldname, null, $menu, $options);
        $mform->setType($fieldname, PARAM_INT);
        $mform->setDefault($fieldname, $value);
        $mform->disabledIf($fieldname, "searchoperator{$i}", 'eq', '');
        return array($elements, null);
    }

    /**
     *
     * @param integer $entryid
     * @param unknown $tags
     * @param object $formdata
     * @return string[]
     */
    public function validate($entryid, $tags, $formdata) {
        $fieldid = $this->_field->id();
        $errors = array();

        $formfieldname = "field_{$fieldid}_{$entryid}";

        // Not every field of this dataynx-instance has to be in the form!
        if (isset($formdata->{$formfieldname})) {

            if (isset($this->_field->field->param5) && $this->_field->field->param5 > 0) {
                $disabled = $this->_field->get_disabled_values_for_user();
                $content = clean_param($formdata->{$formfieldname}, PARAM_INT);
                if (array_search($content, $disabled) !== false) {
                    $menu = $this->_field->options_menu();
                    $errors[$formfieldname] = get_string('limitchoice_error', 'datalynx', $menu[$content]);
                }
            }
        }
        return $errors;
    }
}
