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

use setasign;
use stdClass;

defined('MOODLE_INTERNAL') || die();

pdf_library::ensure_fpdi_loaded();

/**
 * Extend the TCPDF class to create custom Header and Footer.
 * @package mod_datalynx
 * @copyright 2025 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datalynx_tcpdf extends setasign\Fpdi\Tcpdf\Fpdi {
    /**
     * @var stdClass Settings for the PDF
     */
    protected $dlsettings;

    /**
     * Constructor
     *
     * @param stdClass $settings Settings object
     */
    public function __construct($settings) {
        parent::__construct($settings->orientation, $settings->unit, $settings->format);
        $this->dlsettings = $settings;
    }

    // Page header.
    public function Header() { // phpcs:ignore  @codingStandardsIgnoreLine
        // Adjust X to override left margin.
        $x = $this->GetX();
        $this->SetX($this->dlsettings->header->marginleft);
        if (!empty($this->header_string)) {
            $text = $this->set_page_numbers($this->header_string);
            $this->writeHtml($text);
        }
        // Reset X to original.
        $this->SetX($x);
    }

    // Page footer.
    public function Footer() { // phpcs:ignore  @codingStandardsIgnoreLine
        if (!empty($this->dlsettings->footer->text)) {
            $text = $this->set_page_numbers($this->dlsettings->footer->text);
            $this->writeHtml($text);
        }
    }

    // Phpcs:enable.

    /**
     * Set page numbers in text
     *
     * @param string $text
     * @return string
     */
    protected function set_page_numbers($text) {
        $replacements = ['##pagenumber##' => $this->getAliasNumPage(),
                '##totalpages##' => $this->getAliasNbPages()];
        $text = str_replace(array_keys($replacements), $replacements, $text);
        return $text;
    }
}
