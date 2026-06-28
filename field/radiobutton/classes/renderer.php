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
 * @package datalynxfield_radiobutton
 * @subpackage radiobutton
 * @copyright 2014 Ivan Šakić
 * @copyright 2016 David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace datalynxfield_radiobutton;
use datalynxfield_select\renderer as SelectRenderer;
use MoodleQuickForm;
use stdClass;

/**
 * Renderer class for radiobutton field type.
 */
class renderer extends SelectRenderer {
    /**
     * @var datalynxfield_radiobutton The field object.
     */
    protected $field = null; // phpcs:ignore

    /**
     * Renders the field in edit mode.
     *
     * @param MoodleQuickForm $mform The form object.
     * @param stdClass $entry The entry object.
     * @param array $options Additional options.
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        $field = $this->field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $menuoptions = $field->options_menu();
        $fieldname = "field_{$fieldid}_$entryid";
        $required = !empty($options['required']);
        $selected = !empty($entry->{"c{$fieldid}_content"}) ? (int) $entry->{"c{$fieldid}_content"} : 0;

        // Check for default value.
        if (!$selected && $defaultval = $field->get('param2')) {
            $selected = (int) array_search($defaultval, $menuoptions);
        }

        $separator = $field->separators[0]['chr'];
        $param3 = (int) $field->get('param3');
        if (isset($param3) && array_key_exists($param3, $field->separators)) {
            $separator = $field->separators[$param3]['chr'];
        }

        $elemgrp = [];
        foreach ($menuoptions as $id => $option) {
            $radio = &$mform->createElement('radio', $fieldname, '', $option, $id);
            if ($id == $selected) {
                $radio->setChecked(true);
            }
            $elemgrp[] = $radio;
        }

        $group = $mform->addGroup($elemgrp, $fieldname . '_group', null, $separator, false);
        $groupclasses = 'datalynx-radio-group';
        if (str_contains($separator, '<br')) {
            $groupclasses .= ' datalynx-radio-group-vertical';
        }
        $group->setAttributes(['class' => $groupclasses]);

        $mform->setDefaults([$fieldname => (int) $selected]);

        if ($required) {
            $mform->addRule($fieldname . '_group', null, 'required', null, 'client');
        }
    }

    /**
     * Renders the field in display/view mode.
     *
     * @param stdClass $entry The entry object.
     * @param array $options Rendering options.
     * @return string
     */
    public function render_display_mode(stdClass $entry, array $options): string {
        $field = $this->field;
        $fieldid = $field->id();

        if (isset($entry->{"c{$fieldid}_content"})) {
            $selected = (int) $entry->{"c{$fieldid}_content"};
            $fieldformat = $options['field_format'] ?? null;
            $formatmode = '';
            if ($fieldformat) {
                $formatmode = $fieldformat->get_setting('option');
            }

            if ($formatmode === 'stepper') {
                // phpcs:ignore moodle.PHP.ForbiddenGlobalUse.BadGlobal
                global $OUTPUT;
                $menuoptions = $field->options_menu();

                // Find position of selected option in the options menu keys.
                $selectedindex = -1;
                $keys = array_keys($menuoptions);
                $selectedindex = array_search($selected, $keys);
                if ($selectedindex === false) {
                    $selectedindex = -1;
                }

                $icons = [];
                $iconssetting = $fieldformat->get_setting('stepper_icons');
                if (!empty($iconssetting)) {
                    $icons = array_map('trim', explode(',', $iconssetting));
                }

                $steps = [];
                $i = 0;
                foreach ($menuoptions as $key => $label) {
                    $iconclass = '';
                    if (isset($icons[$i]) && $icons[$i] !== '') {
                        $iconclass = $icons[$i];
                        if (!str_contains($iconclass, ' ')) {
                            if (!str_starts_with($iconclass, 'fa-')) {
                                $iconclass = 'fa-' . $iconclass;
                            }
                            $iconclass = 'fa ' . $iconclass;
                        }
                    }

                    $completed = ($selectedindex !== -1 && $i <= $selectedindex);

                    $steps[] = [
                        'label' => format_string($label),
                        'completed' => $completed,
                        'icon' => $iconclass,
                        'hasicon' => !empty($iconclass),
                        'number' => $i + 1,
                    ];
                    $i++;
                }

                $mustachecontext = [
                    'steps' => $steps,
                ];

                // phpcs:ignore moodle.PHP.ForbiddenGlobalUse.BadGlobal
                return $OUTPUT->render_from_template('mod_datalynx/field_radiobutton_stepper', $mustachecontext);
            }
        }

        return parent::render_display_mode($entry, $options);
    }
}
