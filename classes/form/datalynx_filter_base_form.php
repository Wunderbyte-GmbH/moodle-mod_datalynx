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

namespace mod_datalynx\form;
use datalynx;
use moodleform;

/**
 * Base form class for datalynx filter forms.
 *
 * @package mod_datalynx
 * @copyright 2025 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class datalynx_filter_base_form extends moodleform {
    use filter_form_elements;

    /** @var mixed The current filter object. */
    protected $filter = null;
    /** @var mixed The custom filter object. */
    protected $customfilter = null;

    /**
     *
     * @var datalynx null
     */
    protected $dlx = null;

    /**
     * Constructs the filter base form and initialises filter/datalynx properties.
     *
     * @param datalynx $dlx
     * @param mixed $filter
     * @param ?string $action
     * @param mixed $customdata
     * @param string $method
     * @param string $target
     * @param ?array $attributes
     * @param bool $editable
     * @param bool $customfilter
     */
    public function __construct(
        $dlx,
        $filter,
        $action = null,
        $customdata = null,
        $method = 'post',
        $target = '',
        $attributes = null,
        $editable = true,
        $customfilter = false
    ) {
        $this->filter = $filter;
        $this->customfilter = $customfilter;
        $this->dlx = $dlx;

        parent::__construct($action, $customdata, $method, $target, $attributes, $editable);
    }

    /**
     * Returns the form HTML as a string.
     *
     * @return string
     */
    public function html() {
        return $this->_form->toHtml();
    }
}
