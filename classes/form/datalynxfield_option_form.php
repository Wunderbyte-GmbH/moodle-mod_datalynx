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

use mod_datalynx\local\field\datalynxfield_option;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * base form for fields that use multi and single choice options
 *
 * @author david
 *
 */
class datalynxfield_option_form extends datalynxfield_form {
    /**
     * @var datalynxfield_option
     */
    protected $field = null;

    /**
     * Adds option dialog elements after initial data population.
     *
     * @return void
     */
    public function definition_after_data() {
        $this->add_option_dialog();
    }

    /**
     *  Prepare the form to edit the options for a single or multi choice field*
     *
     * @return void
     */
    protected function add_option_dialog() {
        $mform = &$this->_form;
        $options = $this->field->get_options();
        if (!empty($options)) {
            $group = [];
            $group[] = &$mform->createElement(
                'static',
                null,
                null,
                '<table><thead><th>' . get_string('option', 'datalynx') . '</th><th>' .
                    get_string('renameoption', 'datalynx') . '</th><th>' .
                    get_string('deleteoption', 'datalynx') . '</th></thead><tbody>'
            );
            foreach ($options as $id => $option) {
                $option = htmlspecialchars($option);
                $group[] = &$mform->createElement(
                    'static',
                    null,
                    null,
                    "<tr><td>{$option}</td><td>"
                );
                $group[] = &$mform->createElement('text', "renameoption[{$id}]", '', ['size' => 32]);
                $group[] = &$mform->createElement('static', null, null, '</td><td>');
                $group[] = &$mform->createElement('checkbox', "deleteoption[{$id}]", '', null, ['size' => 1]);
                foreach ($options as $newid => $newoption) {
                    $mform->disabledIf("renameoption[{$id}]", "deleteoption[{$newid}]", 'checked');
                }
                $group[] = &$mform->createElement('static', null, null, '</td></tr>');
            }
            $group[] = &$mform->createElement('static', null, null, '</tbody></table>');
            $tablerow = &$mform->createElement(
                'group',
                'existingoptions',
                get_string('existingoptions', 'datalynx'),
                $group,
                null,
                false
            );
            $mform->insertElementBefore($tablerow, 'param2');
        }
        $addnew = &$mform->createElement(
            'textarea',
            'addoptions',
            get_string('addoptions', 'datalynx'),
            'wrap="soft" rows="5" cols="30"'
        );
        $mform->insertElementBefore($addnew, 'param2');
        if (empty($options)) {
            $mform->addRule(
                'addoptions',
                get_string('err_required', 'form'),
                'required',
                null,
                'client'
            );
        }
    }

    /**
     * Validate form data
     *
     * @param array $data The submitted form data.
     * @param array $files The submitted files.
     * @return array Validation errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $oldoptions = $this->field->get_options();
        if (count($oldoptions) == 0 && empty($data['addoptions'])) {
            $errors['existingoptions'] = get_string('nooptions', 'datalynx');
        } else {
            if (
                    isset($data['deleteoption']) && count($data['deleteoption']) == count(
                        $oldoptions
                    ) && empty($data['addoptions'])
            ) {
                $errors['existingoptions'] = get_string('nooptions', 'datalynx');
            } else {
                if (isset($data['deleteoption']) && isset($data['renameoption'])) {
                    $errors['existingoptions'] = get_string('avoidaddanddeletesimultaneously', 'datalynx');
                }
            }
        }

        return $errors;
    }
}
