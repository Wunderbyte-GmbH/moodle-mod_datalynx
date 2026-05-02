<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_datalynx\local;

use coding_exception;

/**
 * Loads the PDF dependencies required by Datalynx across Moodle branches.
 *
 * @package mod_datalynx
 * @copyright 2026 David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdf_library {
    /**
     * Ensure the FPDI/TCPDF dependency chain is available.
     *
     * @return void
     */
    public static function ensure_fpdi_loaded(): void {
        global $CFG;

        require_once($CFG->libdir . '/pdflib.php');

        if (class_exists(\setasign\Fpdi\Tcpdf\Fpdi::class)) {
            return;
        }

        $autoloaders = [
            $CFG->dirroot . '/vendor/autoload.php',
            $CFG->dirroot . '/mod/assign/feedback/editpdf/fpdi/src/autoload.php',
            $CFG->dirroot . '/mod/assign/feedback/editpdf/fpdi/autoload.php',
        ];

        foreach ($autoloaders as $autoloadfile) {
            if (!file_exists($autoloadfile)) {
                continue;
            }

            require_once($autoloadfile);
            if (class_exists(\setasign\Fpdi\Tcpdf\Fpdi::class)) {
                return;
            }
        }

        throw new coding_exception('FPDI library is not available for the Datalynx PDF view.');
    }
}
