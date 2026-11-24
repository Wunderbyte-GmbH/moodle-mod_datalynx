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

namespace mod_datalynx\local\field;

use coding_exception;
use dml_exception;
use mod_datalynx;
use mod_datalynx\datalynx;
use mod_datalynx\local\definition_cache;
use mod_datalynx\local\rule\match_compiler;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Base class for Datalynx Field Types
 * @package mod_datalynx
 * @copyright 2025 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class datalynxfield_base {
    /**
     * Subclasses must override the type with their name.
     *
     * @var string Fieldtype usually datalynxfield_fieldtype.
     */
    public $type = 'unknown';

    /**
     * The datalynx object that this field belongs to.
     *
     * @var datalynx
     */
    public $dlx = null;

    /**
     * The field object itself, if we know it
     *
     * @var object
     */
    public $field = null;

    /**
     * @var datalynxfield_renderer
     */
    protected $renderer = null;

    /**
     * @var array
     */
    protected $distinctvalues = null;

    /**
     * Can this field be used in fieldgroups?
     * Only fields where user can enter data via a form can be used in a fieldgroup.
     * Override if yes.
     *
     * @var bool
     */
    protected $forfieldgroup = false;

    /**
     * Class constructor
     *
     * TODO: $dlx should always be an int value.
     *
     * @param number $dlx datalynx id or class object
     * @param number $field fieldid or fieldobject
     */
    public function __construct($dlx = 0, $field = 0) {
        if (empty($dlx)) {
            throw new coding_exception('Datalynx id or object must be passed to view constructor.');
        } else {
            if ($dlx instanceof \mod_datalynx\datalynx) {
                $this->dlx = $dlx;
            } else {
                // Construct the datalynx object from the integer id.
                $this->dlx = new mod_datalynx\datalynx($dlx);
            }
        }

        if (!empty($field)) {
            // Variable field is the field record.
            if (is_object($field)) {
                $this->field = $field; // Programmer knows what they are doing, we hope.
                // Variable $field is a field id.
            } else {
                if ($fieldobj = $this->dlx->get_field_from_id($field)) {
                    $this->field = $fieldobj->field;
                } else {
                    throw new moodle_exception('invalidfield', 'datalynx', null, null, $field);
                }
            }
        }

        if (empty($this->field)) {
            // We need to define some default values.
            $this->set_field();
        }
    }

    /**
     * Sets up a field object
     *
     * @param stdClass $forminput
     */
    public function set_field($forminput = null): void {
        $this->field = new stdClass();
        $this->field->id = !empty($forminput->id) ? $forminput->id : 0;
        $this->field->type = $this->type;
        $this->field->dataid = $this->dlx->id();
        $this->field->name = !empty($forminput->name) ? trim($forminput->name) : '';
        $this->field->description = !empty($forminput->description) ? trim($forminput->description) : '';
        $this->field->label = !empty($forminput->label) ? $forminput->label : '';
        for ($i = 1; $i <= 10; $i++) {
            $this->field->{"param$i"} = !empty($forminput->{"param$i"}) ? trim(
                $forminput->{"param$i"}
            ) : null;
        }
    }

    /**
     * Insert a new field in the database
     *
     * @param ?stdClass $fromform Form data.
     * @return int|false
     */
    public function insert_field($fromform = null): int|false {
        global $DB, $OUTPUT;

        if (!empty($fromform)) {
            $this->set_field($fromform);
        }

        if (!$this->field->id = $DB->insert_record('datalynx_fields', $this->field)) {
            echo $OUTPUT->notification('Insertion of new field failed!');
            return false;
        } else {
            definition_cache::invalidate((int) $this->field->dataid);
            return $this->field->id;
        }
    }

    /**
     * Update a field in the database
     *
     * @param ?stdClass $fromform Form data.
     * @return bool
     */
    public function update_field($fromform = null): bool {
        global $DB, $OUTPUT;
        $oldname = $this->field->name;
        if (!empty($fromform)) {
            $this->set_field($fromform);
        }
        $newname = $this->field->name;

        if (!$DB->update_record('datalynx_fields', $this->field)) {
            echo $OUTPUT->notification('updating of field failed!');
            return false;
        }
        definition_cache::invalidate((int) $this->field->dataid);

        if ($oldname !== '' && $oldname !== $newname) {
            $this->dlx->replace_field_in_views($oldname, $newname);
            $this->dlx->replace_field_in_filters($oldname, $newname);
        }

        return true;
    }

    /**
     * Delete a field completely
     * @return bool
     */
    public function delete_field(): bool {
        global $DB;

        if (!empty($this->field->id)) {
            if ($filearea = $this->filearea()) {
                $fs = get_file_storage();
                $fs->delete_area_files($this->dlx->context->id, 'mod_datalynx', $filearea);
            }
            $this->delete_content();
            $DB->delete_records('datalynx_fields', ['id' => $this->field->id]);
            definition_cache::invalidate((int) $this->field->dataid);
        }
        return true;
    }

    /**
     * Getter
     *
     * @param string $var Property name.
     * @return mixed
     */
    public function get($var) {
        if (isset($this->field->$var)) {
            return $this->field->$var;
        } else {
            // Returns false if the property does not exist on the field object.
            return false;
        }
    }

    /**
     * Returns the field id
     *
     * @return int|string
     */
    public function id(): int|string {
        return $this->field->id;
    }

    /**
     * Returns the field type
     *
     * @return string
     */
    public function type(): string {
        return $this->type;
    }

    /**
     * Returns the name of the field
     *
     * @return string
     */
    public function name(): string {
        return $this->field->name;
    }

    /**
     * Returns the type name of the field
     *
     * @return string
     */
    public function typename(): string {
        return get_string('pluginname', "datalynxfield_{$this->type}");
    }

    /**
     * Returns an icon representing this field type using Font Awesome classes.
     *
     * @return string HTML for the icon
     */
    public function image(): string {
        $iconmap = [
            'approve'                      => 'fa-solid fa-circle-check',
            'checkbox'                     => 'fa-solid fa-square-check',
            'comment'                      => 'fa-solid fa-comment',
            'coursegroup'                  => 'fa-solid fa-sitemap',
            'datalynxview'                 => 'fa-solid fa-eye',
            'duration'                     => 'fa-solid fa-hourglass-half',
            'editor'                       => 'fa-solid fa-pen-fancy',
            'entry'                        => 'fa-solid fa-database',
            'entryauthor'                  => 'fa-solid fa-user',
            'entrygroup'                   => 'fa-solid fa-users',
            'entryteammemberprofilefield'  => 'fa-solid fa-address-card',
            'entrytime'                    => 'fa-solid fa-calendar-days',
            'fieldgroup'                   => 'fa-solid fa-folder',
            'file'                         => 'fa-solid fa-file',
            'gradeitem'                    => 'fa-solid fa-graduation-cap',
            'identifier'                   => 'fa-solid fa-fingerprint',
            'multiselect'                  => 'fa-solid fa-list-check',
            'number'                       => 'fa-solid fa-hashtag',
            'picture'                      => 'fa-solid fa-image',
            'radiobutton'                  => 'fa-solid fa-circle-dot',
            'rating'                       => 'fa-solid fa-star',
            'select'                       => 'fa-solid fa-chevron-down',
            'status'                       => 'fa-solid fa-flag',
            'tag'                          => 'fa-solid fa-tag',
            'teammemberselect'             => 'fa-solid fa-people-group',
            'text'                         => 'fa-solid fa-font',
            'textarea'                     => 'fa-solid fa-align-left',
            'time'                         => 'fa-solid fa-clock',
            'url'                          => 'fa-solid fa-link',
            'youtube'                      => 'fa-brands fa-youtube',
        ];
        $classes = $iconmap[$this->type] ?? 'fa-solid fa-question-circle';
        return \html_writer::tag('i', '', ['class' => $classes, 'aria-hidden' => 'true']);
    }

    /**
     * Returns the datalynx instance this field belongs to.
     *
     * @return datalynx
     */
    public function dlx(): datalynx {
        return $this->dlx;
    }

    /**
     * Returns the edit form for this field type.
     *
     * @return \mod_datalynx\form\datalynxfield_form
     */
    public function get_form(): \mod_datalynx\form\datalynxfield_form {
        if (class_exists('datalynxfield_' . $this->type . '\form')) {
            $formclass = 'datalynxfield_' . $this->type . '\form';
        } else {
            $formclass = 'mod_datalynx\form\datalynxfield_form';
        }
        $actionurl = new moodle_url(
            '/mod/datalynx/field/field_edit.php',
            ['d' => $this->dlx->id(), 'fid' => $this->id(), 'type' => $this->type]
        );
        return new $formclass($this, $actionurl);
    }

    /**
     * Is this field available for fieldgroups?
     *
     * @return bool
     */
    public function for_use_in_fieldgroup(): bool {
        return $this->forfieldgroup;
    }

    /**
     * Returns the field record as a form data object.
     *
     * @return stdClass
     */
    public function to_form(): stdClass {
        return $this->field;
    }

    /**
     *
     * @return datalynxfield_renderer
     */
    public function renderer(): datalynxfield_renderer {
        if (!$this->renderer) {
            $rendererclass = "datalynxfield_{$this->type}\\renderer";
            $this->renderer = new $rendererclass($this);
        }
        return $this->renderer;
    }

    /**
     * @var array Default render options applied when displaying field patterns.
     */
    protected static $defaultoptions = ['manage' => false, 'visible' => false, 'edit' => false,
            'editable' => false, 'disabled' => false, 'required' => false, 'internal' => false,
    ];

    // CONTENT MANAGEMENT.

    /**
     * Get definition of tags/pattern
     *
     * @param array $tags
     * @param stdClass $entry
     * @param array $options
     * @return array|null
     */
    public function get_definitions($tags, $entry, array $options): ?array {
        return $this->renderer()->replacements(
            $tags,
            $entry,
            array_merge(self::$defaultoptions, $options)
        );
    }

    /**
     * Returns whether this field is an internal (non-user-editable) field.
     *
     * @return bool
     */
    public static function is_internal(): bool {
        return false;
    }

    /**
     * Whether this field's value is locked (read-only) for the given entry.
     *
     * Field types can override this to freeze a value once it has been saved. The renderer uses it
     * to force the field not-editable. The default is no lock.
     *
     * @param stdClass $entry The entry record (as loaded for rendering).
     * @return bool
     */
    public function is_locked_for_entry($entry): bool {
        return false;
    }

    /**
     * Update the content of a field in an entry. That happens in table datalynx_conents.
     *
     * @param stdClass $entry
     * @param ?array $values
     * @return int|string|bool
     * @throws dml_exception
     */
    public function update_content(stdClass $entry, ?array $values = null): int|string|bool {
        global $DB;
        $fieldid = $this->field->id;
        $fieldid = $this->field->id;
        $contentid = isset($entry->{"c{$fieldid}_id"}) ? $entry->{"c{$fieldid}_id"} : null;

        // If the field was not submitted at all (e.g. a disabled input is not sent by the browser),
        // $values is an empty array []. Preserve the existing content in that case.
        // Note: a field submitted with an empty value produces ['' => ''] (non-empty array),
        // which correctly bypasses this guard and proceeds to clear the stored content.
        if (is_array($values) && empty($values) && !is_null($contentid)) {
            return true;
        }

        [$contents, $oldcontents] = $this->format_content($entry, $values);

        $rec = new stdClass();
        $rec->fieldid = $this->field->id;
        $rec->entryid = $entry->id;
        foreach ($contents as $key => $content) {
            $c = $key ? $key : '';
            $rec->{"content$c"} = $content;
        }

        // Note: when the first value in a list is empty the insert is skipped and ordering may be wrong.

        // Insert only if no old contents and there is new contents.
        if (is_null($contentid) && !empty($contents)) {
            return $DB->insert_record('datalynx_contents', $rec);
        }

        // Note: only the specific content row is deleted, not all content for the entry id.
        if (!is_null($contentid) && empty($contents)) {
            return $this->delete_content($entry->id);
        }

        // Update if new is different from old.
        if (!is_null($contentid) && !self::same_contents($contents, $oldcontents)) {
            $rec->id = $contentid; // MUST_EXIST.
            $DB->update_record('datalynx_contents', $rec);
            return $rec->id;
        }
        return true;
    }

    /**
     * Whether new contents equal the stored ones, column by column, as they are stored.
     *
     * Submitted values are typed (an int from the input contract, a float, NULL), stored ones are
     * strings or NULL; a value counts as unchanged when both read the same as stored text, NULL and
     * '' alike. Every update_content() decides "unchanged" with this, so that saving an entry again
     * reports no change and rules that wait for a change do not run.
     *
     * @param array $contents new contents by column index (0 = content, 1 = content1, ...)
     * @param array|null $oldcontents stored contents by column index
     * @return bool
     */
    public static function same_contents(array $contents, ?array $oldcontents): bool {
        foreach ($contents as $key => $content) {
            if ((string) ($content ?? '') !== (string) ($oldcontents[$key] ?? '')) {
                return false;
            }
        }
        return true;
    }

    /**
     * The stored contents of the field in an entry, by column index, as loaded with the entry.
     *
     * @param stdClass $entry
     * @return array
     */
    protected function get_stored_contents(stdClass $entry): array {
        $fieldid = $this->field->id;
        $stored = [];
        foreach ($this->get_content_parts() as $index => $column) {
            $stored[$index] = $entry->{"c{$fieldid}_{$column}"} ?? null;
        }
        return $stored;
    }

    /**
     * Delete all content associated with the field.
     *
     * @param int $entryid
     * @return bool
     * @throws dml_exception
     */
    public function delete_content($entryid = 0): bool {
        global $DB;

        if ($entryid) {
            $params = ['fieldid' => $this->field->id, 'entryid' => $entryid];
        } else {
            $params = ['fieldid' => $this->field->id];
        }

        $rs = $DB->get_recordset('datalynx_contents', $params);
        if ($rs->valid()) {
            $fs = get_file_storage();
            foreach ($rs as $content) {
                $fs->delete_area_files(
                    $this->dlx->context->id,
                    'mod_datalynx',
                    'content',
                    $content->id
                );
            }
        }
        $rs->close();

        return $DB->delete_records('datalynx_contents', $params);
    }

    /**
     * Returns an array of distinct content of the field (GROUP BY)
     *
     * @param int $sortdir
     * @return array
     * @throws dml_exception
     */
    public function get_distinct_content($sortdir = 0): array {
        global $DB;

        if (is_null($this->distinctvalues)) {
            $this->distinctvalues = [];
            $fieldid = $this->field->id;
            $sortdir = $sortdir ? 'DESC' : 'ASC';
            $contentname = $this->get_sort_sql();
            $sql = "SELECT DISTINCT $contentname
            FROM {datalynx_contents} c$fieldid
            WHERE c$fieldid.fieldid = $fieldid AND $contentname IS NOT NULL
            ORDER BY $contentname $sortdir";

            if ($options = $DB->get_records_sql($sql)) {
                foreach ($options as $data) {
                    $value = isset($data->content) ? $data->content : '';
                    if ($value === '') {
                        continue;
                    }
                    $this->distinctvalues[] = $value;
                }
            }
        }
        return $this->distinctvalues;
    }

    /**
     * Prepares content from uploaded csv files to be saved to DB
     *
     * @param array $data
     * @param array $importsettings
     * @param ?array $csvrecord
     * @param ?int $entryid
     * @return bool
     */
    public function prepare_import_content(&$data, $importsettings, ?array $csvrecord = null, ?int $entryid = null): bool {
        $fieldid = $this->field->id;
        $fieldname = $this->name();
        // Internal fields use a different import settings structure.
        if ($this->is_internal()) {
            $setting = reset($importsettings);
            $csvname = $setting['name'];
        } else {
            $csvname = $importsettings[$fieldname]['name'];
        }

        if (isset($csvrecord[$csvname]) && $csvrecord[$csvname] !== '') {
            $data->{"field_{$fieldid}_{$entryid}"} = $csvrecord[$csvname];
        }

        return true;
    }

    /**
     * Retrieve the submitted form data for a specific field
     * and return as array indexed by contentname
     *
     * @param number $entryid
     * @param stdClass $data submitted form data
     * @return array with
     */
    public function get_content_from_data($entryid, $data): array {
        $fieldid = $this->field->id;
        $content = [];
        $fieldgroups = [];
        foreach ($data as $key => $value) {
            if (strpos($key, 'fieldgroup_') === 0) {
                $fieldgroups[$value] = $key;
            }

            if (
                preg_match(
                    "/^field_{$fieldid}_{$entryid}_fieldgroup_(\\d+)_\\d+(?:_|$)/",
                    $key,
                    $matches
                )
            ) {
                $fieldgroups[$matches[1]] = "fieldgroup_{$matches[1]}";
            }
        }

        // Keeping this for backwards compatibility.
        foreach ($this->content_names() as $name) {
            // Add normal fields.
            $delim = $name ? '_' : '';
            $contentname = "field_{$fieldid}_$entryid" . $delim . $name;
            if (isset($data->$contentname)) {
                $content[$name] = $data->$contentname;
            }
            // Add fieldgroup fields.
            foreach ($fieldgroups as $fieldgroup) {
                // Search for fieldgroup.
                $contentname = "field_{$fieldid}_$entryid" . "_" . $fieldgroup . "_0" . $delim . $name;
                $i = 0;
                while (isset($data->$contentname)) {
                    $content[$fieldgroup] = true;
                    $content[$contentname] = $data->$contentname;
                    $i++;
                    $contentname = "field_{$fieldid}_$entryid" . "_" . $fieldgroup . "_" . $i . $delim . $name;
                }
            }
        }

        return $content;
    }

    /**
     * This function should be overriden in each field class which extends this base class.
     * If a field has more than one form element where user content is expected to be submitted
     * all of these elements have to be specified here
     * Example: Field of type file has these form elements: 'filemanager', 'alttext', 'delete',
     * 'editor'
     * So these values have to be returned like that: return array('filemanager', 'alttext',
     * 'delete', 'editor');
     *
     * @return array of strings
     */
    protected function content_names(): array {
        return [''];
    }

    /**
     * Formats content for database storage
     *
     * @param stdClass $entry stdClass object containing all the entry contents (from the database, NOT the
     *        form!)
     * @param ?array $values values from the entry form elements
     * @return array
     */
    protected function format_content(stdClass $entry, ?array $values = null): array {
        $fieldid = $this->field->id;

        $newcontent = null;
        $oldcontent = null;

        if (!empty($values)) {
            $resetted = reset($values);
            // A "0" is a value; only nothing is stored as ''.
            $newcontent = ($resetted === null || $resetted === false || $resetted === []) ? '' : $resetted;
            if (!empty($newcontent) && is_array($newcontent)) {
                // When no value ist selected, then a default value is saved by Quickform. Value is removed here:.
                if (($key = array_search('_qf__force_multiselect_submission', $newcontent)) !== false) {
                    unset($newcontent[$key]);
                }
                $newcontent = implode(",", $newcontent);
            }
            $newcontent = (string) clean_param($newcontent, PARAM_NOTAGS);
        }

        if (isset($entry->{"c{$fieldid}_content"})) {
            $oldcontent = $entry->{"c{$fieldid}_content"};
        }

        return [[$newcontent], [$oldcontent]];
    }

    /**
     * Checks whether the field supports 'group by' filtering option
     *
     * @return bool true if 'group by' is supported, false otherwise
     */
    public function supports_group_by(): bool {
        return false;
    }

    /**
     * Checks whether this field may be used as a search/filter criterion.
     *
     * Data-bearing fields are searchable by default. Pure action/display fields that carry no
     * content - such as the submit and cancel button fields - override this to return false so
     * they are excluded from the filter search field selectors.
     *
     * @return bool true if the field can be filtered/searched, false otherwise
     */
    public function supports_search(): bool {
        return true;
    }

    /**
     * Checks whether entries may be sorted by this field.
     *
     * Data-bearing fields are sortable by default. Pure action/display fields that carry no
     * content - such as the submit and cancel button fields - override this to return false so
     * they are excluded from the filter sort field selectors.
     *
     * @return bool true if entries can be ordered by this field, false otherwise
     */
    public function supports_sort(): bool {
        return true;
    }

    /**
     * To be overriden by classes, that extend the field base class
     * This returns all the column names of the columns used to save content of one specific field
     * in the table "datalynx_contents".
     * Values can be 'content', 'content1', 'content2', until 'content4'
     *
     * @return array of strings
     */
    public function get_content_parts(): array {
        return ['content'];
    }

    /**
     * Get the select part of the sql query.
     *
     * @return string
     */
    public function get_select_sql(): string {
        if (is_numeric($this->field->id) && $this->field->id > 0) {
            $arr = [];
            $arr[] = " c{$this->field->id}.id AS c{$this->field->id}_id ";
            foreach ($this->get_content_parts() as $part) {
                $arr[] = $this->get_sql_compare_text($part) . " AS c{$this->field->id}_$part";
            }
            $selectsql = implode(',', $arr);
            return " $selectsql ";
        } else {
            return '';
        }
    }

    /**
     * Get the sort part of the sql query.
     *
     * @param string $paramname
     * @param string $paramcount
     * @return array|null
     */
    public function get_sort_from_sql($paramname = 'sortie', $paramcount = ''): ?array {
        $fieldid = $this->field->id;
        if (is_numeric($fieldid) && $fieldid > 0) {
            // Use a subquery with GROUP BY to ensure at most one row per entry.
            // A direct JOIN on datalynx_contents can produce multiple rows for multi-value
            // (fieldgroup) fields, causing duplicate e.id values that break SELECT DISTINCT.
            $sql = " LEFT JOIN (SELECT MIN(id) AS id, entryid,
                                       MIN(content) AS content,
                                       MIN(content1) AS content1,
                                       MIN(content2) AS content2,
                                       MIN(content3) AS content3,
                                       MIN(content4) AS content4
                                FROM {datalynx_contents}
                                WHERE fieldid = :$paramname$paramcount
                                GROUP BY entryid) c$fieldid
                    ON c$fieldid.entryid = e.id";
            return [$sql, $fieldid];
        } else {
            return null;
        }
    }

    /**
     * Returns the SQL fragment used for sorting by this field.
     *
     * @return string
     */
    public function get_sort_sql(): string {
        return $this->get_sql_compare_text();
    }

    /**
     * An expression to sort by instead of the stored text, or null to sort by the text.
     *
     * It is selected as c{fieldid}_sort, so it may use the joined content c{fieldid}.content.
     *
     * @return string|null
     */
    public function get_sort_select_sql(): ?string {
        return null;
    }

    /**
     * Returns the SQL JOIN fragment used when searching by this field.
     *
     * @return string
     */
    public function get_search_from_sql(): string {
        $fieldid = $this->field->id;
        if (is_numeric($fieldid) && $fieldid > 0) {
            return " LEFT JOIN {datalynx_contents} c$fieldid ON c$fieldid.entryid = e.id AND c$fieldid.fieldid = $fieldid ";
        } else {
            return '';
        }
    }

    /**
     * Create the SQL query based on different params.
     *
     * @param array $search
     * @return array $fieldsql, $fieldparams, $fromcontent or empty array
     */
    public function get_search_sql(array $search): array {
        global $DB;

        [$not, $operator, $value] = $search;

        static $i = 0;
        $i++;
        $fieldid = $this->field->id;
        $name = "df_{$fieldid}_{$i}";

        // For all NOT criteria except NOT Empty, exclude entries.
        // Which don't meet the positive criterion.
        // Because some fields may not have content records.
        // And the respective entries may be filter out.
        // Despite meeting the criterion.
        $excludeentries = (($not && $operator !== '') || (!$not && $operator === ''));

        if ($excludeentries) {
            $varcharcontent = $DB->sql_compare_text('content');
        } else {
            $varcharcontent = $this->get_sql_compare_text();
        }

        if ($operator === '') {
            // Stated positively, "holds a value"; the exclusion turns it into "holds none".
            [$sql, $params] = $this->get_has_value_sql(!$excludeentries, "df_{$fieldid}_");
        } else {
            if ($operator === '=') {
                $searchvalue = trim($value);
                [$sql, $params] = $DB->get_in_or_equal(
                    $searchvalue,
                    SQL_PARAMS_NAMED,
                    "df_{$fieldid}_"
                );
                $sql = " $varcharcontent $sql ";
            } else {
                if ($operator === 'IN') {
                    $searchvalue = array_map('trim', $value);
                    [$sql, $params] = $DB->get_in_or_equal(
                        $searchvalue,
                        SQL_PARAMS_NAMED,
                        "df_{$fieldid}_"
                    );
                    $sql = " $varcharcontent $sql ";
                } else {
                    if (in_array($operator, ['LIKE', 'BETWEEN', ''])) {
                        $params = [$name => "%$value%"];
                        $sql = $DB->sql_like($varcharcontent, ":$name", false);
                    } else {
                        // The operator originates from the usearch request parameter. Only ever
                        // build SQL from a vetted literal from this allowlist; any other value is
                        // treated as an impossible criterion (empty fragment, skipped by the
                        // caller) so it can never be concatenated into the query.
                        $allowedoperators = ['=', '!=', '<>', '<', '<=', '>', '>='];
                        $operatorkey = array_search($operator, $allowedoperators, true);
                        if ($operatorkey === false) {
                            return ['', [], false];
                        }
                        $safeoperator = $allowedoperators[$operatorkey];
                        $params = [$name => $value];
                        $sql = " $varcharcontent $safeoperator :$name ";
                    }
                }
            }
        }

        if ($excludeentries) {
            // Get entry ids for entries that meet the criterion.
            if ($eids = $this->get_entry_ids_for_content($sql, $params)) {
                // Get NOT IN sql.
                [$notinids, $params] = $DB->get_in_or_equal(
                    $eids,
                    SQL_PARAMS_NAMED,
                    "df_{$fieldid}_",
                    false
                );
                $sql = " e.id $notinids ";
                return [$sql, $params, false];
            } else {
                return ['', [], false];
            }
        } else {
            return [$sql, $params, true];
        }
    }

    /**
     * The stored contents that mean "no value" for this field type.
     *
     * A missing content row means no value, but saving used to store the empty export of an
     * element instead ('0' for a date, '[]' for a member list). The search for "empty" and the
     * cleanup on saving treat these as no value.
     *
     * @return string[]
     */
    public static function get_empty_contents(): array {
        return [''];
    }

    /**
     * Whether a stored content means "no value" ({@see self::get_empty_contents()}).
     *
     * @param string|null $content
     * @return bool
     */
    public function is_stored_content_empty(?string $content): bool {
        return $content === null || in_array($content, static::get_empty_contents(), true);
    }

    /**
     * Whether a stored content row holds no value of the field.
     *
     * The value is in the content column for almost every field type; a type that keeps it in
     * another column as well overrides this (the course group field keeps the group in content1).
     *
     * @param stdClass $row datalynx_contents record with content and content1..content4
     * @return bool
     */
    public static function is_stored_row_empty(stdClass $row): bool {
        return ($row->content ?? null) === null || in_array($row->content, static::get_empty_contents(), true);
    }

    /**
     * SQL stating that the content holds a value: it is neither NULL nor an empty content.
     *
     * The stored text is compared, not a cast of it: the empty contents need not be numbers.
     *
     * @param bool $joined true for the content joined to the entries query (c<fieldid>.content),
     *                     false for a query on datalynx_contents itself (content)
     * @param string $prefix prefix of the named parameters
     * @return array [sql, params]
     */
    protected function get_has_value_sql(bool $joined, string $prefix): array {
        global $DB;
        $raw = $joined ? "c{$this->field->id}.content" : 'content';
        $column = $DB->sql_compare_text($raw);
        [$insql, $params] = $DB->get_in_or_equal(static::get_empty_contents(), SQL_PARAMS_NAMED, $prefix, false);
        return [" ($raw IS NOT NULL AND $column $insql) ", $params];
    }

    /**
     * Remove the content row of an entry if it holds no value.
     *
     * Only a single row is removed: several rows are the lines of a fieldgroup, which are matched
     * up by their order and must keep their places.
     *
     * @param int $entryid
     * @return bool whether a row was removed
     */
    public function drop_empty_content(int $entryid): bool {
        global $DB;

        $rows = $DB->get_records('datalynx_contents', ['fieldid' => $this->id(), 'entryid' => $entryid]);
        if (count($rows) !== 1) {
            return false;
        }
        $row = reset($rows);
        if (!$this->is_stored_content_empty($row->content) || !static::is_stored_row_empty($row)) {
            return false;
        }
        get_file_storage()->delete_area_files($this->dlx->context->id, 'mod_datalynx', 'content', $row->id);
        $DB->delete_records('datalynx_contents', ['id' => $row->id]);
        return true;
    }

    /**
     * Delete the stored content rows that hold only their field's empty content.
     *
     * Used by the upgrade migration and by restore (after_restore). Kept are: the rows of file
     * fields, which carry the files; the rows of fieldgroup subfields, whose lines are matched up by
     * their order; and every row of an entry that has several rows for the field. Idempotent.
     *
     * @param int|null $dataid Restrict to one datalynx instance, or null for all instances.
     * @return int Number of rows deleted.
     */
    public static function delete_stored_empty_contents(?int $dataid = null): int {
        global $DB;

        $conditions = $dataid !== null ? ['dataid' => $dataid] : [];
        $fields = $DB->get_records('datalynx_fields', $conditions, 'id', 'id, dataid, type, param1');
        $subfields = [];
        foreach ($fields as $field) {
            if ($field->type === 'fieldgroup') {
                $entries = json_decode((string) $field->param1, true);
                foreach (\datalynxfield_fieldgroup\field::normalise_subfields(is_array($entries) ? $entries : []) as $entry) {
                    $subfields[(int) explode(':', $entry, 2)[0]] = true;
                }
            }
        }

        $deleted = 0;
        $content = $DB->sql_compare_text('c.content');
        foreach ($fields as $field) {
            $class = "datalynxfield_{$field->type}\\field";
            if (
                isset($subfields[(int) $field->id]) || !class_exists($class) || $class::is_internal()
                || is_a($class, \datalynxfield_file\field::class, true)
                || is_a($class, \datalynxfield_fieldgroup\field::class, true)
            ) {
                continue;
            }
            [$insql, $params] = $DB->get_in_or_equal($class::get_empty_contents(), SQL_PARAMS_NAMED, 'empty');
            $params['fieldid'] = $field->id;
            $rows = $DB->get_records_sql(
                "SELECT c.*
                   FROM {datalynx_contents} c
                  WHERE c.fieldid = :fieldid
                    AND (c.content IS NULL OR $content $insql)
                    AND (SELECT COUNT(1) FROM {datalynx_contents} c2
                          WHERE c2.fieldid = c.fieldid AND c2.entryid = c.entryid) = 1",
                $params
            );
            // The field decides on the whole row: a value may sit in another column.
            $ids = array_keys(array_filter($rows, fn($row) => $class::is_stored_row_empty($row)));
            if (!$ids) {
                continue;
            }
            $cm = get_coursemodule_from_instance('datalynx', $field->dataid);
            foreach (array_chunk($ids, 500) as $chunk) {
                if ($cm) {
                    [$filesql, $fileparams] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
                    get_file_storage()->delete_area_files_select(
                        \context_module::instance($cm->id)->id,
                        'mod_datalynx',
                        'content',
                        $filesql,
                        $fileparams
                    );
                }
                $DB->delete_records_list('datalynx_contents', 'id', $chunk);
                $deleted += count($chunk);
            }
        }
        return $deleted;
    }

    /**
     * Search criteria of a field whose stored text is a number, compared as numbers.
     *
     * The stored text would compare "10" < "9"; a cast in SQL fails on content that is no number.
     * So the entries are matched on the stored values: '' (empty) by SQL, '=', '>', '>=', '<',
     * '<=' and BETWEEN (exclusive bounds) by number. NOT selects the other entries, entries
     * without a value included.
     *
     * @param array $search [not, operator, value]; value is a list of bounds
     * @return array SQL fragment, params, and join flag
     */
    protected function get_numeric_search_sql(array $search): array {
        global $DB;

        [$not, $operator, $value] = $search;

        static $i = 0;
        $i++;
        $fieldid = $this->field->id;
        $name = "df_{$fieldid}_{$i}";

        // For all NOT criteria except NOT Empty, exclude entries which don't meet the positive criterion.
        $excludeentries = (($not && $operator !== '') || (!$not && $operator === ''));

        if ($operator === '') {
            // Stated positively, "holds a value"; the exclusion turns it into "holds none".
            [$sql, $params] = $this->get_has_value_sql(!$excludeentries, "df_{$fieldid}_");
            if (!$excludeentries) {
                return [$sql, $params, true];
            }
            $eids = $this->get_entry_ids_for_content($sql, $params);
            if (!$eids) {
                return ['', [], false];
            }
            [$notin, $params] = $DB->get_in_or_equal($eids, SQL_PARAMS_NAMED, "df_{$fieldid}_", false);
            return [" e.id $notin ", $params, false];
        }

        // Numbers are compared as numbers: the stored text would compare "10" < "9".
        $bounds = array_map(fn($bound) => self::to_number((string) $bound), array_values((array) $value));
        if ($bounds === [] || in_array(null, $bounds, true)) {
            return ['', [], false];
        }
        $matches = match ($operator) {
            '=' => fn(float $number): bool => $number == $bounds[0],
            '>' => fn(float $number): bool => $number > $bounds[0],
            '>=' => fn(float $number): bool => $number >= $bounds[0],
            '<' => fn(float $number): bool => $number < $bounds[0],
            '<=' => fn(float $number): bool => $number <= $bounds[0],
            'BETWEEN' => count($bounds) === 2
                ? fn(float $number): bool => $number > $bounds[0] && $number < $bounds[1]
                : null,
            default => null,
        };
        if ($matches === null) {
            return ['', [], false];
        }

        $eids = $this->get_entry_ids_with_number($matches);
        if (!$eids) {
            return [$not ? '' : ' 1 = 0 ', [], false];
        }
        [$insql, $params] = $DB->get_in_or_equal($eids, SQL_PARAMS_NAMED, "{$name}_", !$not);
        return [" e.id $insql ", $params, false];
    }

    /**
     * A stored or searched value as a number: also with a decimal comma; null when it is none.
     *
     * @param string|null $content
     * @return float|null
     */
    public static function to_number(?string $content): ?float {
        $content = str_replace(',', '.', trim((string) $content));
        return is_numeric($content) ? (float) $content : null;
    }

    /**
     * The entries whose value of this field is a number that matches.
     *
     * @param callable $matches float => bool
     * @return int[]
     */
    protected function get_entry_ids_with_number(callable $matches): array {
        global $DB;
        $entryids = [];
        $rows = $DB->get_recordset('datalynx_contents', ['fieldid' => $this->field->id], '', 'id, entryid, content');
        foreach ($rows as $row) {
            $number = self::to_number($row->content);
            if ($number !== null && $matches($number)) {
                $entryids[(int) $row->entryid] = (int) $row->entryid;
            }
        }
        $rows->close();
        return array_values($entryids);
    }


    /**
     * Get the entry ids for the specific sql using the field id and the field value.
     *
     * @param string $sql
     * @param array $params
     * @return array
     */
    protected function get_entry_ids_for_content(string $sql, array $params): array {
        global $DB;
        $sql = " fieldid = :fieldid AND $sql ";
        $params['fieldid'] = $this->id();
        return $DB->get_records_select_menu('datalynx_contents', $sql, $params, '', 'id,entryid');
    }

    /**
     * Extracts the search value for this field from submitted form data.
     *
     * @param stdClass $formdata
     * @param int $i
     * @return mixed
     */
    public function parse_search($formdata, $i) {
        $fieldid = $this->field->id;
        if (!empty($formdata->{"f_{$i}_$fieldid"})) {
            return $formdata->{"f_{$i}_$fieldid"};
        } else {
            return false;
        }
    }

    /**
     * The search criteria a custom filter value of this field stands for (mod_datalynx#270).
     *
     * The one rule per field type: the custom filter form of the web and the Moodle App both
     * hand their values to {@see \mod_datalynx\local\filter\customfilter_search::criteria()},
     * which asks the field. The value is in the shape
     * {@see \mod_datalynx\local\filter\customfilter_controls::values()} reads it for the
     * field's control. By default that is a list of ids (single choice, team members): any of
     * them, ignoring the empty choice (-1).
     *
     * @param mixed $value
     * @return array list of [not, operator, value]; empty when the value sets no condition
     */
    public function get_customfilter_criteria($value): array {
        if ($this->has_composite_search()) {
            return is_array($value) && $value ? [['', '', $value]] : [];
        }
        $ids = array_values(array_unique(array_filter(
            array_map('intval', is_array($value) ? $value : ($value === null || $value === '' ? [] : [$value])),
            fn(int $id) => $id > 0
        )));

        return $ids ? [['', 'ANY_OF', $ids]] : [];
    }

    /**
     * Whether one search criterion of this field is spread over several form elements.
     *
     * Most fields put their whole search value in a single element named
     * `f_{$i}_{$fieldid}`, so a customfilter can read it straight off the submitted
     * data. A field that renders several elements (e.g. a from/to pair plus hidden
     * coordinates) has to be reassembled by its own {@see parse_search()} instead.
     *
     * @return bool
     */
    public function has_composite_search(): bool {
        return false;
    }

    /**
     * The search value of a composite search from the inputs of a custom filter control.
     *
     * For the clients that send the control's inputs ({key}_{part}, see customfilter_controls)
     * rather than the field's own web search elements: the Moodle App. Returns the same shape as
     * {@see parse_search()}, or null when the inputs hold no search.
     *
     * @param array $inputs input name without the custom filter prefix => value
     * @param string $key the field's key in the inputs (its id)
     * @return array|null
     */
    public function customfilter_value(array $inputs, string $key): ?array {
        return null;
    }

    /**
     * What a custom filter control of a composite search shows besides its name: the values
     * entered, the choices offered and notes on what was matched.
     *
     * @param array $inputs input name without the custom filter prefix => value
     * @param string $key the field's key in the inputs (its id)
     * @return array merged into the control, see customfilter_controls::describe()
     */
    public function customfilter_describe(array $inputs, string $key): array {
        return [];
    }

    /**
     * Check a composite search value that came from outside (a web service, the app).
     *
     * The shape is checked by search_criteria; this checks the meaning of the parts, e.g. that a
     * latitude is one. Parts the field does not know are left as they are.
     *
     * @param array $value the named parts, as strings
     * @return array the value to search with
     * @throws \invalid_parameter_exception
     */
    public function clean_composite_search(array $value): array {
        return $value;
    }

    /**
     * Formats the search parameters for display.
     *
     * @param array $searchparams
     * @return string|false|null
     */
    public function format_search_value($searchparams): string|false|null {
        [$not, $operator, $value] = $searchparams;
        return $not . ' ' . $operator . ' ' . $value;
    }

    /**
     * Normalises and returns the search value ready for SQL comparison.
     *
     * @param mixed $value
     * @return mixed
     */
    public function get_search_value($value) {
        return $value;
    }

    /**
     * Returns the SQL fragment for comparing the field content column.
     *
     * @param string $column
     * @return string
     */
    protected function get_sql_compare_text(string $column = 'content'): string {
        global $DB;
        return $DB->sql_compare_text("c{$this->field->id}.$column");
    }

    /**
     * Whether this field provides join sql for fetching content
     *
     * @return bool
     */
    public function use_join(): bool {
        return false;
    }

    /**
     * Whether this field content resides in datalynx_contents
     *
     * @return bool
     */
    public function is_datalynx_content(): bool {
        return true;
    }

    /**
     * Return file area of field
     *
     * @param string $suffix
     * @return string|bool
     */
    protected function filearea($suffix = null): string|bool {
        if (!empty($suffix)) {
            return 'field-' . str_replace(' ', '_', $suffix);
        } else {
            if (!empty($this->field->name)) {
                return 'field-' . str_replace(' ', '_', $this->field->name);
            } else {
                return false;
            }
        }
    }

    /**
     * What a submission for this field may hold, and of which type.
     *
     * The keys are the content names that follow field_{fieldid}_{entryid}: '' for that element
     * itself, 'url' for field_{fieldid}_{entryid}_url and so on. A value is a PARAM_* type for a
     * single value, ['list' => PARAM_*] for a list of values, or ['fields' => [key => PARAM_*]] for
     * a structure such as an editor's {text, format, itemid}.
     *
     * This is the one declaration of a field's input. The entry submission service cleans every
     * submitted value by it, for the entry form and the web services alike, and drops anything it
     * does not name; the entry form sets the same types on its elements.
     *
     * @param array $options the resolved render options of the tag the field is edited under; a
     *                       few fields take a different input depending on them (e.g. addnew)
     * @return array content name => type; empty for a field without input
     */
    public function get_input_contract(array $options = []): array {
        return ['' => PARAM_NOTAGS];
    }

    /**
     * Clean one submitted value of this field by its input contract.
     *
     * @param string $contentname what follows field_{fieldid}_{entryid}_ in the element name, '' for none
     * @param mixed $value the submitted value
     * @param array $options the resolved render options of the tag the field is edited under
     * @return mixed the cleaned value, or null if the contract has no such input or the value
     *               does not have its shape
     */
    public function clean_submitted_value(string $contentname, $value, array $options = []) {
        $contract = $this->get_input_contract($options);
        if (!array_key_exists($contentname, $contract)) {
            return null;
        }

        return self::clean_by_type($contract[$contentname], $value);
    }

    /**
     * Clean a value by one entry of an input contract.
     *
     * @param string|array $type a PARAM_* type, ['list' => PARAM_*] or ['fields' => [key => PARAM_*]]
     * @param mixed $value
     * @return mixed the cleaned value, null if the value does not have the shape of the type
     */
    public static function clean_by_type($type, $value) {
        if (is_array($type) && isset($type['list'])) {
            if (!is_array($value)) {
                return null;
            }
            $clean = [];
            foreach ($value as $key => $item) {
                if (!is_scalar($item) && $item !== null) {
                    return null;
                }
                $clean[clean_param((string) $key, PARAM_ALPHANUMEXT)] = clean_param((string) $item, $type['list']);
            }
            return $clean;
        }
        if (is_array($type) && isset($type['fields'])) {
            if (!is_array($value)) {
                return null;
            }
            $clean = [];
            foreach ($type['fields'] as $key => $itemtype) {
                if (!array_key_exists($key, $value)) {
                    continue;
                }
                if (!is_scalar($value[$key]) && $value[$key] !== null) {
                    return null;
                }
                $clean[$key] = clean_param((string) $value[$key], $itemtype);
            }
            return $clean;
        }
        if (!is_scalar($value) && $value !== null) {
            return null;
        }

        return clean_param((string) $value, $type);
    }

    /**
     * Whether a submission leaves this field without a value, for the required check.
     *
     * The entry submission service asks this for every editable field whose behavior makes it
     * required, so that "required" is decided in one place and means the same for the web form and
     * the web services. A field whose input is named or shaped differently from a single
     * field_{fieldid}_{entryid} value overrides it.
     *
     * @param string $name the element name of the value: field_{fieldid}_{entryid}, or with a
     *                     fieldgroup line appended
     * @param stdClass $data the submission
     * @return bool
     */
    public function is_submitted_value_empty(string $name, stdClass $data): bool {
        return self::is_blank_submission($data->$name ?? null);
    }

    /**
     * Whether a submitted value is blank: null, an empty or whitespace string, or a list of those.
     *
     * @param mixed $value
     * @return bool
     */
    protected static function is_blank_submission($value): bool {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (!self::is_blank_submission($item)) {
                    return false;
                }
            }
            return true;
        }

        return trim((string) $value) === '';
    }

    /**
     * Store what a submission holds for this field outside the entry's contents.
     *
     * Called by the entry submission service once the entries are saved, for every field the view
     * let the user edit. Most fields keep everything in datalynx_contents and do nothing here.
     *
     * @param int $entryid the submitted entry id; negative for a new entry
     * @param stdClass $data the submission, filtered to the editable fields
     * @return void
     */
    public function after_entry_saved(int $entryid, stdClass $data): void {
    }

    /**
     * Returns operators supported by this field.
     * It contains all operators present in the previous
     * version and should therefore be overridden by a concrete
     * field class to remove unsupported operators from the list.
     *
     * @return array an array of operators, empty if search is not supported
     */
    public function get_supported_search_operators(): array {
        return []; // If search is not supported, offer no operators.
    }

    /**
     * Operators a search criterion for this field may carry, as search_criteria checks them.
     *
     * The operators a search form offers, plus any the field's get_search_sql() also handles
     * because saved or built criteria carry them (e.g. '=' that custom filters write). Without
     * these, criteria the filter engine runs correctly would be rejected when a client sends
     * them back.
     *
     * @return string[]
     */
    public function get_accepted_search_operators(): array {
        return array_map('strval', array_keys($this->get_supported_search_operators()));
    }

    /**
     * Relations this field can express against the value another entry holds for it.
     *
     * A rule that looks for entries matching the one an event just touched compares field by
     * field: same value, different value, or - for anything ordered - within a tolerance of it.
     * A field that cannot be searched for an exact value can express none of these.
     *
     * @return string[] any of match_compiler::OP_SAME, OP_DIFFERENT, OP_WITHIN, OP_ROUTE
     */
    public function supported_relative_criteria(): array {
        return array_key_exists('=', $this->get_supported_search_operators())
            ? [match_compiler::OP_SAME, match_compiler::OP_DIFFERENT]
            : [];
    }

    /**
     * Turn a relation against another entry's value into an ordinary search criterion.
     *
     * The value the other entry holds is known by the time a rule fires, so a relative criterion
     * can be stated as a plain search and evaluated by the normal filter engine. What each field
     * stores in datalynx_contents is not always what its get_search_sql() expects to receive,
     * which is why the conversion belongs to the field rather than to the caller.
     *
     * @param string $relation one of match_compiler::OP_*
     * @param string $storedvalue the other entry's raw datalynx_contents.content for this field
     * @param array $options ['tolerance' => float] in the field's own unit, for OP_WITHIN
     * @return array|null [$not, $operator, $value] search criterion, or null when this field
     *                    cannot express the relation or the value it was given is unusable
     */
    public function compile_relative_criterion(string $relation, string $storedvalue, array $options = []): ?array {
        if (!in_array($relation, $this->supported_relative_criteria(), true) || trim($storedvalue) === '') {
            return null;
        }

        return [$relation === match_compiler::OP_DIFFERENT ? 'NOT' : '', '=', trim($storedvalue)];
    }

    /**
     * Bounds of a tolerance window around a numeric value, widened so that a value sitting exactly
     * on a bound is inside it.
     *
     * The BETWEEN operator is exclusive on at least one side in every field type that offers it,
     * so "within one hour" would otherwise drop a counterpart exactly one hour away.
     *
     * @param float $value centre of the window
     * @param float $tolerance half width of the window, in the same unit as $value
     * @return array [$lower, $upper]
     */
    protected function tolerance_bounds(float $value, float $tolerance): array {
        $epsilon = max(1e-6, abs($value) * 1e-9);

        return [$value - $tolerance - $epsilon, $value + $tolerance + $epsilon];
    }

    /**
     * Returns the number of arguments required for the given search operator.
     *
     * @param string $operator
     * @return int
     */
    public function get_argument_count(string $operator): int {
        if ($operator === "") {
            // Empty operator requires no argument.
            return 0;
        } else {
            return 1;
        }
    }

    /**
     * Are fields of this field type suitable for use in customfilters?
     *
     * @return bool
     */
    public static function is_customfilterfield(): bool {
        return false;
    }

    /**
     * Is $value a valid content or do we see an empty input?
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public static function is_fieldvalue_empty($value): bool {
        if ($value == '') {
            return true;
        }
        return false;
    }
}
