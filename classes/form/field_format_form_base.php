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
 * Base Moodle form for creating and editing datalynx field formats.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Base form for field format create/edit pages.
 *
 * Each field type provides a subclass in datalynxfield_FIELDTYPE\form\field_format_form
 * and overrides format_definition() to add its type-specific settings elements.
 */
abstract class field_format_form_base extends moodleform {
    /**
     * Define the common form elements (name, hidden fields) plus type-specific elements.
     */
    protected function definition() {
        $mform = $this->_form;

        // Hidden: record ID (0 = new).
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        // Hidden: datalynx instance ID.
        $mform->addElement('hidden', 'dataid', 0);
        $mform->setType('dataid', PARAM_INT);

        // Hidden: field type.
        $mform->addElement('hidden', 'fieldtype', '');
        $mform->setType('fieldtype', PARAM_ALPHANUMEXT);

        // General section.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => '32']);
        $mform->setType('name', PARAM_ALPHANUMEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule(
            'name',
            get_string('fieldformat_name_error', 'datalynx'),
            'regex',
            '/^[a-zA-Z0-9_]+$/',
            'client'
        );

        // Field-type-specific settings.
        $mform->addElement('header', 'formatsettings', get_string('fieldformat_settings', 'datalynx'));
        $mform->setExpanded('formatsettings');

        $this->format_definition();

        $this->add_action_buttons();
    }

    /**
     * Subclasses override this to add field-type-specific form elements.
     */
    abstract protected function format_definition(): void;
}
