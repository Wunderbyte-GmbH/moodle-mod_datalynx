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

namespace mod_datalynx\output;

use core\external\exporter;
use renderer_base;

/**
 * Exporter for the Report view browse payload.
 *
 * This exporter is the single source of truth for both the Mustache template
 * context and the web service return structure ({@see get_read_structure()}).
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_view_browser extends exporter {
    /**
     * Constructor.
     *
     * @param array $payload The structured report payload built by the Report view.
     * @param \context $context The module context used for text formatting.
     */
    public function __construct(array $payload, \context $context) {
        parent::__construct($payload, ['context' => $context]);
    }

    /**
     * Related objects definition.
     *
     * @return array
     */
    protected static function define_related(): array {
        return ['context' => 'context'];
    }

    /**
     * Reusable definition of one option-count cell.
     *
     * @return array
     */
    protected static function option_cell_definition(): array {
        return [
            'count' => ['type' => PARAM_INT],
        ];
    }

    /**
     * Reusable definition of the user column sub-structure.
     *
     * @return array
     */
    protected static function user_definition(): array {
        return [
            'fullname' => ['type' => PARAM_TEXT],
            'email' => ['type' => PARAM_TEXT, 'default' => ''],
            'hasemail' => ['type' => PARAM_BOOL, 'default' => false],
        ];
    }

    /**
     * Definition of the additional (display only) properties.
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        $optioncell = ['type' => self::option_cell_definition(), 'multiple' => true];
        $user = ['type' => self::user_definition()];

        $datarow = [
            'type' => [
                'user' => $user,
                'month' => ['type' => PARAM_RAW, 'default' => ''],
                'totalentries' => ['type' => PARAM_INT],
                'optioncells' => $optioncell,
                'notyetanswered' => ['type' => PARAM_INT],
            ],
            'multiple' => true,
        ];

        $sectionrow = [
            'type' => [
                'user' => $user,
                'totalentries' => ['type' => PARAM_INT],
                'optioncells' => $optioncell,
                'notyetanswered' => ['type' => PARAM_INT],
            ],
            'multiple' => true,
        ];

        return [
            'datalynxid' => ['type' => PARAM_INT],
            'viewid' => ['type' => PARAM_INT],
            'viewname' => ['type' => PARAM_TEXT],
            'viewtype' => ['type' => PARAM_ALPHA],
            'ismonthly' => ['type' => PARAM_BOOL],
            'hasdata' => ['type' => PARAM_BOOL],
            'hasrows' => ['type' => PARAM_BOOL],
            'hasmonthlysections' => ['type' => PARAM_BOOL],
            'hasoverall' => ['type' => PARAM_BOOL],
            'userlabel' => ['type' => PARAM_TEXT],
            'monthlabel' => ['type' => PARAM_TEXT],
            'totallabel' => ['type' => PARAM_TEXT],
            'notyetansweredlabel' => ['type' => PARAM_TEXT],
            'aggregationsumlabel' => ['type' => PARAM_TEXT],
            'optioncolumns' => [
                'type' => ['label' => ['type' => PARAM_TEXT]],
                'multiple' => true,
            ],
            'rows' => $datarow,
            'monthlysections' => [
                'type' => [
                    'heading' => ['type' => PARAM_TEXT],
                    'rows' => $sectionrow,
                    'totalentries' => ['type' => PARAM_INT],
                    'optioncells' => $optioncell,
                    'notyetanswered' => ['type' => PARAM_INT],
                ],
                'multiple' => true,
            ],
            'overall' => [
                'type' => [
                    'heading' => ['type' => PARAM_TEXT],
                    'totalentries' => ['type' => PARAM_INT],
                    'optioncells' => $optioncell,
                    'notyetanswered' => ['type' => PARAM_INT],
                ],
            ],
            'hascharts' => ['type' => PARAM_BOOL, 'default' => false],
            'charts' => [
                'type' => [
                    'uniqid' => ['type' => PARAM_ALPHANUMEXT],
                    'title' => ['type' => PARAM_TEXT],
                    'chartdata' => ['type' => PARAM_RAW],
                    'withtable' => ['type' => PARAM_BOOL, 'default' => true],
                ],
                'multiple' => true,
                'default' => [],
            ],
            'scope' => [
                'type' => self::scope_definition(),
            ],
            'emptycontent' => ['type' => PARAM_RAW],
        ];
    }

    /**
     * Definition of the date-scoping control sub-structure.
     *
     * @return array
     */
    protected static function scope_definition(): array {
        $selectoption = [
            'value' => ['type' => PARAM_RAW],
            'label' => ['type' => PARAM_TEXT],
            'selected' => ['type' => PARAM_BOOL, 'default' => false],
        ];

        return [
            'fieldlabel' => ['type' => PARAM_TEXT],
            'modelabel' => ['type' => PARAM_TEXT],
            'yearlabel' => ['type' => PARAM_TEXT],
            'monthlabel' => ['type' => PARAM_TEXT],
            'fromlabel' => ['type' => PARAM_TEXT],
            'tolabel' => ['type' => PARAM_TEXT],
            'field' => ['type' => PARAM_ALPHA],
            'mode' => ['type' => PARAM_ALPHA],
            'from' => ['type' => PARAM_INT, 'default' => 0],
            'to' => ['type' => PARAM_INT, 'default' => 0],
            'fromdate' => ['type' => PARAM_RAW, 'default' => ''],
            'todate' => ['type' => PARAM_RAW, 'default' => ''],
            'showyear' => ['type' => PARAM_BOOL, 'default' => false],
            'showmonth' => ['type' => PARAM_BOOL, 'default' => false],
            'showrange' => ['type' => PARAM_BOOL, 'default' => false],
            'fields' => ['type' => $selectoption, 'multiple' => true],
            'modes' => ['type' => $selectoption, 'multiple' => true],
            'years' => ['type' => $selectoption, 'multiple' => true],
            'months' => ['type' => $selectoption, 'multiple' => true],
        ];
    }
}
