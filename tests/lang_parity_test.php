<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_datalynx;

use basic_testcase;

/**
 * The German language files carry exactly the keys of the English ones (see issue #251).
 *
 * @package mod_datalynx
 * @copyright 2026 onwards The datalynx contributors
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class lang_parity_test extends basic_testcase {
    /**
     * Read the string keys defined by a language file.
     *
     * @param string $file
     * @return string[]
     */
    private function get_keys(string $file): array {
        $string = [];
        if (file_exists($file)) {
            include($file);
        }
        $keys = array_keys($string);
        sort($keys);
        return $keys;
    }

    /**
     * The main component and every subplugin have the same keys in en and de.
     */
    public function test_german_keys_match_english(): void {
        global $CFG;

        $root = $CFG->dirroot . '/mod/datalynx';
        $dirs = [$root];
        foreach (['datalynxfield', 'datalynxview', 'datalynxrule', 'datalynxtool'] as $plugintype) {
            $dirs = array_merge($dirs, array_values(\core_component::get_plugin_list($plugintype)));
        }

        foreach ($dirs as $dir) {
            foreach (glob("$dir/lang/en/*.php") as $enfile) {
                $defile = str_replace('/lang/en/', '/lang/de/', $enfile);
                $name = substr($enfile, strlen($root) + 1);
                $this->assertFileExists($defile, "$name has no German counterpart");
                $this->assertSame($this->get_keys($enfile), $this->get_keys($defile), "$name: German keys differ");
            }
        }
    }
}
