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
 * @copyright 2013 onwards Ivan Šakić, Philipp Hager, Thomas Niedermaier, Michael Pollak
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/course/moodleform_mod.php");
require_once($CFG->dirroot . '/mod/datalynx/classes/datalynx.php');

class mod_datalynx_mod_form extends moodleform_mod {

    protected $_df = null;

    public function definition() {
        global $CFG;

        if ($cmid = optional_param('update', 0, PARAM_INT)) {
            $this->_df = new mod_datalynx\datalynx(0, $cmid);
        }

        $mform = &$this->_form;

        // Name and intro.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Name.
        $mform->addElement('text', 'name', get_string('name'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->setDefault('name', get_string('modulename', 'datalynx'));

        // Intro.
        if ($CFG->branch < 29) {
            // This is valid before v2.9.
            $this->add_intro_editor(false, get_string('intro', 'datalynx'));
        } else {
            // This is valid after v2.9.
            $this->standard_intro_elements();
        }

        // Timing.
        $mform->addElement('header', 'timinghdr', get_string('timing', 'form'));

        // Time available.
        $mform->addElement('date_time_selector', 'timeavailable',
                get_string('dftimeavailable', 'datalynx'), array('optional' => true));
        // Time due.
        $mform->addElement('date_time_selector', 'timedue', get_string('dftimedue', 'datalynx'),
                array('optional' => true));
        $mform->disabledIf('timedue', 'interval', 'gt', 0);

        // Interval between required entries.
        $mform->addElement('duration', 'timeinterval', get_string('dftimeinterval', 'datalynx'));
        $mform->addHelpButton('timeinterval', 'dftimeinterval', 'datalynx');
        $mform->disabledIf('timeinterval', 'timeavailable[off]', 'checked');
        $mform->disabledIf('timeinterval', 'timedue[off]');

        // Number of intervals.
        $mform->addElement('select', 'intervalcount', get_string('dfintervalcount', 'datalynx'),
                array_combine(range(1, 100), range(1, 100)));
        $mform->addHelpButton('intervalcount', 'dfintervalcount', 'datalynx');
        $mform->setDefault('intervalcount', 1);
        $mform->disabledIf('intervalcount', 'timeavailable[off]', 'checked');
        $mform->disabledIf('intervalcount', 'timedue[off]');
        $mform->disabledIf('intervalcount', 'timeinterval', 'eq', '');

        // Allow late.
        $mform->addElement('checkbox', 'allowlate', get_string('dflateallow', 'datalynx'),
                get_string('dflateuse', 'datalynx'));

        // Rss.
        if ($CFG->enablerssfeeds && $CFG->datalynx_enablerssfeeds) {
            $mform->addElement('header', 'rssshdr', get_string('rss'));
            $countoptions = 0;
            $mform->addElement('select', 'rssarticles', get_string('numberrssarticles', 'datalynx'),
                    $countoptions);
        }

        // Entry settings.
        $mform->addElement('header', 'entrysettingshdr', get_string('entrysettings', 'datalynx'));

        if ($CFG->datalynx_maxentries > 0) {
            // Admin limit, select from dropdown.
            $maxoptions = (array_combine(range(0, $CFG->datalynx_maxentries),
                    range(0, $CFG->datalynx_maxentries)));

            // Required entries.
            $mform->addElement('select', 'entriesrequired',
                    get_string('entriesrequired', 'datalynx'),
                    array(0 => get_string('none')) + $maxoptions);
            // Required entries to view.
            $mform->addElement('select', 'entriestoview', get_string('entriestoview', 'datalynx'),
                    array(0 => get_string('none')) + $maxoptions);
            // Max entries.
            $mform->addElement('select', 'maxentries', get_string('entriesmax', 'datalynx'), $maxoptions);
            $mform->setDefault('maxentries', $CFG->datalynx_maxentries);
        } else {
            // No limit or no entries.
            $admindeniesentries = (int) !$CFG->datalynx_maxentries;
            $mform->addElement('hidden', 'admindeniesentries', $admindeniesentries);
            $mform->setType('admindeniesentries', PARAM_INT);

            // Required entries.
            $mform->addElement('text', 'entriesrequired', get_string('entriesrequired', 'datalynx'));
            $mform->setDefault('entriesrequired', 0);
            $mform->addRule('entriesrequired', null, 'numeric', null, 'client');
            $mform->setType('entriesrequired', PARAM_INT);
            $mform->disabledIf('entriesrequired', 'admindeniesentries', 'eq', 1);

            // Required entries to view.
            $mform->addElement('text', 'entriestoview', get_string('entriestoview', 'datalynx'));
            $mform->setDefault('entriestoview', 0);
            $mform->addRule('entriestoview', null, 'numeric', null, 'client');
            $mform->setType('entriestoview', PARAM_INT);
            $mform->disabledIf('entriestoview', 'admindeniesentries', 'eq', 1);

            // Max entries.
            $mform->addElement('text', 'maxentries', get_string('entriesmax', 'datalynx'));
            $mform->addHelpButton('maxentries', 'entriesmax', 'datalynx');
            $mform->setDefault('maxentries', -1);
            $mform->addRule('maxentries', null, 'numeric', null, 'client');
            $mform->setType('maxentries', PARAM_INT);
            $mform->disabledIf('maxentries', 'admindeniesentries', 'eq', 1);
        }

        // Anonymous entries.
        if ($CFG->datalynx_anonymous) {
            $mform->addElement('selectyesno', 'anonymous',
                    get_string('entriesanonymous', 'datalynx'));
            $mform->setDefault('anonymous', 0);
        }

        // Group entries.
        $mform->addElement('selectyesno', 'grouped', get_string('groupentries', 'datalynx'));
        $mform->disabledIf('grouped', 'groupmode', 'eq', 0);
        $mform->disabledIf('grouped', 'groupmode', 'eq', -1);

        // Time limit to manage an entry.
        $mform->addElement('text', 'timelimit', get_string('entrytimelimit', 'datalynx'));
        $mform->addHelpButton('timelimit', 'entrytimelimit', 'datalynx');
        $mform->setType('timelimit', PARAM_INT);
        $mform->setDefault('timelimit', -1);
        $mform->addRule('timelimit', null, 'numeric', null, 'client');

        $options = array(mod_datalynx\datalynx::APPROVAL_NONE => get_string('approval_none', 'datalynx'),
                mod_datalynx\datalynx::APPROVAL_ON_UPDATE => get_string('approval_required_update', 'datalynx'),
                mod_datalynx\datalynx::APPROVAL_ON_NEW => get_string('approval_required_new', 'datalynx'));
        $mform->addElement('select', 'approval', get_string('requireapproval', 'datalynx'), $options);

        // Common course elements.
        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();

        // Add separate participants group option.
        // _elements has a numeric index, this code accesses the elements by name.
        $groups = &$mform->getElement('groupmode');
        $groups->addOption(get_string('separateparticipants', 'datalynx'), -1);

        // Buttons.
        $this->add_action_buttons();
    }

    /**
     */
    public function data_preprocessing(&$data) {
        parent::data_preprocessing($data);
        $data['completionentriesenabled'] = !empty($data['completionentries']) ? 1 : 0;
        if (empty($data['completionentries'])) {
            if (!$data['completionentriesenabled']) {
                $data['completionentries'] = 0;
            } else {
                $data['completionentries'] = 1;
            }
        }
    }

    /**
     */
    public function get_data($slashed = true) {
        if ($data = parent::get_data($slashed)) {
            if (!empty($data->timeinterval)) {
                $data->timedue = $data->timeavailable + ($data->timeinterval * $data->intervalcount);
            }
        }
        return $data;
    }

    public function add_completion_rules() {
        $mform = &$this->_form;

        $group = array();
        $group[] = &$mform->createElement('checkbox', 'completionentriesenabled', '',
                get_string('completionentries', 'datalynx'), array('size' => 1));
        $group[] = &$mform->createElement('text', 'completionentries', '', array('size' => 3));
        $mform->setType('completionentries', PARAM_INT);
        $mform->addGroup($group, 'completionentriesgroup',
                get_string('completionentriesgroup', 'datalynx'), array(' '), false);
        $mform->disabledIf('completionentries', 'completionentriesenabled', 'notchecked');
        $mform->addHelpButton('completionentriesgroup', 'completionentriesgroup', 'datalynx');

        return array('completionentriesgroup');
    }

    public function definition_after_data() {
        parent::definition_after_data();
        parent::data_preprocessing($data);
        $data['completionentriesenabled'] = !empty($data['completionentries']) ? 1 : 0;
        if (empty($data['completionentries'])) {
            if (!$data['completionentriesenabled']) {
                $data['completionentries'] = 0;
            } else {
                $data['completionentries'] = 1;
            }
        }
    }

    public function completion_rule_enabled($data) {
        return (!empty($data['completionentriesenabled']) && $data['completionentries'] > 0);
    }
}
