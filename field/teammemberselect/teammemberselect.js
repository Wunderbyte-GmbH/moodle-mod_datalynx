/**
 * This file is part of the Datalynx module for Moodle - http://moodle.org/.
 *
 * @package mod-datalynx
 * @subpackage datalynxfield-nanogong
 * @copyright 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
 * along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
 */

M.datalynxfield_teammemberselect = {};

M.datalynxfield_teammemberselect.sources = [];

/**
 * Initializes team member select field's autocomplete objects with a list of available users. It attaches event listeners to text fields.
 * @param  YUI      Y               YUI object
 * @param  object   userlistobject  list of users in format {"userid" : "username (email)", ...}
 */
M.datalynxfield_teammemberselect.init_entry_form = function (Y, userlistobject, fieldid, entryid, minteamsize) {
    var key = 0,
        maxresults = 7,
        overflow = false,
        formcancelled = false,
        dropdowns = Y.all('input[type="text"][name^="field_' + fieldid + '"][name*="_dropdown"]'),
        form,
        source = M.datalynxfield_teammemberselect.sources[fieldid] = [],
        autocompletes = [],
        lastvalues = {};

    if (dropdowns.size() == 0) {
        return;
    } else {
        form = dropdowns.item(0).get('form');
    }
    // move the object's contents into the appropriate array
    for (key in userlistobject) {
        source.push(userlistobject[key]);
    }

    // initializes autocomplete objects for text fields
    dropdowns.each(function (input) {
        var autocomplete = new Y.AutoCompleteList({
            inputNode: input,
            source: source.slice(0),
            render: true,
            minQueryLength: 3,
            tabSelect: true,
            activateFirstItem: true,
            circular: true,
            maxResults: 0,
            resultFilters: 'subWordMatch',
            queryDelay: 40,
            width: "400px"
        });

        autocomplete.on('results', function(e) {
            var info = {}, numresults = e.results.length, nummore = 0;
            if (numresults > maxresults) {
                nummore = numresults - maxresults;
                info.display = M.util.get_string('moreresults', 'datalynx', nummore);
                info.raw = 'info';
                info.text = info.display;
                e.results = e.results.slice(0, maxresults - 1);
                e.results.push(info);
                overflow = true;
            } else {
                overflow = false;
            }
        });

        autocomplete.on('select', function (e) {
            if (overflow && e.result.raw === 'info') {
                e.preventDefault();
            }
        });

        // attaches event listeners after the selection has been made
        autocomplete.after('select', function () {
            select_hidden_option(input);
            refresh_user_list(input);
        });

        // store references to all autocomplete object for later list updates
        autocompletes.push(autocomplete);
    });

    form.all('input[type="submit"][name*="cancel"]').on('click', function () {
        formcancelled = true;
    });

    form.on('submit', validate, form, fieldid, entryid);

    function validate(e, fieldid, entryid) {
        var i = 0,
            selects = Y.all('select[name^="field_' + fieldid + '_' + entryid + '"]'),
            errormsg = '',
            fieldset = dropdowns.item(0).ancestor();
        if (formcancelled) {
            return;
        }
        selects.each(function (select) {
            if (select.get('value') != 0) {
                i++;
            }
        });
        if (i < minteamsize) {
            e.preventDefault();
            if (!fieldset.one('.error')) {
                fieldset.addClass('error');
                errormsg = Y.Node.create('<span class="error">' + M.util.get_string('minteamsize_error_form', 'datalynx', minteamsize) +
                                            '<br></span>');
                fieldset.prepend(errormsg);
            }
        } else {
            fieldset.removeClass('error');
            fieldset.all('.error').remove();
        }
        dropdowns.each(function (dropdown) {
            if (!dropdown.get('value')) {
                dropdown.set('value', '...');
            }
        });
    }

    /**
     * Selects the appropriate option in the hidden select element associated with the text field
     * @param  YNode    field text field element wrapped in YUI 3 YNode object
     */
    function select_hidden_option(field) {
        var name = field.get('name').replace('_dropdown', ''),
        select = Y.one('select[name="' + name + '"]');
        select.get("options").each(function (option) {
            if (option.get('text') === field.get('value')) {
                option.set('selected', true);
                lastvalues[field.get('name')] = field.get('value');
                return;
            }
        });
    }

    /**
     * Reloads the full auto complete source list and removes already selected values from it
     */
    function refresh_user_list(input) {
        var newuserlist = source.slice(0), i;
        dropdowns.each(function (field) {
            if (newuserlist.indexOf(field.get('value')) !== -1) {
                newuserlist.splice(newuserlist.indexOf(field.get('value')), 1);
            }
        });

        for (i = 0; i < autocompletes.length; i++) {
            autocompletes[i].set('source', newuserlist.slice(0));
        }
    }

    // update autocomplete lists once after loading to account for preselected values
    dropdowns.each(refresh_user_list);

    // clears the text field and the respective select box, saving the previous value for undo action
    dropdowns.on('click', function (e) {
        var name = e.target.get('name').replace('_dropdown', '');
        e.target.set('value', '');
        Y.one('select[name="' + name + '"] option[value="0"]').set('selected', true);

        refresh_user_list(e.target);
    });

    // undo action allows restoring previous value of a text field by pressing Ctrl+Z while focused on the field
    dropdowns.on('keydown', function (e) {
        var field = e.target;
        if (e.ctrlKey === true && e.keyCode === 90 && lastvalues.hasOwnProperty(field.get('name'))) {
            e.preventDefault();
            e.stopPropagation();
            field.set('value', lastvalues[field.get('name')]);
            select_hidden_option(field);
            refresh_user_list(field);
        }
    });
};

M.datalynxfield_teammemberselect.init_subscribe_links = function(Y, fieldid, userurl, username, canunsubscribe) {
    Y.all('a.datalynxfield_subscribe').each(function (link) {
        var href = link.get('href');
        var params = extract_params(href.split('?')[1]);
        if (params.fieldid !== fieldid) {
            return;
        }
        params['ajax'] = true;
        link.detach('click');
        link.on('click', function (e) {
            e.preventDefault();
            var ul = e.target.ancestor().one('ul');
            if (!ul) {
                ul = Y.Node.create('<ul></ul>');
                e.target.ancestor().prepend(ul);
            }
            // TODO: hide link after triggering
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
                        console.log("Failure! ID: " + id);
                    }
                }
            });
        });
    });

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

    function extract_params(paramstring) {
        var params = paramstring.split('&');
        var output = {};
        for(var i = 0; i < params.length; i++) {
            var param = params[i];
            output[param.split('=')[0]] = param.split('=')[1];
        }
        return output;
    }
}

M.datalynxfield_teammemberselect.init_filter_search_form = function (Y, userlistobject, fieldid) {
    var key = 0,
        maxresults = 7,
        overflow = false,
        dropdowns = Y.all('input[type="text"][name^="f_"][name*="' + fieldid + '_dropdown"]'),
        stringtoid = [],
        source = M.datalynxfield_teammemberselect.sources[fieldid] = [],
        autocompletes = [],
        lastvalues = {};

    // move the object's contents into the appropriate array
    for (key in userlistobject) {
        source.push(userlistobject[key]);
        stringtoid[userlistobject[key]] = key;
    }

    // initializes autocomplete objects for text fields
    dropdowns.each(function (input) {
        var autocomplete = new Y.AutoCompleteList({
            inputNode: input,
            source: source.slice(0),
            render: true,
            minQueryLength: 3,
            tabSelect: true,
            activateFirstItem: true,
            circular: true,
            maxResults: 0,
            resultFilters: 'subWordMatch',
            queryDelay: 40,
            width: "400px"
        });

        autocomplete.on('results', function(e) {
            var info = {}, numresults = e.results.length, nummore = 0;
            if (numresults > maxresults) {
                nummore = numresults - maxresults;
                info.display = M.util.get_string('moreresults', 'datalynx', nummore);
                info.raw = 'info';
                info.text = info.display;
                e.results = e.results.slice(0, maxresults - 1);
                e.results.push(info);
                overflow = true;
            } else {
                overflow = false;
            }
        });

        autocomplete.on('select', function (e) {
            if (overflow && e.result.raw === 'info') {
                e.preventDefault();
            }
        });

        // attaches event listeners after the selection has been made
        autocomplete.after('select', function () {
            select_hidden_option(input);
        });

        // store references to all autocomplete object for later list updates
        autocompletes.push(autocomplete);
    });

    /**
     * Selects the appropriate option in the hidden select element associated with the text field
     * @param  YNode    field text field element wrapped in YUI 3 YNode object
     */
    function select_hidden_option(field) {
        var name = field.get('name').replace('_dropdown', '');
        Y.one('input[type="hidden"][name="' + name + '"]').set('value', stringtoid[field.get('value')]);
        lastvalues[field.get('name')] = field.get('value');
    }

    // clears the text field and the respective select box, saving the previous value for undo action
    dropdowns.on('click', function (e) {
        var name = e.target.get('name').replace('_dropdown', '');
        e.target.set('value', '');
        Y.one('input[type="hidden"][name="' + name + '"]').set('value', 0);
    });

    // undo action allows restoring previous value of a text field by pressing Ctrl+Z while focused on the field
    dropdowns.on('keydown', function (e) {
        var field = e.target;
        if (e.ctrlKey === true && e.keyCode === 90 && lastvalues.hasOwnProperty(field.get('name'))) {
            e.preventDefault();
            e.stopPropagation();
            field.set('value', lastvalues[field.get('name')]);
            select_hidden_option(field);
        }
    });
};
