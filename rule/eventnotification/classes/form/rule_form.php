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

namespace datalynxrule_eventnotification\form;

use datalynxrule_eventnotification\rule;
use html_writer;
use mod_datalynx\datalynx;
use mod_datalynx\form\rule_form as base_rule_form;
use mod_datalynx\local\rule\match_compiler;
use stdClass;

/**
 * Event notification rule form
 *
 * @package    datalynxrule_eventnotification
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_form extends base_rule_form {
    /**
     * Definition of the rule settings form
     */
    public function rule_definition() {
        $br = html_writer::empty_tag('br');
        $mform = &$this->_form;

        // Only-on-change field selection: notification fires only when at least one of the
        // selected fields actually changed its value during the triggering update event. This
        // only makes sense for the "entry updated" event (it is the only event carrying a list of
        // changed fields), so the control is hidden unless that event is selected.
        $dlfields = $this->dlx->get_fields(['entry'], true);
        if (!empty($dlfields)) {
            $options = ['multiple' => true, 'noselectionstring' => get_string('noselection', 'form')];
            $mform->addElement(
                'autocomplete',
                'onlyonchangefields',
                get_string('onlyonchange', 'datalynxrule_eventnotification'),
                $dlfields,
                $options
            );
            $mform->addHelpButton('onlyonchangefields', 'onlyonchange', 'datalynxrule_eventnotification');
            $mform->hideIf('onlyonchangefields', 'entry_updated', 'notchecked');
        }

        // Message subject. When empty then use default subject in message.
        $mform->addElement('text', 'param6', get_string('asyncmessagesubject', 'backup'), ['size' => '64']);
        $mform->setType('param6', PARAM_TEXT);

        $mform->addElement(
            'select',
            'param8',
            get_string('emailtemplate', 'datalynxrule_eventnotification'),
            $this->get_email_template_menu()
        );
        $mform->addHelpButton('param8', 'emailtemplate', 'datalynxrule_eventnotification');

        $mform->addElement('header', 'settingshdr', get_string('settings'));

        // Sender.
        $options = [
                rule::FROM_AUTHOR => get_string('author', 'datalynx'),
                rule::FROM_CURRENT_USER => get_string('user')];
        $mform->addElement('select', 'param2', get_string('fromsender', 'moodle'), $options);

        // Recipient.
        $grp = [];
        $grp[] = &$mform->createElement('checkbox', 'author', null, get_string('author', 'datalynx'), ['size' => 1]);
        $grp[] = &$mform->createElement(
            'checkbox',
            'matchauthors',
            null,
            get_string('matchauthors', 'datalynxrule_eventnotification'),
            ['size' => 1]
        );
        $grp[] = &$mform->createElement(
            'checkbox',
            'subjectauthorpermatch',
            null,
            get_string('matchsubjectauthor', 'datalynxrule_eventnotification'),
            ['size' => 1]
        );

        $options = ['multiple' => true];
        $grp[] = &$mform->createElement('static', '', '', "<br><h4 class=\"w-100 mt-3\">" . get_string('roles') . "</h4>");

        $grp[] = &$mform->createElement(
            'autocomplete',
            'roles',
            get_string('roles'),
            $this->dlx->get_datalynx_permission_names(true),
            $options
        );
        $grp[] = &$mform->createElement(
            'static',
            'roleshelpinfo',
            '',
            '<div class="form-text text-muted">' . get_string('roleshelpinfo', 'datalynxrule_eventnotification') . '</div>'
        );
        $grp[] = &$mform->createElement('static', '', '', $br);

        $grp[] = &$mform->createElement(
            'static',
            '',
            '',
            "<br><h4 class=\"w-100 mt-3\">" . get_string('teammembers', 'datalynx') . "</h4>"
        );
        $grp[] = &$mform->createElement(
            'autocomplete',
            'teams',
            get_string('teams', 'datalynx'),
            $this->get_datalynx_team_fields(),
            $options
        );

        // Single userid can be selected.
        $grp[] = &$mform->createElement(
            'static',
            '',
            '',
            "<br><h4 class=\"w-100 mt-3\">" . get_string('otheruser', 'datalynx') . "</h4>"
        );
        $allusers = $this->get_allusers();
        $grp[] = $mform->createElement('autocomplete', 'specificuserid', get_string('otheruser', 'datalynx'), $allusers);

        $mform->addGroup($grp, 'recipientgrp', get_string('torecipient'), $br, false);

        // Link settings.
        $mform->addElement('header', 'settingshdr', get_string('linksettings', 'datalynx'));
        $mform->addElement('static', '', get_string('targetviewforroles', 'datalynx'));

        foreach ($this->dlx->get_datalynx_permission_names(true) as $permissionid => $permissionname) {
            $views = $this->get_views_visible_to_datalynx_permission($permissionid);
            if (!empty($views)) {
                $mform->addElement('select', "param4[$permissionid]", $permissionname, $views);
            } else {
                $mform->addElement(
                    'static',
                    '',
                    $permissionname,
                    get_string('noviewsavailable', 'datalynx')
                );
            }
        }

        // Content to be included.
        $mform->addElement('header', 'message', get_string('messagecontent', 'datalynxrule_eventnotification'));
        $dlfields = $this->dlx->get_fields();
        $fieldmenu = [];
        foreach ($dlfields as $fieldid => $field) {
            if ($field->type == 'text' || $field->type == 'editor' || $field->type == 'textarea') {
                $fieldmenu[$fieldid] = $field->field->name;
            }
        }
        $options = [
                'multiple' => true,
                'noselectionstring' => get_string('noselection', 'form'),
        ];
        $mform->addElement('autocomplete', 'param7', get_string('searcharea', 'search'), $fieldmenu, $options);

        $this->match_definition();
    }

    /**
     * The "matching entries" block: which other entries this rule should look for.
     *
     * Rows are built by hand rather than through custom_search_definition(): that helper owns the
     * element names searchfield{N}/searchandor{N} globally and is parsed back by prefix, so a
     * second block of them would be merged into the trigger conditions. The idiom is the same
     * though - the stored rows, three blank ones and a reload button that rebuilds the form
     * against what has been chosen so far.
     */
    protected function match_definition() {
        $mform = &$this->_form;

        $fieldoptions = match_compiler::eligible_fields($this->dlx);
        if (!$fieldoptions) {
            // No field of this instance can be compared with another entry's value.
            return;
        }
        $fieldoptions = [0 => get_string('choose')] + $fieldoptions;
        $fields = $this->dlx->get_fields();
        $units = [
            1 => get_string('matchunitvalue', 'datalynxrule_eventnotification'),
            MINSECS => get_string('matchunitminutes', 'datalynxrule_eventnotification'),
            HOURSECS => get_string('matchunithours', 'datalynxrule_eventnotification'),
            DAYSECS => get_string('matchunitdays', 'datalynxrule_eventnotification'),
        ];

        $mform->addElement('header', 'matchhdr', get_string('matchingentries', 'datalynxrule_eventnotification'));
        $mform->addElement(
            'static',
            'matchintro',
            '',
            '<div class="form-text text-muted">'
                . get_string('matchingentries_help', 'datalynxrule_eventnotification') . '</div>'
        );

        $count = 0;
        foreach ($this->get_match_criteria() as $criterion) {
            $fieldid = (int) ($criterion['fieldid'] ?? 0);
            if (!$fieldid || empty($fields[$fieldid]) || !isset($fieldoptions[$fieldid])) {
                continue;
            }
            $this->match_row($count, $fieldoptions, $units, $fields[$fieldid], $criterion);
            $count++;
        }

        // Add 3 more options.
        for ($prevcount = $count; $count < ($prevcount + 3); $count++) {
            $this->match_row($count, $fieldoptions, $units, null, []);
            if ($count > $prevcount) {
                $mform->disabledIf("matchfield$count", 'matchfield' . ($count - 1), 'eq', 0);
            }
        }

        $mform->registerNoSubmitButton('addmatchsettings');
        $mform->addElement('submit', 'addmatchsettings', get_string('reload'));

        // Evaluation options. They only mean anything once a criterion has been chosen.
        $mform->addElement(
            'advcheckbox',
            'matchrequireapproved',
            get_string('matchrequireapproved', 'datalynxrule_eventnotification'),
            '',
            null,
            [0, 1]
        );
        $mform->setDefault('matchrequireapproved', 1);

        $mform->addElement(
            'text',
            'matchdedupescope',
            get_string('matchdedupescope', 'datalynxrule_eventnotification'),
            ['size' => 32]
        );
        $mform->setType('matchdedupescope', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('matchdedupescope', 'matchdedupescope', 'datalynxrule_eventnotification');

        $mform->addElement(
            'advcheckbox',
            'matchdedupe',
            get_string('matchdedupe', 'datalynxrule_eventnotification'),
            '',
            null,
            [0, 1]
        );
        $mform->setDefault('matchdedupe', 1);
        $mform->addHelpButton('matchdedupe', 'matchdedupe', 'datalynxrule_eventnotification');

        $mform->addElement(
            'advcheckbox',
            'matchforgetstale',
            get_string('matchforgetstale', 'datalynxrule_eventnotification'),
            '',
            null,
            [0, 1]
        );
        $mform->setDefault('matchforgetstale', 1);
        $mform->addHelpButton('matchforgetstale', 'matchforgetstale', 'datalynxrule_eventnotification');
        $mform->hideIf('matchforgetstale', 'matchdedupe', 'notchecked');
        $mform->hideIf('matchdedupescope', 'matchdedupe', 'notchecked');

        $mform->addElement(
            'text',
            'matchmax',
            get_string('matchmax', 'datalynxrule_eventnotification'),
            ['size' => 6]
        );
        $mform->setType('matchmax', PARAM_INT);
        $mform->setDefault('matchmax', rule::DEFAULT_MAX_MATCHES);
        $mform->addHelpButton('matchmax', 'matchmax', 'datalynxrule_eventnotification');
    }

    /**
     * One row of the matching block.
     *
     * The controls a row needs depend on the relation it states, and the relations depend on the
     * chosen field, so a stored row is rendered with exactly its own controls while the blank
     * rows carry only the field selector until the reload button has been used.
     *
     * @param int $count row index
     * @param array $fieldoptions field id => name, with a "choose" entry
     * @param array $units seconds per unit => label
     * @param \mod_datalynx\local\field\datalynxfield_base|null $field the chosen field, if any
     * @param array $criterion the stored row, if any
     */
    private function match_row(int $count, array $fieldoptions, array $units, $field, array $criterion) {
        $mform = &$this->_form;

        $arr = [];
        $arr[] = &$mform->createElement('select', "matchfield$count", '', $fieldoptions);
        if ($field === null) {
            $mform->addGroup($arr, "matcharr$count", '', ' ', false);

            return;
        }

        $mform->setDefault("matchfield$count", (int) $criterion['fieldid']);

        $relations = match_compiler::operators_for($field);
        $relation = (string) ($criterion['op'] ?? '');
        $arr[] = &$mform->createElement('select', "matchrel$count", '', $relations);
        $mform->setDefault("matchrel$count", $relation);

        // Show the controls the chosen relation needs. Until one is chosen - the field has just
        // been picked and the form reloaded - offer everything the field could ask for.
        $show = static function (string $candidate) use ($relation, $relations): bool {
            return $relation === '' ? array_key_exists($candidate, $relations) : $relation === $candidate;
        };

        if ($show(match_compiler::OP_WITHIN)) {
            $arr[] = &$mform->createElement('text', "matchtol$count", '', ['size' => 6]);
            $mform->setType("matchtol$count", PARAM_FLOAT);
            $mform->setDefault("matchtol$count", $criterion['tolerance'] ?? '');
            $arr[] = &$mform->createElement('select', "matchtolunit$count", '', $units);
            $mform->setDefault("matchtolunit$count", (int) ($criterion['unit'] ?? HOURSECS));
        }

        if ($show(match_compiler::OP_ROUTE)) {
            $arr[] = &$mform->createElement('text', "matchradius$count", '', ['size' => 6]);
            $mform->setType("matchradius$count", PARAM_FLOAT);
            $mform->setDefault("matchradius$count", $criterion['radius'] ?? '');
            $arr[] = &$mform->createElement('select', "matchdirection$count", '', match_compiler::directions());
            $mform->setDefault(
                "matchdirection$count",
                (string) ($criterion['direction'] ?? match_compiler::DIRECTION_EITHER)
            );
        }

        $mform->addGroup($arr, "matcharr$count", '', ' ', false);
    }

    /**
     * The matching rows to prefill the form with.
     *
     * On a reload they come from what is currently chosen in the form, otherwise from the stored
     * rule - the same two sources, in the same order, as the trigger-condition rows.
     *
     * @return array
     */
    protected function get_match_criteria(): array {
        $ajax = is_array($this->_ajaxformdata) ? $this->_ajaxformdata : [];
        foreach (array_keys($ajax) as $key) {
            if (strpos($key, 'matchfield') === 0) {
                return $this->criteria_from_submitted((object) $ajax);
            }
        }

        return $this->stored_match_config()['criteria'] ?? [];
    }

    /**
     * The stored matching configuration of the rule being edited.
     *
     * @return array
     */
    protected function stored_match_config(): array {
        $rule = $this->rule->rule ?? null;
        if (empty($rule) || !empty($rule->param5) || empty($rule->param9)) {
            return [];
        }
        $decoded = json_decode($rule->param9, true);
        $config = is_array($decoded) ? ($decoded[rule::MATCH_KEY] ?? []) : [];

        return is_array($config) ? $config : [];
    }

    /**
     * Read the matching rows out of submitted form data.
     *
     * @param stdClass $data
     * @return array
     */
    protected function criteria_from_submitted(stdClass $data): array {
        $fields = $this->dlx->get_fields();
        $criteria = [];

        foreach (array_keys((array) $data) as $key) {
            if (strpos($key, 'matchfield') !== 0) {
                continue;
            }
            $count = substr($key, strlen('matchfield'));
            $fieldid = (int) $data->$key;
            $relation = (string) ($data->{"matchrel$count"} ?? '');
            if (!$fieldid || empty($fields[$fieldid])) {
                continue;
            }
            if (!in_array($relation, $fields[$fieldid]->supported_relative_criteria(), true)) {
                continue;
            }

            $criterion = ['fieldid' => $fieldid, 'op' => $relation];
            if ($relation === match_compiler::OP_WITHIN) {
                $criterion['tolerance'] = (float) ($data->{"matchtol$count"} ?? 0);
                $criterion['unit'] = (int) ($data->{"matchtolunit$count"} ?? 1);
            }
            if ($relation === match_compiler::OP_ROUTE) {
                $criterion['radius'] = (float) ($data->{"matchradius$count"} ?? 0);
                $criterion['direction'] = (string) ($data->{"matchdirection$count"}
                    ?? match_compiler::DIRECTION_EITHER);
            }
            $criteria[] = $criterion;
        }

        return $criteria;
    }

    /**
     * Get all users in moodle instance for autocomplete list.
     *
     * @return array with userid -> firstname lastname.
     * @throws coding_exception
     */
    public function get_allusers(): array {
        global $DB;
        $tempusers = $DB->get_records('user', [], '', 'id, firstname, lastname');

        $allusers[0] = get_string('noselection', 'datalynx');
        foreach ($tempusers as $userdata) {
            // Remove empties to make list more usable.
            if ($userdata->lastname == '') {
                continue;
            }
            $allusers[$userdata->id] = "$userdata->firstname $userdata->lastname";
        }
        return $allusers;
    }

    /**
     * Definition after data
     */
    public function definition_after_data() {
        $mform = &$this->_form;
        $data = $this->get_submitted_data();

        foreach (array_keys($this->dlx->get_datalynx_permission_names(true)) as $permissionid) {
            $views = $this->get_views_visible_to_datalynx_permission($permissionid);
            $defaultview = $this->dlx->get_default_view_id();
            if (
                isset($data) && isset($data->param4[$permissionid]) && $defaultview &&
                    in_array($defaultview, array_keys($views))
            ) {
                $mform->setDefault("param4[$permissionid]", $defaultview);
            }
        }
    }

    /**
     * Get the visible to permissions for a view.
     *
     * @param int $permissionid
     * @return array
     */
    private function get_views_visible_to_datalynx_permission(int $permissionid): array {
        global $DB;
        if ($permissionid == datalynx::PERMISSION_ADMIN) {
            $sql = "SELECT id, name
                      FROM {datalynx_views}
                     WHERE dataid = :dataid
                       AND type <> :internaltype";
            return $DB->get_records_sql_menu($sql, ['dataid' => $this->dlx->id(), 'internaltype' => datalynx::INTERNAL_VIEW_EMAIL]);
        } else {
            $sql = "SELECT id, name
                      FROM {datalynx_views}
                     WHERE dataid = :dataid
                       AND type <> :internaltype
                       AND visible & :permissionid <> 0";
            return $DB->get_records_sql_menu($sql, ['dataid' => $this->dlx->id(), 'internaltype' => datalynx::INTERNAL_VIEW_EMAIL,
                    'permissionid' => $permissionid]);
        }
    }

    /**
     * Get the selectable email template views.
     *
     * @return array
     */
    private function get_email_template_menu(): array {
        $views = [0 => get_string('emailtemplatenone', 'datalynxrule_eventnotification')];
        foreach ($this->dlx->get_all_views() as $view) {
            if ($view->type === datalynx::INTERNAL_VIEW_EMAIL) {
                $views[$view->id] = $view->name;
            }
        }

        if (count($views) > 1) {
            $templates = $views;
            unset($templates[0]);
            asort($templates);
            $views = [0 => get_string('emailtemplatenone', 'datalynxrule_eventnotification')] + $templates;
        }

        return $views;
    }

    /**
     * Get team member select fields
     *
     * @return array
     */
    protected function get_datalynx_team_fields() {
        global $DB;
        $sql = "SELECT id, name
                  FROM {datalynx_fields}
                 WHERE dataid = :dataid
                   AND " . $DB->sql_like('type', ':type');
        $params = ['dataid' => $this->dlx->id(), 'type' => 'teammemberselect'];
        return $DB->get_records_sql_menu($sql, $params);
    }

    /**
     * Get roles used in context
     *
     * @return array
     */
    protected function menu_roles_used_in_context(): array {
        $roles = [];
        foreach (get_roles_used_in_context($this->dlx->context) as $roleid => $role) {
            $roles[$roleid] = $role->coursealias ? $role->coursealias : ($role->name ? $role->name : $role->shortname);
        }
        return $roles;
    }

    /**
     * Set data for the form
     *
     * @param stdClass $data
     */
    public function set_data($data) {
        $recipients = [];
        if (!empty($data->param3)) {
            $decoded = json_decode($data->param3, true);
            $recipients = is_array($decoded) ? $decoded : [];
        }
        if (isset($recipients['author'])) {
            $data->author = $recipients['author'];
        }
        if (isset($recipients['roles'])) {
            $data->roles = $recipients['roles'];
        }
        if (isset($recipients['teams'])) {
            $data->teams = $recipients['teams'];
        }
        if (isset($recipients['specificuserid'])) {
            $data->specificuserid = $recipients['specificuserid'];
        }
        if (!empty($recipients['matchauthors'])) {
            $data->matchauthors = 1;
        }
        if (!empty($recipients['subjectauthorpermatch'])) {
            $data->subjectauthorpermatch = 1;
        }

        if (!empty($data->param4)) {
            $decoded = json_decode($data->param4, true);
            $data->param4 = is_array($decoded) ? $decoded : [];
        }
        if (!empty($data->param7)) {
            $decoded = json_decode($data->param7);
            $data->param7 = is_array($decoded) ? $decoded : [];
        } else {
            $data->param7 = [];
        }

        // Populate the autocomplete from the reserved on-change field list stored in param9.
        $data->onlyonchangefields = [];
        if (!empty($data->param9)) {
            $conditions = json_decode($data->param9, true);
            if (is_array($conditions) && !empty($conditions[rule::ONCHANGE_KEY])) {
                $data->onlyonchangefields = array_values((array) $conditions[rule::ONCHANGE_KEY]);
            }
        }

        // The matching rows themselves are prefilled in definition(); only the options beside
        // them are ordinary form values.
        $config = $this->stored_match_config();
        $data->matchrequireapproved = (int) ($config['requireapproved'] ?? 1);
        $data->matchdedupe = (int) !empty($config['dedupe'] ?? rule::SCOPE_RULE);
        $scope = (string) ($config['dedupe'] ?? '');
        $data->matchdedupescope = ($scope === '' || $scope === rule::SCOPE_RULE) ? '' : $scope;
        $data->matchforgetstale = (int) ($config['forgetstale'] ?? 1);
        $data->matchmax = (int) ($config['maxmatches'] ?? rule::DEFAULT_MAX_MATCHES);

        parent::set_data($data);
    }

    /**
     * Get data from the form
     *
     * @param bool $slashed
     * @return ?stdClass
     */
    public function get_data($slashed = true) {
        if ($data = parent::get_data($slashed)) {
            // Set recipient.
            $recipients = [];
            if (isset($data->author)) {
                $recipients['author'] = 1;
            }

            if (isset($data->roles)) {
                $recipients['roles'] = $data->roles;
            }

            if (isset($data->teams)) {
                $recipients['teams'] = $data->teams;
            }
            if (isset($data->specificuserid)) {
                $recipients['specificuserid'] = $data->specificuserid;
            }
            if (isset($data->matchauthors)) {
                $recipients['matchauthors'] = 1;
            }
            if (isset($data->subjectauthorpermatch)) {
                $recipients['subjectauthorpermatch'] = 1;
            }
            $data->param3 = json_encode($recipients);
            $data->param4 = json_encode(!empty($data->param4) && is_array($data->param4) ? $data->param4 : []);
            $data->param7 = json_encode(!empty($data->param7) && is_array($data->param7) ? $data->param7 : []);

            // Store the selected on-change field IDs under a reserved key in param9. This is
            // independent of the trigger conditions, so a notification can fire "only on change"
            // even when no other condition is configured.
            // Normalise the selected field IDs: real fields have numeric IDs (stored as int so they
            // compare strictly), while internal fields (e.g. the status field) have string IDs such
            // as 'status' that must be preserved verbatim for the change-detection match.
            $onchangefields = !empty($data->onlyonchangefields)
                ? array_values(array_unique(array_map(
                    static fn($id) => ctype_digit((string) $id) ? (int) $id : (string) $id,
                    (array) $data->onlyonchangefields
                )))
                : [];
            $conditions = [];
            if (!empty($data->param9)) {
                $decoded = json_decode($data->param9, true);
                if (is_array($decoded)) {
                    $conditions = $decoded;
                }
            }
            unset($conditions[rule::ONCHANGE_KEY], $conditions[rule::MATCH_KEY]);
            if ($onchangefields) {
                $conditions[rule::ONCHANGE_KEY] = $onchangefields;
            }

            // The counterpart matching configuration shares param9 with the trigger conditions,
            // under its own reserved key. parent::get_data() has just rebuilt param9 from the
            // condition rows, so this has to run after it.
            if ($criteria = $this->criteria_from_submitted($data)) {
                $scope = trim((string) ($data->matchdedupescope ?? ''));
                $conditions[rule::MATCH_KEY] = [
                    'criteria' => $criteria,
                    'requireapproved' => (int) !empty($data->matchrequireapproved),
                    'dedupe' => empty($data->matchdedupe) ? '' : ($scope !== '' ? $scope : rule::SCOPE_RULE),
                    'forgetstale' => (int) !empty($data->matchforgetstale),
                    'maxmatches' => max(1, (int) ($data->matchmax ?? rule::DEFAULT_MAX_MATCHES)),
                ];
            }

            $data->param9 = $conditions ? json_encode($conditions) : null;
            unset($data->onlyonchangefields);
        }
        return $data;
    }

    /**
     * Validate the rule settings.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $fields = $this->dlx->get_fields();
        foreach (array_keys($data) as $key) {
            if (strpos($key, 'matchfield') !== 0) {
                continue;
            }
            $count = substr($key, strlen('matchfield'));
            $fieldid = (int) $data[$key];
            if (!$fieldid || empty($fields[$fieldid])) {
                continue;
            }
            $relation = (string) ($data["matchrel$count"] ?? '');
            if (!in_array($relation, $fields[$fieldid]->supported_relative_criteria(), true)) {
                // A field was picked but the form has not been reloaded to offer its relations yet.
                $errors["matcharr$count"] = get_string('errmatchreload', 'datalynxrule_eventnotification');
                continue;
            }
            if ($relation === match_compiler::OP_WITHIN && (float) ($data["matchtol$count"] ?? 0) <= 0) {
                $errors["matcharr$count"] = get_string('errmatchtolerance', 'datalynxrule_eventnotification');
            }
            if ($relation === match_compiler::OP_ROUTE && (float) ($data["matchradius$count"] ?? 0) < 0) {
                $errors["matcharr$count"] = get_string('errmatchradius', 'datalynxrule_eventnotification');
            }
        }

        return $errors;
    }
}
