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
 * Base class for datalynx field formats.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx\local\field_format;

use MoodleQuickForm;
use stdClass;

/**
 * Base class for field format objects.
 *
 * Subclasses live at datalynxfield_FIELDTYPE\field_format and are loaded by the manager.
 * Each subclass may override config_form() to add field-type-specific settings to the
 * format-edit form, and get_default_settings_for_name() to infer sensible defaults when
 * a format is auto-created from a legacy hardcoded tag name.
 */
abstract class base {
    /** @var stdClass The underlying DB record. */
    protected stdClass $record;

    /** @var array Decoded settings (lazy-loaded). */
    private ?array $settings = null;

    /**
     * Constructor.
     *
     * @param stdClass $record DB record from datalynx_field_formats.
     */
    public function __construct(stdClass $record) {
        $this->record = $record;
    }

    /**
     * Returns the record ID.
     *
     * @return int
     */
    public function get_id(): int {
        return (int) $this->record->id;
    }

    /**
     * Returns the format name (used inside tags, e.g. ##author:NAME##).
     *
     * @return string
     */
    public function get_name(): string {
        return $this->record->name;
    }

    /**
     * Returns the field type this format belongs to (e.g. 'entryauthor').
     *
     * @return string
     */
    public function get_fieldtype(): string {
        return $this->record->fieldtype;
    }

    /**
     * Returns the datalynx instance ID this format belongs to.
     *
     * @return int
     */
    public function get_dataid(): int {
        return (int) $this->record->dataid;
    }

    /**
     * Returns a decoded settings array.
     *
     * @return array
     */
    public function get_settings(): array {
        if ($this->settings === null) {
            $decoded = json_decode($this->record->settings ?? '{}', true);
            $this->settings = is_array($decoded) ? $decoded : [];
        }
        return $this->settings;
    }

    /**
     * Returns a single setting value, or null if not set.
     *
     * @param string $key
     * @return mixed|null
     */
    public function get_setting(string $key) {
        return $this->get_settings()[$key] ?? null;
    }

    /**
     * Returns the underlying DB record for saving purposes.
     *
     * @return stdClass
     */
    public function get_record(): stdClass {
        return $this->record;
    }

    /**
     * Adds field-type-specific form elements for configuring this format type.
     *
     * Subclasses override this to add their own settings (e.g. a display_field select
     * for entryauthor, or a dateformat text field for entrytime).
     * The base implementation does nothing (formats with no extra settings are valid).
     *
     * @param MoodleQuickForm $mform The form to add elements to.
     */
    public function config_form(MoodleQuickForm &$mform): void {
        // No extra settings by default.
    }

    /**
     * Returns inferred default settings when a format is auto-created from a legacy
     * hardcoded tag name (e.g. ##author:firstname## → name='firstname').
     *
     * Subclasses override this to populate meaningful default settings so that the
     * migration from hardcoded tags to DB-backed formats preserves existing behaviour.
     *
     * @param string $name The format name as detected from the template tag.
     * @return array Key-value settings array, empty if no defaults apply.
     */
    public function get_default_settings_for_name(string $name): array {
        return [];
    }
}
