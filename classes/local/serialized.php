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

use stdClass;

/**
 * The one reader of the values datalynx stores with serialize().
 *
 * Filters (customsort, customsearch), behaviours (visibleto, editableby), view patterns and
 * settings, legacy rule params and the message queue keep the serialized format, so existing
 * instances, older backups and the upgrade steps read their data as they always did. Reading
 * never instantiates a class other than stdClass and the ones a caller names explicitly: a
 * stored value cannot inject an object.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class serialized {
    /**
     * The value a serialized string holds.
     *
     * @param mixed $value the stored string
     * @param string[] $classes classes besides stdClass the value may hold
     * @return mixed false when empty or not readable
     */
    public static function decode($value, array $classes = []) {
        if (!is_string($value) || $value === '') {
            return false;
        }
        $allowed = array_merge([stdClass::class], $classes);

        // The safe form of unserialize(): no class outside $allowed is instantiated.
        return @unserialize($value, ['allowed_classes' => $allowed]); // phpcs:ignore moodle.PHP.ForbiddenFunctions.Found
    }

    /**
     * The array a serialized string holds.
     *
     * @param mixed $value the stored string, or an array already decoded
     * @return array empty when empty, not readable or not an array
     */
    public static function to_array($value): array {
        if (is_array($value)) {
            return $value;
        }
        $decoded = self::decode($value);

        return is_array($decoded) ? $decoded : [];
    }
}
