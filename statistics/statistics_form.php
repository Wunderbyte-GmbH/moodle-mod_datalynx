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
 * @package datalynx
 * @subpackage statistics
 * @copyright 2013 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * Statistics option form
 */
class datalynx_statistics_form extends moodleform {

    private $_df = null;

    public function __construct($df, $action = null, $customdata = null, $method = 'post', $target = '',
            $attributes = null, $editable = true) {
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable);
        $this->_df = $df;
    }

    public function definition() {
        $df = $this->_df;
        $mform = &$this->_form;
        $mform->addElement('static', '', '', '');
        $mform->addElement('html', '<div style="width: 33%; float: left;">');
        $mform->addElement('date_selector', 'from', get_string('from'));
        $mform->addElement('date_selector', 'to', get_string('to'));
        $radioarray = array();
        $radioarray[] = &$mform->createElement('radio', 'mode', '',
                get_string('period', 'datalynx'), datalynx_statistics_class::MODE_PERIOD);
        $radioarray[] = &$mform->createElement('radio', 'mode', '',
                get_string('ondate', 'datalynx'), datalynx_statistics_class::MODE_ON_DATE);
        $radioarray[] = &$mform->createElement('radio', 'mode', '',
                get_string('todate', 'datalynx'), datalynx_statistics_class::MODE_UNTIL_DATE);
        $radioarray[] = &$mform->createElement('radio', 'mode', '',
                get_string('fromdate', 'datalynx'), datalynx_statistics_class::MODE_FROM_DATE);
        $radioarray[] = &$mform->createElement('radio', 'mode', '',
                get_string('alltime', 'datalynx'), datalynx_statistics_class::MODE_ALL_TIME);
        $mform->addGroup($radioarray, 'modearray', get_string('modearray', 'datalynx'),
                array(' ', ' ', '<br />', ' ', ' '), false);
        $mform->addHelpButton('modearray', 'modearray', 'datalynx');
        $mform->disabledIf('from', 'mode', 'eq', datalynx_statistics_class::MODE_UNTIL_DATE);
        $mform->disabledIf('from', 'mode', 'eq', datalynx_statistics_class::MODE_ALL_TIME);
        $mform->disabledIf('to', 'mode', 'eq', datalynx_statistics_class::MODE_ON_DATE);
        $mform->disabledIf('to', 'mode', 'eq', datalynx_statistics_class::MODE_FROM_DATE);
        $mform->disabledIf('to', 'mode', 'eq', datalynx_statistics_class::MODE_ALL_TIME);
        $mform->addElement('html', '</div><div style="width: 33%; float: left;">');
        $mform->addElement('checkbox', 'show[0]', '', get_string('numtotalentries', 'datalynx'), 1);
        $mform->addElement('checkbox', 'show[1]', '', get_string('numapprovedentries', 'datalynx'), 1);
        $mform->addElement('checkbox', 'show[2]', '', get_string('numdeletedentries', 'datalynx'), 1);
        $mform->addElement('checkbox', 'show[3]', '', get_string('numvisits', 'datalynx'), 1);
        $mform->addElement('html', '</div><div style="clear:both;"></div><div>');
        $mform->addElement('submit', 'refresh', get_string('refresh'));
        $mform->addElement('html', '</div>');

        $mform->addElement('hidden', 'from_old', time());
        $mform->setType('from_old', PARAM_INT);
        $mform->addElement('hidden', 'to_old', time());
        $mform->setType('to_old', PARAM_INT);
        $mform->addElement('hidden', 'mode_old', datalynx_statistics_class::MODE_ALL_TIME);
        $mform->setType('mode_old', PARAM_INT);
        $mform->addElement('hidden', 'show_old[0]', 1);
        $mform->setType('show_old[0]', PARAM_INT);
        $mform->addElement('hidden', 'show_old[1]', 1);
        $mform->setType('show_old[1]', PARAM_INT);
        $mform->addElement('hidden', 'show_old[2]', 1);
        $mform->setType('show_old[2]', PARAM_INT);
        $mform->addElement('hidden', 'show_old[3]', 1);
        $mform->setType('show_old[3]', PARAM_INT);

        $mform->disable_form_change_checker();
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        switch ($data['mode']) {
            case datalynx_statistics_class::MODE_PERIOD:
                if ($data['from'] && $data['to'] && ($data['from'] > $data['to'])) {
                    $errors['from'] = get_string('fromto_error', 'datalynx');
                }
                break;
            case datalynx_statistics_class::MODE_FROM_DATE:
                if ($data['from'] && ($data['from'] > time())) {
                    $errors['from'] = get_string('fromaftertoday_error', 'datalynx');
                }
                break;
            case datalynx_statistics_class::MODE_ON_DATE:
            case datalynx_statistics_class::MODE_UNTIL_DATE:
            case datalynx_statistics_class::MODE_ALL_TIME:
            default:
                break;
        }

        return $errors;
    }
}
