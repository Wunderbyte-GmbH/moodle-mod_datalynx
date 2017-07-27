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
// Along with Moodle. If not, see <http:// Www.gnu.org/licenses/>.

/**
 * @package mod
 * @subpackage datalynx
 * @copyright 2012 Itamar Tzadok
 * @license http:// Www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

/**
 * Datalynx views loader
 */
M.mod_datalynx_load_views = {};

M.mod_datalynx_load_views.init = function (Y, options) {
    YUI().use('node-base', 'event-base', 'io-base', function (Y) {
        // Get field name from options.
        var dffield = options.dffield;
        var viewfield = options.viewfield;
        var textfieldfield = options.textfieldfield;
        var actionurl = options.acturl;

        Y.on('change', function (e) {

            // Get view select.
            var view = Y.Node.one('#id_' + viewfield);

            // Get textfield select.
            var textfield = Y.Node.one('#id_' + textfieldfield);

            // Get the datalynx id.
            var dfid = this.get('options').item(this.get('selectedIndex')).get('value');

            // Remove view, textfield options (but the first choose) from view select.
            if (view) {
                var viewchoose = view.get('options').item(0);
                view.setContent(viewchoose);
                view.set('selectedIndex', 0);
            }
            if (textfield) {
                var textfieldchoose = textfield.get('options').item(0);
                textfield.setContent(textfieldchoose);
                textfield.set('selectedIndex', 0);
            }

            // Load views and/or textfields from datalynx.
            if (dfid != 0) {

                Y.io(actionurl, {
                    method: 'POST',
                    data: 'dfid=' + dfid,
                    on: {
                        success: function (id, o) {
                            if (o.responseText != '') {
                                console.log(o.responseText);
                                var respoptions = o.responseText.split('#');

                                // Add view options.
                                if (view) {
                                    var viewoptions = respoptions[0].split(',');
                                    for (var i = 0; i < viewoptions.length; ++i) {
                                        var arr = viewoptions[i].trim().split(' ');
                                        var qid = arr.shift();
                                        var qname = arr.join(' ');
                                        view.append(Y.Node.create('<option value="' + qid + '">' + qname + '</option>'));
                                    }
                                }

                                // Add textfield options.
                                if (textfield) {
                                    var textfieldoptions = respoptions[1].split(',');
                                    for (var i = 0; i < textfieldoptions.length; ++i) {
                                        var arr = textfieldoptions[i].trim().split(' ');
                                        var qid = arr.shift();
                                        var qname = arr.join(' ');
                                        textfield.append(Y.Node.create('<option value="' + qid + '">' + qname + '</option>'));
                                    }
                                }
                            }
                        },
                        failure: function (id, o) {
                            console.log("Error while loading views and textfields.")
                        }
                    }
                });

            }
        }, '#id_' + dffield);
    });
};
