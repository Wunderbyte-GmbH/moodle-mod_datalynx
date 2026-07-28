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

        $customfilterfieldlistfields = [];
        if ($customfilter->fieldlist) {
            $customfilterfieldlistfields = json_decode($customfilter->fieldlist);
        }
        $fields = $view->get_view_fields();
        $fieldoptions = [];
        $sortfields = [];
        foreach ($fields as $fieldid => $field) {
            $select = false;
            foreach ($customfilterfieldlistfields as $fid => $listfield) {
                if ($field->field->id == $fid) {
                    $select = true;
                    if ($listfield->sortable) {
                        $sortfields[$fid] = $listfield->name;
                    }
                    break;
                }
            }
            if ($select == false) {
                switch ($field->field->name) {
                    case (get_string("approved", "datalynx")):
                        if ($customfilter->approve) {
                            $select = true;
                        }
                        break;
                    case (get_string("timecreated", "datalynx")):
                        if ($customfilter->timecreated) {
                            $select = true;
                        }
                        if ($customfilter->timecreatedsortable) {
                            $sortfields[$fieldid] = $field->field->name;
                        }
                        break;
                    case (get_string("timemodified", "datalynx")):
                        if ($customfilter->timemodified) {
                            $select = true;
                        }
                        if ($customfilter->timemodifiedsortable) {
                            $sortfields[$fieldid] = $field->field->name;
                        }
                        break;
                    case (get_string("status", "datalynx")):
                        if ($customfilter->status) {
                            $select = true;
                        }
                        break;
                }
            }
            if ($select) {
                $fieldoptions[$fieldid] = $field->field->name;
            }
        }

        $mform = &$this->_form;
        $mform->addElement('header', 'collapseCustomfilter', get_string('search'));
        $mform->setExpanded('collapseCustomfilter', false);

        if ($customfilter->fulltextsearch) {
            $mform->addElement('text', 'search', get_string('search'));
            $mform->setType('search', PARAM_TEXT);
        }

        // Search for author.
        if (isset($customfilter->authorsearch) && $customfilter->authorsearch) {
            // Add users that have written an entry in the current datalynx instance to list.
            global $DB;
            $entryauthors = $DB->get_records_sql('SELECT DISTINCT userid, firstname, lastname
                FROM {datalynx_entries}
                INNER JOIN {user} on {datalynx_entries}.userid = {user}.id
                WHERE {datalynx_entries}.dataid = ' . $this->dlx->id() . ';');

            $menu = [];
            foreach ($entryauthors as $userid => $author) {
                $menu[$userid] = $author->firstname . " " . $author->lastname;
            }
            $options = ['multiple' => true];
            $mform->addElement('autocomplete', 'authorsearch', get_string('authorsearch', 'datalynx'), $menu, $options);
            $mform->setType('authorsearch', PARAM_INT);
        }

        // Custom search.
        if ($customfilter->fieldlist) {
            $this->customfilter_search_definition($fields, $fieldoptions);
        }

        if (!empty($sortfields)) {
            // These are collected outside keep_sortable_fieldoptions(), so format them here.
            $sortfields = array_map([$this, 'format_field_label'], $sortfields);
            // Important, keep fieldids intact.
            $sortfields = [0 => get_string('choosedots')] + $sortfields;

            $grp = [];
            $grp[] = $mform->createElement('select', 'customfiltersortfield', '', $sortfields);
            $directions = ["0" => get_string('asc'), "1" => get_string('desc')];

            $grp[] = $mform->createElement('select', 'customfiltersortdirection', '', $directions);
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
    public function export_for_template() {
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
            } else if ($name === 'search') {
                $fulltextsearch = [
                    'label' => $element->getLabel() ?: get_string('search', 'datalynx'),
                    'html' => $renderfield($element),
                ];
            } else if ($name === 'authorsearch') {
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
