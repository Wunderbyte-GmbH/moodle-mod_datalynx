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

import Ajax from 'core/ajax';

/** @type {boolean} */
let isListeningForUpdates = false;

/** @type {boolean} */
let isListeningForViewUpdates = false;

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
 * Handle click events from one approval switch.
 *
 * @param {MouseEvent} event
 */
const handleApprovalClick = (event) => {
    const control = event.currentTarget;
    if (!(control instanceof HTMLButtonElement) || control.disabled) {
        return;
    }

    event.preventDefault();
    void toggleApproval(control);
};

/**
 * Handle keyboard toggling for one approval switch.
 *
 * @param {KeyboardEvent} event
 */
const handleApprovalKeydown = (event) => {
    if (event.key !== ' ' && event.key !== 'Enter') {
        return;
    }

    const control = event.currentTarget;
    if (!(control instanceof HTMLButtonElement) || control.disabled) {
        return;
    }

    event.preventDefault();
    void toggleApproval(control);
};

/**
 * Bind approval switch handlers inside one DOM root.
 *
 * @param {ParentNode} root
 */
const initialiseApprovalControls = (root) => {
    if (!('querySelectorAll' in root)) {
        return;
    }

    root.querySelectorAll(APPROVE_SELECTOR).forEach((control) => {
        if (!(control instanceof HTMLButtonElement) || control.dataset.approvalInitialized === '1') {
            return;
        }

        control.dataset.approvalInitialized = '1';
        control.addEventListener('click', handleApprovalClick);
        control.addEventListener('keydown', handleApprovalKeydown);
    });
};

/**
 * Start listening for refreshed AJAX browse regions.
 */
const listenForViewUpdates = () => {
    if (isListeningForViewUpdates) {
        return;
    }

    document.addEventListener('mod_datalynx:viewContentUpdated', (event) => {
        const root = event.detail?.target;
        if (root instanceof HTMLElement) {
            initialiseApprovalControls(root);
        }
    });
    isListeningForViewUpdates = true;
};

/**
 * Toggle approval for one entry via AJAX.
 *
 * @param {HTMLButtonElement} control
 * @returns {Promise<void>}
 */
const toggleApproval = (control) => {
    const {entryid, d, view} = control.dataset;

    if (!entryid || !d) {
        reloadPage();
        return Promise.resolve();
    }

    control.disabled = true;
    control.setAttribute('aria-busy', 'true');

    return Ajax.call([{
        methodname: 'mod_datalynx_toggle_entry_approval',
        args: {
            entryid: Number(entryid),
            d: Number(d),
            viewid: Number(view || 0),
            action: 'toggle-approval'
        }
    }])[0]
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
 * Initialize the module.
 *
 * @param {string|ParentNode} root
 */
export const init = (root = document) => {
    const container = typeof root === 'string' ? document.querySelector(root) : root;
    if (!container) {
        return;
    }

    initialiseApprovalControls(container);
    listenForViewUpdates();

    if (isListeningForUpdates) {
        return;
    }

    initialiseApprovalControls(document);
    isListeningForUpdates = true;
};
