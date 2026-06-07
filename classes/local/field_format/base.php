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

namespace mod_datalynx\local\field_format;

/**
 * Base class for all field formats in mod_datalynx.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /** @var \stdClass The db record from datalynx_field_formats */
    protected $record;

    /**
     * Constructor.
     *
     * @param \stdClass $record The database record.
     */
    public function __construct(\stdClass $record) {
        $this->record = $record;
    }

    /**
     * Defines specific configuration options on the Moodle Form.
     *
     * @param \MoodleQuickForm $mform The form object.
     */
    abstract public function config_form(\MoodleQuickForm &$mform);

    /**
     * Validates configuration options.
     *
     * @param array $data Form data.
     * @param array $files Uploaded files.
     * @return array Validation errors.
     */
    public function validation(array $data, array $files): array {
        return [];
    }

    /**
     * Applies format changes to display rendering.
     *
     * @param string $html The original HTML.
     * @param \stdClass $entry The database entry.
     * @return string The formatted HTML.
     */
    public function format_display(string $html, \stdClass $entry): string {
        return $html;
    }

    /**
     * Applies format changes to edit rendering.
     *
     * @param \MoodleQuickForm $mform The form.
     * @param string $elementname The name of the element.
     * @param \stdClass $entry The database entry.
     */
    public function format_edit(\MoodleQuickForm &$mform, string $elementname, \stdClass $entry) {
        // Default does nothing.
    }

    /**
     * Returns the raw record.
     *
     * @return \stdClass
     */
    public function get_record(): \stdClass {
        return $this->record;
    }

    /**
     * Get the format name.
     *
     * @return string
     */
    public function get_name(): string {
        return $this->record->name ?? '';
    }

    /**
     * Get the target field type.
     *
     * @return string
     */
    public function get_fieldtype(): string {
        return $this->record->fieldtype ?? '';
    }

    /**
     * Get the settings array.
     *
     * @return array
     */
    public function get_settings(): array {
        if (empty($this->record->settings)) {
            return [];
        }
        if (is_array($this->record->settings)) {
            return $this->record->settings;
        }
        $settings = json_decode($this->record->settings, true);
        return is_array($settings) ? $settings : [];
    }

    /**
     * Get a specific setting value.
     *
     * @param string $name The setting name.
     * @param mixed $default The default value if not set.
     * @return mixed
     */
    public function get_setting(string $name, $default = null) {
        $settings = $this->get_settings();
        return $settings[$name] ?? $default;
    }

    /**
     * Returns inferred default settings when a format is auto-created from a legacy
     * hardcoded tag name (e.g. ##author:firstname## -> name='firstname').
     * Subclasses override this to populate meaningful default settings.
     *
     * @param string $name The format name as detected from the template tag.
     * @return array Key-value settings array, empty if no defaults apply.
     */
    public function get_default_settings_for_name(string $name): array {
        return [];
    }
}
