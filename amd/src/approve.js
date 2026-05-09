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
 * Javascript to handle approval of entries.
 *
 * @module      mod_datalynx/approve
 * @copyright   2023 onwards
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Config from 'core/config';

/** @type {boolean} */
let isListeningForUpdates = false;

/**
 * CSS selector for one approval control.
 *
 * @type {string}
 */
const APPROVE_SELECTOR = '.datalynxfield_approve[role="switch"]';

/**
 * Return the localized label for the supplied state.
 *
 * @param {HTMLButtonElement} control
 * @param {boolean} isApproved
 * @returns {string}
 */
const getStateLabel = (control, isApproved) => isApproved
    ? control.dataset.approvedLabel || ''
    : control.dataset.notApprovedLabel || '';

/**
 * Sync the switch UI with the persisted approval state.
 *
 * @param {HTMLButtonElement} control
 * @param {boolean} isApproved
 */
const updateApprovalControl = (control, isApproved) => {
    const label = control.querySelector('.datalynxfield_approve-label');
    const statelabel = getStateLabel(control, isApproved);

    control.dataset.approved = isApproved ? '1' : '0';
    control.setAttribute('aria-checked', isApproved ? 'true' : 'false');
    control.setAttribute('aria-label', statelabel);
    control.setAttribute('title', statelabel);

    if (label) {
        label.textContent = statelabel;
    }
};

/**
 * Reload the page when the switch cannot recover from an AJAX failure.
 */
const reloadPage = () => {
    window.location.reload();
};

/**
 * Toggle approval for one entry via AJAX.
 *
 * @param {HTMLButtonElement} control
 * @returns {Promise<void>}
 */
const toggleApproval = (control) => {
    const {entryid, d, view, sesskey} = control.dataset;

    if (!entryid || !d || !sesskey) {
        reloadPage();
        return Promise.resolve();
    }

    control.disabled = true;
    control.setAttribute('aria-busy', 'true');

    return fetch(`${Config.wwwroot}/mod/datalynx/field/approve/ajax.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
            entryid,
            d,
            view: view || '0',
            sesskey,
            action: 'toggle-approval'
        })
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }

            return response.json();
        })
        .then((data) => {
            if (!data || typeof data.approved === 'undefined') {
                reloadPage();
                return;
            }

            updateApprovalControl(control, Number(data.approved) === 1);
        })
        .catch(() => {
            reloadPage();
        })
        .finally(() => {
            control.disabled = false;
            control.removeAttribute('aria-busy');
        });
};

/**
 * Handle click events from approval switches.
 *
 * @param {MouseEvent} event
 */
const handleApprovalClick = (event) => {
    const target = event.target instanceof Element
        ? event.target
        : event.target instanceof Node
            ? event.target.parentElement
            : null;
    if (!target) {
        return;
    }

    const control = target.closest(APPROVE_SELECTOR);
    if (!(control instanceof HTMLButtonElement) || control.disabled) {
        return;
    }

    event.preventDefault();
    void toggleApproval(control);
};

/**
 * Initialize the module.
 */
export const init = () => {
    if (isListeningForUpdates) {
        return;
    }

    document.addEventListener('click', handleApprovalClick);
    isListeningForUpdates = true;
};
