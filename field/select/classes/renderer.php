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
 * @package datalynxfield_select
 * @subpackage select
 * @copyright 2014 Ivan Šakić
 * @copyright 2016 David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_select;

use mod_datalynx\local\field\datalynxfield_renderer;
use MoodleQuickForm;
use stdClass;

/**
 * Class datalynxfield_select_renderer Renderer for select field type
 */
class renderer extends datalynxfield_renderer {
    /**
     *
     * @var datalynxfield_select
     */
    protected $field = null;

    /**
     *
     * {@inheritDoc}
     * @param MoodleQuickForm $mform The Moodle form instance.
     * @param stdClass $entry The entry object.
     * @param array $options Rendering options.
     * @see datalynxfield_renderer::render_edit_mode()
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options): void {
        $field = $this->field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_$entryid";
        $required = !empty($options['required']);
        $selected = $this->get_selected_option($entry);
        $autocomplete = $field->get('param6');

        // Render as autocomplete field (param6 not empty) or select field.
        if ($autocomplete) {
            $select = &$mform->addElement('autocomplete', $fieldname, null);
        } else {
            $select = &$mform->addElement('select', $fieldname, null);
        }

        $disabled = $this->get_disabled_options();
        // An autocomplete has its own empty choice; "Choose..." would show as the selected value.
        $menu = ($autocomplete ? [] : ['' => get_string('choosedots')]) + $this->get_ordered_labels();
        foreach ($menu as $id => $name) {
            if (array_search($id, $disabled) === false || $id == $selected) {
                $select->addOption($name, $id);
            } else {
                $select->addOption($name, $id, ['disabled' => 'disabled']);
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
     * @param stdClass $entry The entry object.
     * @param array $options Rendering options.
     * @return string
     * @see datalynxfield_renderer::render_display_mode()
     */
    public function render_display_mode(stdClass $entry, array $options): string {
        $field = $this->field;
        $fieldid = $field->id();

        if (isset($entry->{"c{$fieldid}_content"})) {
            $selected = (int) $entry->{"c{$fieldid}_content"};
            $menuoptions = $field->options_menu();

            $fieldformat = $options['field_format'] ?? null;
            $formatmode = '';
            if ($fieldformat) {
                $formatmode = $fieldformat->get_setting('option');
            }

            if (!empty($options['options']) || $formatmode === 'options') {
                $str = [];
                foreach ($menuoptions as $key => $option) {
                    $isselected = (int) ($key == $selected);
                    $str[] = "$isselected $option";
                }
                $str = implode(',', $str);
                return format_string($str);
            }

            if (!empty($options['key']) || $formatmode === 'key') {
                if ($selected) {
                    return format_string($selected);
                } else {
                    return '';
                }
            }

            if ($selected && isset($menuoptions[$selected])) {
                return format_string($menuoptions[$selected]);
            }
        }
        return '';
    }

    /**
     *
     * {@inheritDoc}
     * @param MoodleQuickForm $mform The Moodle form instance.
     * @param int $i The search index.
     * @param string $value The current search value.
     * @return array
     * @see datalynxfield_renderer::render_search_mode()
     */
    public function render_search_mode(MoodleQuickForm &$mform, int $i = 0, string $value = ''): array {
        $field = $this->field;
        $fieldid = $field->id();
        $fieldname = "f_{$i}_{$fieldid}";
        $menu = [-1 => ''] + $this->format_option_labels($field->options_menu());
        $options = ['multiple' => true];
        $elements = [];
        $elements[] = $mform->createElement('autocomplete', $fieldname, null, $menu, $options);
        $mform->setType($fieldname, PARAM_INT);
        $decoded = json_decode($value);
        if (is_array($decoded)) {
            $mform->setDefault($fieldname, $decoded);
        }

        $showprofilecompare = !empty($mform->showprofilecompare) || !empty($mform->getAttribute('showprofilecompare'));
        if ($showprofilecompare) {
            $mform->disabledIf($fieldname, "searchoperator{$i}", 'neq', 'ANY_OF');

            // Profile field selector, used by the MY_PROFILE operator.
            $profilename = "f_{$i}_{$fieldid}_profile";
            $profilemenu = ['' => get_string('choosedots')] + \datalynxfield_select\field::get_profile_field_menu();
            $elements[] = $mform->createElement('select', $profilename, null, $profilemenu);
            $mform->setType($profilename, PARAM_ALPHANUMEXT);
            if (!is_array($decoded) && $value !== '') {
                $mform->setDefault($profilename, $value);
            }
            $mform->disabledIf($profilename, "searchoperator{$i}", 'neq', 'MY_PROFILE');
        }

        return [$elements, null];
    }

    /**
     *
     * @param integer $entryid
     * @param unknown $tags
     * @param object $formdata
     * @return string[]
     */
    public function validate($entryid, $tags, $formdata): array {
        $fieldid = $this->field->id();
        $errors = [];

        $formfieldname = "field_{$fieldid}_{$entryid}";

        // Not every field of this dataynx-instance has to be in the form!
        if (isset($formdata->{$formfieldname})) {
            if (isset($this->field->field->param5) && is_numeric($this->field->field->param5) && $this->field->field->param5 > 0) {
                $disabled = $this->field->get_disabled_values_for_user($entryid);
                $content = clean_param($formdata->{$formfieldname}, PARAM_INT);
                if (array_search($content, $disabled) !== false) {
                    $menu = $this->field->options_menu();
                    // Shown where the field renders its error, e.g. the group of radio buttons.
                    $errors[$this->get_error_element($formfieldname)] =
                        get_string('limitchoiceerror', 'datalynx', $menu[$content]);
                }
            }
        }
        // A team member select may take its team size from this field: not below its members.
        if (isset($formdata->$formfieldname) && !$errors) {
            $errors = \datalynxfield_teammemberselect\field::limit_errors(
                $this->field,
                $entryid,
                $this->get_error_element($formfieldname),
                $formdata->$formfieldname
            );
        }
        return $errors;
    }

    /**
     * A single choice is searched by any of its options.
     *
     * @return string
     */
    public function get_customfilter_control(): string {
        return 'select';
    }

    /**
     * The options of the field.
     *
     * @return array
     */
    public function get_customfilter_options(): array {
        return $this->format_option_labels($this->field->options_menu());
    }

    /**
     * The option chosen in an entry; for an entry without one, the field's default option.
     *
     * @param stdClass $entry
     * @return int 0 for none
     */
    protected function get_selected_option(stdClass $entry): int {
        $field = $this->field;
        $fieldid = $field->id();
        $selected = !empty($entry->{"c{$fieldid}_content"}) ? (int) $entry->{"c{$fieldid}_content"} : 0;
        // The default is matched against the raw option text.
        if (!$selected && $defaultval = $field->get('param2')) {
            $selected = (int) array_search($defaultval, $field->options_menu());
        }

        return $selected;
    }

    /**
     * The options the user may no longer choose: an option may be chosen a limited number of
     * times (param5).
     *
     * @return array option ids
     */
    protected function get_disabled_options(): array {
        $limit = $this->field->field->param5 ?? null;
        return is_numeric($limit) && $limit > 0 ? $this->field->get_disabled_values_for_user() : [];
    }

    /**
     * The option labels in the order they are offered: as configured, or alphabetically (param4).
     *
     * @return array option id => label
     */
    protected function get_ordered_labels(): array {
        $labels = $this->format_option_labels($this->field->options_menu());
        if ($this->field->field->param4) {
            uasort($labels, fn($a, $b) => strcoll((string) $a, (string) $b));
        }

        return $labels;
    }

    /**
     * A single choice is edited with a select; with autocomplete (param6) the app lets the user
     * type and pick from the matching choices, as the web does.
     *
     * @param stdClass $entry
     * @param array $options
     * @return array|null
     */
    public function get_edit_control(stdClass $entry, array $options): ?array {
        $selected = $this->get_selected_option($entry);
        // An option chosen before it was used up stays choosable.
        $disabled = array_diff($this->get_disabled_options(), [$selected]);

        return $this->edit_control('select', ['' => $selected ?: ''], $options, [
            'choices' => self::edit_choices($this->get_ordered_labels(), $disabled),
            'autocomplete' => !empty($this->field->get('param6')),
        ]);
    }
}
