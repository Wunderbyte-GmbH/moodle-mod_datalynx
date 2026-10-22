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

namespace datalynxfield_identifier;

use mod_datalynx\local\field\datalynxfield_base;
use moodle_exception;
use stdClass;

/**
 * Field class for the identifier field type.
 *
 * @package datalynxfield_identifier
 */
class field extends datalynxfield_base {
    /** @var string The field type. */
    public $type = 'identifier';

    /** @var int Salt size used when the field does not define one. */
    const DEFAULT_SALT_SIZE = 10;

    /** @var int Number of candidate keys to try before giving up. */
    const MAX_KEY_ATTEMPTS = 100;

    /**
     * Returns the salt options.
     *
     * @return array
     */
    public static function get_salt_options(): array {
        global $CFG;

        $options = ['' => get_string('none'), 'random' => get_string('random', 'datalynx')];
        if (!empty($CFG->passwordsaltmain)) {
            $options['system'] = get_string('system', 'datalynxfield_identifier');
        }
        return $options;
    }

    /**
     * Formats the content for the field.
     *
     * @param stdClass $entry The entry object.
     * @param ?array $values The values to format.
     * @return array
     */
    protected function format_content($entry, ?array $values = null): array {
        $fieldid = $this->field->id;
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
     * Generates an identifier key for an entry that is not used by any identifier field on the site.
     *
     * The first candidate uses the configured salt. As that salt may be deterministic, every further
     * candidate uses a random salt.
     *
     * @param stdClass $entry The entry object.
     * @return string
     * @throws moodle_exception When no unused key could be found.
     */
    protected function generate_identifier_key($entry): string {
        $identifierkey = $this->get_hash_string($entry);
        $attempts = 1;
        while (self::key_exists($identifierkey)) {
            if ($attempts++ >= self::MAX_KEY_ATTEMPTS) {
                throw new moodle_exception('identifierkeyfailed', 'datalynxfield_identifier');
            }
            $identifierkey = $this->get_hash_string($entry, true);
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
    protected function get_hash_string($entry, $forcerandomsalt = false): string {
        global $CFG, $USER;

        if ($forcerandomsalt) {
            $salt = 'random';
        } else {
            $salt = !empty($this->field->param1) ? $this->field->param1 : '';
        }
        $fieldsaltsize = !empty($this->field->param2) ? $this->field->param2 : self::DEFAULT_SALT_SIZE;
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
     * Checks whether a key is already stored by any identifier field on the site.
     *
     * @param string $key The key to check.
     * @param int $excludecontentid A content id to ignore, e.g. the content that holds the key.
     * @return bool True if the key is taken.
     */
    public static function key_exists(string $key, int $excludecontentid = 0): bool {
        global $DB;

        $sql = "SELECT 1
                  FROM {datalynx_contents} dc
                  JOIN {datalynx_fields} df ON df.id = dc.fieldid
                 WHERE df.type = :type
                   AND dc.id <> :excludeid
                   AND " . $DB->sql_compare_text('dc.content') . " = " . $DB->sql_compare_text(':content');
        return $DB->record_exists_sql(
            $sql,
            ['type' => 'identifier', 'excludeid' => $excludecontentid, 'content' => $key]
        );
    }

    /**
     * Replaces the identifier keys of a datalynx instance that are also used elsewhere on the site.
     *
     * Restoring a backup on the site it was made on copies the keys of the original entries. Keys
     * that are not taken (e.g. when restoring from the recycle bin) are kept.
     *
     * @param int $dataid The datalynx instance id.
     * @return int The number of replaced keys.
     * @throws moodle_exception When no unused key could be found.
     */
    public static function replace_duplicate_keys(int $dataid): int {
        global $DB;

        $sql = "SELECT dc.id, dc.entryid, dc.content, df.param2
                  FROM {datalynx_contents} dc
                  JOIN {datalynx_fields} df ON df.id = dc.fieldid
                 WHERE df.dataid = :dataid
                   AND df.type = :type";
        $contents = $DB->get_records_sql($sql, ['dataid' => $dataid, 'type' => 'identifier']);

        $replaced = 0;
        foreach ($contents as $content) {
            if (empty($content->content) || !self::key_exists($content->content, $content->id)) {
                continue;
            }
            $saltsize = !empty($content->param2) ? $content->param2 : self::DEFAULT_SALT_SIZE;
            $attempts = 0;
            do {
                if ($attempts++ >= self::MAX_KEY_ATTEMPTS) {
                    throw new moodle_exception('identifierkeyfailed', 'datalynxfield_identifier');
                }
                $newkey = md5(implode('_', [$content->entryid, complex_random_string($saltsize)]));
            } while (self::key_exists($newkey));

            $DB->set_field('datalynx_contents', 'content', $newkey, ['id' => $content->id]);
            $replaced++;
        }
        return $replaced;
    }

    /**
     * Returns the supported search operators.
     *
     * @return array
     */
    public function get_supported_search_operators(): array {
        return ['' => get_string('empty', 'datalynx'), '=' => get_string('equal', 'datalynx'),
                'LIKE' => get_string('contains', 'datalynx')];
    }
}
