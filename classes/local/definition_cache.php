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

namespace mod_datalynx\local;

use cache;
use stdClass;

/**
 * Application cache for the definition records of a datalynx instance.
 *
 * Fields, views, filters, custom filters, behaviors, field layouts, field formats and rules are
 * read on every page and every web service call, but change only through the management pages,
 * presets and restore. One cache entry per instance holds the records of all eight tables; it is
 * filled with one query per table on a miss.
 *
 * Every code path that writes one of these tables must call {@see invalidate()} with the
 * instance id. Code that writes them directly with $DB (tests, CLI scripts) has to do the same,
 * or it reads its own stale definitions afterwards.
 *
 * Records come back as fresh stdClass objects on every call, so callers may change them.
 *
 * Reads that need an SQL ordering or anything other than the plain records of one instance
 * (the sortable management tables, lookups by id across instances) still go to the database.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class definition_cache {
    /** @var string[] The cached tables, without the datalynx_ prefix. */
    public const TABLES = [
        'fields', 'views', 'filters', 'customfilters', 'behaviors', 'renderers', 'field_formats', 'rules',
    ];

    /** @var string Key of the pending list in the request cache. */
    private const PENDING = 'pending';

    /**
     * The records of one definition table of an instance, keyed by id in ascending id order.
     *
     * @param int $dataid The datalynx instance id.
     * @param string $table One of {@see TABLES}, with or without the datalynx_ prefix.
     * @return stdClass[]
     */
    public static function get_records(int $dataid, string $table): array {
        $table = self::table($table);
        $records = [];
        foreach (self::load($dataid)[$table] as $id => $row) {
            $records[$id] = (object) $row;
        }
        return $records;
    }

    /**
     * One record of a definition table of an instance.
     *
     * @param int $dataid The datalynx instance id.
     * @param string $table One of {@see TABLES}, with or without the datalynx_ prefix.
     * @param int $id The record id.
     * @return stdClass|null Null when the instance has no record with that id.
     */
    public static function get_record(int $dataid, string $table, int $id): ?stdClass {
        $row = self::load($dataid)[self::table($table)][$id] ?? null;
        return $row === null ? null : (object) $row;
    }

    /**
     * The record of a definition table of an instance with the given name; the oldest one wins a duplicate name.
     *
     * The comparison is exact. Callers that relied on a case-insensitive database collation
     * fall back to the database on a miss.
     *
     * @param int $dataid The datalynx instance id.
     * @param string $table One of {@see TABLES}, with or without the datalynx_ prefix.
     * @param string $name
     * @return stdClass|null
     */
    public static function get_record_by_name(int $dataid, string $table, string $name): ?stdClass {
        foreach (self::load($dataid)[self::table($table)] as $row) {
            if ((string) ($row['name'] ?? '') === $name) {
                return (object) $row;
            }
        }
        return null;
    }

    /**
     * Forget the cached definitions of an instance. Call after writing any of the cached tables.
     *
     * Inside a database transaction the instance is also remembered for this request: until the
     * transaction is over its definitions are read from the database and not cached, because
     * they may still be rolled back, and afterwards the cache entry is dropped once more in case
     * another request refilled it from the state before the commit.
     *
     * @param int $dataid The datalynx instance id.
     * @return void
     */
    public static function invalidate(int $dataid): void {
        global $DB;
        if ($dataid <= 0) {
            return;
        }
        self::cache()->delete($dataid);
        field_format\manager::reset_static_caches();
        if ($DB->is_transaction_started()) {
            $pending = self::pending();
            $pending[$dataid] = $dataid;
            self::pendingcache()->set(self::PENDING, $pending);
        }
    }

    /**
     * Forget the cached definitions of the instance a definition record belongs to.
     *
     * For write paths that only know the record id. Call it before deleting the record.
     *
     * @param string $table One of {@see TABLES}, with or without the datalynx_ prefix.
     * @param int $id The record id.
     * @return void
     */
    public static function invalidate_for_record(string $table, int $id): void {
        global $DB;
        $dataid = (int) $DB->get_field('datalynx_' . self::table($table), 'dataid', ['id' => $id]);
        self::invalidate($dataid);
    }

    /**
     * Forget the cached definitions of all instances.
     *
     * @return void
     */
    public static function purge(): void {
        self::cache()->purge();
        self::pendingcache()->purge();
        field_format\manager::reset_static_caches();
    }

    /**
     * The cached definitions of an instance, loading them on a miss.
     *
     * @param int $dataid
     * @return array<string, array<int, array>> Rows as arrays, per table, keyed by id.
     */
    private static function load(int $dataid): array {
        global $CFG, $DB;

        // Upgrade steps write the definition tables directly; nothing is cached while they run.
        if (!empty($CFG->upgraderunning) || during_initial_install()) {
            return self::read($dataid);
        }

        $pending = self::pending();
        if ($pending) {
            if ($DB->is_transaction_started()) {
                if (isset($pending[$dataid])) {
                    return self::read($dataid);
                }
            } else {
                self::cache()->delete_many(array_values($pending));
                self::pendingcache()->delete(self::PENDING);
            }
        }

        $cache = self::cache();
        $definitions = $cache->get($dataid);
        if ($definitions === false) {
            $definitions = self::read($dataid);
            $cache->set($dataid, $definitions);
        }
        return $definitions;
    }

    /**
     * Read the definitions of an instance from the database.
     *
     * @param int $dataid
     * @return array<string, array<int, array>> Rows as arrays, per table, keyed by id.
     */
    private static function read(int $dataid): array {
        global $DB;
        $definitions = [];
        foreach (self::TABLES as $table) {
            $definitions[$table] = [];
            foreach ($DB->get_records('datalynx_' . $table, ['dataid' => $dataid], 'id ASC') as $id => $record) {
                $definitions[$table][$id] = (array) $record;
            }
        }
        return $definitions;
    }

    /**
     * The instances changed inside a transaction in this request.
     *
     * @return int[]
     */
    private static function pending(): array {
        return self::pendingcache()->get(self::PENDING) ?: [];
    }

    /**
     * The table name without the datalynx_ prefix, checked against the cached tables.
     *
     * @param string $table
     * @return string
     */
    private static function table(string $table): string {
        $table = preg_replace('/^datalynx_/', '', $table);
        if (!in_array($table, self::TABLES, true)) {
            throw new \coding_exception("Table datalynx_{$table} is not a cached definition table");
        }
        return $table;
    }

    /**
     * The definition cache.
     *
     * @return \core_cache\application_cache|cache
     */
    private static function cache() {
        return cache::make('mod_datalynx', 'definitions');
    }

    /**
     * The request cache holding the instances changed inside a transaction.
     *
     * @return \core_cache\request_cache|cache
     */
    private static function pendingcache() {
        return cache::make('mod_datalynx', 'definitionspending');
    }
}
