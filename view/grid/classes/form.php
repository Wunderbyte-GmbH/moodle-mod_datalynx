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
 * Grid view configuration form.
 *
 * @package    datalynxview_grid
 * @copyright  2013 onwards edulabs.org and associated programmers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxview_grid;

use mod_datalynx\form\datalynxview_base_form;

/**
 * Grid view configuration form.
 *
 * @package    datalynxview_grid
 * @copyright  2025 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form extends datalynxview_base_form {
    /**
     * Add view specific elements to the form.
     */
    public function view_definition_after_gps() {
        $view = $this->view;
        $editoroptions = $view->editors();
        $editorattr = ['cols' => 40, 'rows' => 12];

        $mform = &$this->_form;

        // Grid Appearance Settings.
        $mform->addElement('header', 'gridsettingshdr', get_string('gridsettings', 'datalynxview_grid'));

        $options = [
            'col' => get_string('wrapperbootstraprowcols', 'datalynxview_grid'),
            'col-12' => get_string('wrapperbootstrapcol12', 'datalynxview_grid'),
            'col-12 col-md-6' => get_string('wrapperbootstrapcol6', 'datalynxview_grid'),
            'col-12 col-md-6 col-lg-4' => get_string('wrapperbootstrapcol4', 'datalynxview_grid'),
            'col-12 col-md-6 col-lg-3' => get_string('wrapperbootstrapcol3', 'datalynxview_grid'),
            'entry' => get_string('wrapperlegacy', 'datalynxview_grid'),
            'custom' => get_string('wrappercustom', 'datalynxview_grid'),
            'none' => get_string('wrappernone', 'datalynxview_grid'),
        ];
        $mform->addElement('select', 'param3', get_string('entrywrapper', 'datalynxview_grid'), $options);
        $mform->addHelpButton('param3', 'entrywrapper', 'datalynxview_grid');

        $mform->addElement('text', 'param4', get_string('customwrapperclass', 'datalynxview_grid'));
        $mform->setType('param4', PARAM_TEXT);
        $mform->addHelpButton('param4', 'customwrapperclass', 'datalynxview_grid');
        $mform->hideIf('param4', 'param3', 'neq', 'custom');

        // Repeated entry (param2).
        $mform->addElement('header', 'entrytemplatehdr', get_string('entrytemplate', 'datalynx'));

        $mform->addElement('editor', 'eparam2_editor', '', $editorattr, $editoroptions['param2']);
        $mform->setDefault("eparam2_editor[format]", FORMAT_HTML);
        $this->add_tags_selector('eparam2_editor', 'general', true);
        $this->add_tags_selector('eparam2_editor', 'field');
        $this->add_tags_selector('eparam2_editor', 'character');
    }

    /**
     * Preprocess data before setting it to the form.
     *
     * @param stdClass $data
     */
    public function data_preprocessing(&$data) {
        parent::data_preprocessing($data);
        if (!isset($data->param3) || $data->param3 === '') {
            $data->param3 = 'entry'; // Backward compatibility default.
        }
    }

    /**
     * Set data to the form.
     *
     * @param stdClass $data
     */
    public function set_data($data) {
        $this->data_preprocessing($data);
        parent::set_data($data);
    }
}
