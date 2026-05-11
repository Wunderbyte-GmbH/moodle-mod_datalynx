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
 * Fetches structured browse payloads and renders Mustache templates into a target region.
 *
 * @module      mod_datalynx/viewbrowser
 * @copyright   2026 David Bogner
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {init as initApprove} from 'mod_datalynx/approve';

/**
 * Notify feature scripts that a browse region received fresh DOM from AJAX.
 *
 * @param {HTMLElement} element
 */
const notifyContentUpdated = (element) => {
    element.dispatchEvent(new CustomEvent('mod_datalynx:viewContentUpdated', {
        bubbles: true,
        detail: {
            target: element,
        }
    }));
};

/**
 * Fetch the browse payload from the configured external function.
 *
 * @param {Object} options
 * @returns {Promise<Object>}
 */
const fetchPayload = (options) => Ajax.call([{
    methodname: options.methodname,
    args: options.args || {},
}])[0];

/**
 * Render a visible error state into the browser region.
 *
 * @param {HTMLElement} element
 * @param {string} message
 */
const renderErrorState = (element, message) => {
    element.replaceChildren();

    const alert = document.createElement('div');
    alert.className = 'alert alert-danger';
    alert.textContent = message;

    element.appendChild(alert);
};

export default {
    /**
     * Fetch and render one browse region into the supplied element.
     *
     * @param {string|HTMLElement} target
     * @param {Object} options
     * @returns {Promise<void>}
     */
    init(target, options) {
        const element = typeof target === 'string' ? document.querySelector(target) : target;
        if (!element) {
            return Promise.resolve();
        }

        return fetchPayload(options)
            .then((payload) => Templates.renderForPromise(options.template, payload))
            .then(({html, js}) => Templates.replaceNodeContents(element, html, js))
            .then(() => {
                initApprove(element);
                notifyContentUpdated(element);
            })
            .catch((error) => {
                renderErrorState(element, options.errormessage || 'Unable to load view entries.');
                Notification.exception(error);
            });
    },

    fetch(options) {
        return fetchPayload(options);
    }
};
