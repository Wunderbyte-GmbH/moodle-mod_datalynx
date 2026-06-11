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
 * Open the field-behavior editor in an AJAX modal (core dynamic form) instead of a full page.
 *
 * @module      mod_datalynx/behaviorform
 * @copyright   2026 David Bogner
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import {getString} from 'core/str';

/** @type {string} CSS selector for the add/edit behavior triggers. */
const TRIGGER_SELECTOR = '[data-action="datalynx-editbehavior"]';

/** @type {string} The dynamic form class to render in the modal. */
const FORM_CLASS = 'mod_datalynx\\form\\datalynxfield_behavior_dynamic_form';

/** @type {boolean} Guard so we only attach the global listener once. */
let initialised = false;

/**
 * Attach the click handler that opens the behavior editor modal.
 */
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest(TRIGGER_SELECTOR);
        if (!trigger) {
            return;
        }
        e.preventDefault();
        openBehaviorModal(trigger);
    });
};

/**
 * Build and show the modal form for the given trigger element.
 *
 * @param {HTMLElement} trigger The add/edit link that was clicked.
 */
const openBehaviorModal = async(trigger) => {
    const d = parseInt(trigger.getAttribute('data-d'), 10);
    const cmid = parseInt(trigger.getAttribute('data-cmid'), 10);
    const id = parseInt(trigger.getAttribute('data-behavior-id'), 10) || 0;

    const modal = new ModalForm({
        formClass: FORM_CLASS,
        args: {d, cmid, id},
        // No modalConfig.type: that would use the deprecated ModalFactory. ModalForm defaults to
        // the modern core/modal_save_cancel.
        modalConfig: {title: await getString('behavioreditmodaltitle', 'datalynx')},
        returnFocus: trigger,
    });

    // Refresh the behavior list once the behavior has been saved. Navigate with a clean GET URL
    // rather than reload() so the list never raises a "confirm form resubmission" dialog.
    modal.addEventListener(modal.events.FORM_SUBMITTED, () => {
        window.location.assign(window.location.pathname + '?d=' + d);
    });

    // When a condition source field is chosen, reload that row's operator/value widgets over AJAX
    // via the no-submit "reloadconditions" button - no page reload, no manual Reload click.
    modal.addEventListener('change', (e) => {
        const name = e.target.name || '';
        if (!/^condfield\d+$/.test(name)) {
            return;
        }
        const form = e.target.closest('form');
        const button = form && form.querySelector('[name="reloadconditions"]');
        if (button) {
            // Don't run client-side validation (e.g. required name) on a no-submit reload.
            window.skipClientValidation = true;
            modal.processNoSubmitButton(button);
        }
    });

    modal.show();
};
