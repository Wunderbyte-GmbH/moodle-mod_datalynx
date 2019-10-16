<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Search area for mod_datalynx activities.
 *
 * @package    mod_datalynx
 * @copyright  2019 Michael Pollak <moodle@michaelpollak.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * This is heavily based on mod_book and mod_data, thank you.
 */

namespace mod_datalynx\search;

defined('MOODLE_INTERNAL') || die();

/**
 * Search area for mod_datalynx activity entries.
 *
 * @package    mod_datalynx
 * @copyright  2019 Michael Pollak <moodle@michaelpollak.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entry extends \core_search\base_mod {

    /**
     * @var array Cache of datalynx entries.
     */
    protected $entriesdata = array();

    /**
     * Returns a recordset with all required entry information.
     *
     * @param int $modifiedfrom timestamp
     * @param \context|null $context Optional context to restrict scope of returned results
     * @return moodle_recordset|null Recordset (or null if no results)
     */
    public function get_document_recordset($modifiedfrom = 0, \context $context = null) {
        global $DB;

        list ($contextjoin, $contextparams) = $this->get_context_restriction_sql(
                $context, 'datalynx', 'dl', SQL_PARAMS_NAMED);
        if ($contextjoin === null) {
            return null;
        }

        $sql = "SELECT de.*, dl.course
                FROM {datalynx_entries} de
                JOIN {datalynx} dl ON dl.id = de.dataid $contextjoin
                WHERE de.timemodified >= ? ORDER BY de.timemodified ASC";
        return $DB->get_recordset_sql($sql, array_merge($contextparams, [$modifiedfrom]));
    }

    /**
     * Returns the documents associated with this entry id.
     *
     * @param stdClass $entry
     * @param array    $options
     * @return \core_search\document
     */
    public function get_document($entry, $options = array()) {
        try {
            $cm = $this->get_cm('datalynx', $entry->dataid, $entry->course);
            $context = \context_module::instance($cm->id);
        } catch (\dml_missing_record_exception $ex) {
            // Notify it as we run here as admin, we should see everything.
            debugging('Error retrieving mod_data ' . $entry->id . ' document, not all required data is available: ' .
                $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        } catch (\dml_exception $ex) {
            // Notify it as we run here as admin, we should see everything.
            debugging('Error retrieving mod_datalynx' . $entry->id . ' document: ' . $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        }

        // Prepare associative array with data from DB.
        $doc = \core_search\document_factory::instance($entry->id, $this->componentname, $this->areaname);
        $doc->set('contextid', $context->id);
        $doc->set('courseid', $entry->course);
        $doc->set('userid', $entry->userid);
        if ($entry->groupid > 0) {
            $doc->set('groupid', $entry->groupid);
        }
        $doc->set('owneruserid', \core_search\manager::NO_OWNER_ID);
        $doc->set('modified', $entry->timemodified);

        $indexfields = $this->get_fields_for_entries($entry);

        if (count($indexfields) < 1) {
            return false;
        }

        $doc->set('title', $indexfields[0]);
        $doc->set('content', ''); // Content needs to be defined even if we only see a single field.

        if (isset($indexfields[1])) {
            $doc->set('content', $indexfields[1]);
        }

        if (isset($indexfields[2])) {
            $doc->set('description1', $indexfields[2]);
        }

        if (isset($indexfields[3])) {
            $doc->set('description2', $indexfields[3]);
        }

        return $doc;
    }

    /**
     * Check if the current user has access.
     *
     * @throws \dml_missing_record_exception
     * @throws \dml_exception
     * @param int $id
     * @return bool
     */
    public function check_access($id) {
        global $DB;

        if (isguestuser()) {
            return \core_search\manager::ACCESS_DENIED;
        }

        $sql = "SELECT de.*, dl.*
                FROM {datalynx_entries} de
                JOIN {datalynx} dl ON dl.id = de.dataid
                WHERE de.id = ?";

        $entry = $DB->get_record_sql($sql, array( $id ), IGNORE_MISSING);

        if (!$entry) {
            return \core_search\manager::ACCESS_DELETED;
        }

        $cm = $this->get_cm('datalynx', $entry->dataid, $entry->course);
        $context = \context_module::instance($cm->id);

        if (!has_capability('mod/datalynx:viewentry', $context)) {
            return \core_search\manager::ACCESS_DENIED;
        }

        return \core_search\manager::ACCESS_GRANTED;
    }

    // Important for moodle 3.3 support.
    public function get_recordset_by_timestamp($modifiedfrom = 0) {
        return $this->get_document_recordset($modifiedfrom);
    }

    public function get_doc_url(\core_search\document $doc) {
        $entry = $this->get_entry($doc->get('itemid'));
        return new \moodle_url('/mod/datalynx/view.php', array( 'd' => $entry->dataid, 'eids' => $entry->id ));
    }

    public function get_context_url(\core_search\document $doc) {
        $entry = $this->get_entry($doc->get('itemid'));
        return new \moodle_url('/mod/datalynx/view.php', array('d' => $entry->dataid));
    }

    /**
     * Get database entry data.
     *
     * @throws \dml_exception
     * @param int $entryid
     * @return stdClass
     */
    protected function get_entry($entryid) {
        global $DB;

        if (empty($this->entriesdata[$entryid])) {
            $this->entriesdata[$entryid] = $DB->get_record('datalynx_entries', array( 'id' => $entryid ), '*', MUST_EXIST);
        }

        return $this->entriesdata[$entryid];
    }


    /**
     * Make all the field content that is attachted to an entry searchable.
     *
     * @param StdClass $entry
     * @return array
     */
    protected function get_fields_for_entries($entry) {
        global $DB;

        $indexfields = array();
        $validfieldtypes = array('text', 'textarea', 'url', 'number', 'editor');

        $sql = "SELECT dc.*, df.name AS fieldname, df.type AS fieldtype
                FROM {datalynx_contents} dc, {datalynx_fields} df
                WHERE dc.fieldid = df.id
                AND dc.entryid = :entryid";

        $contents = $DB->get_records_sql($sql, array('entryid' => $entry->id));
        $filteredcontents = array();

        // Filter invalid fieldtypes.
        foreach ($contents as $content) {
            if (in_array($content->fieldtype, $validfieldtypes)) {
                $filteredcontents[] = $content;
            }
        }

        foreach ($filteredcontents as $content) {
            $indexfields[] = $content->fieldname . ": " . $content->content;
        }

        // Limited to 4 fields as a document only has 4 content fields.
        if (count($indexfields) > 4) {
            $indexfields[3] = implode('; ', array_slice($indexfields, 3));
        }

        return $indexfields;
    }
}
