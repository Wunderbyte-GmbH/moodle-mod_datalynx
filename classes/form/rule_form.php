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
use core_form\dynamic_form;
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
class rule_form extends dynamic_form {
    use filter_form_elements;

    /**
     * @var object
     */
    protected $rule = null;

    /**
     * @var \mod_datalynx\datalynx
     */
    protected $dlx = null;

    /**
     * Lazily get datalynx instance.
     *
     * @return \mod_datalynx\datalynx
     */
    protected function get_dlx(): \mod_datalynx\datalynx {
        if ($this->dlx === null) {
            if (isset($this->_customdata['rule'])) {
                $this->dlx = $this->_customdata['rule']->dlx;
            } else {
                $d = 0;
                $cmid = 0;
                if (is_array($this->_ajaxformdata)) {
                    $d = isset($this->_ajaxformdata['d']) ? (int)$this->_ajaxformdata['d'] : 0;
                    $cmid = isset($this->_ajaxformdata['cmid']) ? (int)$this->_ajaxformdata['cmid'] : 0;
                }
                if (!$d) {
                    $d = optional_param('d', 0, PARAM_INT);
                }
                if (!$cmid) {
                    $cmid = optional_param('cmid', 0, PARAM_INT);
                }
                $this->dlx = new \mod_datalynx\datalynx($d, $cmid);
            }
        }
        return $this->dlx;
    }

    /**
     * Lazily get rule instance.
     *
     * @return \mod_datalynx\local\rule\base
     */
    protected function get_rule() {
        if ($this->rule === null) {
            if (isset($this->_customdata['rule'])) {
                $this->rule = $this->_customdata['rule'];
            } else {
                $rid = 0;
                $type = '';
                if (is_array($this->_ajaxformdata)) {
                    $rid = isset($this->_ajaxformdata['rid']) ? (int)$this->_ajaxformdata['rid'] : 0;
                    $type = isset($this->_ajaxformdata['type']) ? clean_param($this->_ajaxformdata['type'], PARAM_ALPHA) : '';
                }
                if (!$rid) {
                    $rid = optional_param('rid', 0, PARAM_INT);
                }
                if (!$type) {
                    $type = optional_param('type', '', PARAM_ALPHA);
                }

                $rm = $this->get_dlx()->get_rule_manager();
                if ($rid) {
                    $this->rule = $rm->get_rule_from_id($rid, true);
                } else if ($type) {
                    $this->rule = $rm->get_rule($type);
                }
            }
        }
        return $this->rule;
    }

    /**
     * Context for the dynamic submission.
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        return $this->get_dlx()->context;
    }

    /**
     * Check permissions for dynamic submission.
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('mod/datalynx:managetemplates', $this->get_context_for_dynamic_submission());
    }

    /**
     * Set data for dynamic submission.
     */
    public function set_data_for_dynamic_submission(): void {
        $rule = $this->get_rule();
        $data = $rule->to_form();
        $data->d = $this->get_dlx()->id();
        $data->cmid = $this->get_dlx()->cm->id;
        $data->rid = $rule->get_id();
        $data->type = $rule->type;
        $this->set_data($data);
    }

    /**
     * Process dynamic submission (save/update the rule).
     *
     * @return array
     */
    public function process_dynamic_submission(): array {
        $data = $this->get_data();
        $rule = $this->get_rule();
        $dlx = $this->get_dlx();
        if (!$rule->get_id()) {
            $ruleid = $rule->insert_rule($data);
            $other = ['dataid' => $dlx->id()];
            $event = \mod_datalynx\event\rule_created::create(
                ['context' => $dlx->context, 'objectid' => $ruleid, 'other' => $other]
            );
            $event->trigger();
        } else {
            $data->id = $rule->get_id();
            $rule->update_rule($data);
            $ruleid = $rule->get_id();
            $other = ['dataid' => $dlx->id()];
            $event = \mod_datalynx\event\rule_updated::create(
                ['context' => $dlx->context, 'objectid' => $ruleid, 'other' => $other]
            );
            $event->trigger();
        }
        return ['rid' => $ruleid, 'name' => $data->name];
    }

    /**
     * Page URL for dynamic submission.
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/mod/datalynx/rule/index.php', ['d' => $this->get_dlx()->id()]);
    }


    /**
     * Form definition
     *
     * @throws coding_exception
     */
    public function definition() {
        global $CFG;
        $this->rule = $this->get_rule();
        $this->dlx = $this->get_dlx();
        $mform = &$this->_form;

        // Hidden parameters.
        $mform->addElement('hidden', 'd', $this->dlx->id());
        $mform->setType('d', PARAM_INT);
        $mform->addElement('hidden', 'cmid', $this->dlx->cm->id);
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'rid', $this->rule->get_id());
        $mform->setType('rid', PARAM_INT);
        $mform->addElement('hidden', 'type', $this->rule->type);
        $mform->setType('type', PARAM_ALPHA);

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

        // Trigger conditions: a repeatable AND/OR/NOT search (identical to the filter UI).
        // Only sends the notification when the event's entry matches these conditions.
        $fieldoptions = [0 => get_string('choose')] + $this->dlx->get_fields(['entry'], true);
        if (count($fieldoptions) > 1) {
            $mform->addElement(
                'static',
                'conditionshdr',
                '',
                '<h4 class="mt-3">' . get_string('triggerconditions', 'datalynxrule_eventnotification') . '</h4>'
                    . '<div class="form-text text-muted">'
                    . get_string('triggerconditions_help', 'datalynxrule_eventnotification') . '</div>'
            );

            $customsearch = $this->get_condition_customsearch();
            $this->custom_search_definition($customsearch, $this->dlx->get_fields(), $fieldoptions, true);
        }
        $this->rule_definition();
        // Buttons.
        $this->add_action_buttons();
    }

    /**
     * Resolve the customsearch used to prefill the trigger-condition rows.
     *
     * On a no-submit reload the rows come from the currently submitted criteria; otherwise
     * from the stored rule param9 (a JSON object keyed by field id). The returned value is
     * consumable by {@see filter_form_elements::custom_search_definition()} (which accepts
     * either an aggregated array or a flat submitted array).
     *
     * @return array
     */
    protected function get_condition_customsearch(): array {
        $ajax = is_array($this->_ajaxformdata) ? $this->_ajaxformdata : [];
        foreach (array_keys($ajax) as $key) {
            if (strpos($key, 'searchandor') === 0) {
                return $this->dlx->get_filter_manager()->build_search_options_array((object) $ajax, false);
            }
        }
        $stored = $this->rule->rule->param9 ?? '';
        if (empty($stored)) {
            return [];
        }
        $decoded = json_decode($stored, true);
        return is_array($decoded) ? $decoded : [];
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
        // The trigger-condition rows are prefilled from param9 in definition()
        // via get_condition_customsearch(), so no per-field mapping is needed here.
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

            // Multi-condition trigger stored as JSON in param9; retire legacy param5/param10.
            $conditions = $this->dlx->get_filter_manager()->build_search_options_array($data, true);
            $data->param9 = $conditions ? json_encode($conditions) : null;
            $data->param5 = null;
            $data->param10 = null;
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
