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

use stdClass;

/**
 * Base class for Datalynx field types that require no content. Example: User profile fields.
 * @package mod_datalynx
 * @copyright 2025 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class datalynxfield_no_content extends datalynxfield_base {
    /**
     * No content to update; always returns true.
     *
     * @param stdClass $entry
     * @param array|null $values
     * @return bool
     */
    public function update_content(stdClass $entry, ?array $values = null) {
        return true;
    }

    /**
     * No content to delete; always returns true.
     *
     * @param int $entryid
     * @return bool
     */
    public function delete_content($entryid = 0) {
        return true;
    }

    /**
     * No distinct content available; returns an empty array.
     *
     * @param int $sortdir
     * @return array
     */
    public function get_distinct_content($sortdir = 0) {
        return [];
    }

    /**
     * No SELECT SQL needed for this field type.
     *
     * @return string
     */
    public function get_select_sql() {
        return '';
    }

    /**
     * No sort SQL needed for this field type.
     *
     * @return string
     */
    public function get_sort_sql() {
        return '';
    }

    /**
     * This field type does not store content in datalynx_contents.
     *
     * @return bool
     */
    public function is_datalynx_content() {
        return false;
    }

    /**
     * No file area for this field type.
     *
     * @param string|null $suffix
     * @return false
     */
    protected function filearea($suffix = null) {
        return false;
    }
}
