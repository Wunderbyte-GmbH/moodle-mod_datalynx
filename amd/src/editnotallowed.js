// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/*
 * @package    mod_datalynx
 * @author     David Bogner
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Shows a modal informing the user that the current view cannot be edited and redirects
 * to the default view once the user acknowledges the message.
 *
 * @module     mod_datalynx/editnotallowed
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import * as Str from 'core/str';

/**
 * Display the "view cannot be edited" modal and redirect when it is dismissed.
 *
 * @param {string} redirectUrl URL of the default view to redirect to.
 * @param {string} title Modal title.
 * @param {string} message Modal body message.
 */
export const init = async(redirectUrl, title, message) => {
    const okLabel = await Str.get_string('ok', 'moodle');

    const modal = await Modal.create({
        title: title,
        body: `<p>${message}</p>`,
        footer: `<button type="button" class="btn btn-primary" data-action="dlx-editnotallowed-ok">${okLabel}</button>`,
        removeOnClose: true,
        isVerticallyCentered: true,
    });

    let redirected = false;
    const redirect = () => {
        if (redirected) {
            return;
        }
        redirected = true;
        window.location.assign(redirectUrl);
    };

    // Redirect whichever way the user dismisses the modal (OK button, close icon or Esc key).
    modal.getRoot().on(ModalEvents.hidden, redirect);
    modal.getRoot().on('click', '[data-action="dlx-editnotallowed-ok"]', () => {
        modal.hide();
    });

    await modal.show();
};
