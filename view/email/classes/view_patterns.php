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

namespace datalynxview_email;

use mod_datalynx\local\view\datalynxview_patterns as base_patterns;

/**
 * Email-specific view patterns.
 *
 * @package    datalynxview_email
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_patterns extends base_patterns {
    /**
     * Add notification-specific reference tags to the email template menu.
     *
     * @param bool $checkvisibility
     * @return array
     */
    protected function ref_patterns($checkvisibility = true) {
        $patterns = parent::ref_patterns($checkvisibility);
        $cat = get_string('reference', 'datalynx');

        $patterns['##notificationentryurl##'] = [true, $cat];
        $patterns['##notificationentrylink##'] = [true, $cat];
        $patterns['##notificationdatalynxurl##'] = [true, $cat];
        $patterns['##notificationdatalynxlink##'] = [true, $cat];

        return $patterns;
    }

    /**
     * Resolve notification-specific reference tags.
     *
     * @param string $tag
     * @param mixed $entry
     * @param array|null $options
     * @return string
     */
    protected function get_ref_replacements($tag, $entry = null, array $options = null) {
        switch ($tag) {
            case '##notificationentryurl##':
                return $options['notificationentryurl'] ?? '';
            case '##notificationentrylink##':
                return $options['notificationentrylink'] ?? '';
            case '##notificationdatalynxurl##':
                return $options['notificationdatalynxurl'] ?? '';
            case '##notificationdatalynxlink##':
                return $options['notificationdatalynxlink'] ?? '';
            default:
                return parent::get_ref_replacements($tag, $entry, $options);
        }
    }
}
