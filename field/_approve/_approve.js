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

M.dataformfield__approve = {};

M.dataformfield__approve.init = function (Y, approvedicon, disapprovedicon) {
    Y.all('.dataformfield__approve').each(function (link) {
        var href = link.get('href');
        var params = extract_params(href.split('?')[1]);
        var child = link.get('firstChild');
        var parent = link.get('parentNode');
        parent.replaceChild(child, link);
        child.on('click', function (e) {
            var actionurl = 'field/_approve/ajax.php';
            Y.io(actionurl, {
                method: 'POST',
                data: params,
                on: {
                    success: function (id, o) {
                        if (o.responseText === 'true' && e.target.hasClass('approved')) {
                            e.target.toggleClass('approved');
                            e.target.set('src', disapprovedicon);
                            e.target.set('alt', 'approve');
                            e.target.set('title', 'approve');
                            params.action = 'approve';
                        } else if (o.responseText === 'true' && !e.target.hasClass('approved')) {
                            e.target.toggleClass('approved');
                            e.target.set('src', approvedicon);
                            e.target.set('alt', 'disapprove');
                            e.target.set('title', 'disapprove');
                            params.action = 'disapprove';
                        }
                    },
                    failure: function (id) {
                        alert("Failure! ID: " + id);
                    }
                }
            });
        });
    });

    function extract_params(paramstring) {
        var params = paramstring.split('&');
        var output = {};
        for(var i = 0; i < params.length; i++) {
            var param = params[i];
            var paramname = param.split('=')[0];
            var paramvalue = param.split('=')[1];
            output[paramname] = paramvalue;
        }
        if ('approve' in output) {
            output.entryid = output.approve;
            output.action = 'approve';
        } else if ('disapprove' in output) {
            output.entryid = output.disapprove;
            output.action = 'disapprove';
        } else {
            output.entryid = output.approve;
            output.action = 'approve';
        }
        return output;
    }

};
