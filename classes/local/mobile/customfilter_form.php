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

namespace mod_datalynx\local\mobile;

use mod_datalynx\datalynx;
use mod_datalynx\local\filter\customfilter_controls;
use stdClass;

/**
 * The custom filter in the Moodle App: the transport only (mod_datalynx#270).
 *
 * Which custom filters a view offers, and the inputs a request of the app's browse page carries:
 * the form's inputs when the filter was just applied, otherwise the ones the page state carries
 * on. The controls, their inputs and what they search are the same as on the web
 * (\mod_datalynx\local\filter\customfilter_controls, customfilter_search).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class customfilter_form {
    /**
     * The custom filters a view offers: those whose ##customfilter:NAME## tag it contains, as the
     * web shows them.
     *
     * @param datalynx $dlx
     * @param stdClass $viewrecord
     * @return stdClass[] id => datalynx_customfilters record, in the order of the tags
     */
    public static function offered(datalynx $dlx, stdClass $viewrecord): array {
        $text = ($viewrecord->section ?? '') . ' ' . ($viewrecord->param2 ?? '');
        if (!preg_match_all('/##customfilter:(.+?)##/', $text, $matches)) {
            return [];
        }
        $byname = [];
        foreach (\mod_datalynx\local\definition_cache::get_records($dlx->id(), 'customfilters') as $record) {
            $byname[$record->name] ??= $record;
        }
        $offered = [];
        foreach (array_unique($matches[1]) as $name) {
            if (isset($byname[$name])) {
                $offered[(int) $byname[$name]->id] = $byname[$name];
            }
        }

        return $offered;
    }

    /**
     * The inputs the app sent, without the prefix: from the form when it was just applied,
     * otherwise as the page state carries them on (JSON).
     *
     * @param array $args the raw arguments of the content request
     * @param string $carried the JSON the page state carried
     * @return array input name => string
     */
    public static function inputs(array $args, string $carried): array {
        $inputs = [];
        if (!empty($args['cfapply'])) {
            foreach (customfilter_controls::inputs_from($args) as $name => $value) {
                if (is_scalar($value)) {
                    $inputs[$name] = trim((string) $value);
                }
            }
        } else {
            $decoded = $carried === '' ? null : json_decode($carried, true);
            foreach (is_array($decoded) ? $decoded : [] as $name => $value) {
                if (is_string($name) && is_scalar($value)) {
                    $inputs[$name] = (string) $value;
                }
            }
        }

        return array_filter($inputs, fn(string $value) => $value !== '');
    }
}
