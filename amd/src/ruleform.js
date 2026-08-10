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
 * Open the rule editor in an AJAX modal (core dynamic form) instead of a full page.
 *
 * @module      mod_datalynx/ruleform
 * @copyright   2026 David Bogner
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import {getString} from 'core/str';

/** @type {string} CSS selector for the add/edit rule triggers. */
const TRIGGER_SELECTOR = '[data-action="datalynx-editrule"]';

/** @type {string} CSS selector for the add rule dropdown select. */
const ADD_SELECT_SELECTOR = '.ruleadd form select';

/** @type {boolean} Guard so we only attach the global listener once. */
let initialised = false;

/**
 * Attach the click handler that opens the rule editor modal.
 */
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;

    // Intercept and handle rule addition dropdown.
    const select = document.querySelector(ADD_SELECT_SELECTOR);
    if (select) {
        select.removeAttribute('onchange');
        select.addEventListener('change', (e) => {
            const type = e.target.value;
            if (!type) {
                return;
            }
            e.preventDefault();

            const form = e.target.closest('form');
            const dInput = form.querySelector('input[name="d"]');
            const cmidInput = form.querySelector('input[name="cmid"]');
            const d = dInput ? parseInt(dInput.value, 10) : 0;
            const cmid = cmidInput ? parseInt(cmidInput.value, 10) : 0;

            e.target.value = '';

            openRuleModal({
                d: d,
                cmid: cmid,
                rid: 0,
                type: type,
                formClass: `\\datalynxrule_${type}\\form\\rule_form`,
                trigger: e.target
            });
        });
    }

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest(TRIGGER_SELECTOR);
        if (!trigger) {
            return;
        }
        e.preventDefault();
        openRuleModal({
            d: parseInt(trigger.getAttribute('data-d'), 10),
            cmid: parseInt(trigger.getAttribute('data-cmid'), 10),
            rid: parseInt(trigger.getAttribute('data-rid'), 10) || 0,
            type: trigger.getAttribute('data-type') || '',
            formClass: trigger.getAttribute('data-formclass') || '',
            trigger: trigger
        });
    });
};

/**
 * Build and show the modal form for the given configuration.
 *
 * @param {object} params Object containing d, cmid, rid, type, formClass, and trigger element.
 */
const openRuleModal = async(params) => {
    const modal = new ModalForm({
        formClass: params.formClass,
        args: {d: params.d, cmid: params.cmid, rid: params.rid, type: params.type},
        modalConfig: {
            title: params.rid
                ? await getString('ruleedit', 'datalynx', '')
                : await getString('ruleadd', 'datalynx')
        },
        returnFocus: params.trigger,
    });

    modal.addEventListener(modal.events.FORM_SUBMITTED, () => {
        window.location.assign(window.location.pathname + '?d=' + params.d);
    });

    // Which controls a row needs depends on what has been chosen in it, so changing the field or
    // the relation reloads the rows via AJAX. The trigger conditions and the matching criteria are
    // two independent blocks with a reload button each; the changed element decides which one runs.
    const reloadButtons = [
        {pattern: /^(searchfield|searchandor)\d+$/, button: 'addsearchsettings'},
        {pattern: /^(matchfield|matchrel)\d+$/, button: 'addmatchsettings'},
    ];

    modal.addEventListener('change', (e) => {
        const name = e.target.name || '';
        const match = reloadButtons.find((candidate) => candidate.pattern.test(name));
        if (!match) {
            return;
        }
        const form = e.target.closest('form');
        const button = form && form.querySelector('[name="' + match.button + '"]');
        if (button) {
            window.skipClientValidation = true;
            modal.processNoSubmitButton(button);
        }
    });

    modal.show();
};
