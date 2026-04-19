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
 * Contains class mod_customfilter_base_form
 *
 * @package mod_datalynx
 * @copyright 2016 Thomas Niedermaier
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx\form;
use mod_datalynx\customfilter\datalynx;
use moodleform;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/**
 * Class mod_datalynx_customfilter_base_form
 *
 * @package mod_datalynx\customfilter
 */
abstract class datalynx_customfilter_base_form extends moodleform {
    /** @var ?object The customfilter instance being managed. */
    protected $customfilter = null;

    /**
     *
     * @var datalynx null
     */
    protected $dl = null;

    /**
     * mod_datalynx_customfilter_base_form constructor.
     *
     * @param mixed $dl The datalynx instance.
     * @param mixed $customfilter The customfilter object or null.
     * @param null $action
     * @param null $customdata
     * @param string $method
     * @param string $target
     * @param null $attributes
     * @param bool $editable
     */
    public function __construct(
        $dl,
        $customfilter,
        $action = null,
        $customdata = null,
        $method = 'post',
        $target = '',
        $attributes = null,
        $editable = true
    ) {
        $this->customfilter = $customfilter;
        $this->dl = $dl;

        parent::__construct($action, $customdata, $method, $target, $attributes, $editable);
    }
}
