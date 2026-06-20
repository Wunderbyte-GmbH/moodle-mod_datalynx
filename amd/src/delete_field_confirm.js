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
 * JS module to display a modal dialog for confirming field deletion.
 *
 * @module      mod_datalynx/delete_field_confirm
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import { get_strings } from 'core/str';

export const init = async (title, message, confirmUrl, cancelUrl) => {
    const strings = await get_strings([
        {key: 'yes', component: 'moodle'},
        {key: 'no', component: 'moodle'}
    ]);

    Notification.confirm(
        title,
        message,
        strings[0],
        strings[1],
        () => {
            window.location.href = confirmUrl;
        },
        () => {
            window.location.href = cancelUrl;
        }
    );
};
