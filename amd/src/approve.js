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
let isListeningForEvents = false;

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
 * Parse one rendered approval toggle from the web service response.
 *
 * @param {string} controlhtml
 * @returns {?HTMLButtonElement}
 */
const createReplacementControl = (controlhtml) => {
    if (typeof controlhtml !== 'string' || !controlhtml.trim()) {
        return null;
    }

    const template = document.createElement('template');
    template.innerHTML = controlhtml.trim();

    const replacement = template.content.firstElementChild;
    return replacement instanceof HTMLButtonElement ? replacement : null;
};

/**
 * Reload the page when the switch cannot recover from an AJAX failure.
 */
const reloadPage = () => {
    window.location.reload();
};

/**
 * Extract one approval control from a delegated event.
 *
 * @param {Event} event
 * @returns {?HTMLButtonElement}
 */
const getApprovalControlFromEvent = (event) => {
    if (!(event.target instanceof Element)) {
        return null;
    }

    const control = event.target.closest(APPROVE_SELECTOR);
    return control instanceof HTMLButtonElement ? control : null;
};

/**
 * Handle delegated click events from approval switches.
 *
 * @param {MouseEvent} event
 */
const handleApprovalClick = (event) => {
    const control = getApprovalControlFromEvent(event);
    if (!control || control.disabled) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    void toggleApproval(control);
};

/**
 * Handle delegated keyboard toggling for approval switches.
 *
 * @param {KeyboardEvent} event
 */
const handleApprovalKeydown = (event) => {
    if (event.key !== ' ' && event.key !== 'Enter') {
        return;
    }

    const control = getApprovalControlFromEvent(event);
    if (!control || control.disabled) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    void toggleApproval(control);
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
            if (!data || typeof data.approved === 'undefined' || typeof data.controlhtml !== 'string') {
                reloadPage();
                return;
            }

            const replacement = createReplacementControl(data.controlhtml);
            if (!replacement) {
                reloadPage();
                return;
            }

            const shouldRestoreFocus = document.activeElement === control;
            control.replaceWith(replacement);
            updateApprovalControl(replacement, Number(data.approved) === 1);

            if (shouldRestoreFocus) {
                replacement.focus();
            }
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
 */
export const init = () => {
    if (isListeningForEvents) {
        return;
    }

    document.addEventListener('click', handleApprovalClick);
    document.addEventListener('keydown', handleApprovalKeydown);
    isListeningForEvents = true;
};
