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
defined('MOODLE_INTERNAL') or die();

require_once($CFG->libdir . '/formslib.php');
HTML_QuickForm::registerElementType('checkboxgroup',
        "$CFG->dirroot/mod/datalynx/checkboxgroup/checkboxgroup.php", 'HTML_QuickForm_checkboxgroup');

/**
 */
class datalynx_field_renderer_form extends moodleform {

    /**
     *
     * @var datalynx
     */
    private $datalynx;

    public function __construct(datalynx $datalynx) {
        $this->datalynx = $datalynx;
        parent::__construct();
    }

    protected function definition() {
        $mform = &$this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'd', $this->datalynx->id());
        $mform->setType('d', PARAM_INT);

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), array('size' => '32'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', "Renderer name may not contain the pipe symbol \" | \"!", 'regex',
                '/^[^\|]+$/', 'client');

        $mform->addElement('text', 'description', get_string('description'), array('size' => '64'));
        $mform->setType('description', PARAM_TEXT);

        /*
         * Make this more readable:
         * shownothing = 0
         * asdisplay = 1
         * custom = 2
         * disabled = 3
         * none = 4
         * If *template is an integer we assume it is an option.
         */

        // When not visible.
        $group = array();
        $group[] = $mform->createElement('radio', 'notvisibleoptions', '', get_string('shownothing', 'datalynx'), 0);
        $group[] = $mform->createElement('radio', 'notvisibleoptions', '', get_string('custom', 'datalynx'), 2);
        $group[] = $mform->createElement('textarea', 'notvisibletemplate', '', '');
        $mform->disabledIf('notvisibletemplate', 'notvisibleoptions', 'eq', 0);
        $mform->addGroup($group, 'notvisiblegroup', get_string('notvisible', 'datalynx'),
                array('<br />'
                ), false);
        $mform->setType('notvisibletemplate', PARAM_CLEANHTML);

        // Display template.
        $group = array();
        $group[] = $mform->createElement('radio', 'displayoptions', '', get_string('none'), 4);
        $group[] = $mform->createElement('radio', 'displayoptions', '', get_string('custom', 'datalynx'), 2);
        $group[] = $mform->createElement('textarea', 'displaytemplate', '', '');
        $mform->setDefault('displaytemplate', '#value');
        $mform->disabledIf('displaytemplate', 'displayoptions', 'eq', 4);
        $mform->addGroup($group, 'displaytemplategroup', get_string('displaytemplate', 'datalynx'),
                array('<br />'
                ), false);
        $mform->setType('displaytemplate', PARAM_CLEANHTML);
        $mform->addHelpButton('displaytemplategroup', 'displaytemplate', 'datalynx');

        // When empty.
        $group = array();
        $group[] = $mform->createElement('radio', 'novalueoptions', '',
                get_string('shownothing', 'datalynx'), 0);
        $group[] = $mform->createElement('radio', 'novalueoptions', '',
                get_string('asdisplay', 'datalynx'), 1);
        $group[] = $mform->createElement('radio', 'novalueoptions', '',
                get_string('custom', 'datalynx'), 2);
        $group[] = $mform->createElement('textarea', 'novaluetemplate', '', '');
        $mform->disabledIf('novaluetemplate', 'novalueoptions', 'eq', 0);
        $mform->disabledIf('novaluetemplate', 'novalueoptions', 'eq', 1);
        $mform->addGroup($group, 'novaluetemplategroup', get_string('novalue', 'datalynx'), array('<br />'), false);
        $mform->setType('novaluetemplate', PARAM_CLEANHTML);

        // Edit template.
        $group = array();
        $group[] = $mform->createElement('radio', 'editoptions', '', get_string('none'), 4);
        $group[] = $mform->createElement('radio', 'editoptions', '', get_string('asdisplay', 'datalynx'), 1);
        $group[] = $mform->createElement('radio', 'editoptions', '', get_string('custom', 'datalynx'), 2);
        $group[] = $mform->createElement('textarea', 'edittemplate', '', '');
        $mform->setDefault('edittemplate', '#input');
        $mform->disabledIf('edittemplate', 'editoptions', 'eq', 4);
        $mform->disabledIf('edittemplate', 'editoptions', 'eq', 1);
        $mform->addGroup($group, 'edittemplategroup', get_string('edittemplate', 'datalynx'), array('<br />'), false);
        $mform->setType('edittemplate', PARAM_CLEANHTML);
        $mform->addHelpButton('edittemplategroup', 'edittemplate', 'datalynx');

        // When not editable.
        $group = array();
        $group[] = $mform->createElement('radio', 'noteditableoptions', '', get_string('shownothing', 'datalynx'), 0);
        $group[] = $mform->createElement('radio', 'noteditableoptions', '', get_string('asdisplay', 'datalynx'), 1);
        $group[] = $mform->createElement('radio', 'noteditableoptions', '', get_string('disabled', 'datalynx'), 3);
        $group[] = $mform->createElement('radio', 'noteditableoptions', '', get_string('custom', 'datalynx'), 2);
        $group[] = $mform->createElement('textarea', 'noteditabletemplate', '', '');
        $mform->disabledIf('noteditabletemplate', 'noteditableoptions', 'eq', 0);
        $mform->disabledIf('noteditabletemplate', 'noteditableoptions', 'eq', 1);
        $mform->disabledIf('noteditabletemplate', 'noteditableoptions', 'eq', 3);
        $mform->addGroup($group, 'noteditablegroup', get_string('noteditable', 'datalynx'), array('<br />'), false);
        $mform->setType('noteditabletemplate', PARAM_CLEANHTML);

        $this->add_action_buttons();
    }

    // Process validated data from form.
    public function get_data() {
        $data = parent::get_data();

        if (!$data) {
            return null;
        }

        $formfields = array('notvisible', 'display', 'novalue', 'edit', 'noteditable');

        foreach ($formfields as $formfield) {
            $template = $formfield . 'template';
            if (!isset($data->$template)) {
                $option = $formfield . 'options';
                $data->$template = $data->$option;
            }
        }

        return $data;
    }

    /**
     *  Add data to formfields.
     * {@inheritDoc}
     * @see moodleform::set_data()
     */
    public function set_data($data) {
        $formfields = array('notvisible', 'display', 'novalue', 'edit', 'noteditable');

        foreach ($formfields as $formfield) {
            $template = $formfield . 'template';
            $option = $formfield . 'options';
            if (is_numeric($data->$template)) {
                // If we see an integer value we set options and delete template.
                $data->$option = $data->$template;
                unset($data->$template);
            } else {
                // Else we set options to custom which now is always 2.
                $data->$option = 2;
            }
        }
        parent::set_data($data);
    }

    /**
     *
     * {@inheritDoc}
     * @see moodleform::validation()
     */
    public function validation($data, $files) {
        global $DB;

        $errors = array();
        if (isset($data['displaytemplate']) && strpos($data['displaytemplate'], '#value') === false) {
            $errors['displaytemplate'] = 'You must use tag #value somewhere in this template!';
        }
        if (isset($data['edittemplate']) && strpos($data['edittemplate'], '#input') === false) {
            $errors['edittemplate'] = 'You must use tag #input somewhere in this template!';
        }
        if ($data['id'] == 0) {
            // To prevent duplicate renderer names when creating a new renderer.
            if ($DB->record_exists('datalynx_renderers', array('name' => $data['name'], 'dataid' => $data['d']))) {
                $errors['name'] = get_string('duplicatename', 'datalynx');
            }
        } else {
            // To prevent duplicate renderer names when updating existing renderers.
            $sql = "SELECT 'x'
                    FROM {datalynx_renderers} r
                    WHERE r.name = ? AND r.dataid = ? AND r.id <> ?";
            $params = array($data['name'], $data['d'], $data['id']);
            if ($DB->record_exists_sql($sql, $params)) {
                $errors['name'] = get_string('duplicatename', 'datalynx');
            }
        }

        return $errors;
    }
}