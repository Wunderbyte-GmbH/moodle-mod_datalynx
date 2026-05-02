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

use renderable;
use templatable;

/**
 * Renderable wrapper for the Tabular browser payload.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tabular_view_browser implements renderable, templatable {
    /** @var array */
    protected array $payload;

    /**
     * Constructor.
     *
     * @param array $payload
     */
    public function __construct(array $payload) {
        $this->payload = $payload;
    }

    /**
     * Export the payload for Mustache rendering.
     *
     * @param mixed $output
     * @return array
     */
    public function export_for_template($output): array {
        return $this->payload;
    }
}
