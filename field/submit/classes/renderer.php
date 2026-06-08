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

namespace datalynxfield_submit;

use mod_datalynx\local\field\datalynxfield_renderer;
use MoodleQuickForm;
use stdClass;


/**
 * Renderer for the submit button field.
 *
 * @package    datalynxfield_submit
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends datalynxfield_renderer {
    /**
     * Search and collate field patterns that occur in given text.
     * Matches ##submit##, ##submit:arrow##, ##submit:text=Save##, etc.
     *
     * @param string $text
     * @return array
     */
    public function search($text) {
        $found = [];
        $matches = [];
        if (preg_match_all('/##submit(?::[^#]+)?##(?:@)?/', $text, $matches)) {
            $found = array_merge($found, $matches[0]);
        }
        return array_unique($found);
    }

    /**
     * Replacements for the submit button patterns.
     *
     * @param ?array $tags
     * @param ?stdClass $entry
     * @param ?array $options
     * @return array
     */
    public function replacements(?array $tags = null, ?stdClass $entry = null, ?array $options = null) {
        $edit = !empty($options['edit']) ? $options['edit'] : false;
        $replacements = [];

        foreach ($tags as $tag) {
            if ($edit) {
                $tagoptions = [];
                $clean = trim($tag, '#@'); // E.g., "submit:arrow:text=Save" or "submit:myformat".
                $parts = explode(':', $clean);
                // Shift off the prefix 'submit'.
                array_shift($parts);

                if (!empty($parts[0])) {
                    $format = \mod_datalynx\local\field_format\manager::get_format_by_name(
                        $this->field->dlx()->id(),
                        $parts[0]
                    );
                    if ($format && $format->get_fieldtype() === 'submit') {
                        $tagoptions['field_format'] = $format;
                    }
                }

                if (!isset($tagoptions['field_format'])) {
                    foreach ($parts as $part) {
                        $tagoptions[$part] = true;
                    }
                }

                $replacements[$tag] = ['', [[$this, 'display_edit'], [$entry, $tagoptions]]];
            } else {
                $replacements[$tag] = ['html', ''];
            }
        }

        return $replacements;
    }

    /**
     * Renders the submit button inside the form.
     *
     * @param MoodleQuickForm $mform
     * @param stdClass $entry
     * @param array $options
     */
    public function display_edit(&$mform, $entry, array $options = []) {
        $label = get_string('savechanges');
        $class = 'datalynx-custom-submit';

        $format = $options['field_format'] ?? null;
        if ($format) {
            $settings = $format->get_settings();
            if (!empty($settings['buttontext'])) {
                $label = $settings['buttontext'];
            }
            if (!empty($settings['cssclasses'])) {
                $class = $settings['cssclasses'];
            }
            if (!empty($settings['showarrow'])) {
                $label .= ' →';
            }
        } else {
            // Process options from pattern (e.g. ##submit:arrow:text=Create##).
            foreach (array_keys($options) as $option) {
                if ($option === 'arrow') {
                    $label .= ' →';
                } else if (strpos($option, 'text=') === 0) {
                    $label = substr($option, 5);
                    $label = str_replace('_', ' ', $label);
                } else if (strpos($option, 'class=') === 0) {
                    $class = substr($option, 6);
                    $class = str_replace('_', ' ', $class);
                }
            }
        }

        $mform->addElement('submit', 'submitbutton', $label, [
            'class' => $class,
        ]);
    }

    /**
     * Supported patterns.
     *
     * @return array
     */
    protected function patterns() {
        $cat = get_string('formactions', 'datalynx');
        return [
            '##submit##' => [true, $cat],
        ];
    }
}
