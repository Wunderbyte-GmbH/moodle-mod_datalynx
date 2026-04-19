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
/**
 * Base class for Datalynx field types that offer a set of options with multiple choice
 * @package mod_datalynx
 * @copyright 2025 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datalynxfield_option_multiple extends datalynxfield_option {
    /**
     * Update the selected options in the entries. The field value of an entry saves the selected
     * line numbers in a multiselect field. When an option is deleted, a line is deleted. Example:
     * line 2 is deleted, therefore line 3 becomes line 2, line 4 becomes line 3 and so on.
     * Therefore the values of the field in the entries have to be remapped to the new line numbers of the options
     *
     * @see datalynxfield_option::update_options()
     * @param array $map Mapping of old option keys to new option keys.
     */
    public function update_options($map = []) {
        global $DB;
        $params = [];
        $i = 0;
        $where = 'c.fieldid = :fieldid AND (';
        foreach (array_keys($map) as $old) {
            $where .= $DB->sql_like('c.content', ":old{$i}") . ' OR ';
            $params["old{$i}"] = "%#{$old}#%";
            $i++;
        }
        $where = rtrim($where, "OR ") . ")";
        $selectsql = "SELECT c.id, c.content
        FROM {datalynx_contents} c
        WHERE {$where}
        ";
        $params['fieldid'] = $this->field->id;

        $oldcontents = $DB->get_records_sql_menu($selectsql, $params);
        foreach ($oldcontents as $id => $oldcontent) {
            $prepareoldcontent = str_replace('#', '', $oldcontent);
            $prepared = explode(",", $prepareoldcontent);
            $replaced = [];
            foreach ($prepared as $value) {
                if ($map[$value] !== 0) {
                    $replaced[$map[$value]] = $map[$value];
                }
            }
            $implodedcontent = implode(",", $replaced);
            $newcontent = "#" . str_replace(",", "#,#", $implodedcontent) . "#";

            $DB->set_field('datalynx_contents', 'content', $newcontent, ['id' => $id]);
        }
    }

    /**
     * does not support group by filter settings
     *
     * @see datalynxfield_base::supports_group_by()
     */
    public function supports_group_by() {
        return false;
    }

    /**
     * Prepare the content of the field for database storage when an entry
     * is modified or created
     * (non-PHPdoc)
     *
     * @see datalynxfield_base::format_content()
     * @param stdClass $entry Entry object.
     * @param array|null $values Values from the entry form.
     * @return array
     */
    protected function format_content($entry, ?array $values = null) {
        $fieldid = $this->field->id;
        $contents = [];
        $oldcontents = [];

        // Old contents.
        if (isset($entry->{"c{$fieldid}_content"})) {
            $oldcontents[] = $entry->{"c{$fieldid}_content"};
        }

        $newvalues = reset($values);
        foreach ($newvalues as $key => $value) {
            if (empty($value)) {
                unset($newvalues[$key]);
            }
        }
        // New contents.
        if (!empty($newvalues)) {
            $content = '#' . implode('#,#', $newvalues) . '#';
            $contents[] = $content;
        } else {
            $contents[] = ''; // Keep empties in database.
        }

        return [$contents, $oldcontents];
    }

    /**
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
        $name = "df_{$fieldid}_{$i}";

        $sql = '';
        $params = [];
        $conditions = [];
        $notinidsequal = false;

        // For all NOT criteria except NOT Empty, exclude entries.
        // Which don't meet the positive criterion.
        // Because some fields may not have content records.
        // And the respective entries may be filter out.
        // Despite meeting the criterion.
        $excludeentries = (($not && $operator !== '') || (!$not && $operator === ''));

        if ($operator === 'EXACTLY' && empty($value)) {
            $operator = '';
        }

        $content = "c{$this->field->id}.content";
        $usecontent = true;
        if ($operator === 'ANY_OF') {
            foreach ($value as $key => $sel) {
                $xname = $name . $key;
                $likesel = str_replace('%', '\%', $sel);

                $conditions[] = $DB->sql_like($content, ":{$xname}");
                $params[$xname] = "%#$likesel#%";
            }
            $sql = " $not (" . implode(" OR ", $conditions) . ") ";
        } else {
            if ($operator === 'ALL_OF') {
                foreach ($value as $key => $sel) {
                    $xname = $name . $key;
                    $likesel = str_replace('%', '\%', $sel);

                    $conditions[] = $DB->sql_like($content, ":{$xname}");
                    $params[$xname] = "%#$likesel#%";
                }
                $sql = " $not (" . implode(" AND ", $conditions) . ") ";
            } else {
                if ($operator === 'EXACTLY' || $operator === '=') {
                    if ($not) {
                        $content = "content";
                        $usecontent = false;
                    } else {
                        $content = "c{$this->field->id}.content";
                        $usecontent = true;
                    }

                    $j = 0;
                    foreach (array_keys($this->options_menu()) as $key) {
                        if (in_array($key, $value)) {
                            $xname = $name . $j++;
                            $likesel = str_replace('%', '\%', $key);

                            $conditions[] = $DB->sql_like($content, ":{$xname}", true, true, false);
                            $params[$xname] = "%#$likesel#%";
                        }
                    }
                    foreach (array_keys($this->options_menu()) as $key) {
                        if (!in_array($key, $value)) {
                            $xname = $name . $j++;
                            $likesel = str_replace('%', '\%', $key);

                            $conditions[] = $DB->sql_like($content, ":{$xname}", true, true, true);
                            $params[$xname] = "%#$likesel#%";
                        }
                    }

                    if ($not) {
                        $sqlfind = " (" . implode(" AND ", $conditions) . ") ";

                        $sql = ' 1 ';
                        if ($eids = $this->get_entry_ids_for_content($sqlfind, $params)) { // There are.
                            // Non-empty.
                            // Contents.
                            [$contentids, $paramsnot] = $DB->get_in_or_equal(
                                $eids,
                                SQL_PARAMS_NAMED,
                                "df_{$fieldid}_x_",
                                false
                            );
                            $params = array_merge($params, $paramsnot);
                            $sql = " (e.id $contentids) ";
                        }
                    } else {
                        $sql = " (" . implode(" AND ", $conditions) . ") ";
                    }
                } else {
                    if ($operator === '') { // EMPTY.
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
                                $sql = " 1 = 0 ";
                            } else {
                                $sql = " 1 = 1 ";
                            }
                        }
                    }
                }
            }
        }

        if ($excludeentries && $operator !== '' && $operator !== 'EXACTLY') {
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
     * Returns the search operators supported by multi-select fields.
     *
     * @return array
     */
    public function get_supported_search_operators() {
        return ['ANY_OF' => get_string('anyof', 'datalynx'),
                'ALL_OF' => get_string('allof', 'datalynx'),
                'EXACTLY' => get_string('exactly', 'datalynx'), '' => get_string('empty', 'datalynx')];
    }

    /**
     * Returns the number of arguments required for the given search operator.
     *
     * @param string $operator
     * @return int
     */
    public function get_argument_count(string $operator) {
        if ($operator === "") { // Empty operator requires no argument.
            return 0;
        } else {
            return 1;
        }
    }
}
