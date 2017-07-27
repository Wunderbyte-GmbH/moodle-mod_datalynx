/**
 * This file is part of the Datalynx module for Moodle - http:// Moodle.org/.
 *
 * @package mod-datalynx
 * @subpackage datalynxfield-nanogong
 * @copyright 2011 Itamar Tzadok
 * @license http:// Www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 *
 * The Datalynx has been developed as an enhanced counterpart
 * of Moodle's Database activity module (1.9.11+ (20110323)).
 * To the extent that Datalynx code corresponds to Database code,
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
 * along with Moodle.  If not, see <http:// Www.gnu.org/licenses/>.
 */

M.datalynxfield_teammemberselect = {};

M.datalynxfield_teammemberselect.sources = [];

M.datalynxfield_teammemberselect.init_subscribe_links = function (Y, fieldid, userurl, username, canunsubscribe) {
    function extract_params(paramstring) {
        var params = paramstring.split('&');
        var output = {};
        for (var i = 0; i < params.length; i++) {
            var param = params[i];
            output[param.split('=')[0]] = param.split('=')[1];
        }
        return output;
    }

    function add_user(listelement) {
        var item = Y.Node.create('<li><a href=' + userurl + '>' + username + '</a></li>');
        listelement.append(item);
    }

    function remove_user(listelement) {
        listelement.all('li').each(function (item) {
            var userurlparams = extract_params(userurl.split('?')[1]);
            var anchorparams = extract_params(item.one('a').get('href').split('?')[1]);
            if (userurlparams.id == anchorparams.id) {
                item.remove();
            }
        });
        if (!listelement.hasChildNodes()) {
            listelement.remove();
        }
    }

    Y.all('a.datalynxfield_subscribe').each(function (link) {
        var href = link.get('href');
        var params = extract_params(href.split('?')[1]);
        if (params.fieldid !== fieldid) {
            return;
        }
        params.ajax = true;
        link.detach('click');
        link.on('click', function (e) {
            e.preventDefault();
            var ul = e.target.ancestor().one('ul');
            if (!ul) {
                ul = Y.Node.create('<ul></ul>');
                e.target.ancestor().prepend(ul);
            }
            // TODO: hide link after triggering.
            var actionurl = 'field/teammemberselect/ajax.php';
            Y.io(actionurl, {
                method: 'POST',
                data: params,
                on: {
                    success: function (id, o) {
                        if (o.responseText === 'true' && e.target.hasClass('subscribed')) {
                            if (canunsubscribe) {
                                e.target.toggleClass('subscribed');
                                e.target.set('title', M.util.get_string('subscribe', 'datalynx', {}));
                                e.target.set('innerHTML', M.util.get_string('subscribe', 'datalynx', {}));
                                params.action = 'subscribe';
                                e.target.set('href', e.target.get('href').replace('unsubscribe', 'subscribe'));
                            }
                            remove_user(ul);
                        } else if (o.responseText === 'true' && !e.target.hasClass('subscribed')) {
                            e.target.toggleClass('subscribed');
                            e.target.set('title', M.util.get_string('unsubscribe', 'datalynx', {}));
                            e.target.set('innerHTML', M.util.get_string('unsubscribe', 'datalynx', {}));
                            params.action = 'unsubscribe';
                            e.target.set('href', e.target.get('href').replace('subscribe', 'unsubscribe'));
                            add_user(ul);
                        }
                    },
                    failure: function (id) {
                        Y.log("Failure! ID: " + id, 'error', 'datalynxfield_teammemberselect');
                    }
                }
            });
        });
    });
};
