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

        // Repeated entry (param2).
        $mform->addElement('header', 'entrytemplatehdr', get_string('entrytemplate', 'datalynx'));

        $mform->addElement('editor', 'eparam2_editor', '', $editorattr, $editoroptions['param2']);
        $mform->setDefault("eparam2_editor[format]", FORMAT_HTML);
        $this->add_tags_selector('eparam2_editor', 'general', true);
        $this->add_tags_selector('eparam2_editor', 'field');
        $this->add_tags_selector('eparam2_editor', 'character');
    }
}
