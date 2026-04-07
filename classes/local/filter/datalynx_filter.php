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
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx\local\filter;

use datalynxfield_no_content_can_join;
use datalynxfield_option_single;
use datalynxfield_select;
use datalynxfield_teammemberselect;
use datalynxfield_userinfo;
use mod_datalynx\local\field\datalynxfield_option_multiple;
use mod_datalynx\local\view\base;

/**
 * Filter class
 * @package mod_datalynx
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datalynx_filter {
    /** @var int Filter record id. */
    public $id;
    /** @var int Datalynx instance id. */
    public $dataid;
    /** @var string Filter name. */
    public $name;
    /** @var string Filter description. */
    public $description;
    /** @var int Visibility setting. */
    public $visible;
    /** @var int Entries per page. */
    public $perpage;
    /** @var int Current page number. */
    public $pagenum;
    /** @var int Entry selection mode. */
    public $selection;
    /** @var string Group-by field identifier. */
    public $groupby;
    /** @var string Serialised custom sort options. */
    public $customsort;
    /** @var string Serialised custom search options. */
    public $customsearch;
    /** @var string Simple search string. */
    public $search;
    /** @var array|null Content fields to fetch. */
    public $contentfields;
    /** @var string|null Comma-separated entry ids to restrict results. */
    public $eids;
    /** @var string|null User restriction. */
    public $users;
    /** @var string|null Group restriction. */
    public $groups;
    /** @var int Current page offset. */
    public $page;
    /** @var string Author search string. */
    public $authorsearch;

    /** @var string|null SQL fragment for filtered tables. */
    protected $filteredtables = null;

    /** @var array|null Fields used in search. */
    protected $searchfields = null;

    /** @var array|null Fields used in sort. */
    protected $sortfields = null;

    /** @var array|null JOIN fragments. */
    protected $joins = null;

    /** @var array Entry ids excluded from results. */
    protected $entriesexcluded = [];

    /**
     * constructor
     *
     * @param object $filterdata Filter data object.
     */
    public function __construct($filterdata) {
        $this->id = empty($filterdata->id) ? 0 : $filterdata->id;
        $this->dataid = $filterdata->dataid; // Required.
        $this->name = empty($filterdata->name) ? '' : $filterdata->name;
        $this->description = empty($filterdata->description) ? '' : $filterdata->description;
        $this->visible = !isset($filterdata->visible) ? 1 : $filterdata->visible;

        $this->perpage = empty($filterdata->perpage) ? 0 : $filterdata->perpage;
        $this->selection = empty($filterdata->selection) ? 0 : $filterdata->selection;
        $this->groupby = empty($filterdata->groupby) ? '' : $filterdata->groupby;
        $this->customsort = empty($filterdata->customsort) ? '' : $filterdata->customsort;
        $this->customsearch = empty($filterdata->customsearch) ? '' : $filterdata->customsearch;
        if (empty($filterdata->search)) {
            if (empty($filterdata->usersearch)) {
                $this->search = '';
            } else {
                $this->search = $filterdata->usersearch;
            }
        } else {
            $this->search = $filterdata->search;
        }
        $this->contentfields = empty($filterdata->contentfields) ? null : $filterdata->contentfields;

        // Eids may be null when no entry restriction is set.
        $this->eids = empty($filterdata->eids) ? null : $filterdata->eids;

        $this->users = empty($filterdata->users) ? null : $filterdata->users;
        $this->groups = empty($filterdata->groups) ? null : $filterdata->groups;
        $this->page = empty($filterdata->page) ? 0 : $filterdata->page;
    }

    /**
     * Returns a plain stdClass object representation of this filter.
     *
     * @return \stdClass
     */
    public function get_filter_obj() {
        $filter = new stdClass();
        $filter->id = $this->id;
        $filter->dataid = $this->dataid;
        $filter->name = $this->name;
        $filter->description = $this->description;
        $filter->visible = $this->visible;

        $filter->perpage = $this->perpage;
        $filter->selection = $this->selection;
        $filter->groupby = $this->groupby;
        $filter->customsort = $this->customsort;
        $filter->customsearch = $this->customsearch;
        $filter->search = $this->search;
        $filter->authorsearch = $this->authorsearch;

        return $filter;
    }

    /**
     * Builds and returns the full SQL fragments (tables, where, order, content) for this filter.
     *
     * @param array $fields
     * @return array
     */
    public function get_sql($fields) {
        $this->init_filter_sql();

        // SEARCH sql.
        [$searchtables, $wheresearch, $searchparams] = $this->get_search_sql($fields);
        // SORT sql.
        [$sorttables, $sortorder, $sortparams] = $this->get_sort_sql($fields);
        // CONTENT sql ($datalynxcontent is an array of fieldid whose content needs to be fetched).
        [$datalynxcontent, $whatcontent, $contenttables, $contentparams] = $this->get_content_sql(
            $fields
        );

        return [" $searchtables $sorttables $contenttables ", $wheresearch, $sortorder,
                $whatcontent, array_merge($searchparams, $sortparams, $contentparams), $datalynxcontent];
    }

    /**
     * TODO: Write comment
     */
    public function init_filter_sql() {
        $this->filteredtables = null;
        $this->searchfields = [];
        $this->sortfields = [];
        $this->joins = [];

        if ($this->customsearch) {
            $this->searchfields = is_array($this->customsearch) ? $this->customsearch : unserialize(
                $this->customsearch
            );
        }
        if ($this->customsort) {
            $this->sortfields = is_array($this->customsort) ? $this->customsort : unserialize(
                $this->customsort
            );
        }
        // Defines what field should be sorted by.
        $customfiltersortfield = optional_param('customfiltersortfield', null, PARAM_INT);
        if ($customfiltersortfield) {
            $customfiltersortdirection = optional_param('customfiltersortdirection', '0', PARAM_INT);
            $customfiltersort = [$customfiltersortfield => $customfiltersortdirection];
            if ($this->customsort) {
                $this->sortfields = array_merge($this->sortfields, $customfiltersort);
            } else {
                $this->sortfields = $customfiltersort;
            }
        }
    }

    /**
     * Get field specific SQL for searching.
     *
     * @param array $fields
     * @return array
     */
    public function get_search_sql(array $fields): array {
        global $DB;

        $searchfrom = [];
        $searchwhere = [];
        $searchparams = []; // Named params array.

        $searchfields = $this->searchfields;
        $simplesearch = $this->search;
        $searchtables = '';

        if ($searchfields) {
            $whereand = [];
            $whereor = [];
            foreach ($searchfields as $fieldid => $searchfield) {
                // If we got this far there must be some actual search values.
                if (empty($fields[$fieldid])) {
                    continue;
                }

                $field = $fields[$fieldid];
                $internalfield = $field::is_internal();

                // Register join field if applicable.
                $this->register_join_field($field);

                // Add AND search clauses.
                if (!empty($searchfield['AND'])) {
                    foreach ($searchfield['AND'] as $option) {
                        if ($fieldsqloptions = $field->get_search_sql($option)) {
                            [$fieldsql, $fieldparams, $fromcontent] = $fieldsqloptions;
                            if ($fieldsql) {
                                // If we use values from content we make it an implied AND statement.
                                if (is_numeric($fieldid) && $this->add_fieldid($option, $field)) {
                                    $whereand[] = " ( " . $fieldsql . " AND c$fieldid.fieldid = $fieldid )";
                                } else {
                                    $whereand[] = $fieldsql;
                                }
                                $searchparams = array_merge($searchparams, $fieldparams);

                                // Add searchfrom (JOIN) only for search in datalynx content or external.
                                // tables or fields inherited from datalynxfield_no_content_can_join.

                                $fieldshouldaddjoin = !$internalfield || $field instanceof datalynxfield_no_content_can_join;

                                if ($fieldshouldaddjoin && $fromcontent) {
                                    $searchfrom[$fieldid] = $fieldid;
                                }
                            }
                        }
                    }
                }

                // Add OR search clause.
                if (!empty($searchfield['OR'])) {
                    foreach ($searchfield['OR'] as $option) {
                        if ($fieldsqloptions = $field->get_search_sql($option)) {
                            [$fieldsql, $fieldparams, $fromcontent] = $fieldsqloptions;
                            // If we use values from content we make it an implied AND statement.
                            if (is_numeric($fieldid) && $this->add_fieldid($option, $field)) {
                                $whereor[] = " ( " . $fieldsql . " AND c$fieldid.fieldid = $fieldid )";
                            } else {
                                $whereor[] = $fieldsql;
                            }
                            $searchparams = array_merge($searchparams, $fieldparams);

                            // Add searchfrom (JOIN) only for search in datalynx content or external.
                            // tables or fields inherited from datalynxfield_no_content_can_join.

                            $fieldshouldaddjoin = !$internalfield || $field instanceof datalynxfield_no_content_can_join;

                            if ($fieldshouldaddjoin && $fromcontent) {
                                $searchfrom[$fieldid] = $fieldid;
                            }
                        }
                    }
                }
            }

            // Compile sql for search settings.
            if ($searchfrom) {
                foreach ($searchfrom as $fieldid) {
                    // Add only tables which are not already added.
                    if (empty($this->filteredtables) || !in_array($fieldid, $this->filteredtables)) {
                        $this->filteredtables[] = $fieldid;
                        // Here the LEFT JOIN query is built within field class.
                        $searchtables .= $fields[$fieldid]->get_search_from_sql();
                    }
                }
            }

            if ($whereand) {
                $searchwhere[] = implode(' AND ', $whereand);
            }
            if ($whereor) {
                $searchwhere[] = '(' . implode(' OR ', $whereor) . ')';
            }
        }
        if ($simplesearch) {
            $searchtables .= " JOIN {datalynx_contents} cs ON cs.entryid = e.id ";
            $searchtables .= " JOIN {datalynx_fields} fsimple ON cs.fieldid = fsimple.id ";
            $searchlike = ['search1' => $DB->sql_like('cs.content', ':search1', false, false),
                    'search2' => $DB->sql_like('u.firstname', ':search2', false, false),
                    'search3' => $DB->sql_like('u.lastname', ':search3', false, false),
                    'search4' => $DB->sql_like('u.username', ':search4', false, false)];
            foreach (array_keys($searchlike) as $namekey) {
                $searchparams[$namekey] = '%' . $DB->sql_like_escape($simplesearch) . '%';
            }

            // Add search for option fields, which store option IDs.
            $i = 0;
            foreach ($fields as $field) {
                if ($field instanceof datalynxfield_option_multiple) {
                    foreach ($field->get_options() as $id => $option) {
                        if (stripos($option, $simplesearch) !== false) {
                            $paramlike = "fieldquicksearch$i";
                            $paramid = "fieldid$i";
                            $searchlike[$paramlike] = "(" .
                                    $DB->sql_like("cs.content", ":$paramlike", false, false) .
                                    " AND fsimple.id = :$paramid)";
                            $searchparams[$paramlike] = "%#{$id}%#";
                            $searchparams[$paramid] = $field->id();
                            $i++;
                        }
                    }
                } else {
                    if ($field instanceof datalynxfield_option_single) {
                        foreach ($field->get_options() as $id => $option) {
                            if (stripos($option, $simplesearch) !== false) {
                                $paramlike = "fieldquicksearch$i";
                                $paramid = "fieldid$i";
                                $searchlike[$paramlike] = "(cs.content = :$paramlike AND fsimple.id = :$paramid)";
                                $searchparams[$paramlike] = "$id";
                                $searchparams[$paramid] = $field->id();
                                $i++;
                            }
                        }
                    } else {
                        if ($field instanceof datalynxfield_teammemberselect) {
                            foreach ($field->options_menu() as $id => $option) {
                                if (stripos($option, $simplesearch) !== false) {
                                    $paramlike = "fieldquicksearch$i";
                                    $paramid = "fieldid$i";
                                    $searchlike[$paramlike] = "(" .
                                            $DB->sql_like("cs.content", ":$paramlike", false, false) .
                                            " AND fsimple.id = :$paramid)";
                                    $searchparams[$paramlike] = "%\"$id\"%";
                                    $searchparams[$paramid] = $field->id();
                                    $i++;
                                }
                            }
                        } else {
                            if ($field instanceof datalynxfield_userinfo) {
                                $paramlike = "fieldquicksearch$i";
                                $paramid = "fieldid$i";
                                $searchlike[$paramlike] = "(" .
                                        $DB->sql_like(
                                            "c{$field->id()}.data",
                                            ":$paramlike",
                                            false,
                                            false
                                        ) . ")";
                                $searchparams[$paramlike] = '%' . $DB->sql_like_escape(
                                    $simplesearch
                                ) . '%';
                                $searchparams[$paramid] = $field->id();
                                $i++;
                            }
                        }
                    }
                }
            }

            $searchwhere[] = ' (' . implode(' OR ', $searchlike) . ') ';
        }

        $wheresearch = $searchwhere ? ' AND (' . implode(' AND ', $searchwhere) . ')' : '';

        // Register referred tables.
        $this->filteredtables = $searchfrom;

        return [$searchtables, $wheresearch, $searchparams];
    }

    /**
     * Check if we should add this fieldid to our whereand and whereor clause.
     * Catches all field situations that cause problems.
     *
     * @param array $option Search option array (operator at index 1).
     * @param object $field The field object being evaluated.
     * @return bool True if the fieldid should be added, false otherwise.
     */
    public function add_fieldid($option, $field) {

        // If the field says it needs no content we trust its judgement.
        if (!$field->is_datalynx_content()) {
            return false;
        }
        // The operator "" means we look for empty fields, don't add fieldids.
        if ($option[1] == "") {
            return false;
        }
        // Exclude tags because they use an intermediate db query.
        if ($field->type == 'tag') {
            return false;
        }

        return true;
    }

    /**
     * Builds the SQL ORDER BY and JOIN fragments for the sort settings of this filter.
     *
     * @param array $fields
     * @return array [$sorttables, $sortorder, $params]
     */
    public function get_sort_sql($fields) {
        global $DB;

        $sorties = [];
        $orderby = ["e.timecreated ASC"];
        $params = [];
        $sorttables = '';
        $stringindexed = false;

        $sortfields = $this->sortfields; // Stores fieldids like in the db.

        if ($sortfields) {
            $orderby = [];
            foreach ($sortfields as $fieldid => $sortdir) {
                if (empty($fields[$fieldid])) {
                    continue;
                }

                $field = $fields[$fieldid];

                $sortname = $field->get_sort_sql();
                // Add non-internal fields to sorties.
                if (!$field::is_internal()) {
                    $sorties[$fieldid] = $sortname;
                }

                // Here we can check if fields are special.
                if (
                        $field instanceof datalynxfield_option_multiple ||
                        $field instanceof datalynxfield_option_single
                ) {
                    // Read values of field from database.
                    $fieldvalues = $DB->get_field(
                        'datalynx_fields',
                        'param1',
                        ['id' => $fieldid],
                        MUST_EXIST
                    );
                    $fieldvalues = explode("\n", $fieldvalues);

                    $replacestring = $sortname; // Works only for single values yet.

                    // Select does not work with REPLACE and has no spacer hashes infront and behind values.
                    if ($field instanceof datalynxfield_select) {
                        foreach ($fieldvalues as $key => $value) {
                            $replacestring = "REGEXP_REPLACE($replacestring,'^" . ($key + 1) . "$', '$value')";
                        }
                    } else {
                        $spacer = "#";
                        foreach ($fieldvalues as $key => $value) {
                            $replacestring = "REPLACE($replacestring,'$spacer" . ($key + 1) . "$spacer', '$value')";
                        }
                    }

                    $orderby[] = $replacestring . ($sortdir ? ' DESC' : ' ASC');
                    $stringindexed = true;
                } else {
                    $orderby[] = "$sortname " . ($sortdir ? ' DESC' : ' ASC');
                }

                // Register join field if applicable.
                $this->register_join_field($field);
            }
        }

        // Compile sql for sort settings.
        $sortorder = ' ORDER BY ' . implode(', ', $orderby) . ' ';
        if ($sorties) {
            $sortfrom = array_keys($sorties);
            $paramcount = 0;
            foreach ($sortfrom as $fieldid) {
                // Add only tables which are not already added.
                if (empty($this->filteredtables) || !in_array($fieldid, $this->filteredtables)) {
                    $this->filteredtables[] = $fieldid;
                    [$fromsql, $params["sortie$paramcount"]] = $fields[$fieldid]->get_sort_from_sql(
                        'sortie',
                        $paramcount
                    );
                    $sorttables .= $fromsql;
                    $paramcount++;
                }
            }
        }

        // If one of the sort vars needs the indexed values join fields.
        if ($stringindexed) {
            $sorttables .= " LEFT JOIN {datalynx_fields} f ON c$fieldid.fieldid = f.id ";
        }

        return [$sorttables, $sortorder, $params];
    }

    /**
     * Builds the SQL SELECT and JOIN fragments for fetching content fields.
     *
     * @param array $fields
     * @return array [$datalynxcontent, $whatcontent, $contenttables, $params]
     */
    public function get_content_sql($fields) {
        $contentfields = $this->contentfields;

        $params = [];
        $datalynxcontent = [];
        $whatcontent = ' ';
        $contenttables = ' ';

        if ($contentfields) {
            $whatcontent = [];
            $contentfrom = [];
            $paramcount = 0;
            foreach ($contentfields as $fieldid) {
                // Skip non-selectable fields (some of the internal fields e.g. entryauthor which
                // are included in the select clause by default).
                if (!isset($fields[$fieldid]) || !$selectsql = $fields[$fieldid]->get_select_sql()) {
                    continue;
                }

                $field = $fields[$fieldid];

                // Register join field if applicable.
                if ($this->register_join_field($field)) {
                    // Processing is done separately.
                    continue;
                }

                // Add what content if field already included for sort or search.
                if (in_array($fieldid, $this->filteredtables)) {
                    $whatcontent[] = $selectsql;

                    // If not in sort or search separate datalynx_contents content b/c of limit on.
                    // Joins.
                    // This content would be fetched after the entries and added to the entries.
                } else {
                    if ($field->is_datalynx_content()) {
                        $datalynxcontent[] = $fieldid;
                    } else {
                        $whatcontent[] = $selectsql;
                        $this->filteredtables[] = $fieldid;
                        [$contentfrom[$fieldid], $params["contentie$paramcount"]] = $field->get_sort_from_sql(
                            'contentie',
                            $paramcount
                        );
                        $paramcount++;
                    }
                }
            }

            // Process join fields.
            foreach ($this->joins as $joinfield) {
                $whatcontent[] = $field->get_select_sql();
                [$sqlfrom, $fieldparams] = $field->get_join_sql();
                $contentfrom[$fieldid] = $sqlfrom;
                $params = array_merge($params, $fieldparams);
            }

            $whatcontent = !empty($whatcontent) ? ', ' . implode(', ', $whatcontent) : ' ';
            $contenttables = ' ' . implode(' ', $contentfrom);
        }
        return [$datalynxcontent, $whatcontent, $contenttables, $params];
    }

    /**
     *
     * @param object $field The field object to register.
     * @return bool True if the field is registered, false otherwise
     */
    public function register_join_field($field): bool {
        if ($field->use_join()) {
            if (!isset($this->joins[$field->type])) {
                $this->joins[$field->type] = $field;
            }
            return true;
        }
        return false;
    }

    /**
     * Appends additional sort options to the current custom sort settings.
     *
     * @param array $sorties fieldid => sortdir pairs
     */
    public function append_sort_options(array $sorties) {
        if ($sorties) {
            $sortoptions = $this->customsort ? unserialize($this->customsort) : [];
            foreach ($sorties as $fieldid => $sortdir) {
                $sortoptions[$fieldid] = $sortdir;
            }
            $this->customsort = serialize($sortoptions);
        }
    }

    /**
     * Appends additional search options to the current custom search settings.
     *
     * @param array $searchies fieldid => search option pairs
     */
    public function append_search_options(array $searchies) {
        if ($searchies) {
            $searchoptions = $this->customsearch ? unserialize($this->customsearch) : [];
            foreach ($searchies as $fieldid => $searchy) {
                if (empty($searchoptions[$fieldid])) {
                    $searchoptions[$fieldid] = $searchies[$fieldid];
                }
            }
            $this->customsearch = serialize($searchoptions);
        }
    }
}
