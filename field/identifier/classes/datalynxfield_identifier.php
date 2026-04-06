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
 *
 * @package datalynxfield_identifier
 * @subpackage identifier
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_datalynx\local\field\datalynxfield_base;



/**
 * Field class for the identifier field type.
 *
 * @package datalynxfield_identifier
 */
class datalynxfield_identifier extends datalynxfield_base {
    /** @var string The field type. */
    public $type = 'identifier';

    /**
     * Returns the salt options.
     *
     * @return array
     */
    public static function get_salt_options() {
        global $CFG;

        $options = ['' => get_string('none'), 'random' => get_string('random', 'datalynx')];
        if (!empty($CFG->passwordsaltmain)) {
            $options[] = get_string('system', 'datalynxfield_identifier');
        }
        return $options;
    }

    /**
     * Formats the content for the field.
     *
     * @param stdClass $entry The entry object.
     * @param array|null $values The values to format.
     * @return array
     */
    protected function format_content($entry, array $values = null) {
        $fieldid = $this->field->id;
        $oldcontents = [];
        $contents = [];
        // Old content (should not exist if we get here, as update should be triggered only when no content).
        if (isset($entry->{"c{$fieldid}_content"})) {
            $oldcontent = $entry->{"c{$fieldid}_content"};
        } else {
            $oldcontent = null;
        }
        // Just to make sure that we come from the form where it is requested (a value of 1).
        if (!empty($values)) {
            $content = $this->generate_identifier_key($entry);
        } else {
            $content = null;
        }
        return [[$content], [$oldcontent]];
    }

    /**
     * Generates a unique identifier key for an entry.
     *
     * @param stdClass $entry The entry object.
     * @return string
     */
    protected function generate_identifier_key($entry) {
        global $CFG, $USER;

        $identifierkey = $this->get_hash_string($entry);
        $uniqueness = !empty($this->field->param4) ? $this->field->param4 : false;
        if ($uniqueness) {
            // We check against stored idenitifiers in this field.
            // To prevent this from going forever under certain configurations.
            // After 10 times force random salt we should allow it to end at some point.
            $count = 0;
            while (!$this->is_unique_key($identifierkey)) {
                $count++;
                $forcerandomsalt = ($count > 10 ? true : false);
                $identifierkey = $this->get_hash_string($entry, $forcerandomsalt);
            }
        }

        return $identifierkey;
    }

    /**
     * Returns a hash string for an entry.
     *
     * @param stdClass $entry The entry object.
     * @param bool $forcerandomsalt Whether to force a random salt.
     * @return string
     */
    protected function get_hash_string($entry, $forcerandomsalt = false) {
        global $CFG, $USER;

        if ($forcerandomsalt) {
            $salt = 'random';
        } else {
            $salt = !empty($this->field->param1) ? $this->field->param1 : '';
        }
        $fieldsaltsize = !empty($this->field->param2) ? $this->field->param2 : 10;
        // Entry identifiers.
        $entryid = $entry->id;
        $timeadded = (!empty($entry->timecreated) ? $entry->timecreated : time());
        $userid = (!empty($entry->userid) ? $entry->userid : $USER->id);

        // Collate elements for hashing.
        $elements = [];
        $elements[] = $entryid;

        // Salt.
        switch ($salt) {
            case '':
                $elements[] = $timeadded;
                $elements[] = $userid;
                break;
            case 'system':
                if (!empty($CFG->passwordsaltmain)) {
                    $elements[] = $CFG->passwordsaltmain;
                } else {
                    $elements[] = $timeadded;
                    $elements[] = $userid;
                }
                break;
            case 'random':
                $elements[] = complex_random_string($fieldsaltsize);
                break;
        }

        // Generate and return the hash.
        return md5(implode('_', $elements));
    }

    /**
     * Checks if a key is unique.
     *
     * @param string $key The key to check.
     * @return bool
     */
    protected function is_unique_key($key) {
        global $DB;

        return $DB->record_exists(
            'datalynx_contents',
            ['fieldid' => $this->fieldid, 'content' => $key]
        );
    }

    /**
     * Returns the supported search operators.
     *
     * @return array
     */
    public function get_supported_search_operators() {
        return ['' => get_string('empty', 'datalynx'), '=' => get_string('equal', 'datalynx'),
                'LIKE' => get_string('contains', 'datalynx')];
    }
}
