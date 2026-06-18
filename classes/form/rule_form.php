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

namespace mod_datalynx\form;

use coding_exception;
use mod_datalynx\local\rule\manager;
use moodleform;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Datalynx rule form base class.
 *
 * @package    mod_datalynx
 * @copyright  2025 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_form extends moodleform {
    /**
     * @var object
     */
    protected $rule = null;

    /**
     * @var \mod_datalynx\datalynx
     */
    protected $dlx = null;

    /**
     * rule_form constructor.
     *
     * @param mixed $rule
     * @param mixed $action
     * @param mixed $customdata
     * @param string $method
     * @param string $target
     * @param mixed $attributes
     * @param bool $editable
     */
    public function __construct(
        $rule,
        $action = null,
        $customdata = null,
        $method = 'post',
        $target = '',
        $attributes = null,
        $editable = true
    ) {
        $this->rule = $rule;
        $this->dlx = $this->rule->dlx;

        parent::__construct($action, $customdata, $method, $target, $attributes, $editable);
    }

    /**
     * Form definition
     *
     * @throws coding_exception
     */
    public function definition() {
        global $CFG;
        $mform = &$this->_form;

        // Buttons.
        $this->add_action_buttons();

        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Name.
        $mform->addElement('text', 'name', get_string('name'), ['size' => '32']);
        $mform->addRule('name', null, 'required', null, 'client');

        // Description.
        $mform->addElement('text', 'description', get_string('description'), ['size' => '64']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
            $mform->setType('description', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_RAW);
            $mform->setType('description', PARAM_RAW);
        }

        // Enabled.
        $mform->addElement(
            'advcheckbox',
            'enabled',
            get_string('ruleenabled', 'datalynx'),
            '',
            null,
            [0, 1]
        );

        // Events.
        $eventmenu = manager::get_event_data($this->dlx->id());
        $eventgroup = [];
        foreach ($eventmenu as $eventname => $eventlabel) {
            $eventgroup[] = &$mform->createElement('checkbox', $eventname, null, $eventlabel, ['size' => 32]);
        }
        $mform->addGroup(
            $eventgroup,
            'eventsgroup',
            get_string('triggeringevent', 'datalynx'),
            '<br />',
            false
        );

        $choices = ['0' => get_string('noselection', 'datalynx')] + $this->dlx->get_fields(['entry'], true);
        if (count($choices) > 1) {
            $mform->addElement(
                'select',
                'param5',
                get_string('triggerspecificevent', 'datalynxrule_eventnotification'),
                $choices,
                ['onchange' => 'window.skipClientValidation = true; this.form.elements["reloadconditions"].click();']
            );
            $mform->registerNoSubmitButton('reloadconditions');

            $submitted = $mform->getSubmitValue('param5');
            if ($submitted !== null && $submitted !== '') {
                $fieldid = (int)$submitted;
            } else {
                $fieldid = !empty($this->rule->rule->param5) ? (int)$this->rule->rule->param5 : 0;
            }

            if ($fieldid) {
                $field = $this->dlx->get_field_from_id($fieldid);
                if ($field) {
                    $value = !empty($this->rule->rule->param10) ? $this->rule->rule->param10 : '';
                    if ($value !== '') {
                        $decoded = json_decode($value, true);
                        if ($decoded === null) {
                            if ($field instanceof \mod_datalynx\local\field\datalynxfield_option_multiple) {
                                $value = json_encode(explode(',', $value));
                            }
                        }
                    }

                    // Set up a hidden searchoperator0 so validation / disabledIf doesn't disable fields.
                    $operators = $field->get_supported_search_operators();
                    $defaultoperator = '';
                    foreach (array_keys($operators) as $op) {
                        if ($op !== '') {
                            $defaultoperator = $op;
                            break;
                        }
                    }
                    $mform->addElement('hidden', 'searchoperator0', $defaultoperator);
                    $mform->setType('searchoperator0', PARAM_RAW);

                    [$elems, $separators] = $field->renderer()->render_search_mode($mform, 0, $value);
                    $label = get_string('condition', 'datalynxrule_eventnotification');
                    $sep = $separators ? array_merge([' ', ' ', ' '], $separators) : ' ';
                    $mform->addGroup($elems, 'conditiongrp', $label, $sep, false);
                }
            } else {
                $mform->addElement(
                    'text',
                    'param10',
                    get_string('condition', 'datalynxrule_eventnotification'),
                    ['disabled' => 'disabled']
                );
                $mform->setType('param10', PARAM_TEXT);
            }
            $mform->addElement('submit', 'reloadconditions', get_string('reload'), ['class' => 'd-none']);
        }
        $this->rule_definition();
        // Buttons.
        $this->add_action_buttons();
    }

    /**
     * Data to form
     *
     * @param array|stdClass $data
     */
    public function set_data($data) {
        if (!empty($data->param1)) {
            $selectedevents = json_decode($data->param1, true) ?? [];
            foreach ($selectedevents as $eventname) {
                $data->$eventname = true;
            }
        }
        if (!empty($data->param5)) {
            $fieldid = (int)$data->param5;
            $field = $this->dlx->get_field_from_id($fieldid);
            if ($field) {
                $value = !empty($data->param10) ? $data->param10 : '';
                if ($value !== '') {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        $value = $decoded;
                    } else {
                        if ($field instanceof \mod_datalynx\local\field\datalynxfield_option_multiple) {
                            $value = explode(',', $value);
                        }
                    }
                }
                $data->{"f_0_{$fieldid}"} = $value;
            }
        }
        parent::set_data($data);
    }

    /**
     * Get data
     *
     * @param bool $slashed
     * @return object
     * @throws coding_exception
     */
    public function get_data($slashed = true) {
        if ($data = parent::get_data($slashed)) {
            $eventmenu = manager::get_event_data($this->dlx->id());
            $selectedevents = [];
            foreach (array_keys($eventmenu) as $eventname) {
                if (isset($data->$eventname)) {
                    $selectedevents[] = $eventname;
                }
            }
            $data->param1 = json_encode($selectedevents);

            if (!empty($data->param5)) {
                $fieldid = (int)$data->param5;
                $field = $this->dlx->get_field_from_id($fieldid);
                if ($field) {
                    $val = $field->parse_search($data, 0);
                    if ($val === false) {
                        $data->param10 = '';
                    } else if (is_array($val)) {
                        $data->param10 = json_encode($val);
                    } else {
                        $data->param10 = (string)$val;
                    }
                } else {
                    $data->param10 = '';
                }
            } else {
                $data->param10 = '';
            }
        }
        return $data;
    }

    /**
     * Add action buttons
     *
     * @param bool $cancel
     * @param null $submit
     * @throws coding_exception
     */
    public function add_action_buttons($cancel = true, $submit = null) {
        $mform = &$this->_form;

        $buttonarray = [];
        // Save and display.
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        // Save and continue.
        $buttonarray[] = &$mform->createElement(
            'submit',
            'submitbutton',
            get_string('savecontinue', 'datalynx')
        );
        // Cancel.
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Validate user data
     *
     * @param array $data
     * @param array $files
     * @return array
     * @throws coding_exception
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($this->dlx->name_exists('rules', $data['name'], $this->rule->get_id())) {
            $errors['name'] = get_string('invalidname', 'datalynx', get_string('rule', 'datalynx'));
        }

        return $errors;
    }
}
