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
 * Manager for datalynx field formats.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx\local\field_format;

use stdClass;

/**
 * Manager for datalynx field format records.
 *
 * Provides CRUD operations, static per-request caches, and the
 * auto_create_formats_from_templates() migration helper.
 */
class manager {
    /** @var array<string, base[]> Static per-request cache keyed by "dataid:fieldtype" (empty string = all). */
    private static array $instancecache = [];

    /** @var array<string, base|null> Static per-request cache keyed by "dataid:name". */
    private static array $namecache = [];

    /**
     * Returns all format objects for the given datalynx instance, optionally filtered by field type.
     *
     * Results are cached per request to avoid repeated DB queries from patterns() calls.
     *
     * @param int    $datalynxid  The datalynx instance ID.
     * @param string $fieldtype   Optional field type filter (e.g. 'entryauthor').
     * @return base[]
     */
    public static function get_formats_for_instance(int $datalynxid, string $fieldtype = ''): array {
        global $DB;

        $cachekey = "{$datalynxid}:{$fieldtype}";
        if (isset(self::$instancecache[$cachekey])) {
            return self::$instancecache[$cachekey];
        }

        $params = ['dataid' => $datalynxid];
        $where = 'dataid = :dataid';
        if ($fieldtype !== '') {
            $where .= ' AND fieldtype = :fieldtype';
            $params['fieldtype'] = $fieldtype;
        }

        $records = $DB->get_records_select('datalynx_field_formats', $where, $params, 'name ASC');
        $formats = [];
        foreach ($records as $record) {
            $instance = self::get_format_instance($record);
            if ($instance !== null) {
                $formats[$record->id] = $instance;
            }
        }

        self::$instancecache[$cachekey] = $formats;
        return $formats;
    }

    /**
     * Returns a single format object by datalynx instance ID and format name.
     *
     * Result is cached per request.
     *
     * @param int    $datalynxid  The datalynx instance ID.
     * @param string $name        The format name.
     * @return base|null
     */
    public static function get_format_by_name(int $datalynxid, string $name): ?base {
        global $DB;

        $cachekey = "{$datalynxid}:{$name}";
        if (array_key_exists($cachekey, self::$namecache)) {
            return self::$namecache[$cachekey];
        }

        $record = $DB->get_record('datalynx_field_formats', ['dataid' => $datalynxid, 'name' => $name]);
        if (!$record) {
            self::$namecache[$cachekey] = null;
            return null;
        }

        $instance = self::get_format_instance($record);
        self::$namecache[$cachekey] = $instance;
        return $instance;
    }

    /**
     * Returns a single format object by its DB record ID.
     *
     * @param int $id The DB record ID.
     * @return base|null
     */
    public static function get_format_by_id(int $id): ?base {
        global $DB;

        $record = $DB->get_record('datalynx_field_formats', ['id' => $id]);
        if (!$record) {
            return null;
        }

        return self::get_format_instance($record);
    }

    /**
     * Saves (inserts or updates) a field format from form data.
     *
     * Invalidates caches for the affected datalynx instance.
     *
     * @param stdClass $formdata Form data with id, dataid, fieldtype, name, settings fields.
     * @return int The ID of the saved record.
     */
    public static function save_format(stdClass $formdata): int {
        global $DB;

        // Build settings array from any extra form fields.
        $settings = [];
        if (!empty($formdata->settings) && is_array($formdata->settings)) {
            $settings = $formdata->settings;
        }

        // Collect field-type-specific settings from form data fields.
        // Each field_format subclass writes its own settings keys directly on $formdata.
        $knownbasefields = ['id', 'dataid', 'fieldtype', 'name', 'sesskey', 'action', 'settings'];
        foreach ((array)$formdata as $key => $value) {
            if (!in_array($key, $knownbasefields, true)) {
                $settings[$key] = $value;
            }
        }

        $record = new stdClass();
        $record->dataid    = (int) $formdata->dataid;
        $record->fieldtype = $formdata->fieldtype;
        $record->name      = $formdata->name;
        $record->settings  = json_encode((object) $settings);

        self::invalidate_cache((int) $formdata->dataid);

        if (!empty($formdata->id)) {
            $record->id = (int) $formdata->id;
            $DB->update_record('datalynx_field_formats', $record);
            return $record->id;
        } else {
            return $DB->insert_record('datalynx_field_formats', $record);
        }
    }

    /**
     * Deletes a field format record.
     *
     * Invalidates caches for the affected datalynx instance.
     *
     * @param int $id The record ID.
     */
    public static function delete_format(int $id): void {
        global $DB;

        $record = $DB->get_record('datalynx_field_formats', ['id' => $id]);
        if ($record) {
            $DB->delete_records('datalynx_field_formats', ['id' => $id]);
            self::invalidate_cache((int) $record->dataid);
        }
    }

    /**
     * Instantiates the correct base subclass for a given DB record.
     *
     * @param stdClass $record A DB record from datalynx_field_formats.
     * @return base|null  Returns null if the subclass cannot be found.
     */
    public static function get_format_instance(stdClass $record): ?base {
        $classname = "\\datalynxfield_{$record->fieldtype}\\field_format";
        if (!class_exists($classname)) {
            return null;
        }
        return new $classname($record);
    }

    /**
     * Scans all view templates for the given datalynx instance and creates
     * datalynx_field_formats records for any field-format tags found.
     *
     * Handles both regular fields (using their DB name) and virtual fields
     * whose tag prefix differs from the DB field name (entryauthor, entrytime, entrygroup).
     *
     * Already-existing records are left untouched (by name+dataid uniqueness).
     * New records get default settings inferred via get_default_settings_for_name().
     *
     * @param int $datalynxid The datalynx instance ID.
     */
    public static function auto_create_formats_from_templates(int $datalynxid): void {
        global $DB;

        // Build field-name → field-type map for regular DB fields.
        $fieldsbyname = [];
        $fields = $DB->get_records('datalynx_fields', ['dataid' => $datalynxid], '', 'name, type');
        foreach ($fields as $field) {
            $fieldsbyname[$field->name] = $field->type;
        }

        // Virtual fields whose tag prefix differs from their DB field name.
        $fieldsbyname['author']       = 'entryauthor';
        $fieldsbyname['group']        = 'entrygroup';
        $fieldsbyname['timecreated']  = 'entrytime';
        $fieldsbyname['timemodified'] = 'entrytime';

        // Scan all view text columns for ##prefix:name## tags.
        $views = $DB->get_records('datalynx_views', ['dataid' => $datalynxid]);
        $textcolumns = ['section', 'param1', 'param2', 'param3', 'param4', 'param5',
            'param6', 'param7', 'param8', 'param9', 'param10',
        ];

        $found = []; // [fieldtype => [name => true]].

        foreach ($views as $view) {
            foreach ($textcolumns as $col) {
                if (empty($view->$col)) {
                    continue;
                }
                // Match ##prefix:name## patterns.
                if (preg_match_all('/##([a-zA-Z0-9_]+):([a-zA-Z0-9_]+)##/', $view->$col, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $prefix     = $match[1];
                        $formatname = $match[2];
                        if (isset($fieldsbyname[$prefix])) {
                            $fieldtype = $fieldsbyname[$prefix];
                            $found[$fieldtype][$formatname] = true;
                        }
                    }
                }
            }
        }

        // Insert missing records.
        foreach ($found as $fieldtype => $names) {
            foreach (array_keys($names) as $formatname) {
                $exists = $DB->record_exists('datalynx_field_formats', [
                    'dataid'    => $datalynxid,
                    'fieldtype' => $fieldtype,
                    'name'      => $formatname,
                ]);
                if ($exists) {
                    continue;
                }

                $newrecord            = new stdClass();
                $newrecord->dataid    = $datalynxid;
                $newrecord->fieldtype = $fieldtype;
                $newrecord->name      = $formatname;
                $newrecord->settings  = json_encode(new stdClass());

                // Ask the field_format class for inferred default settings.
                $instance = self::get_format_instance($newrecord);
                if ($instance !== null) {
                    $defaults = $instance->get_default_settings_for_name($formatname);
                    if (!empty($defaults)) {
                        $newrecord->settings = json_encode((object) $defaults);
                    }
                }

                $DB->insert_record('datalynx_field_formats', $newrecord);
            }
        }

        self::invalidate_cache($datalynxid);
    }

    /**
     * Clears all per-request caches for the given datalynx instance.
     *
     * @param int $datalynxid
     */
    public static function invalidate_cache(int $datalynxid): void {
        // Remove all instancecache entries for this dataid.
        foreach (array_keys(self::$instancecache) as $key) {
            if (str_starts_with($key, "{$datalynxid}:")) {
                unset(self::$instancecache[$key]);
            }
        }
        // Remove all namecache entries for this dataid.
        foreach (array_keys(self::$namecache) as $key) {
            if (str_starts_with($key, "{$datalynxid}:")) {
                unset(self::$namecache[$key]);
            }
        }
    }
}
