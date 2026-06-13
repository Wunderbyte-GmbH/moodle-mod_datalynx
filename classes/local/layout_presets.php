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

namespace mod_datalynx\local;

use mod_datalynx\local\field\datalynxfield_layout;

/**
 * Curated Bootstrap-5 presets for the field-layout editor.
 *
 * Each preset is a starting point the author can apply in one click and then hand-edit. Snippets use
 * only the render tokens the server substitutes ({@see datalynxfield_layout}): #value (display, empty,
 * not-visible templates) and #input (edit template). #name is intentionally NOT used because the
 * renderer does not substitute it.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class layout_presets {
    /** @var string Display snippet: plain Bootstrap card. */
    const CARD_BASIC = '<div class="card"><div class="card-body">#value</div></div>';

    /** @var string Display snippet: card with a soft shadow. */
    const CARD_SHADOW = '<div class="card shadow-sm"><div class="card-body">#value</div></div>';

    /** @var string Display snippet: info alert/callout. */
    const ALERT_INFO = '<div class="alert alert-info mb-0" role="alert"><i class="fa fa-circle-info me-1"></i>#value</div>';

    /** @var string Display snippet: left accent border. */
    const BORDER_START = '<div class="border-start border-4 border-primary ps-3 py-1">#value</div>';

    /** @var string Display snippet: primary badge. */
    const BADGE_PRIMARY = '<span class="badge bg-primary">#value</span>';

    /** @var string Display snippet: success pill. */
    const PILL_SUCCESS = '<span class="badge rounded-pill bg-success">#value</span>';

    /** @var string Display snippet: icon followed by the value. */
    const ICON_VALUE = '<span><i class="fa fa-check-circle text-success me-1"></i>#value</span>';

    /** @var string Display snippet: muted bordered chip. */
    const CHIP_MUTED = '<span class="badge bg-light text-dark border">#value</span>';

    /** @var string Display snippet: lead paragraph. */
    const LEAD = '<p class="lead mb-0">#value</p>';

    /** @var string Display snippet: blockquote. */
    const BLOCKQUOTE = '<blockquote class="blockquote border-start border-3 ps-3 mb-0"><p class="mb-0">#value</p></blockquote>';

    /** @var string Display snippet: single list-group item. */
    const LIST_GROUP = '<ul class="list-group"><li class="list-group-item">#value</li></ul>';

    /** @var string Display snippet: heading. */
    const HEADING = '<h4 class="mb-1">#value</h4>';

    /** @var string Edit snippet: input group with a leading icon. */
    const INPUT_GROUP_ICON =
        '<div class="input-group"><span class="input-group-text"><i class="fa fa-pencil"></i></span>#input</div>';

    /** @var string Edit snippet: input with muted help text below. */
    const INPUT_HELP = '#input<small class="form-text text-muted d-block">…</small>';

    /** @var string Edit snippet: input group with a currency prefix. */
    const INPUT_PREFIX_EURO = '<div class="input-group"><span class="input-group-text">€</span>#input</div>';

    /** @var string Edit snippet: labelled input block. */
    const INPUT_LABELLED = '<div class="mb-2"><label class="form-label fw-semibold">…</label>#input</div>';

    /** @var string No-value snippet: a muted placeholder dash. */
    const NOVALUE_DASH = '<span class="text-muted fst-italic">—</span>';

    /**
     * Return the full preset catalogue for the editor, with localized labels.
     *
     * @return array {
     *     families: array<int, array{key:string, label:string}>,
     *     presets:  array<int, array{id,family,familylabel,label,scope,templates}>,
     *     wholelooks: array<int, array{id,label,templates}>
     * }
     */
    public static function all(): array {
        return [
            'families' => [
                ['key' => 'cards', 'label' => get_string('presetfamily_cards', 'datalynx')],
                ['key' => 'inline', 'label' => get_string('presetfamily_inline', 'datalynx')],
                ['key' => 'edit', 'label' => get_string('presetfamily_edit', 'datalynx')],
                ['key' => 'typographic', 'label' => get_string('presetfamily_typographic', 'datalynx')],
            ],
            'presets' => [
                // Cards & panels (display).
                self::preset('card_basic', 'cards', 'display', ['display' => self::CARD_BASIC]),
                self::preset('card_shadow', 'cards', 'display', ['display' => self::CARD_SHADOW]),
                self::preset('alert_info', 'cards', 'display', ['display' => self::ALERT_INFO]),
                self::preset('border_start', 'cards', 'display', ['display' => self::BORDER_START]),
                // Inline accents (display).
                self::preset('badge_primary', 'inline', 'display', ['display' => self::BADGE_PRIMARY]),
                self::preset('pill_success', 'inline', 'display', ['display' => self::PILL_SUCCESS]),
                self::preset('icon_value', 'inline', 'display', ['display' => self::ICON_VALUE]),
                self::preset('chip_muted', 'inline', 'display', ['display' => self::CHIP_MUTED]),
                // Edit-form styles (edit).
                self::preset('input_group_icon', 'edit', 'edit', ['edit' => self::INPUT_GROUP_ICON]),
                self::preset('input_help', 'edit', 'edit', ['edit' => self::INPUT_HELP]),
                self::preset('input_prefix_euro', 'edit', 'edit', ['edit' => self::INPUT_PREFIX_EURO]),
                self::preset('input_labelled', 'edit', 'edit', ['edit' => self::INPUT_LABELLED]),
                // Typographic (display).
                self::preset('lead', 'typographic', 'display', ['display' => self::LEAD]),
                self::preset('blockquote', 'typographic', 'display', ['display' => self::BLOCKQUOTE]),
                self::preset('list_group', 'typographic', 'display', ['display' => self::LIST_GROUP]),
                self::preset('heading', 'typographic', 'display', ['display' => self::HEADING]),
            ],
            'wholelooks' => [
                self::wholelook('look_card', [
                    'display' => self::CARD_BASIC,
                    'edit' => self::INPUT_GROUP_ICON,
                    'novalue' => self::NOVALUE_DASH,
                ]),
                self::wholelook('look_badge', [
                    'display' => self::BADGE_PRIMARY,
                    'edit' => self::INPUT_HELP,
                    // Radio-only outcome: when empty, show nothing.
                    'novalue' => datalynxfield_layout::NO_VALUE_SHOW_NOTHING,
                ]),
                self::wholelook('look_quote', [
                    'display' => self::BLOCKQUOTE,
                    'edit' => self::INPUT_LABELLED,
                    // Radio-only outcome: when empty, reuse the display template.
                    'novalue' => datalynxfield_layout::NO_VALUE_SHOW_DISPLAY_MODE_TEMPLATE,
                ]),
            ],
        ];
    }

    /**
     * Build a single-scope preset record.
     *
     * @param string $id Unique preset id (also the lang string suffix preset_<id>).
     * @param string $family One of cards|inline|edit|typographic.
     * @param string $scope The template this preset targets: display|edit|novalue|notvisible.
     * @param array $templates Map of template key => HTML snippet (or a signifier for radio-only).
     * @return array
     */
    private static function preset(string $id, string $family, string $scope, array $templates): array {
        return [
            'id' => $id,
            'family' => $family,
            'familylabel' => get_string('presetfamily_' . $family, 'datalynx'),
            'label' => get_string('preset_' . $id, 'datalynx'),
            'scope' => $scope,
            'templates' => $templates,
        ];
    }

    /**
     * Build a coordinated "whole look" record that fills several templates at once.
     *
     * @param string $id Unique id (also the lang string suffix preset_<id>).
     * @param array $templates Map of template key (display|edit|novalue|...) => HTML snippet or signifier.
     * @return array
     */
    private static function wholelook(string $id, array $templates): array {
        return [
            'id' => $id,
            'label' => get_string('preset_' . $id, 'datalynx'),
            'templates' => $templates,
        ];
    }
}
