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
 * Bulk selection and bulk actions for datalynx lists (entries, fields, views, filters, rules, presets).
 *
 * The controls are plain elements carrying data attributes; this module listens for them on the
 * document, so it also covers view content that is injected with AJAX later on:
 *
 * - data-datalynx-selectall="<group>" on a checkbox ticks or unticks every "<group>selector" checkbox.
 * - data-datalynx-bulkgroup="<group>" + data-datalynx-bulkaction="<action>" + data-datalynx-bulkurl="<url>"
 *   on a button sends the ticked ids of the group to the url as the <action> parameter.
 *
 * @module      mod_datalynx/bulkactions
 * @copyright   2025 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    SELECTALL: '[data-datalynx-selectall]',
    BULKACTION: '[data-datalynx-bulkaction]',
};

/** @type {boolean} */
let isListening = false;

/**
 * Find the list a control belongs to, so several views on one page do not affect each other.
 *
 * @param {string} group
 * @param {Element|null} context the control that was used
 * @returns {Document|Element}
 */
const getScopeRoot = (group, context) => {
    const rootSelectors = [
        '.datalynxview-Tabular',
        '.mod-datalynx-tabular-view-browser',
        '.mod-datalynx-grid-view-browser',
    ];

    for (const selector of rootSelectors) {
        const root = context?.closest(selector);
        if (root) {
            return root;
        }
    }

    const scopedCheckbox = context?.closest('table, [data-region], .entriesview')?.querySelector(
        `input[type="checkbox"][name="${group}selector"]`
    );
    if (scopedCheckbox) {
        return scopedCheckbox.closest('table, [data-region], .entriesview');
    }

    return document;
};

/**
 * Tick or untick every selector checkbox of a group.
 *
 * @param {string} group
 * @param {boolean} checked
 * @param {Element|null} context
 */
const selectAllNone = (group, checked, context) => {
    getScopeRoot(group, context).querySelectorAll(`input[type="checkbox"][name="${group}selector"]`).forEach(cb => {
        cb.checked = checked;
    });
};

/**
 * Send the ticked ids of a group to the action url.
 *
 * @param {string} group
 * @param {string} url
 * @param {string} action
 * @param {Element|null} context
 */
const bulkAction = (group, url, action, context) => {
    const checkboxes = getScopeRoot(group, context).querySelectorAll(
        `input[type="checkbox"][name="${group}selector"]:checked`
    );
    if (!checkboxes.length) {
        return;
    }
    const ids = Array.from(checkboxes).map(cb => cb.value).join(',');
    const targetUrl = new URL(url, window.location.href);
    targetUrl.searchParams.set(action, ids);
    window.location.href = targetUrl.toString();
};

export default {
    init() {
        if (isListening) {
            return;
        }
        isListening = true;

        document.addEventListener('change', (e) => {
            const control = e.target instanceof Element ? e.target.closest(SELECTORS.SELECTALL) : null;
            if (control) {
                selectAllNone(control.dataset.datalynxSelectall, control.checked, control);
            }
        });

        document.addEventListener('click', (e) => {
            const control = e.target instanceof Element ? e.target.closest(SELECTORS.BULKACTION) : null;
            if (!control) {
                return;
            }
            e.preventDefault();
            bulkAction(
                control.dataset.datalynxBulkgroup,
                control.dataset.datalynxBulkurl,
                control.dataset.datalynxBulkaction,
                control
            );
        });
    }
};
