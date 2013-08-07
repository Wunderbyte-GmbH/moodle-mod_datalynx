/**
 * This file is part of the Dataform module for Moodle - http://moodle.org/.
 *
 * @package mod-dataform
 * @subpackage dataformfield-nanogong
 * @copyright 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * The Dataform has been developed as an enhanced counterpart
 * of Moodle's Database activity module (1.9.11+ (20110323)).
 * To the extent that Dataform code corresponds to Database code,
 * certain copyrights on Database module may obtain.
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
 */

M.dataformfield_teammemberselect = {};

M.dataformfield_teammemberselect.init_entry_form = function (Y, entryid, fieldid) {
    return;
    var dropdowns = Y.all(".teammemberselect_" + fieldid + "_" + entryid);
    dropdowns.each(function (dropdown) {
        remove_selected_users();
        dropdown.on('change', remove_selected_users);
    });

    function remove_selected_users () {
        var selectedvalue = 0;
        dropdowns.each(function (dropdown) {
            selectedvalue = dropdown.get('value');
                dropdowns.each(function (otherdropdown) {
                    if (dropdown.get('id') !== otherdropdown.get('id')) {
                        otherdropdown.all("option").each(function (option) {
                            option.removeAttribute('disabled');
                        });
                        if (selectedvalue > 0) {
                            otherdropdown.one("option[value='" + selectedvalue + "']").set('disabled', 'disabled');
                        }
                    }
                });
        });
    }
};


