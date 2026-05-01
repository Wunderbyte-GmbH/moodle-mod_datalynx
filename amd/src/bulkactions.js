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
 * Provides utility functions for bulk selection and actions in datalynx views.
 *
 * @module      mod_datalynx/bulkactions
 * @copyright   2025 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const getActionContext = () => document.activeElement instanceof Element ? document.activeElement : null;

const getScopeRoot = (group) => {
    const context = getActionContext();
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

export default {
    init() {
        // These global names are kept for backward compatibility with existing inline handlers.
        // eslint-disable-next-line dot-notation
        window['select_allnone'] = (group, checked) => {
            getScopeRoot(group).querySelectorAll(`input[type="checkbox"][name="${group}selector"]`).forEach(cb => {
                cb.checked = checked;
            });
        };

        // eslint-disable-next-line dot-notation
        window['bulk_action'] = (group, url, action) => {
            const checkboxes = getScopeRoot(group).querySelectorAll(
                `input[type="checkbox"][name="${group}selector"]:checked`
            );
            if (!checkboxes.length) {
                return;
            }
            const ids = Array.from(checkboxes).map(cb => cb.value).join(',');
            const targetUrl = new URL(url);
            targetUrl.searchParams.set(action, ids);
            window.location.href = targetUrl.toString();
        };
    }
};
