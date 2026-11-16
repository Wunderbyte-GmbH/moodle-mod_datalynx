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
 * Field class for the fieldgroup field type.
 *
 * @package    datalynxfield_fieldgroup
 * @subpackage fieldgroup
 * @copyright  2018 michael pollak <moodle@michaelpollak.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_fieldgroup;

use mod_datalynx\local\field\datalynxfield_base;
use stdClass;

/**
 * Field class for the fieldgroup field type.
 */
class field extends datalynxfield_base {
    /**
     * No input: nothing to enter.
     *
     * @param array $options
     * @return array
     */
    public function get_input_contract(array $options = []): array {
        return [];
    }

    /**
     * Never empty. A fieldgroup holds no value itself; its lines are checked subfield by subfield.
     *
     * @param string $name
     * @param stdClass $data
     * @return bool
     */
    public function is_submitted_value_empty(string $name, stdClass $data): bool {
        return false;
    }

    /**
     * A list of subfields in the stored form: "fieldid", or "fieldid:format|behavior|layout" with
     * trailing separators trimmed, as the field settings form writes it.
     *
     * Older versions stored bare numbers and empty slots; entries without a field id are dropped.
     *
     * @param array $entries the stored list, decoded
     * @param callable|null $mapfieldid maps a field id to the one to store; 0 or false drops the entry
     * @return string[]
     */
    public static function normalise_subfields(array $entries, ?callable $mapfieldid = null): array {
        $normalised = [];
        foreach ($entries as $entry) {
            if (!is_scalar($entry)) {
                continue;
            }
            [$fieldid, $modifiers] = array_pad(explode(':', trim((string) $entry), 2), 2, '');
            if (!ctype_digit($fieldid) || !(int) $fieldid) {
                continue;
            }
            $fieldid = $mapfieldid ? (int) $mapfieldid((int) $fieldid) : (int) $fieldid;
            if ($fieldid <= 0) {
                continue;
            }
            $modifiers = rtrim($modifiers, '|');
            $normalised[] = $fieldid . ($modifiers !== '' ? ":$modifiers" : '');
        }
        return $normalised;
    }

    /**
     * Store the subfield lists of fieldgroups in the current form ({@see self::normalise_subfields()}),
     * dropping entries that name no field of the fieldgroup's instance.
     *
     * Used by the upgrade migration and by restore (after_restore), so old lists (bare numbers,
     * empty slots) end up as the field settings form writes them. Idempotent.
     *
     * @param int|null $dataid Restrict to one datalynx instance, or null for all instances.
     * @return int Number of fieldgroups updated.
     */
    public static function normalize_stored_subfields(?int $dataid = null): int {
        global $DB;

        $conditions = ['type' => 'fieldgroup'] + ($dataid !== null ? ['dataid' => $dataid] : []);
        $updated = 0;
        $instancefields = [];
        foreach ($DB->get_records('datalynx_fields', $conditions, 'id', 'id, dataid, param1') as $fieldgroup) {
            if (!isset($instancefields[$fieldgroup->dataid])) {
                $instancefields[$fieldgroup->dataid] = array_flip(
                    array_map('intval', $DB->get_fieldset_select('datalynx_fields', 'id', 'dataid = ?', [$fieldgroup->dataid]))
                );
            }
            $fields = $instancefields[$fieldgroup->dataid];
            $entries = json_decode((string) $fieldgroup->param1, true);
            $param1 = json_encode(self::normalise_subfields(
                is_array($entries) ? $entries : [],
                static fn(int $fieldid): int => isset($fields[$fieldid]) ? $fieldid : 0
            ));
            if ($param1 !== $fieldgroup->param1) {
                $DB->set_field('datalynx_fields', 'param1', $param1, ['id' => $fieldgroup->id]);
                $updated++;
            }
        }
        return $updated;
    }

    /**
     * The ids of the subfields, without their format, behavior and layout.
     *
     * @return int[]
     */
    public function get_subfield_ids(): array {
        $ids = array_map(fn($entry) => (int) explode(':', $entry, 2)[0], self::normalise_subfields((array) $this->fieldids));
        return array_values(array_unique($ids));
    }

    /** @var string The field type identifier. */
    public $type = 'fieldgroup';

    /**
     * Fieldids of the fields belonging to the fieldgroup.
     * @var int[]
     */
    public $fieldids = [];

    /**
     * Constructor for the fieldgroup field.
     *
     * @param int $dlx The datalynx id.
     * @param int|stdClass $field The field id or object.
     */
    public function __construct($dlx = 0, $field = 0) {
        parent::__construct($dlx, $field);
        if (!empty($this->field->param1)) {
            $this->fieldids = json_decode($this->field->param1, true);
        }
    }

    /**
     * Get the content names for this field.
     *
     * @return string[]
     */
    protected function content_names(): array {
        return [''];
    }

    /**
     * Check if the field supports group by.
     *
     * @return bool
     */
    public function supports_group_by(): bool {
        return false;
    }

    /**
     * Get the supported search operators for this field.
     *
     * @return array
     */
    public function get_supported_search_operators(): array {
        return [];
    }

    /**
     * Check if the field is a custom filter field.
     *
     * @return bool
     */
    public static function is_customfilterfield(): bool {
        return false;
    }

    /**
     * Get the select SQL for this field.
     *
     * @return string
     */
    public function get_select_sql(): string {
        return '';
    }

    /**
     * Get the sort SQL for this field.
     *
     * @return string
     */
    public function get_sort_sql(): string {
        return '';
    }

    /**
     * Get the file area for this field.
     *
     * @param ?string $suffix
     * @return bool
     */
    protected function filearea($suffix = null): bool {
        return false;
    }
}
