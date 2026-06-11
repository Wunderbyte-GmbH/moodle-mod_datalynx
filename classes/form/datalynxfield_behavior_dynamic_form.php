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

use context;
use context_module;
use core_form\dynamic_form;
use mod_datalynx\datalynx;
use mod_datalynx\local\field\datalynxfield_behavior;

/**
 * AJAX (modal) version of the field-behavior editor.
 *
 * Renders the same controls and uses the same element names as the legacy
 * {@see datalynxfield_behavior_form}. Selecting a condition source field reloads that row's
 * operator/value widgets over AJAX (the no-submit "reloadconditions" button) instead of reloading
 * the whole page.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datalynxfield_behavior_dynamic_form extends dynamic_form {
    use behavior_form_elements;

    /** @var datalynx The datalynx instance, reconstructed from the AJAX payload. */
    protected $dlx = null;

    /**
     * Lazily build the datalynx instance from the AJAX form data (d = dataid, cmid = course module id).
     *
     * @return datalynx
     */
    protected function get_dlx() {
        if ($this->dlx === null) {
            $d = (int) $this->optional_param('d', 0, PARAM_INT);
            $cmid = (int) $this->optional_param('cmid', 0, PARAM_INT);
            $this->dlx = new datalynx($d, $cmid);
        }
        return $this->dlx;
    }

    /**
     * The behavior id being edited (0 for a new behavior).
     *
     * @return int
     */
    private function get_behaviorid(): int {
        return (int) $this->optional_param('id', 0, PARAM_INT);
    }

    /**
     * The form operates in the datalynx module context.
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        return context_module::instance($this->get_dlx()->cm->id);
    }

    /**
     * Only users who can manage templates may edit behaviors.
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('mod/datalynx:managetemplates', $this->get_context_for_dynamic_submission());
    }

    /**
     * Define the static part of the form. The dynamic condition rows are built in
     * {@see self::definition_after_data()}.
     */
    public function definition() {
        $mform = $this->_form;
        $dlx = $this->get_dlx();

        // The modal manages its own lifecycle and is saved over AJAX.
        $mform->disable_form_change_checker();

        // Use stable element ids (id_<name>) inside the modal - this is the only place this form is
        // rendered, so there is no collision, and the capability checkboxes stay addressable.
        $mform->updateAttributes(['data-random-ids' => 0]);

        $mform->addElement('hidden', 'cmid', $dlx->cm->id);
        $mform->setType('cmid', PARAM_INT);

        $this->behavior_definition(!$this->get_behaviorid());
    }

    /**
     * Build the dynamic condition rows (no action buttons - the modal supplies Save/Cancel).
     */
    public function definition_after_data() {
        parent::definition_after_data();
        $this->behavior_definition_after_data(false);
    }

    /**
     * Seed the form from the behavior being edited (or sensible defaults for a new one).
     */
    public function set_data_for_dynamic_submission(): void {
        $dlx = $this->get_dlx();
        $behaviorid = $this->get_behaviorid();
        $data = $behaviorid
            ? datalynxfield_behavior::get_behavior($behaviorid)
            : datalynxfield_behavior::get_default_form_data($dlx);
        $data->cmid = $dlx->cm->id;
        $this->set_data($data);
    }

    /**
     * Persist the behavior, reusing the legacy serialisation.
     *
     * @return array{behaviorid: int, name: string}
     */
    public function process_dynamic_submission(): array {
        $data = $this->get_data();
        if (!$data->id) {
            $data->id = (int) datalynxfield_behavior::insert_behavior($data);
        } else {
            datalynxfield_behavior::update_behavior($data);
        }
        return ['behaviorid' => (int) $data->id, 'name' => $data->name];
    }

    /**
     * Get data from the form, collapsing permission checkboxes and condition rows.
     *
     * @return \stdClass|null
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
     * Validate form data (name required, unique, no pipe symbol).
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        return $this->validate_behavior($data);
    }

    /**
     * URL of the page hosting the form (the behavior management page).
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/mod/datalynx/fieldbehavior/index.php', ['d' => $this->get_dlx()->id()]);
    }
}
