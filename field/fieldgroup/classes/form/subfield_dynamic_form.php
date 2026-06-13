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
 * Dynamic form for adding/editing a single subfield entry in a fieldgroup.
 *
 * @package    datalynxfield_fieldgroup
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace datalynxfield_fieldgroup\form;

use context;
use context_module;
use core_form\dynamic_form;
use mod_datalynx\datalynx;
use mod_datalynx\local\field_format\manager;

/**
 * Modal form for adding or editing a fieldgroup subfield (field + optional format/behavior/layout).
 *
 * Received args: d (datalynx id), cmid, value (existing entry string or empty for add).
 * Returns: value string, field/format/behavior/layout names for JS to update the row.
 */
class subfield_dynamic_form extends dynamic_form {
    /** @var datalynx|null Lazily-built datalynx instance. */
    protected ?datalynx $dlx = null;

    /**
     * Lazily build the datalynx instance from AJAX form params.
     */
    protected function get_dlx(): datalynx {
        if ($this->dlx === null) {
            $d    = (int) $this->optional_param('d', 0, PARAM_INT);
            $cmid = (int) $this->optional_param('cmid', 0, PARAM_INT);
            $this->dlx = new datalynx($d, $cmid);
        }
        return $this->dlx;
    }

    /**
     * The form operates in the datalynx module context.
     */
    protected function get_context_for_dynamic_submission(): context {
        return context_module::instance($this->get_dlx()->cm->id);
    }

    /**
     * Only users who can manage templates may edit fieldgroup subfields.
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('mod/datalynx:managetemplates', $this->get_context_for_dynamic_submission());
    }

    /**
     * Static form elements: hidden identifiers and the field selector.
     * Format/behavior/layout dropdowns are built in definition_after_data() once fieldid is known.
     */
    public function definition() {
        $mform = $this->_form;
        $dlx   = $this->get_dlx();

        $mform->disable_form_change_checker();
        $mform->updateAttributes(['data-random-ids' => 0]);

        $mform->addElement('hidden', 'd', $dlx->id());
        $mform->setType('d', PARAM_INT);

        $mform->addElement('hidden', 'cmid', $dlx->cm->id);
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('hidden', 'originalvalue', '');
        $mform->setType('originalvalue', PARAM_RAW);

        // Field selector.
        $fields    = $dlx->get_fields(null, false, true);
        $fieldopts = [];
        foreach ($fields as $fieldid => $field) {
            if ($field->for_use_in_fieldgroup()) {
                $fieldopts[$fieldid] = $field->name();
            }
        }
        asort($fieldopts);
        $mform->addElement('select', 'fieldid', get_string('subfieldfield', 'datalynx'), $fieldopts);
        $mform->addRule('fieldid', null, 'required', null, 'client');

        // No-submit button: clicking it triggers an AJAX reload of the dependent dropdowns via
        // definition_after_data() once the selected fieldid is known on the server side.
        $mform->registerNoSubmitButton('reloadmodifiers');
        $mform->addElement(
            'submit',
            'reloadmodifiers',
            get_string('subfieldreloadformats', 'datalynx'),
            ['class' => 'btn btn-secondary d-none']
        );
    }

    /**
     * Rebuild the format dropdown for the currently selected field type.
     * Behavior and layout dropdowns are always the same (not field-type-specific).
     */
    public function definition_after_data() {
        global $DB;
        parent::definition_after_data();

        $mform = $this->_form;
        $dlx   = $this->get_dlx();
        $dlxid = $dlx->id();

        // Determine selected field type.
        // getElementValue() returns an array for select elements; extract the scalar first.
        $raw      = $mform->getElementValue('fieldid');
        $fieldid  = (int)(is_array($raw) ? reset($raw) : $raw);
        $fieldobj = $dlx->get_field_from_id($fieldid);
        $fieldtype = $fieldobj ? $fieldobj->type : '';

        // Format dropdown (depends on selected field type).
        $formats   = manager::get_formats_for_instance($dlxid, $fieldtype);
        $formatopts = ['' => get_string('subfieldformatnone', 'datalynx')];
        foreach ($formats as $fmt) {
            $name = $fmt->get_name();
            $formatopts[$name] = $name;
        }
        if ($mform->elementExists('formatname')) {
            $mform->removeElement('formatname');
        }
        $mform->addElement('select', 'formatname', get_string('subfieldformat', 'datalynx'), $formatopts);

        // Behavior dropdown (instance-level, same for all field types).
        $behaviors    = $DB->get_records('datalynx_behaviors', ['dataid' => $dlxid], 'name ASC');
        $behavioropts = ['' => get_string('subfieldbehaviornone', 'datalynx')];
        foreach ($behaviors as $b) {
            $behavioropts[$b->name] = $b->name;
        }
        if ($mform->elementExists('behaviorname')) {
            $mform->removeElement('behaviorname');
        }
        $mform->addElement('select', 'behaviorname', get_string('subfieldbehavior', 'datalynx'), $behavioropts);

        // Layout (renderer) dropdown (instance-level, same for all field types).
        $layouts    = $DB->get_records('datalynx_renderers', ['dataid' => $dlxid], 'name ASC');
        $layoutopts = ['' => get_string('subfieldlayoutnone', 'datalynx')];
        foreach ($layouts as $l) {
            $layoutopts[$l->name] = $l->name;
        }
        if ($mform->elementExists('layoutname')) {
            $mform->removeElement('layoutname');
        }
        $mform->addElement('select', 'layoutname', get_string('subfieldlayout', 'datalynx'), $layoutopts);
    }

    /**
     * Pre-populate the form from the incoming value string (e.g. "5:euros|readonly|compact").
     */
    public function set_data_for_dynamic_submission(): void {
        $value = $this->optional_param('value', '', PARAM_RAW);
        $parts = explode(':', $value, 2);
        $modifiers = explode('|', $parts[1] ?? '', 3);

        $data = new \stdClass();
        $data->d             = $this->get_dlx()->id();
        $data->cmid          = $this->get_dlx()->cm->id;
        $data->originalvalue = $value;
        $data->fieldid       = (int) $parts[0];
        $data->formatname    = $modifiers[0] ?? '';
        $data->behaviorname  = $modifiers[1] ?? '';
        $data->layoutname    = $modifiers[2] ?? '';
        $this->set_data($data);
    }

    /**
     * Assemble the result returned to the FORM_SUBMITTED JavaScript event.
     *
     * @return array Associative with value, fieldname, formatname, behaviorname, layoutname, originalvalue.
     */
    public function process_dynamic_submission(): array {
        $data = $this->get_data();

        $fieldid      = (int) $data->fieldid;
        $formatname   = trim($data->formatname ?? '');
        $behaviorname = trim($data->behaviorname ?? '');
        $layoutname   = trim($data->layoutname ?? '');

        // Build the storage string: "fieldid:formatname|behaviorname|layoutname", trimming trailing pipes.
        $suffix = rtrim($formatname . '|' . $behaviorname . '|' . $layoutname, '|');
        $value  = $fieldid . ($suffix !== '' ? ':' . $suffix : '');

        $dlx      = $this->get_dlx();
        $fieldobj = $dlx->get_field_from_id($fieldid);

        return [
            'value'         => $value,
            'fieldname'     => $fieldobj ? $fieldobj->name() : "#{$fieldid}",
            'formatname'    => $formatname,
            'behaviorname'  => $behaviorname,
            'layoutname'    => $layoutname,
            'originalvalue' => $data->originalvalue ?? '',
        ];
    }

    /**
     * URL of the page hosting the form (the field management page).
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/mod/datalynx/field/index.php', ['d' => $this->get_dlx()->id()]);
    }
}
