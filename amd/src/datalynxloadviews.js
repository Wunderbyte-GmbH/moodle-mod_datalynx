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

export default {
    init(options) {
        const {dffield, viewfield, textfieldfield, presentdlid, thisfieldstring, update, fieldtype} = options;
        const dffieldElement = document.getElementById(`id_${dffield}`);
        const viewElement = document.getElementById(`id_${viewfield}`);
        const textfieldElement = document.getElementById(`id_${textfieldfield}`);

        if (dffieldElement) {
            dffieldElement.addEventListener("change", function() {
                const dfid = Number(this.value);

                populateSelect(viewElement, []);
                populateSelect(textfieldElement, []);

                if (!dfid) {
                    return undefined;
                }

                const [viewsRequest, textfieldsRequest] = Ajax.call([
                    {
                        methodname: 'mod_datalynx_get_view_names',
                        args: {d: dfid},
                    },
                    {
                        methodname: 'mod_datalynx_get_text_field_names',
                        args: {d: dfid},
                    }
                ]);

                return Promise.all([viewsRequest, textfieldsRequest])
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
            });
        }
    }
};
