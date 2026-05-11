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
 * Dynamically loads datalynx views and textfields via AJAX based on selected datalynx instance.
 *
 * @module      mod_datalynx/datalynxloadviews
 * @copyright   2025 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

const configs = [];
let isListening = false;

/**
 * Replace all options in a select with the provided entries.
 *
 * @param {HTMLSelectElement|null} element
 * @param {Array<{id:number|string,name:string}>} options
 */
function populateSelect(element, options) {
    if (!element) {
        return;
    }

    element.innerHTML = '';
    options.forEach(({id, name}) => {
        const optionElement = document.createElement('option');
        optionElement.value = String(id);
        optionElement.textContent = name;
        element.appendChild(optionElement);
    });
}

const getElementId = (field) => `id_${field}`;

const getSelectElement = (field) => field ? document.getElementById(getElementId(field)) : null;

const loadDependentOptions = (config, dffieldElement) => {
    const {dffield, viewfield, textfieldfield, presentdlid, thisfieldstring, update, fieldtype} = config;
    if (dffieldElement.id !== getElementId(dffield)) {
        return;
    }

    const viewElement = getSelectElement(viewfield);
    const textfieldElement = getSelectElement(textfieldfield);
    const dfid = Number(dffieldElement.value);

    populateSelect(viewElement, []);
    populateSelect(textfieldElement, []);

    if (!dfid) {
        return;
    }

    const requests = [{
        methodname: 'mod_datalynx_get_text_field_names',
        args: {d: dfid},
    }];

    if (viewfield) {
        requests.unshift({
            methodname: 'mod_datalynx_get_view_names',
            args: {d: dfid},
        });
    }

    const [firstRequest, secondRequest] = Ajax.call(requests);
    const viewsPromise = viewfield ? firstRequest : Promise.resolve([]);
    const textfieldsPromise = viewfield ? secondRequest : firstRequest;

    return Promise.all([viewsPromise, textfieldsPromise])
        .then(([views, textfields]) => {
            populateSelect(viewElement, views);

            const textfieldOptions = [...textfields];
            if (dfid === presentdlid && update === 0 && fieldtype === 'text') {
                textfieldOptions.unshift({
                    id: -1,
                    name: thisfieldstring,
                });
            }

            populateSelect(textfieldElement, textfieldOptions);
            return textfieldOptions;
        })
        .catch(Notification.exception);
};

const handleDocumentChange = (event) => {
    if (!(event.target instanceof HTMLSelectElement)) {
        return;
    }

    configs.forEach((config) => {
        if (event.target.id === getElementId(config.dffield)) {
            void loadDependentOptions(config, event.target);
        }
    });
};

export const init = (options) => {
    if (!configs.some((config) =>
        config.dffield === options.dffield &&
        config.viewfield === options.viewfield &&
        config.textfieldfield === options.textfieldfield
    )) {
        configs.push(options);
    }

    if (isListening) {
        return;
    }

    document.addEventListener('change', handleDocumentChange);
    isListening = true;
};

export default {
    init,
};
