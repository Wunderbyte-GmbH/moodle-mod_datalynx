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
 * Date-scope controls for the Report view: re-fetches and re-renders the browse region.
 *
 * @module      mod_datalynx/report_controls
 * @copyright   2026 David Bogner
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import Templates from 'core/templates';
import ViewBrowser from 'mod_datalynx/viewbrowser';

/**
 * Read the current scope-control values from the region.
 *
 * @param {HTMLElement} region
 * @returns {Object}
 */
const readScopeArgs = (region) => {
    const value = (name) => {
        const el = region.querySelector(`[data-report-control="${name}"]`);
        return el ? el.value : '';
    };

    return {
        timescopefield: value('field'),
        timescopemode: value('mode') || 'all',
        timescopeyear: parseInt(value('year'), 10) || 0,
        timescopemonth: parseInt(value('month'), 10) || 0,
        timescopefrom: value('fromdate'),
        timescopeto: value('todate'),
    };
};

/**
 * Fetch a fresh payload for the current scope and render it into the region.
 *
 * @param {HTMLElement} region
 * @param {Object} options
 * @returns {Promise<void>}
 */
const refresh = (region, options) => {
    const args = Object.assign({}, options.args || {}, readScopeArgs(region));

    return ViewBrowser.fetch({methodname: options.methodname, args})
        .then((payload) => Templates.renderForPromise(options.template, payload))
        .then(({html, js}) => Templates.replaceNodeContents(region, html, js))
        .catch((error) => {
            Notification.exception(error);
            return error;
        });
};

export default {
    /**
     * Bind the scope controls within one browse region.
     *
     * @param {string|HTMLElement} target
     * @param {Object} options
     */
    init(target, options) {
        const region = typeof target === 'string' ? document.querySelector(target) : target;
        if (!region || region.dataset.reportControlsBound) {
            return;
        }
        region.dataset.reportControlsBound = '1';

        // Delegate on the persistent region element so controls survive re-renders.
        region.addEventListener('change', (event) => {
            if (event.target.closest('[data-report-control]')) {
                refresh(region, options);
            }
        });
    }
};
