// This file is part of Moodle - http://moodle.org/.
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

/**
 * This file is part of the Dataform module for Moodle - http://moodle.org/.
 *
 * @package dataformview
 * @subpackage tabular
 * @copyright 2013 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

M.dataformview_tabular = {};

M.dataformview_tabular.init = function(Y) {
    var firstentryfield = Y.one('div.felement [name^="field_"]');
    if (!firstentryfield) {
        Y.all('input[type="checkbox"][name$="bulkedit"]').hide();
        return;
    }
    var firstentryid = firstentryfield.get('name').split('_')[2].replace(/\[.+\]/, '');

    Y.all('input[type="checkbox"][name$="bulkedit"]').on('change', function (e) {
        var checkbox = e.target;
        var fieldid = checkbox.get('name').split('_')[1];

        Y.all('[name^="field_' + fieldid + '"]:not([name^="field_' + fieldid + '_' + firstentryid + '"])' +
                ':not([name^="field_' + fieldid + '_bulkedit"])').each(function () {
            this.set('disabled', (checkbox.get('checked') ? 'disabled' : null ));
        });
    });

    var editedelement;
    var mform = Y.one('#mform1');
    if (mform) {
        mform.on('submit', function () {
            var editedentries = Y.one('[name="update"]').get('value').split(',');
            editedentries.splice(editedentries.indexOf(firstentryid), 1);

            Y.all('input[type="checkbox"][name$="bulkedit"]:checked').each(function (checkbox) {
                var fieldid = checkbox.get('name').split('_')[1];
                Y.all('[name^="field_' + fieldid + '"]').set('disabled', null);

                Y.all('[name^="field_' + fieldid + '_' + firstentryid + '"]').each(function (field) {
                    for (var i = 0; i < editedentries.length; i++) {
                        var entryid = editedentries[i];
                        editedelement = Y.one('[name^="' + field.get('name').replace(fieldid + '_' + firstentryid, fieldid + '_' + entryid) + '"]');
                        editedelement.set('value', field.get('value'));
                        editedelement.set('selected', field.get('selected'));
                        editedelement.all('option').set('selected', false);
                        console.log(field.all('option[selected]'));
                        field.all('option[selected]').each(select_option);
                        editedelement.set('checked', field.get('checked'));
                    }
                });
            });
        });
    }

    function select_option(selectedoption) {
        editedelement.one('option[value="' + selectedoption.get('value') + '"]').set('selected', true);
    }
};

