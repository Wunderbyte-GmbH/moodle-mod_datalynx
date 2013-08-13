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

/**
 * Initializes team member select field's autocomplete objects with a list of available users. It attaches event listeners to text fields.
 * @param  YUI      Y               YUI object
 * @param  object   userlistobject  list of users in format {"userid" : "username (email)", ...}
 */
M.dataformfield_teammemberselect.init_entry_form = function (Y, userlistobject) {
    var key, source = [], autocompletes = [], lastvalues = [];

    for (key in userlistobject) {
        source.push(userlistobject[key]);
    }

    // initializes autocomplete objects for text fields
    Y.all('input[type="text"][name^="dataformfield_dropdown"]').each(function (input) {
        var autocomplete = new Y.AutoCompleteList({
            inputNode: input,
            source: source.slice(0),
            render: true,
            minQueryLength: 3,
            tabSelect: true,
            activateFirstItem: true,
            circular: true,
            maxResults: 5,
            resultFilters: 'subWordMatch',
            queryDelay: 40,
            width: "400px",
        });

        // attaches event listeners after the selection has been made
        autocomplete.after('select', function () {
            select_hidden_option(input);
            refresh_user_list();
        });

        // store references to all autocomplete object for later list updates
        autocompletes.push(autocomplete);
    });

    /**
     * Selects the appropriate option in the hidden select element associated with the text field
     * @param  YNode    field text field element wrapped in YUI 3 YNode object
     */
    function select_hidden_option(field) {
        var name = field.get('name').replace('dataformfield_dropdown', 'field'),
        select = Y.one('select[name="' + name + '"]');
        select.get("options").each(function (option) {
            console.log(option.get('text') + " " + field.get('value'));
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
    function refresh_user_list() {
        var newuserlist = source.slice(0), i;

        Y.all('input[type="text"][name^="dataformfield_dropdown"]').each(function (field) {
            if (newuserlist.indexOf(field.get('value')) !== -1) {
                newuserlist.splice(newuserlist.indexOf(field.get('value')), 1);
            }
        });

        for (i = 0; i < autocompletes.length; i++) {
            autocompletes[i].set('source', newuserlist.slice(0));
        }
    }

    // update autocomplete lists once after loading to account for preselected values
    refresh_user_list();

    // clears the text field and the respective select box, saving the previous value for undo action
    Y.all('input[type="text"][name^="dataformfield_dropdown"]').on('click', function (e) {
        var name = e.target.get('name').replace('dataformfield_dropdown', 'field');
        e.target.set('value', '');
        Y.one('select[name="' + name + '"] option[value="0"]').set('selected', true);

        refresh_user_list();
    });

    // undo action allows restoring previous value of a text field by pressing Ctrl+Z while focused on the field
    Y.all('input[type="text"][name^="dataformfield_dropdown"]').on('keydown', function (e) {
        var field = e.target;
        if (e.ctrlKey === true && e.keyCode === 90 && lastvalues.hasOwnProperty(field.get('name'))) {
            e.preventDefault();
            e.stopPropagation();
            field.set('value', lastvalues[field.get('name')]);
            select_hidden_option(field);
            refresh_user_list();
        }
    });
};


