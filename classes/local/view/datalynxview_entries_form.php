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
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx\local\view;
use mod_datalynx\local\entry\entry_submission_service;
use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Entries form
 *
 * @package mod_datalynx
 */
class datalynxview_entries_form extends moodleform {
    /**
     * Form definition
     */
    protected function definition() {
        $view = $this->_customdata['view'];
        $mform = &$this->_form;
        $mform->addElement('hidden', 'new', optional_param('new', 0, PARAM_INT));
        $mform->setType('new', PARAM_INT);

        $hassubmit = (strpos($view->view->eparam2 ?? '', '##submit') !== false)
            || (strpos($view->view->param2 ?? '', '##submit') !== false);
        $hascancel = (strpos($view->view->eparam2 ?? '', '##cancel') !== false)
            || (strpos($view->view->param2 ?? '', '##cancel') !== false);
        $hascustombuttons = ($hassubmit || $hascancel);

        if (!$hascustombuttons) {
            $this->add_action_buttons();
        }

        $view->definition_to_form($mform);

        if (!$hascustombuttons) {
            // Add delegate action button... try.
            $this->add_delegate_action_buttons();
        }
    }

    /**
     * Add action buttons that delegate functions to allow multiple buttons on one form.
     *
     * @return void
     */
    public function add_delegate_action_buttons(): void {
        $mform =& $this->_form;
        $buttonarray = [];

        $mform->addElement('static', 'button_spacer', '', '<div class="w-100 my-4"></div>');
        // Save Button (Primary).
        $buttonarray[] = $mform->createElement(
            'static',
            'delegate_save',
            '',
            '<button type="button" class="btn btn-primary" id="id_delegate_save" name="delegate_save" ' .
            'data-datalynx-delegate-click="id_submitbutton">' .
            get_string('savechanges') . '</button>'
        );

        // Cancel Button (Secondary).
        $buttonarray[] = $mform->createElement(
            'static',
            'delegate_cancel',
            '',
            '<button type="button" class="btn btn-secondary" id="id_delegate_cancel" name="delegate_cancel" ' .
            'data-datalynx-delegate-click="id_cancel">' .
            get_string('cancel') . '</button>'
        );

        $mform->addGroup($buttonarray, 'delegatebuttonar', '', null, false);
        $mform->closeHeaderBefore('delegatebuttonar');
    }

    /**
     * Validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($errors) {
            return $errors;
        }

        // The entry submission service validates for the web form and the web services alike.
        $service = new entry_submission_service($this->_customdata['view']);
        $entryids = explode(',', (string) $this->_customdata['update']);

        return $service->validate($entryids, $service->filter_data($entryids, (object) $data));
    }

    /**
     * Returns an HTML version of the form
     *
     * @return string HTML version of the form
     */
    public function html(): string {
        return $this->_form->toHtml();
    }
}
