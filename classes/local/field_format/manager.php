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
    /** @var array<string, base[]> Static cache for instance formats. */
    private static array $instancecache = [];

    /** @var array<string, base|null> Static cache for formats by name. */
    private static array $namecache = [];

    /**
     * Fetch all formats defined for a specific Datalynx instance.
     *
     * @param int $datalynxid The datalynx instance ID.
     * @param string $fieldtype Optional field type to filter by.
     * @return base[] List of format objects.
     */
    public static function get_formats_for_instance(int $datalynxid, string $fieldtype = ''): array {
        global $DB;
        $cachekey = $datalynxid . ':' . $fieldtype;
        if (isset(self::$instancecache[$cachekey])) {
            return self::$instancecache[$cachekey];
        }

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
        self::$instancecache[$cachekey] = $formats;
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
        $cachekey = $datalynxid . ':' . $name;
        if (array_key_exists($cachekey, self::$namecache)) {
            return self::$namecache[$cachekey];
        }

        $record = $DB->get_record('datalynx_field_formats', ['dataid' => $datalynxid, 'name' => $name]);
        $result = $record ? self::get_format_instance($record) : null;
        self::$namecache[$cachekey] = $result;
        return $result;
    }

    /**
     * Save (insert or update) a field format configuration in the database.
     *
     * @param \stdClass $record The database record to save.
     * @return int The ID of the saved record.
     */
    public static function save_format(\stdClass $record): int {
        global $DB;
        self::$instancecache = [];
        self::$namecache = [];
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
        self::$instancecache = [];
        self::$namecache = [];
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
                    $dummy = new \stdClass();
                    $dummy->fieldtype = $type;
                    $dummy->name = '';
                    $dummy->settings = json_encode([]);
                    $formatinstance = new $classname($dummy);
                    if ($formatinstance->has_options()) {
                        $types[$type] = get_string('pluginname', "datalynxfield_$type");
                    }
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
        $fieldsbyname['group'] = 'entrygroup';
        $fieldsbyname['timecreated'] = 'entrytime';
        $fieldsbyname['timemodified'] = 'entrytime';
        $fieldsbyname['ratings'] = 'rating';
        $fieldsbyname['comments'] = 'comment';

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
                    $settings = new \stdClass();
                    $classname = "\\datalynxfield_{$fieldtype}\\field_format";
                    if (class_exists($classname)) {
                        $dummy = new \stdClass();
                        $dummy->fieldtype = $fieldtype;
                        $dummy->name = $formatname;
                        $dummy->settings = json_encode([]);
                        $formatobj = new $classname($dummy);
                        $defaultsettings = $formatobj->get_default_settings_for_name($formatname);
                        if (!empty($defaultsettings)) {
                            $settings = (object)$defaultsettings;
                        }
                    }
                    $newrecord->settings = json_encode($settings);
                    self::save_format($newrecord);
                }
            }
        }
    }

    /**
     * Migrate legacy `userinfo` field instances into `entryauthor` field formats.
     *
     * The removed `userinfo` field type let a user inline-edit one of their own user profile
     * fields. That behaviour now lives in an `entryauthor` field format whose option targets a
     * custom user profile field, with `editable`/`mandatory` settings. This converter is pure DB
     * (it never instantiates the removed userinfo classes) so it is safe to run from both the
     * upgrade step and from restore of older course backups.
     *
     * For each `datalynx_fields` row of type `userinfo` it:
     *  - derives an alphanumeric format name from the field name (the `##author:{name}##` tag);
     *  - if the name had to be sanitised/disambiguated, rewrites the tag in all view templates;
     *  - upserts an `entryauthor` field format {option: shortname, editable, mandatory};
     *  - deletes the legacy `userinfo` field row.
     *
     * Idempotent: re-running updates the existing format and is a no-op once the field rows are gone.
     *
     * @param int|null $datalynxid Restrict to a single instance, or null for all instances.
     * @return void
     */
    public static function migrate_userinfo_fields(?int $datalynxid = null): void {
        global $DB;

        $conditions = ['type' => 'userinfo'];
        if ($datalynxid !== null) {
            $conditions['dataid'] = $datalynxid;
        }

        if (!$userinfofields = $DB->get_records('datalynx_fields', $conditions)) {
            return;
        }

        // Pseudo-field ids and built-in option names the format name must not collide with.
        $reserved = ['name', 'firstname', 'lastname', 'username', 'id', 'idnumber', 'picture',
                'picturelarge', 'email', 'institution', 'department', 'badges', 'edit',
                'userid', 'userfirstname', 'userlastname', 'userusername', 'useridnumber',
                'userpicture', 'useremail', 'userinstitution', 'userdepartment'];

        foreach ($userinfofields as $field) {
            $dataid = (int) $field->dataid;
            $oldname = (string) $field->name;
            $shortname = (string) $field->param2;

            if ($shortname === '') {
                // No target profile field; nothing meaningful to migrate. Drop the dangling field.
                $DB->delete_records('datalynx_fields', ['id' => $field->id]);
                continue;
            }

            // Derive an alphanumeric, non-reserved format name.
            $cleanname = preg_replace('/[^A-Za-z0-9]/', '', $oldname);
            if ($cleanname === '' || in_array(strtolower($cleanname), $reserved, true)) {
                $cleanname = 'userinfo' . $field->id;
            }

            // If the name changed, the existing `##author:{oldname}##` tag must be rewritten.
            if ($cleanname !== $oldname) {
                self::rewrite_author_tag($dataid, $oldname, $cleanname);
            }

            // Upsert the entryauthor field format.
            $settings = json_encode([
                'option' => $shortname,
                'editable' => empty($field->param6) ? 0 : 1,
                'mandatory' => empty($field->param7) ? 0 : 1,
            ]);
            $existing = $DB->get_record(
                'datalynx_field_formats',
                ['dataid' => $dataid, 'fieldtype' => 'entryauthor', 'name' => $cleanname]
            );
            if ($existing) {
                $existing->settings = $settings;
                $DB->update_record('datalynx_field_formats', $existing);
            } else {
                $record = new \stdClass();
                $record->dataid = $dataid;
                $record->name = $cleanname;
                $record->fieldtype = 'entryauthor';
                $record->settings = $settings;
                $DB->insert_record('datalynx_field_formats', $record);
            }

            // Remove the legacy field row (userinfo stored no datalynx content of its own).
            $DB->delete_records('datalynx_fields', ['id' => $field->id]);
        }

        // Drop cached formats so freshly created ones are visible immediately.
        self::$instancecache = [];
        self::$namecache = [];
    }

    /**
     * Rewrite a `##author:{oldname}##` tag to `##author:{newname}##` across all view templates of
     * a datalynx instance and invalidate the cached pattern column.
     *
     * @param int $datalynxid The datalynx instance id.
     * @param string $oldname The original (tag) name.
     * @param string $newname The sanitised format name.
     * @return void
     */
    protected static function rewrite_author_tag(int $datalynxid, string $oldname, string $newname): void {
        global $DB;

        $search = "##author:{$oldname}##";
        $replace = "##author:{$newname}##";
        $textfields = ['section', 'param1', 'param2', 'param3', 'param4', 'param5',
                'param6', 'param7', 'param8', 'param9', 'param10'];

        $views = $DB->get_records('datalynx_views', ['dataid' => $datalynxid]);
        foreach ($views as $view) {
            $changed = false;
            foreach ($textfields as $textfield) {
                if (!empty($view->$textfield) && strpos($view->$textfield, $search) !== false) {
                    $view->$textfield = str_replace($search, $replace, $view->$textfield);
                    $changed = true;
                }
            }
            if ($changed) {
                // Invalidate the serialized pattern cache so it is re-derived from the new tags.
                $view->patterns = null;
                $DB->update_record('datalynx_views', $view);
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
