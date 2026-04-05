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

namespace mod_datalynx;
use rating;

/**
 * Extends the core rating class to provide datalynx-specific aggregate value rendering.
 *
 * @package mod_datalynx
 * @copyright 2025 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datalynx_rating extends rating {
    /**
     * Returns this ratings aggregate value
     *
     * @return string
     */
    public function get_aggregate_value($aggregation) {
        $aggregate = isset($this->aggregate[$aggregation]) ? $this->aggregate[$aggregation] : '';

        if ($aggregate && $aggregation != RATING_AGGREGATE_COUNT) {
            if ($aggregation != RATING_AGGREGATE_SUM && !$this->settings->scale->isnumeric) {
                // Round aggregate as we're using it as an index.
                $aggregate = $this->settings->scale->scaleitems[round($aggregate)];
            } else {
                // Aggregation is SUM or the scale is numeric.
                $aggregate = round($aggregate, 1);
            }
        }
        return $aggregate;
    }
}
