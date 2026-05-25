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
 * Entry per user tool form.
 *
 * @package    datalynxtool_entryperuser
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxtool_entryperuser\form;

use moodleform;
use HTML_QuickForm;
use moodle_url;
use html_writer;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for configuring default entry values per user.
 */
class entryperuser_form extends moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = &$this->_form;
        $dlx = $this->_customdata['dlx'];
        $step = $this->_customdata['step'];
        $selectedviewid = $this->_customdata['selectedviewid'];

        $mform->addElement('hidden', 'step', $step);
        $mform->setType('step', PARAM_INT);

        if ($step == 1) {
            $this->definition_step_1($mform, $dlx, $selectedviewid);
        } else {
            $this->definition_step_2($mform, $dlx, $selectedviewid);
        }
    }

    /**
     * Definition for Step 1: Select View.
     */
    protected function definition_step_1(&$mform, $dlx, $selectedviewid) {
        $views = $dlx->get_views();
        $viewoptions = [];

        foreach ($views as $viewid => $view) {
            $hasedit = false;
            foreach ((array) $view->view as $val) {
                if (is_string($val) && strpos($val, '##edit##') !== false) {
                    $hasedit = true;
                    break;
                }
            }
            if ($hasedit) {
                $viewoptions[$viewid] = format_string($view->view->name);
            }
        }

        $mform->addElement('header', 'selectviewhdr', get_string('selectview', 'datalynxtool_entryperuser'));

        if (empty($viewoptions)) {
            $mform->addElement('static', 'noviews_error', '', html_writer::span(
                get_string('noviews', 'datalynxtool_entryperuser'),
                'text-danger font-weight-bold'
            ));
            $this->add_action_buttons(true, null);
        } else {
            $mform->addElement('select', 'viewid', get_string('selectview', 'datalynxtool_entryperuser'), $viewoptions);
            $mform->setType('viewid', PARAM_INT);
            $mform->addHelpButton('viewid', 'selectview', 'datalynxtool_entryperuser');

            if ($selectedviewid && isset($viewoptions[$selectedviewid])) {
                $mform->setDefault('viewid', $selectedviewid);
            }

            $buttonarray = [];
            $buttonarray[] = &$mform->createElement('submit', 'nextbutton', get_string('next', 'datalynxtool_entryperuser'));
            $buttonarray[] = &$mform->createElement('cancel', 'cancelbutton', get_string('cancel'));
            $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
        }
    }

    /**
     * Definition for Step 2: Input default values.
     */
    protected function definition_step_2(&$mform, $dlx, $selectedviewid) {
        $mform->addElement('hidden', 'viewid', $selectedviewid);
        $mform->setType('viewid', PARAM_INT);

        $view = $dlx->get_view_from_id($selectedviewid);
        if (!$view) {
            $mform->addElement('static', 'error_noview', '', get_string('noviews', 'datalynxtool_entryperuser'));
            return;
        }

        $mform->addElement('header', 'viewformhdr', format_string($view->view->name));

        // Detect required fields.
        $fields = $view->get_entry_form_fields();
        $requiredfields = [];
        foreach ($fields as $field) {
            if ($field->get_behavior()->is_required()) {
                $requiredfields[] = $field;
            }
        }

        // Show warnings / info.
        if (!empty($requiredfields)) {
            $mform->addElement('static', 'warning_msg', '', html_writer::div(
                get_string('requiredfieldswarning', 'datalynxtool_entryperuser'),
                'alert alert-warning'
            ));
        } else {
            $mform->addElement('static', 'info_msg', '', html_writer::div(
                get_string('norequiredfields', 'datalynxtool_entryperuser'),
                'alert alert-info'
            ));
        }

        // View edit link suggestion.
        $viewsurl = new moodle_url('/mod/datalynx/view/index.php', ['d' => $dlx->id()]);
        $suggestion = get_string('createviewsuggestion', 'datalynxtool_entryperuser', $viewsurl->out());
        $mform->addElement('static', 'suggestion_msg', '', html_writer::div($suggestion, 'text-muted mb-3'));

        // Render view form elements.
        $view->editentries = [-1];
        $view->set_display_definition();
        $view->definition_to_form($mform);

        // Buttons.
        $buttonarray = [];
        $buttonarray[] = &$mform->createElement(
            'submit',
            'submitbutton',
            get_string('generateentries', 'datalynxtool_entryperuser')
        );
        $buttonarray[] = &$mform->createElement(
            'submit',
            'backbutton',
            get_string('back', 'datalynxtool_entryperuser'),
            ['class' => 'btn btn-secondary']
        );
        $buttonarray[] = &$mform->createElement('cancel', 'cancelbutton', get_string('cancel'));
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
    }

    /**
     * Form validation.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $step = isset($data['step']) ? (int) $data['step'] : 1;

        // Validation only applies to Step 2 when generating entries.
        // We bypass validation when the user clicks 'Back'.
        if ($step == 2 && !isset($data['backbutton'])) {
            $dlx = $this->_customdata['dlx'];
            $viewid = isset($data['viewid']) ? (int) $data['viewid'] : 0;
            $view = $dlx->get_view_from_id($viewid);

            if ($view) {
                $view->editentries = [-1];
                $view->set_display_definition();
                $patterns = $view->get_entry_form_patterns();
                $fields = $view->get_entry_form_fields();

                if (!empty($patterns) && !empty($fields)) {
                    foreach ($fields as $fid => $field) {
                        if (!isset($patterns[$fid])) {
                            continue;
                        }
                        $newerrors = $field->renderer()->validate(-1, $patterns[$fid], (object) $data);
                        $errors = array_merge($errors, $newerrors);
                    }
                }
            }
        }

        return $errors;
    }
}
