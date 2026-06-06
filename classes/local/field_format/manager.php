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
 * Manager class for Field Formats in mod_datalynx.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /**
     * Fetch all formats defined for a specific Datalynx instance, optionally filtered by field type.
     *
     * @param int    $datalynxid The datalynx instance ID.
     * @param string $fieldtype  Optional field type filter (e.g. 'entryauthor').
     * @return base[] List of format objects.
     */
    public static function get_formats_for_instance(int $datalynxid, string $fieldtype = ''): array {
        global $DB;
        $params = ['dataid' => $datalynxid];
        if ($fieldtype !== '') {
            $params['fieldtype'] = $fieldtype;
        }
        $records = $DB->get_records('datalynx_field_formats', $params, 'name ASC');
        $formats = [];
        foreach ($records as $record) {
            $instance = self::get_format_instance($record);
            if ($instance) {
                $formats[$record->id] = $instance;
            }
        }
        return $formats;
    }

    /**
     * Get a specific field format by its database record ID.
     *
     * @param int $formatid The database ID of the format.
     * @return base|null The format instance or null if not found.
     */
    public static function get_format_by_id(int $formatid): ?base {
        global $DB;
        $record = $DB->get_record('datalynx_field_formats', ['id' => $formatid]);
        if ($record) {
            return self::get_format_instance($record);
        }
        return null;
    }

    /**
     * Get a specific field format by its name and Datalynx instance ID.
     *
     * @param int $datalynxid The datalynx instance ID.
     * @param string $name The alphanumeric format name.
     * @return base|null The format instance or null if not found.
     */
    public static function get_format_by_name(int $datalynxid, string $name): ?base {
        global $DB;
        $record = $DB->get_record('datalynx_field_formats', ['dataid' => $datalynxid, 'name' => $name]);
        if ($record) {
            return self::get_format_instance($record);
        }
        return null;
    }

    /**
     * Save (insert or update) a field format configuration in the database.
     *
     * @param \stdClass $record The database record to save.
     * @return int The ID of the saved record.
     */
    public static function save_format(\stdClass $record): int {
        global $DB;
        if (empty($record->id)) {
            return $DB->insert_record('datalynx_field_formats', $record);
        } else {
            $DB->update_record('datalynx_field_formats', $record);
            return $record->id;
        }
    }

    /**
     * Delete a field format after verifying it is not referenced in any view templates.
     *
     * @param int $formatid The format ID to delete.
     * @return bool True on success.
     * @throws \moodle_exception If the format is currently in use in views.
     */
    public static function delete_format(int $formatid): bool {
        global $DB;
        $format = self::get_format_by_id($formatid);
        if (!$format) {
            return false;
        }

        $usedinviews = [];
        if (self::is_format_used_in_views($format->get_record()->dataid, $format->get_name(), $usedinviews)) {
            $viewslist = implode(', ', $usedinviews);
            throw new \moodle_exception('errorformatinuse', 'mod_datalynx', '', $viewslist);
        }

        return $DB->delete_records('datalynx_field_formats', ['id' => $formatid]);
    }

    /**
     * Check if a field format name is referenced in any view templates of the datalynx instance.
     *
     * @param int $datalynxid The Datalynx instance ID.
     * @param string $formatname The format name.
     * @param ?array $usedinviews List of view names referencing the format.
     * @return bool True if used in at least one view.
     */
    public static function is_format_used_in_views(int $datalynxid, string $formatname, ?array &$usedinviews = []): bool {
        global $DB;
        $views = $DB->get_records('datalynx_views', ['dataid' => $datalynxid]);
        $used = false;
        $usedinviews = [];

        $escformat = preg_quote($formatname, '/');
        // Look for the first pattern style.
        $pattern1 = "/##[^#:]+:{$escformat}##/";
        // Look for the second pattern style.
        $pattern2 = "/\\[\\[[^:|\\]]+:{$escformat}(?:\\|[^\\]]*)?\\]\\]/";

        $textfields = [
            'patterns', 'section', 'param1', 'param2', 'param3',
            'param4', 'param5', 'param6', 'param7', 'param8',
            'param9', 'param10',
        ];

        foreach ($views as $view) {
            $inview = false;
            foreach ($textfields as $field) {
                if (!empty($view->$field)) {
                    if (preg_match($pattern1, $view->$field) || preg_match($pattern2, $view->$field)) {
                        $inview = true;
                        break;
                    }
                }
            }
            if ($inview) {
                $usedinviews[] = $view->name;
                $used = true;
            }
        }
        return $used;
    }

    /**
     * Get list of field types that support custom formats.
     *
     * @return array Array of [type => name]
     */
    public static function get_supported_field_types(): array {
        global $CFG;
        $types = [];
        $directories = get_list_of_plugins('mod/datalynx/field/');
        foreach ($directories as $type) {
            if (file_exists("$CFG->dirroot/mod/datalynx/field/$type/classes/field_format.php")) {
                // Dynamic check to make sure the class is loaded and is a subclass of base.
                $classname = "\\datalynxfield_{$type}\\field_format";
                if (class_exists($classname) && is_subclass_of($classname, 'mod_datalynx\local\field_format\base')) {
                    $types[$type] = get_string('pluginname', "datalynxfield_$type");
                }
            }
        }
        asort($types);
        return $types;
    }

    /**
     * Instantiate the format base subclass for a database format record.
     *
     * @param \stdClass $record The database format record.
     * @return base|null The format subclass object.
     */
    public static function get_format_instance(\stdClass $record): ?base {
        $fieldtype = $record->fieldtype;
        $classname = "\\datalynxfield_{$fieldtype}\\field_format";
        if (class_exists($classname)) {
            return new $classname($record);
        }
        return null;
    }

    /**
     * Scan all view templates for a datalynx instance and automatically create missing field formats.
     *
     * @param int $datalynxid
     * @return void
     */
    public static function auto_create_formats_from_templates(int $datalynxid): void {
        global $DB, $CFG;

        // Fetch all fields for this datalynx instance.
        $fields = $DB->get_records('datalynx_fields', ['dataid' => $datalynxid]);
        $fieldsbyname = [];
        foreach ($fields as $field) {
            $fieldsbyname[strtolower($field->name)] = $field->type;
        }

        // Add special handling for "author" as "entryauthor" field type.
        $fieldsbyname['author'] = 'entryauthor';

        // Fetch all views for this datalynx instance.
        $views = $DB->get_records('datalynx_views', ['dataid' => $datalynxid]);
        if (empty($views)) {
            return;
        }

        // Array of field types and formats.
        $detectedformats = [];

        // Regex patterns.
        $pattern1 = '/##([^#:]+):([a-zA-Z0-9_]+)##/';
        $pattern2 = '/\[\[([^:|\]]+):([a-zA-Z0-9_]+)(?:\|[^\]]*)?\]\]/';

        $textfields = [
            'patterns', 'section', 'param1', 'param2', 'param3',
            'param4', 'param5', 'param6', 'param7', 'param8',
            'param9', 'param10',
        ];

        foreach ($views as $view) {
            foreach ($textfields as $textfield) {
                if (empty($view->$textfield)) {
                    continue;
                }
                $text = $view->$textfield;

                // Match the first pattern style.
                if (preg_match_all($pattern1, $text, $matches1, PREG_SET_ORDER)) {
                    foreach ($matches1 as $match) {
                        $fieldname = strtolower($match[1]);
                        $formatname = $match[2];

                        // Skip system tags.
                        if (in_array($fieldname, ['viewlink', 'viewsesslink', 'viewurl', 'viewcontent', 'comments'])) {
                            continue;
                        }

                        if (isset($fieldsbyname[$fieldname])) {
                            $fieldtype = $fieldsbyname[$fieldname];
                            $detectedformats[$fieldtype][] = $formatname;
                        }
                    }
                }

                // Match the second pattern style.
                if (preg_match_all($pattern2, $text, $matches2, PREG_SET_ORDER)) {
                    foreach ($matches2 as $match) {
                        $fieldname = strtolower($match[1]);
                        $formatname = $match[2];

                        if (isset($fieldsbyname[$fieldname])) {
                            $fieldtype = $fieldsbyname[$fieldname];
                            $detectedformats[$fieldtype][] = $formatname;
                        }
                    }
                }
            }
        }

        // Now create the detected formats if they don't exist yet.
        foreach ($detectedformats as $fieldtype => $formatnames) {
            $formatnames = array_unique($formatnames);
            // Check if this fieldtype actually has a format class before inserting.
            $classname = "\\datalynxfield_{$fieldtype}\\field_format";
            if (!class_exists($classname)) {
                continue;
            }

            foreach ($formatnames as $formatname) {
                // Check if already exists.
                $exists = $DB->record_exists('datalynx_field_formats', [
                    'dataid' => $datalynxid,
                    'name' => $formatname,
                    'fieldtype' => $fieldtype,
                ]);
                if (!$exists) {
                    $newrecord = new \stdClass();
                    $newrecord->dataid = $datalynxid;
                    $newrecord->name = $formatname;
                    $newrecord->fieldtype = $fieldtype;
                    $newrecord->settings = json_encode(new \stdClass());
                    $DB->insert_record('datalynx_field_formats', $newrecord);
                }
            }
        }
    }

    /**
     * Scan and migrate legacy formats for all datalynx instances.
     *
     * @return void
     */
    public static function auto_create_formats_from_all_instances(): void {
        global $DB;
        try {
            $datalynxids = $DB->get_fieldset_select('datalynx', 'id', 'id > 0');
            foreach ($datalynxids as $datalynxid) {
                self::auto_create_formats_from_templates($datalynxid);
            }
        } catch (\Exception $e) {
            // Safe fallback during early DB upgrade stages if table/fields do not exist yet.
            $unused = $e;
        }
    }
}
