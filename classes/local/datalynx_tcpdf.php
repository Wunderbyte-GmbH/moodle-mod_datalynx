<?php

namespace mod_datalynx\local;
use setasign;
use stdClass;

/**
 * Extend the TCPDF class to create custom Header and Footer.
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