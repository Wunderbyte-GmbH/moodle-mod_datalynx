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
 * @package mod_datalynx
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx\local\field;

use stdClass;

/**
 * Base class for Datalynx field types that offer a set of options with single choice
 */
class datalynxfield_option_single extends datalynxfield_option {
    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_option::update_options()
     * @param array $map Mapping of old option keys to new option keys.
     */
    public function update_options($map = []) {
        global $DB;

        $params = [];
        $i = 0;
        $updatesql = "UPDATE {datalynx_contents}
                         SET content = (
                        CASE";
        foreach ($map as $old => $new) {
            $updatesql .= " WHEN content = :old{$i} THEN :new{$i} ";
            $params["old{$i}"] = $old;
            $params["new{$i}"] = $new;
            $i++;
        }
        $updatesql .= "ELSE 0 END) WHERE fieldid = :fieldid";
        $params['fieldid'] = $this->field->id;

        $DB->execute($updatesql, $params);
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_base::format_content()
     * @param stdClass $entry Entry object.
     * @param ?array $values Values from the entry form.
     * @return array
     */
    protected function format_content($entry, ?array $values = null) {
        $fieldid = $this->field->id;
        // Old contents.
        $oldcontents = [];
        if (isset($entry->{"c{$fieldid}_content"})) {
            $oldcontents[] = $entry->{"c{$fieldid}_content"};
        }
        // New contents.
        $contents = [];

        $selected = null;

        // We want to store empty values as well.
        foreach ($values as $value) {
            $selected = $value;
        }

        // Add the content.
        $contents[] = $selected;

        return [$contents, $oldcontents];
    }

    /**
     * Computes which values of this field have already been chosen by the given user and
     * determines which ones have reached their limit
     *
     * @param int $entryid ID of the entry being edited; 0 for new entries.
     * @return array an array of disabled values
     */
    public function get_disabled_values_for_user($entryid = 0) {
        global $DB;

        $sql = "SELECT dc.content, COUNT(dc.id)
        FROM {datalynx_contents} dc
        INNER JOIN {datalynx_entries} de ON dc.entryid = de.id
        WHERE de.dataid = :dataid
        AND dc.fieldid = :fieldid
        AND de.id != :entryid
        GROUP BY dc.content
        HAVING COUNT(dc.id) >= :selectlimit";
        $params = ['dataid' => $this->df->id(), 'fieldid' => $this->field->id,
                'selectlimit' => $this->field->param5, 'entryid' => $entryid];

        $results = $DB->get_records_sql($sql, $params);

        return array_keys($results);
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_base::supports_group_by()
     */
    public function supports_group_by() {
        return true;
    }

    /**
     * Get search sql for single choice fields.
     * {@inheritDoc}
     * @see datalynxfield_base::get_search_sql()
     * @param array $search Search criteria array.
     * @return array
     */
    public function get_search_sql(array $search): array {
        global $DB;

        [$not, $operator, $value] = $search;

        static $i = 0; // FIXME: might cause problems!
        $i++;
        $fieldid = $this->field->id;

        $sql = null;
        $params = [];
        $name = "df_{$fieldid}_{$i}";
        $notinidsequal = false;

        // For all NOT criteria except NOT Empty, exclude entries.
        // Which don't meet the positive criterion.
        // Because some fields may not have content records.
        // And the respective entries may be filter out.
        // Despite meeting the criterion.
        $excludeentries = (($not && $operator !== '') || (!$not && $operator === ''));

        $content = "c{$this->field->id}.content";

        $usecontent = true;
        if ($operator === 'ANY_OF' || $operator === '=') {
            [$insql, $params] = $DB->get_in_or_equal($value, SQL_PARAMS_NAMED, "param_{$i}_");
            $sql = " $not ($content $insql) ";
        } else {
            if ($operator === '') {
                $usecontent = false;
                $sqlnot = $DB->sql_like("content", ":{$name}_hascontent");
                $params["{$name}_hascontent"] = "%";

                if ($eids = $this->get_entry_ids_for_content($sqlnot, $params)) { // There are non-empty.
                    // Contents.
                    [$contentids, $paramsnot] = $DB->get_in_or_equal(
                        $eids,
                        SQL_PARAMS_NAMED,
                        "df_{$fieldid}_x_",
                        !!$not
                    );
                    $params = array_merge($params, $paramsnot);
                    $sql = " (e.id $contentids) ";
                } else { // There are no non-empty contents.
                    if ($not) {
                        $sql = " 0 ";
                    } else {
                        $sql = " 1 = 1 ";
                    }
                }
            }
        }

        if ($excludeentries && $operator !== '') {
            $sqlnot = str_replace($content, 'content', $sql);
            $sqlnot = str_replace('NOT (', '(', $sqlnot);
            if ($eids = $this->get_entry_ids_for_content($sqlnot, $params)) {
                // Get NOT IN sql.
                [$notinids, $paramsnot] = $DB->get_in_or_equal(
                    $eids,
                    SQL_PARAMS_NAMED,
                    "df_{$fieldid}_x_",
                    $notinidsequal
                );
                $params = array_merge($params, $paramsnot);
                $sql = " ($sql OR e.id $notinids) ";
            }
        }

        return [$sql, $params, $usecontent];
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_base::prepare_import_content()
     * @param stdClass $data Form data object (passed by reference).
     * @param array $importsettings Import settings.
     * @param ?array $csvrecord CSV record data.
     * @param ?int $entryid Entry ID.
     * @return bool
     */
    public function prepare_import_content(&$data, $importsettings, $csvrecord = null, $entryid = null) {
        // Import only from csv.
        if ($csvrecord) {
            $fieldid = $this->field->id;
            $fieldname = $this->name();
            $csvname = $importsettings[$fieldname]['name'];
            $label = !empty($csvrecord[$csvname]) ? $csvrecord[$csvname] : null;

            if ($label) {
                $options = $this->options_menu();
                if ($optionkey = array_search($label, $options)) {
                    $data->{"field_{$fieldid}_{$entryid}"} = $optionkey;
                }
            }
        }
        return true;
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_base::get_supported_search_operators()
     */
    public function get_supported_search_operators() {
        return ['ANY_OF' => get_string('anyof', 'datalynx'), '' => get_string('empty', 'datalynx')];
    }
}
