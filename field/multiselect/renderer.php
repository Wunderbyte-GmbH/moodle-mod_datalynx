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
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . "/../renderer.php");

/**
 */
class datalynxfield_multiselect_renderer extends datalynxfield_renderer {

    /**
     *
     * @var datalynxfield_multiselect
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
        $fieldname = "field_{$fieldid}_$entryid";
        $required = !empty($options['required']);
        $autocomplete = $field->get('param6');

        $content = !empty($entry->{"c{$fieldid}_content"}) ? $entry->{"c{$fieldid}_content"} : null;

        $selected = array();
        if ($entryid > 0 && $content) {
            $contentprepare = str_replace("#", "", $content);
            $selectedraw = explode(',', $contentprepare);

            foreach ($selectedraw as $item) {
                $selected[$item] = $item;
            }
        }

        // If we edit an existing entry that is not required we need a workaround.
        if ($entryid > 0 && !$required && $autocomplete) {
            global $PAGE;
            $PAGE->requires->js_amd_inline("
            require(['jquery'], function($) {
                $('option[value=\"-999\"]').removeAttr('selected');
            });");
        }

        // We create a hidden field to force sending.
        if (!$required && $autocomplete) {
            $mform->addElement('html',
                '<input type="hidden" name="' . $fieldname . '[-1]" value="-999">');
        }

        // Check for default values.
        if (!$selected && $field->get('param2')) {
            $selected = $field->default_values();
        }

        // Normally we just pass integer, option for addnew.
        $paramtype = PARAM_INT;

        // Render as autocomplete field (param6 not empty) or select field.
        if ($autocomplete) {
            $menuoptions = $field->options_menu(false, true);
            $menuoptions[-999] = null; // Allow this option for empty values.

            // If we see the pattern addnew open up option to add menuoptions.
            $fieldattr = array();
            if (isset($options['addnew'])) {
                $fieldattr['tags'] = true;
                $paramtype = PARAM_NOTAGS;
            }

            $select = &$mform->addElement('autocomplete', $fieldname, null, $menuoptions, $fieldattr);
        } else {
            $menuoptions = $field->options_menu();

            // Adapt height of select menu to options within bounds.
            $size = 5;
            if (count($menuoptions) < 10) {
                $size = count($menuoptions);
            }

            $select = &$mform->addElement('select', $fieldname, null, $menuoptions, array('size' => $size));
        }
        $select->setMultiple(true);
        $select->setSelected($selected);
        $mform->setType($fieldname, $paramtype);

        if ($required) {
            $mform->addRule($fieldname, null, 'required', null, 'client');
        }
    }

    /**
     * transform the raw database value into HTML suitable for displaying on the entry page
     * (non-PHPdoc)
     *
     * @return string HTML
     *@see datalynxfield_renderer::render_display_mode()
     */
    public function render_display_mode(stdClass $entry, array $options): string {
        $field = $this->_field;
        $fieldid = $field->id();

        if (isset($entry->{"c{$fieldid}_content"})) {
            $content = $entry->{"c{$fieldid}_content"};
            $contentprepare = str_replace("#", "", $content);

            $options = $field->options_menu();

            $contents = explode(',', $contentprepare);

            $str = array();
            foreach ($options as $key => $option) {
                $selected = (int) in_array($key, $contents);
                if ($selected) {
                    $str[] = $option;
                }
            }

            $separator = $field->separators[(int) $field->get('param3')]['chr'];

            // If we see a fieldgroup we simply display comma separations for now.
            if (isset($options['fieldgroup'])) {
                $separator = ", ";
            }

            if ($separator == '</li><li>' && count($str) > 0) {
                $str = '<ul><li>' . implode($separator, $str) . '</li></ul>';
            } else {
                $str = implode($separator, $str);
            }
        } else {
            $str = '';
        }

        return $str;
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_renderer::render_search_mode()
     */
    public function render_search_mode(MoodleQuickForm &$mform, int $i = 0, string $value = '') {
        $field = $this->_field;
        $fieldid = $field->id();
        $fieldname = "f_{$i}_{$fieldid}";
        $menu = array(-1 => '') + $field->options_menu();
        $options = array('multiple' => true);
        $elements = array();
        $elements[] = $mform->createElement('autocomplete', $fieldname, null, $menu, $options);
        $mform->setType($fieldname, PARAM_INT);
        $mform->setDefault($fieldname, $value);

        if ($mform->_formName == 'mod_datalynx_filter_form' || $mform->_formName == 'mod_datalynx_advanced_filter_form') {
            $mform->disabledIf($fieldname, "searchoperator{$i}", 'eq', '');
        }

        // Add a checkbox to select if any of or all elements are needed, only show this in customfilter forms..
        if ($mform->_formName == 'mod_datalynx_customfilter_frontend_form') {
            // NOTE: Do not set to 0|1 as 1 will be cleared as form input by modform.
            $elements[] = $mform->createElement('advcheckbox', $fieldname . "[andor]", get_string('customfilterandor', 'datalynx'), '', array(), array(0, -2));
        }

        return array($elements, null);
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_renderer::validate()
     */
    public function validate($entryid, $tags, $formdata) {
        $fieldid = $this->_field->id();
        $formfieldname = "field_{$fieldid}_{$entryid}";

        $errors = array();
        foreach ($tags as $tag) {
            list(, $behavior, ) = $this->process_tag($tag);

            // Remove placeholder for empty autocomplete.
            if (isset($formdata->{$formfieldname}[0]) && $formdata->{$formfieldname}[0] == -999) {
                unset($formdata->{$formfieldname}[0]);
            }

            // Variable $behavior datalynx_field_behavior.
            if ($behavior->is_required()) {
                if (empty($formdata->$formfieldname)) {
                    $errors[$formfieldname] = get_string('fieldrequired', 'datalynx');
                } else {
                    $empty = true;
                    foreach ($formdata->$formfieldname as $value) {
                        $empty = $empty && empty($value);
                    }
                    if ($empty) {
                        $errors[$formfieldname] = get_string('fieldrequired', 'datalynx');
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Array of patterns this field supports
     * The label pattern should always be first where applicable
     * so that it is processed first in view templates
     * so that in turn patterns it may contain could be processed.
     *
     * @return array pattern => array(visible in menu, category)
     */
    protected function patterns() {
        $fieldname = $this->_field->name();

        $patterns = parent::patterns();
        $patterns["[[$fieldname]]"] = array(true);
        $patterns["[[$fieldname:addnew]]"] = array(true);

        return $patterns;
    }
}
