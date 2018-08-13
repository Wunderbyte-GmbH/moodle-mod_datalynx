// This file is part of Moodle - http:// Moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify.
// It under the terms of the GNU General Public License as published by.
// The Free Software Foundation, either version 3 of the License, or.
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,.
// But WITHOUT ANY WARRANTY; without even the implied warranty of.
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// Along with Moodle.  If not, see <http:// Www.gnu.org/licenses/>.

/**
 * This file is part of the Datalynx module for Moodle - http:// Moodle.org/.
 *
 * @package datalynxview
 * @subpackage tabular
 * @copyright 2013 Ivan Šakić
 * @license http:// Www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

M.datalynxview_tabular = {};

M.datalynxview_tabular.init = function (Y) {
    var firstentryfield = Y.one('div.felement [name^="field_"]');
    if (!firstentryfield) {
        Y.all('input[type="checkbox"][name$="bulkedit"]').hide();
        return;
    }
    var firstentryid = firstentryfield.get('name').split('_')[2].replace(/\[.+\]/, '');

    Y.all('input[type="checkbox"][name$="bulkedit"]').on('change', function (e) {
        var checkbox = e.target;
        var fieldid = checkbox.get('name').split('_')[1];

        Y.all('[name^="field_' + fieldid + '"]:not([name^="field_' + fieldid + '_' + firstentryid + '"])' + ':not([name^="field_' + fieldid + '_bulkedit"])').each(function () {
                this.set('disabled', (checkbox.get('checked') ? 'disabled' : null ));
        });
    });
}