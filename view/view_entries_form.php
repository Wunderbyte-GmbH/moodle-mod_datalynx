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
 * @package datalynxview
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once($CFG->libdir . '/formslib.php');

class datalynxview_entries_form extends moodleform {

    protected function definition() {
        global $USER, $OUTPUT;
        $view = $this->_customdata['view'];
        $mform = &$this->_form;
        $mform->addElement('hidden', 'new', optional_param('new', 0, PARAM_INT));
        $mform->setType('new', PARAM_INT);

        $this->add_action_buttons();

        $view->definition_to_form($mform);

        $this->add_action_buttons(true, null, "_1");
    }

    /**
     * Override add_action_buttons to allow multiple buttons on one form.
     *
     * @param bool $cancel whether to show cancel button, default true
     * @param string $submitlabel label for submit button, defaults to get_string('savechanges')
     * @param string $multiple if we need a secondary buttongroup we add an iterator here.
     */
    function add_action_buttons($cancel = true, $submitlabel = null, $multiple = null){
        if (is_null($submitlabel)){
            $submitlabel = get_string('savechanges');
        }
        $mform =& $this->_form;
        if ($cancel){
            // When two elements we need a group.
            $buttonarray=array();
            $buttonarray[] = &$mform->createElement('submit', 'submitbutton'.$multiple, $submitlabel);
            $buttonarray[] = &$mform->createElement('cancel', null, null, array('id' => 'cancel'.$multiple));
            
            $mform->addGroup($buttonarray, 'buttonar'.$multiple, '', array(' '), false);
            $mform->closeHeaderBefore('buttonar'.$multiple);
        } else {
            // No group needed.
            $mform->addElement('submit', 'submitbutton'.$multiple, $submitlabel);
            $mform->closeHeaderBefore('submitbutton'.$multiple);
        }
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($errors)) {
            $view = $this->_customdata['view'];
            $patterns = $view->get__patterns('field');
            $fields = $view->get_view_fields();
            $entryids = explode(',', $this->_customdata['update']);

            foreach ($entryids as $entryid) {

                // If we see a fieldgroup loop through all visible lines.
                $fieldgroupmarkers = preg_grep('/^fieldgroup_/', array_keys($data));
                if (count($fieldgroupmarkers) > 0) {
                    $fieldgroupid = $data[reset($fieldgroupmarkers)];

                    // Append the correct patterns to match.
                    $patterns = $fields[$fieldgroupid]->renderer()->get_fieldgroup_patterns($patterns);

                    $maxlines = $fields[$fieldgroupid]->field->param2;
                    for ($i = 0; $i < $maxlines; $i++) {
                        $thisentryid = "{$entryid}_fieldgroup_{$fieldgroupid}_{$i}";
                        foreach ($fields as $fid => $field) {
                            $newerrors = $field->renderer()->validate($thisentryid, $patterns[$fid], (object) $data);
                            $errors = array_merge($errors, $newerrors);
                        }
                    }

                } else {
                    // If no fieldgroup use standard behaviour.
                    foreach ($fields as $fid => $field) {
                        $errors = array_merge($errors,
                            $field->renderer()->validate($entryid, $patterns[$fid], (object) $data));
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Returns an HTML version of the form
     *
     * @return string HTML version of the form
     */
    public function html() {
        return $this->_form->toHtml();
    }
}
