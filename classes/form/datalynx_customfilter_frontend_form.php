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
 * @package mod_datalynx
 * @copyright 2014 onwards by edulabs.org and associated programmers
 * @copyright based on the work by 2013 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx\form;

use mod_datalynx\local\filter\customfilter_controls;
use mod_datalynx\local\filter\customfilter_search;
use moodle_exception;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * Class customfilter_frontend_form to display the customfilter options in browse mode
 *
 */
class datalynx_customfilter_frontend_form extends datalynx_filter_base_form {
    /**
     * Defines the custom filter frontend form elements.
     *
     * @return void
     */
    public function definition() {
        $view = $this->_customdata['view'];

        if (!$customfilter = $this->customfilter) {
            throw new moodle_exception('nocustomfilter', 'datalynx');
        }

        // The controls of the filter, the same ones the Moodle App shows (mod_datalynx#270): the
        // inputs are named and read alike, see customfilter_controls.
        $search = new customfilter_search($this->dlx, $customfilter);
        $described = customfilter_controls::describe($search, $view, []);
        $fields = $search->get_fields($view);
        $prefix = customfilter_controls::PREFIX;

        $mform = &$this->_form;
        $mform->addElement('header', 'collapseCustomfilter', get_string('search'));
        $mform->setExpanded('collapseCustomfilter', false);

        $count = 1;
        foreach ($described['controls'] as $control) {
            $key = $control['key'];
            $name = $prefix . $key;
            $options = array_column($control['options'], 'label', 'value');
            if ($key === 'search') {
                $mform->addElement('text', $name, get_string('search'));
                $mform->setType($name, PARAM_TEXT);
                continue;
            }
            if ($key === 'userid') {
                $mform->addElement('autocomplete', $name, $control['name'], $options, ['multiple' => true]);
                $mform->setType($name, PARAM_INT);
                continue;
            }

            $elements = [];
            switch ($control['control']) {
                case 'text':
                    $elements[] = $mform->createElement('text', $name, null, ['size' => 32]);
                    $mform->setType($name, PARAM_TEXT);
                    break;
                case 'range':
                    foreach (['from', 'to'] as $bound) {
                        $label = get_string("customfilter{$bound}", 'datalynx');
                        $elements[] = $mform->createElement(
                            'text',
                            "{$name}_{$bound}",
                            null,
                            ['size' => 6, 'placeholder' => $label, 'aria-label' => $label]
                        );
                        $mform->setType("{$name}_{$bound}", PARAM_RAW_TRIMMED);
                    }
                    break;
                case 'daterange':
                    foreach (['from', 'to'] as $bound) {
                        $elements[] = $mform->createElement(
                            'date_selector',
                            "{$name}_{$bound}",
                            get_string("customfilter{$bound}", 'datalynx'),
                            ['optional' => true]
                        );
                    }
                    break;
                case 'select':
                case 'multiselect':
                    $elements[] = $mform->createElement('autocomplete', $name, null, $options, ['multiple' => true]);
                    $mform->setType($name, PARAM_TEXT);
                    if ($control['control'] === 'multiselect') {
                        $elements[] = $mform->createElement(
                            'advcheckbox',
                            "{$name}_mode",
                            get_string('customfilterandor', 'datalynx'),
                            '',
                            [],
                            ['any', 'all']
                        );
                    }
                    break;
                case 'approve':
                case 'status':
                case 'presence':
                    if ($control['control'] === 'presence') {
                        $options = [
                            'exists' => get_string('fileexist', 'datalynx'),
                            'missing' => get_string('filemissing', 'datalynx'),
                        ];
                    }
                    $elements[] = $mform->createElement('select', $name, null, ['' => get_string('choosedots')] + $options);
                    break;
                default:
                    // A search no control can express keeps the field's own elements (f_{i}_{fieldid}).
                    $field = $fields[$key] ?? $fields[(int) $key] ?? null;
                    if ($field) {
                        [$elements] = $field->renderer()->render_search_mode($mform, $count, '');
                    }
            }
            if ($elements) {
                $mform->addGroup($elements, "customsearcharr{$count}", $control['name'], ' ', false);
                $count++;
            }
        }

        if ($described['sortfields']) {
            $grp = [];
            $sortfields = ['' => get_string('choosedots')] + array_column($described['sortfields'], 'label', 'value');
            $grp[] = $mform->createElement('select', "{$prefix}sort", '', $sortfields);
            $grp[] = $mform->createElement('select', "{$prefix}sortdir", '', ['0' => get_string('asc'), '1' => get_string('desc')]);
            $mform->addGroup($grp, "customfiltersort_grp", get_string('sortby'), ' ', false);
        }

        // Show buttons in line with each other.
        $buttonarray = [];
        $buttonarray[] = &$mform->createElement('submit', 'customsearch', get_string("search"));

        // Add a button that resets all custom filter values at once.
        $clearcustomsearch = '<a class="btn btn-secondary" href="';
        $clearcustomsearch .= new moodle_url(
            '/mod/datalynx/view.php',
            ['id' => $this->dlx->cm->id, 'view' => $view->view->id, 'filter' => 0]
        );
        $clearcustomsearch .= '"> ' . get_string('resetsettings', 'datalynx') . '</a>';
        $buttonarray[] = &$mform->createElement('static', 'clearcustomsearch', '', $clearcustomsearch);

        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
    }

    /**
     * Export form elements for rendering in a Mustache template.
     *
     * @return \stdClass The template context data.
     */
    public function export_for_template(): \stdClass {
        global $OUTPUT;

        $quickform = $this->_form;

        // Render a single element through Moodle's per-element renderer instead of raw toHtml().
        // Using ingroup = true selects the *-inline templates (field only, no fitem/label wrapper)
        // and still calls the element's export_for_template(), so complex elements such as
        // date_time_selector run form_init_date_js() and autocompletes load their enhancement JS.
        // The grid template renders our own styled label, so the element's own label is suppressed.
        $renderfield = function ($element) use ($OUTPUT) {
            $origlabel = $element->getLabel();
            $element->setLabel('');
            $html = $OUTPUT->mform_element($element, false, false, '', true);
            $element->setLabel($origlabel);
            if ($html === false || $html === null) {
                // Fallback for elements without a Moodle template.
                $html = $element->toHtml();
            }
            return $html;
        };

        // Ensure all elements and group sub-elements have their standard Moodle IDs set for Behat & accessibility.
        foreach ($quickform->_elements as $element) {
            $name = $element->getName();
            if ($name && !$element->getAttribute('id')) {
                $element->updateAttributes(['id' => 'id_' . $name]);
            }
            if ($element instanceof \HTML_QuickForm_group) {
                foreach ($element->getElements() as $subelem) {
                    $subname = $subelem->getName();
                    if ($subname && !$subelem->getAttribute('id')) {
                        $subelem->updateAttributes(['id' => 'id_' . $subname]);
                    }
                }
            }
        }

        $data = new \stdClass();
        // The action attribute is stored as a moodle_url object; out(false) yields a raw URL so the
        // mustache {{action}} placeholder escapes it exactly once (avoids double-encoded &amp;amp;).
        $action = $quickform->getAttribute('action');
        $data->action = ($action instanceof \moodle_url) ? $action->out(false) : $action;
        $data->method = $quickform->getAttribute('method');
        $data->formid = $quickform->getAttribute('id');
        $data->filtername = get_string('search');
        $data->isexpanded = $this->is_submitted() ||
            optional_param('cfilter', 0, PARAM_INT);

        $hiddenfields = '';
        if (isset($quickform->_pageparams)) {
            $hiddenfields .= $quickform->_pageparams;
        }
        $fulltextsearch = null;
        $authorsearch = null;
        $searchfields = [];
        $sortby = null;
        $buttons = [];

        foreach ($quickform->_elements as $element) {
            $name = $element->getName();
            $type = $element->getType();

            if ($type === 'hidden') {
                $hiddenfields .= $element->toHtml();
            } else if ($name === customfilter_controls::PREFIX . 'search') {
                $fulltextsearch = [
                    'label' => $element->getLabel() ?: get_string('search', 'datalynx'),
                    'html' => $renderfield($element),
                ];
            } else if ($name === customfilter_controls::PREFIX . 'userid') {
                $authorsearch = [
                    'label' => $element->getLabel(),
                    'html' => $renderfield($element),
                ];
            } else if (strpos($name, 'customsearcharr') === 0) {
                $searchfields[] = [
                    'label' => $element->getLabel(),
                    'html' => $renderfield($element),
                ];
            } else if ($name === 'customfiltersort_grp') {
                $sortby = [
                    'label' => $element->getLabel(),
                    'html' => $renderfield($element),
                ];
            } else if ($name === 'buttonar') {
                if ($element instanceof \HTML_QuickForm_group) {
                    foreach ($element->getElements() as $subelem) {
                        $buttons[] = [
                            'html' => $subelem->toHtml(),
                        ];
                    }
                } else {
                    $buttons[] = [
                        'html' => $element->toHtml(),
                    ];
                }
            } else if ($name === 'sesskey' || $name === '_qf__' . $this->_formname) {
                $hiddenfields .= $element->toHtml();
            }
        }

        $data->hiddenfields = $hiddenfields;
        $data->fulltextsearch = $fulltextsearch;
        $data->authorsearch = $authorsearch;
        $data->searchfields = $searchfields;
        $data->sortby = $sortby;
        $data->buttons = $buttons;

        return $data;
    }
}
