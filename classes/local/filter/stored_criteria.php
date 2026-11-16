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

namespace mod_datalynx\local\filter;

/**
 * The stored form of the search and sort criteria of saved filters.
 *
 * Saving a filter, the upgrade and restore bring the criteria into one form:
 * - criteria and sort entries only for fields of the instance; a criterion on a team member
 *   profile field (entryteammemberprofilefield_<teamfieldid>_<institution|department>) only
 *   while its team member select field exists;
 * - team member select: USER without a value (it always means the current user), OTHER_USER and
 *   ANY_OF with a list of positive user ids, '' (empty) without a value;
 * - team member profile field: LITERAL_VALUE with a non-empty value, MY_PROFILE_FIELD without one.
 *
 * Criteria that cannot be brought into this form are dropped.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stored_criteria {
    /** @var string Pattern of the key of a team member profile field criterion. */
    const PROFILE_KEY = '/^entryteammemberprofilefield_(\d+)_(institution|department)$/';

    /**
     * The types of the fields of an instance, by field id.
     *
     * @param int $dataid
     * @return string[] field id => type
     */
    public static function field_types(int $dataid): array {
        global $DB;
        return $DB->get_records_menu('datalynx_fields', ['dataid' => $dataid], '', 'id, type');
    }

    /**
     * Search criteria in the stored form.
     *
     * @param array $criteria field key => conjunction => list of [not, operator, value]
     * @param string[] $fieldtypes the instance's field types, see field_types()
     * @param callable|null $mapuserid maps a stored user id to the one to store (restore); 0 drops it
     * @return array
     */
    public static function normalise_search(array $criteria, array $fieldtypes, ?callable $mapuserid = null): array {
        $normalised = [];
        foreach ($criteria as $key => $conjunctions) {
            $type = self::field_type((string) $key, $fieldtypes);
            if ($type === null || !is_array($conjunctions)) {
                continue;
            }
            foreach ($conjunctions as $conjunction => $list) {
                if (!in_array($conjunction, ['AND', 'OR'], true) || !is_array($list)) {
                    continue;
                }
                foreach ($list as $criterion) {
                    if (!is_array($criterion) || count($criterion) !== 3) {
                        continue;
                    }
                    $criterion = self::normalise_criterion($type, array_values($criterion), $mapuserid);
                    if ($criterion !== null) {
                        $normalised[$key][$conjunction][] = $criterion;
                    }
                }
            }
        }
        return $normalised;
    }

    /**
     * Sort criteria in the stored form: only fields of the instance.
     *
     * @param array $sort field key => direction
     * @param string[] $fieldtypes the instance's field types, see field_types()
     * @return array
     */
    public static function normalise_sort(array $sort, array $fieldtypes): array {
        return array_filter(
            $sort,
            fn($key): bool => self::field_type((string) $key, $fieldtypes) !== null,
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Bring the serialized criteria of a filter record into the stored form.
     *
     * @param object $filter a datalynx_filters record or filter (customsearch, customsort)
     * @param string[] $fieldtypes the instance's field types, see field_types()
     * @param callable|null $mapuserid see normalise_search()
     * @return bool whether the record changed
     */
    public static function normalise_filter_record(object $filter, array $fieldtypes, ?callable $mapuserid = null): bool {
        $changed = false;
        foreach (['customsearch' => 'search', 'customsort' => 'sort'] as $column => $kind) {
            if (empty($filter->$column) || !is_string($filter->$column)) {
                continue;
            }
            $criteria = @unserialize($filter->$column, ['allowed_classes' => false]);
            $criteria = is_array($criteria) ? $criteria : [];
            $criteria = $kind === 'search'
                ? self::normalise_search($criteria, $fieldtypes, $mapuserid)
                : self::normalise_sort($criteria, $fieldtypes);
            $stored = $criteria ? serialize($criteria) : '';
            if ($stored !== $filter->$column) {
                $filter->$column = $stored;
                $changed = true;
            }
        }
        return $changed;
    }

    /**
     * Bring the criteria of the saved filters into the stored form.
     *
     * Used by the upgrade migration and by restore (after_restore). Idempotent.
     *
     * @param int|null $dataid Restrict to one datalynx instance, or null for all instances.
     * @return int Number of filters updated.
     */
    public static function normalize_stored_filters(?int $dataid = null): int {
        global $DB;

        $conditions = $dataid !== null ? ['dataid' => $dataid] : [];
        $filters = $DB->get_records('datalynx_filters', $conditions, 'id', 'id, dataid, customsearch, customsort');
        $fieldtypes = [];
        $updated = 0;
        foreach ($filters as $filter) {
            $fieldtypes[$filter->dataid] ??= self::field_types((int) $filter->dataid);
            if (self::normalise_filter_record($filter, $fieldtypes[$filter->dataid])) {
                $DB->update_record('datalynx_filters', $filter);
                $updated++;
            }
        }
        return $updated;
    }

    /**
     * The type of the field a criterion key names, or null if the instance has no such field.
     *
     * Keys of internal fields (status, approve, userid, timecreated, ...) are kept as they are.
     *
     * @param string $key
     * @param string[] $fieldtypes
     * @return string|null
     */
    private static function field_type(string $key, array $fieldtypes): ?string {
        if ($key === '' || is_numeric($key)) {
            return $fieldtypes[(int) $key] ?? null;
        }
        if (str_starts_with($key, 'entryteammemberprofilefield_')) {
            return preg_match(self::PROFILE_KEY, $key, $matches) && ($fieldtypes[(int) $matches[1]] ?? '') === 'teammemberselect'
                ? 'entryteammemberprofilefield' : null;
        }
        return 'internal';
    }

    /**
     * One criterion in the stored form, or null to drop it.
     *
     * @param string $type field type
     * @param array $criterion [not, operator, value]
     * @param callable|null $mapuserid
     * @return array|null
     */
    private static function normalise_criterion(string $type, array $criterion, ?callable $mapuserid): ?array {
        [$not, $operator, $value] = $criterion;

        if ($type === 'teammemberselect') {
            switch ($operator) {
                case 'USER':
                case '':
                    return [$not, $operator, ''];
                case 'OTHER_USER':
                case 'ANY_OF':
                    $userids = [];
                    foreach ((array) $value as $userid) {
                        $userid = (int) $userid;
                        $userid = $userid > 0 && $mapuserid ? (int) $mapuserid($userid) : $userid;
                        if ($userid > 0) {
                            $userids[$userid] = $userid;
                        }
                    }
                    return $userids ? [$not, $operator, array_values($userids)] : null;
                default:
                    return [$not, $operator, $value];
            }
        }

        if ($type === 'entryteammemberprofilefield') {
            switch ($operator) {
                case 'LITERAL_VALUE':
                    $value = is_scalar($value) ? trim((string) $value) : '';
                    return $value !== '' ? [$not, $operator, $value] : null;
                case 'MY_PROFILE_FIELD':
                    return [$not, $operator, ''];
                default:
                    return null;
            }
        }

        return [$not, $operator, $value];
    }
}
