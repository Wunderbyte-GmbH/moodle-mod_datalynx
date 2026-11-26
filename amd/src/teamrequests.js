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
 * The buttons of a team whose joining needs the author's approval: request, withdraw, approve,
 * decline. Each calls mod_datalynx_team_subscription; on success the page reloads, so that the
 * entry shows its team as the server has it.
 *
 * @module      mod_datalynx/teamrequests
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Toast from 'core/toast';

let listening = false;

/**
 * Run the action of a button.
 *
 * @param {HTMLElement} button
 */
const run = (button) => {
    button.disabled = true;
    Promise.resolve(Ajax.call([{
        methodname: 'mod_datalynx_team_subscription',
        args: {
            d: parseInt(button.dataset.d, 10),
            entryid: parseInt(button.dataset.entryid, 10),
            fieldid: parseInt(button.dataset.fieldid, 10),
            userid: parseInt(button.dataset.userid, 10),
            action: button.dataset.dlxTeamAction,
            data: button.dataset.requestdata || '',
        },
    }])[0]).then((response) => {
        if (response.success) {
            window.location.reload();
        } else {
            Toast.add(response.error, {type: 'danger'});
            button.disabled = false;
        }
        return response;
    }).catch((error) => {
        button.disabled = false;
        Notification.exception(error);
    });
};

/**
 * Listen for clicks on the team buttons of the page (once).
 */
export const init = () => {
    if (listening) {
        return;
    }
    listening = true;
    document.addEventListener('click', (e) => {
        const button = e.target.closest('[data-dlx-team-action]');
        if (button) {
            e.preventDefault();
            run(button);
        }
    });
};
