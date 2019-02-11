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
 * @subpackage checkbox
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once(dirname(__FILE__) . "/../multiselect/renderer.php");

/**
 * Class datalynxfield_checkbox_renderer Renderer for checkbox field type
 */
class datalynxfield_checkbox_renderer extends datalynxfield_multiselect_renderer {

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
        $menuoptions = $field->options_menu();
        if (isset($options['required'])) {
            $required = $options['required'];
        } else {
            $required = false;
        }

        $content = !empty($entry->{"c{$fieldid}_content"}) ? $entry->{"c{$fieldid}_content"} : null;

        $separator = $field->separators[(int) $field->get('param2')]['chr'];

        $elemgrp = array();
        foreach ($menuoptions as $i => $option) {
            $elemgrp[] = &$mform->createElement('advcheckbox', $i, null, $option, null,
                    array(null, $i));
        }

        $mform->addGroup($elemgrp, $fieldname, null, $separator, true);

        $selected = array();
        if ($entryid > 0 and $content) {
            $contentprepare = str_replace("#", "", $content);
            $selectedraw = explode(',', $contentprepare);

            foreach ($selectedraw as $item) {
                $selected[$item] = $item;
            }
        }

        // Check for default values.
        if (!$selected and $field->get('param2')) {
            $selected = $field->default_values();
        }

        $mform->getElement($fieldname)->setValue($selected);

        if ($required) {
            $mform->addGroupRule($fieldname, get_string('err_required', 'form'), 'required', null, 1);
        }
    }

}
