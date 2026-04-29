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
 * Controls tabular-view bulk-edit field toggles.
 *
 * @module      mod_datalynx/tabularbulkedit
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    toggle: '[data-datalynx-bulkedit-toggle="1"]',
    input: '[data-datalynx-bulkedit-input="1"]',
};

let initialised = false;

const getInputs = () => Array.from(document.querySelectorAll(SELECTORS.input));

const getFirstEditableEntryId = () => {
    const firstInput = document.querySelector(SELECTORS.input);
    return firstInput ? firstInput.dataset.datalynxEntryid : null;
};

const getInputsForField = fieldId => getInputs().filter(input => input.dataset.datalynxBulkeditField === fieldId);

const rememberOriginalState = input => {
    if (typeof input.dataset.datalynxBulkeditInitiallyDisabled === 'undefined') {
        input.dataset.datalynxBulkeditInitiallyDisabled = input.disabled ? '1' : '0';
    }
};

const applyToggleState = toggle => {
    const firstEditableEntryId = getFirstEditableEntryId();
    const fieldId = toggle.dataset.datalynxBulkeditField;

    if (!firstEditableEntryId) {
        toggle.style.display = 'none';
        return;
    }

    const matchingInputs = getInputsForField(fieldId);
    const controlledInputs = matchingInputs.filter(input => input.dataset.datalynxEntryid !== firstEditableEntryId);

    toggle.style.display = controlledInputs.length ? '' : 'none';

    controlledInputs.forEach(input => {
        rememberOriginalState(input);
        input.disabled = toggle.checked || input.dataset.datalynxBulkeditInitiallyDisabled === '1';
    });
};

const initExistingState = () => {
    getInputs().forEach(rememberOriginalState);
    document.querySelectorAll(SELECTORS.toggle).forEach(applyToggleState);
};

const registerListeners = () => {
    document.addEventListener('change', event => {
        const toggle = event.target.closest(SELECTORS.toggle);
        if (!toggle) {
            return;
        }

        applyToggleState(toggle);
    });
};

export const init = () => {
    if (initialised) {
        initExistingState();
        return;
    }

    initialised = true;
    registerListeners();
    initExistingState();
};

export default {
    init,
};
