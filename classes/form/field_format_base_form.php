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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use moodleform;
use stdClass;

/**
 * Base form class for editing field formats in mod_datalynx.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class field_format_base_form extends moodleform {
    /**
     * Define the form elements.
     */
    public function definition() {
        $mform = &$this->_form;

        // Hidden fields.
        $mform->addElement('hidden', 'd');
        $mform->setType('d', PARAM_INT);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'fieldtype');
        $mform->setType('fieldtype', PARAM_ALPHANUM);

        // General settings header.
        $mform->addElement('header', 'generalhdr', get_string('general', 'form'));

        // Name field.
        $mform->addElement('text', 'name', get_string('name'));
        $mform->setType('name', PARAM_ALPHANUM);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('name', 'fieldformatname', 'mod_datalynx');

        // Subclass-specific field definition.
        $this->format_definition();

        // Action buttons.
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Override this to define fields specific to the format type.
     */
    abstract protected function format_definition();

    /**
     * Form validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        if (empty($data['name'])) {
            $errors['name'] = get_string('required');
        } else if (!preg_match('/^[a-zA-Z0-9]+$/', $data['name'])) {
            $errors['name'] = get_string('erroralphanumeric', 'mod_datalynx');
        } else {
            // Uniqueness check.
            $datalynxid = $data['d'];
            $formatid = $data['id'] ?? 0;
            $name = $data['name'];
            $select = 'dataid = :dataid AND name = :name';
            $params = ['dataid' => $datalynxid, 'name' => $name];
            if ($formatid) {
                $select .= ' AND id <> :id';
                $params['id'] = $formatid;
            }
            if ($DB->record_exists_select('datalynx_field_formats', $select, $params)) {
                $errors['name'] = get_string('errorformatnameexists', 'mod_datalynx');
            }
        }

        // Additional subclass validation.
        $suberrors = $this->format_validation($data, $files);
        if (!empty($suberrors)) {
            $errors = array_merge($errors, $suberrors);
        }

        return $errors;
    }

    /**
     * Subclasses can override this to implement specific validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    protected function format_validation($data, $files) {
        return [];
    }
}
