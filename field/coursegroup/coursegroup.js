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
 * @package mod-datalynx
 * @subpackage datalynxfield_coursegroup
 * @copyright 2012 Itamar Tzadok
 * @license http:// Www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

/**
 * Category coursegroups loader
 */
M.datalynxfield_coursegroup_load_course_groups = {};

M.datalynxfield_coursegroup_load_course_groups.init = function (Y, options) {
    YUI().use('node-base', 'event-base', 'io-base', function (Y) {
        // Get field name from options.
        var coursefield = options.coursefield;
        var groupfield = options.groupfield;
        var groupidfield = options.groupfield + 'id';
        var actionurl = options.acturl;

        Y.on('change', function (e) {

            // Get group select.
            var group = Y.Node.one('#id_' + groupfield);

            // Get the courseid.
            var courseid = this.get('options').item(this.get('selectedIndex')).get('value');

            // Remove options (but first choose) from group select.
            var optionchoose = group.get('options').item(0);
            group.setContent(optionchoose);
            group.set('selectedIndex', 0);

            // Load groups from course.
            if (courseid != 0) {

                Y.io(actionurl, {
                    method: 'POST',
                    data: 'courseid=' + courseid,
                    on: {
                        success: function (id, o) {
                            if (o.responseText != '') {
                                // Add options.
                                var groupoptions = group.get('options');
                                var respoptions = o.responseText.split(',');
                                for (var i = 0; i < respoptions.length; ++i) {
                                    var arr = respoptions[i].trim().split(' ');
                                    var qid = arr.shift();
                                    var qname = arr.join(' ');
                                    group.append(Y.Node.create('<option value="' + qid + '">' + qname + '</option>'));
                                }
                            }
                        },
                        failure: function (id, o) {
                            // Do something.
                        }
                    }
                });

            }
        }, '#id_' + coursefield);

        Y.on('change', function (e) {

            // Get groupid field.
            var group = Y.Node.one('#id_' + groupidfield);

            // Get the selected group from group.
            var gid = this.get('options').item(this.get('selectedIndex')).get('value');

            // Assign to groupid.
            group.set('value', gid);
        }, '#id_' + groupfield);
    });
};