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
 * @copyright 2016 onwards David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx\form;
use mod_datalynx;
use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Legacy full-page field-behavior form.
 *
 * The form body and data transforms live in {@see behavior_form_elements} so the AJAX
 * {@see datalynxfield_behavior_dynamic_form} shares them. This class remains as the no-JS fallback
 * reached from fieldbehavior/behavior_edit.php.
 */
class datalynxfield_behavior_form extends moodleform {
    use behavior_form_elements;

    /**
     *
     * @var mod_datalynx\datalynx
     */
    private $dlx;

    /**
     * datalynx_field_behavior_form constructor.
     *
     * @param \mod_datalynx\datalynx $dlx
     */
    public function __construct(mod_datalynx\datalynx $dlx) {
        $this->dlx = $dlx;
        parent::__construct();
    }

    /**
     * Returns the datalynx instance this form operates on.
     *
     * @return \mod_datalynx\datalynx
     */
    protected function get_dlx() {
        return $this->dlx;
    }

    /**
     * Define the form elements for the behavior form.
     */
    protected function definition() {
        $this->behavior_definition(!required_param('id', PARAM_INT));
    }

    /**
     * Build the dynamic availability-condition rows and the action buttons.
     */
    public function definition_after_data() {
        parent::definition_after_data();
        $this->behavior_definition_after_data(true);
    }

    /**
     * Get data from the form, collapsing permission checkboxes and condition rows.
     *
     * @return object
     */
    public function get_data() {
        $data = parent::get_data();
        if ($data) {
            $data = $this->transform_behavior_data($data);
        }
        return $data;
    }

    /**
     * Set form data, expanding permission arrays into checkboxes and stashing conditions.
     *
     * @param array|\stdClass $data
     */
    public function set_data($data) {
        $this->prepare_behavior_data($data);
        parent::set_data($data);
    }

    /**
     * Validate form data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        return $this->validate_behavior($data);
    }
}
