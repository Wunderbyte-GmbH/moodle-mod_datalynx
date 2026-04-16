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
 * @package datalynxfield_checkbox
 * @subpackage checkbox
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace datalynxfield_checkbox;
use datalynxfield_multiselect\renderer as MultiSelectRenderer;
use stdClass;
use MoodleQuickForm;

/**
 * Class datalynxfield_checkbox_renderer Renderer for checkbox field type
 */
class renderer extends MultiSelectRenderer {
    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_renderer::render_edit_mode()
     *
     * @param MoodleQuickForm $mform The Moodle form object.
     * @param stdClass $entry The entry object.
     * @param array $options Rendering options.
     * @return void
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        $field = $this->field;
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

        $elemgrp = [];
        foreach ($menuoptions as $i => $option) {
            $elemgrp[] = &$mform->createElement(
                'advcheckbox',
                $i,
                null,
                $option,
                ['size' => 1],
                [null, $i]
            );
        }

        $mform->addGroup($elemgrp, $fieldname, null, $separator, true);

        $selected = [];
        if ($entryid > 0 && $content) {
            $contentprepare = str_replace("#", "", $content);
            $selectedraw = explode(',', $contentprepare);

            foreach ($selectedraw as $item) {
                $selected[$item] = $item;
            }
        }

        // Check for default values.
        if (!$selected && $field->get('param2')) {
            $selected = $field->default_values();
        }

        $mform->getElement($fieldname)->setValue($selected);

        if ($required) {
            $mform->addGroupRule($fieldname, get_string('err_required', 'form'),
                    'required', null, 1, 'client');
        }
    }
}
