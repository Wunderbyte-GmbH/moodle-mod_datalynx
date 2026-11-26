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
 * entry shows its team as the server has it. A request that needs a pickup point asks for it
 * first, with suggestions from the site's geocoder (mod_datalynx#327).
 *
 * @module      mod_datalynx/teamrequests
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import * as Toast from 'core/toast';
import {getString} from 'core/str';

let listening = false;

/**
 * Run the action of a button.
 *
 * @param {HTMLElement} button
 * @param {Object} data extension data of a request
 */
const run = (button, data = {}) => {
    button.disabled = true;
    Promise.resolve(Ajax.call([{
        methodname: 'mod_datalynx_team_subscription',
        args: {
            d: parseInt(button.dataset.d, 10),
            entryid: parseInt(button.dataset.entryid, 10),
            fieldid: parseInt(button.dataset.fieldid, 10),
            userid: parseInt(button.dataset.userid, 10),
            action: button.dataset.dlxTeamAction,
            data: JSON.stringify(data),
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
 * Ask for the pickup point, then request.
 *
 * @param {HTMLElement} button
 */
const askPickup = async(button) => {
    const label = await getString('pickupaddress', 'datalynxfield_teammemberselect');
    const body = document.createElement('div');
    body.innerHTML = '<label class="form-label fw-semibold" for="dlx-pickup-input"></label>'
        + '<input type="text" class="form-control" id="dlx-pickup-input" autocomplete="off">'
        + '<div class="list-group mt-2" data-region="suggestions"></div>';
    body.querySelector('label').textContent = label;
    const modal = await ModalSaveCancel.create({title: button.textContent, body: body, show: true, removeOnClose: true});
    // The request is sent with the button the user started from ("Request a seat"), not "Save changes".
    modal.setSaveButtonText(button.textContent.trim());
    const input = body.querySelector('input');
    const list = body.querySelector('[data-region="suggestions"]');
    let picked = null;
    let timer = null;

    input.addEventListener('input', () => {
        picked = null;
        clearTimeout(timer);
        const query = input.value.trim();
        list.innerHTML = '';
        if (query.length < 3) {
            return;
        }
        timer = setTimeout(() => {
            Promise.resolve(Ajax.call([{
                methodname: 'mod_datalynx_geocode_search',
                args: {fieldid: parseInt(button.dataset.pickupfield, 10), query: query},
            }])[0]).then((result) => {
                list.innerHTML = '';
                (result.places || []).forEach((place) => {
                    const item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'list-group-item list-group-item-action';
                    item.textContent = place.address;
                    item.addEventListener('click', () => {
                        picked = place;
                        input.value = place.address;
                        list.innerHTML = '';
                    });
                    list.appendChild(item);
                });
                return result;
            }).catch(() => {
                list.innerHTML = '';
            });
        }, 400);
    });

    modal.getRoot().on(ModalEvents.save, () => {
        const pickup = picked ? picked : {address: input.value.trim()};
        run(button, {pickup: pickup});
    });
    input.focus();
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
        if (!button) {
            return;
        }
        e.preventDefault();
        if (button.dataset.pickupfield) {
            askPickup(button);
        } else {
            run(button);
        }
    });
};
