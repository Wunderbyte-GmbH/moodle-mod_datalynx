// This file is part of Moodle - http:// Moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// It under the terms of the GNU General Public License as published by
// The Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// But WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// Along with Moodle. If not, see <http:// Www.gnu.org/licenses/>.

/**
 * @package mod_datalynx
 * @copyright 2013 onwards David Bogner, Michael Pollak, Ivan Sakic and others.
 * @copyright based on the work by 2011 Itamar Tzadok
 * @license http:// Www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 *
 */

M.mod_datalynx = {};

/**
 * Tag management in atto
 */

M.mod_datalynx.tag_manager = {};

M.mod_datalynx.tag_manager.tags = [];
M.mod_datalynx.tag_manager.currenttag = null;
M.mod_datalynx.tag_manager.dialog = null;
M.mod_datalynx.tag_manager.hidedialog = false;
M.mod_datalynx.tag_manager.behaviors = [];
M.mod_datalynx.tag_manager.renderers = {};
M.mod_datalynx.tag_manager.types = [];

/**
 * @param editor Node
 */
M.mod_datalynx.tag_manager.add_tag_spans = function (editordiv) {
    var preg_quote = function (str, delimiter) {
        return String(str).replace(new RegExp('[.\\\\+*?\\[\\^\\]$(){}=!<>|:\\' + (delimiter || '') + '-]', 'g'), '\\$&');
    };
    var editor = editordiv.one(".editor_atto  .editor_atto_content");
    var textarea = editordiv.one("textarea");

    // Field tags.
    var tagregex = /\[\[([^\|\]]+)(?:\|([^\|\]]*))?(?:\|([^\|\]]*))?]](@?)/g;
    var oldcontent = textarea.get('value');
    var newcontent = oldcontent;
    var splittag;
    var tag;
    var replacement;
    while ((splittag = tagregex.exec(oldcontent)) !== null) {
        tag = splittag[0];
        var field = splittag[1];
        var behavior = typeof(splittag[2]) !== "undefined" ? splittag[2] : "";
        var renderer = typeof(splittag[3]) !== "undefined" ? splittag[3] : "";
        if (splittag[4] !== '@') {
            replacement = M.mod_datalynx.tag_manager.create_advanced_tag('field', field, behavior, renderer);
            newcontent = newcontent.replace(new RegExp(preg_quote(tag) + "(?!@)"), replacement);
        }
    }

    // Action tags.
    tagregex = /##([^#]*)?##(@?)/g;
    splittag = [];
    while ((splittag = tagregex.exec(oldcontent)) !== null) {
        tag = splittag[0];
        var action = splittag[1];
        if (splittag[2] !== '@') {
            replacement = M.mod_datalynx.tag_manager.create_advanced_tag('action', action, '', '');
            newcontent = newcontent.replace(new RegExp(preg_quote(tag) + "(?!@)"), replacement);
        }
    }

    editor.setHTML(newcontent);
    textarea.set('value', newcontent);
    textarea.simulate('change');
};

/**
 * @param editor Node
 */
M.mod_datalynx.tag_manager.remove_tag_spans = function (editordiv) {
    var editor = editordiv.one(".editor_atto .editor_atto_content");
    var textarea = editordiv.one("textarea");
    var newcontent = editor.getHTML();
    var spans = editor.all("button.datalynx-field-tag");
    spans.each(function (span) {
        var field = span.getAttribute("data-datalynx-field");
        var behavior = span.getAttribute("data-datalynx-behavior");
        var renderer = span.getAttribute("data-datalynx-renderer");
        var replacement = M.mod_datalynx.tag_manager.create_raw_tag('field', field, behavior, renderer);
        newcontent = newcontent.replace(span.get('outerHTML'), replacement);
    });

    spans = editor.all("button.datalynx-action-tag");
    spans.each(function (span) {
        var action = span.getHTML();
        var replacement = M.mod_datalynx.tag_manager.create_raw_tag('action', action, '', '');
        newcontent = newcontent.replace(span.get('outerHTML'), replacement);
    });

    editor.setHTML(newcontent);
    textarea.set('value', newcontent);
    textarea.simulate('change');
};

M.mod_datalynx.tag_manager.init_span_dialog = function (Y) {
    var config = {
        draggable: false,
        modal: false,
        closeButton: true,
        width: '300px'
    };

    var dialog = M.mod_datalynx.tag_manager.dialog = new M.core.dialogue(config);
    var dialogcontent = Y.Node.create('<div id="datalynx-tag-dialog-content">' + '<div id="datalynx-field-tag-contols">' + '<p><label for="datalynx-tag-fieldtype">' + 
        M.util.get_string('fieldtype', 'datalynx', null) + ':</label><span id="datalynx-tag-fieldtype"></span></p>' + 
        '<p><label for="datalynx-tag-behavior-menu">' + M.util.get_string('behavior', 'datalynx', null) + 
        ':</label><select id="datalynx-tag-behavior-menu"></select></p>' + '<p><label for="datalynx-tag-renderer-menu">' + 
        M.util.get_string('renderer', 'datalynx', null) + ':</label><select id="datalynx-tag-renderer-menu"></select></p>' + '</div>' + 
        '<button type="button" id="datalynx-tag-button-delete">' + M.util.get_string('deletetag', 'datalynx', null) + '</button>' + '</div>');
    var behaviorselect = dialogcontent.one('#datalynx-tag-behavior-menu');
    var rendererselect = dialogcontent.one('#datalynx-tag-renderer-menu');
    dialog.set('bodyContent', dialogcontent);
    dialog.on('click', function () {
        M.mod_datalynx.tag_manager.hidedialog = false;
    });

    Y.one('body').on('click', function () {
        var dialoghide = M.mod_datalynx.tag_manager.hidedialog;
        if (dialoghide) {
            dialog.hide();
            M.mod_datalynx.tag_manager.currenttag = null;
        }
        dialoghide = true;
    });

    Y.one("#datalynx-tag-button-delete").on('click', function () {
        dialog.hide();
        M.mod_datalynx.tag_manager.hidedialog = true;
        M.mod_datalynx.tag_manager.currenttag.remove();
        M.mod_datalynx.tag_manager.currenttag = null;

        var attoeditors = Y.all("#datalynx-view-edit-form div.editor_atto");
        attoeditors.each(function (attoeditor) {
            var editordiv = attoeditor.ancestor();
            var editor = editordiv.one(".editor_atto .editor_atto_content");
            var textarea = editordiv.one("textarea");
            textarea.set('value', editor.getHTML());
            editor.simulate('change');
            textarea.simulate('change');
        });
    });

    behaviorselect.on('click', function () {
        var bhvalue = behaviorselect.get("value");
        var targetid = dialog.get("target");
        Y.one('#' + targetid).setAttribute("data-datalynx-behavior", bhvalue);
    });

    rendererselect.on('click', function () {
        var bhvalue = rendererselect.get("value");
        var targetid = dialog.get("target");
        Y.one('#' + targetid).setAttribute("data-datalynx-renderer", bhvalue);
    });
};

M.mod_datalynx.tag_manager.show_tag_dialog = function (event, Y) {
    var tag = M.mod_datalynx.tag_manager.currenttag = event.target;
    var dialog = M.mod_datalynx.tag_manager.dialog;
    var fieldname, tagtype;
    if (tag.hasClass("datalynx-field-tag")) {
        Y.one('#datalynx-field-tag-contols').show();
        fieldname = tag.getAttribute("data-datalynx-field");
        tagtype = M.util.get_string('field', 'datalynx', null);
        dialog.set('headerContent', M.util.get_string('tagproperties', 'datalynx', {tagtype: tagtype, tagname: fieldname}));
        if (fieldname.indexOf(':') !== -1) {
            fieldname = fieldname.split(':')[0];
        }
        M.mod_datalynx.tag_manager.populate_select(dialog.bodyNode.one("#datalynx-tag-behavior-menu"),
            M.mod_datalynx.tag_manager.behaviors,
            tag.getAttribute("data-datalynx-behavior"));
        M.mod_datalynx.tag_manager.populate_select(dialog.bodyNode.one("#datalynx-tag-renderer-menu"),
            M.mod_datalynx.tag_manager.renderers[fieldname],
            tag.getAttribute("data-datalynx-renderer"));
        Y.one('#datalynx-tag-fieldtype').set('innerHTML', M.mod_datalynx.tag_manager.types[fieldname]);

        dialog.set('target', tag.get("id"));
        dialog.show();
        dialog.set('align', {node: tag, points: [Y.WidgetPositionAlign.TL, Y.WidgetPositionAlign.BL]});

        M.mod_datalynx.tag_manager.hidedialog = false;
    } else if (tag.hasClass("datalynx-action-tag")) {
        Y.one('#datalynx-field-tag-contols').hide();
        fieldname = tag.getAttribute("data-datalynx-field");
        tagtype = M.util.get_string('action', 'datalynx', null);
        dialog.set('headerContent', M.util.get_string('tagproperties', 'datalynx', {tagtype: tagtype, tagname: fieldname}));

        dialog.set('target', tag.get("id"));
        dialog.show();
        dialog.set('align', {node: tag, points: [Y.WidgetPositionAlign.TL, Y.WidgetPositionAlign.BL]});
        M.mod_datalynx.tag_manager.hidedialog = false;
    } else {
        M.mod_datalynx.tag_manager.hidedialog = true;
    }
};

M.mod_datalynx.tag_manager.populate_select = function (select, data, selectedvalue) {
    select.set('innerHTML', '');
    for (var key in data) {
        if (data.hasOwnProperty(key)) {
            select.appendChild(Y.Node.create('<option value="' + key + '">' + data[key] + '</option>'));
        }
    }
    if (selectedvalue != null) {
        select.set("value", selectedvalue);
    }
};

M.mod_datalynx.tag_manager.init = function (Y, behaviors, renderers, types) {
    var attoeditors = Y.all("#datalynx-view-edit-form div.editor_atto");
    attoeditors.each(function (attoeditor) {
        var editordiv = attoeditor.ancestor();
        M.mod_datalynx.tag_manager.add_tag_spans(editordiv);
        attoeditor.one(".editor_atto_content").on('click', M.mod_datalynx.tag_manager.show_tag_dialog, null, Y);
        attoeditor.one("button[title='HTML']").on('click', M.mod_datalynx.tag_manager.toggle_tags, null, Y, editordiv);
        var id = attoeditor.siblings().item(0).get('id').replace('id_', '');
        Y.all('select[name^="' + id + '"]').each(function (Yselect) {
            Yselect.on('change', M.mod_datalynx.tag_manager.insert_field_tag, null, Y, editordiv);
        });
    });

    M.mod_datalynx.tag_manager.behaviors = behaviors;
    M.mod_datalynx.tag_manager.renderers = renderers;
    M.mod_datalynx.tag_manager.types = types;

    M.mod_datalynx.tag_manager.init_span_dialog(Y);

    Y.one("#datalynx-view-edit-form").on("submit", M.mod_datalynx.tag_manager.prepare_submit, null, Y);
};

M.mod_datalynx.tag_manager.toggle_tags = function (event, Y, editordiv) {
    if (editordiv.one("textarea").getAttribute('hidden') === 'hidden') {
        M.mod_datalynx.tag_manager.remove_tag_spans(editordiv);
    } else {
        M.mod_datalynx.tag_manager.add_tag_spans(editordiv);
    }
};

M.mod_datalynx.tag_manager.prepare_submit = function (event, Y) {
    var attoeditors = Y.all("#datalynx-view-edit-form div.editor_atto");
    attoeditors.each(function (attoeditor) {
        var editordiv = attoeditor.ancestor();
        M.mod_datalynx.tag_manager.remove_tag_spans(editordiv);
    });
};

M.mod_datalynx.tag_manager.insert_field_tag = function (event, Y, editordiv) {
    var evvalue = event.target.get('value');
    var textarea = editordiv.one("textarea");

    if (evvalue === '') {
        return;
    }

    textarea.focus();
    if (textarea.getAttribute('hidden') !== 'hidden') {
        var editor = document.getElementById(textarea.get('id'));
        switch (evvalue) {
            case '9':
                insertAtCursor(editor, "\t");
                break;
            case '10':
                insertAtCursor(editor, "\n");
                break;
            default:
                insertAtCursor(editor, evvalue);
                break;
        }
    } else {
        var replacement = '';
        var field = '';
        if (/\[\[[^\]]+\]\]/.test(evvalue)) {
            field = evvalue.replace(/[\[\]]+/g, '');
            replacement = M.mod_datalynx.tag_manager.create_advanced_tag('field', field, '', '');
        } else if (/##[^\]]+##/.test(evvalue)) {
            field = evvalue.replace(/#+/g, '');
            replacement = M.mod_datalynx.tag_manager.create_advanced_tag('action', field, '', '');
        } else {
            replacement = evvalue;
        }

        M.mod_datalynx.tag_manager.insert_at_caret(Y, editordiv, replacement);
    }

    event.target.set('value', '');
    event.target.simulate('change');
    textarea.simulate('change');
};

M.mod_datalynx.tag_manager.create_advanced_tag = function (type, fieldname, behavior, renderer) {
    var output = '';
    switch (type) {
        case 'field':
            output = '<button type="button" contenteditable="false" ' + 'class="datalynx-tag datalynx-field-tag" data-datalynx-field="' + fieldname + '" data-datalynx-behavior="' + behavior + '" data-datalynx-renderer="' + renderer + '">' + fieldname + '</button>';
            break;
        case 'action':
            output = '<button type="button" contenteditable="false" ' + 'class="datalynx-tag datalynx-action-tag" data-datalynx-field="' + fieldname + '">' + fieldname + '</button>';
            break;
        default:
            output = fieldname;
            break;
    }
    return output;
};

M.mod_datalynx.tag_manager.create_raw_tag = function (type, fieldname, behavior, renderer) {
    var output = '';
    switch (type) {
        case 'field':
            output = "[[" + fieldname;
            if ((behavior !== "")) {
                output += "|" + behavior;
                if ((renderer !== "")) {
                    output += "|" + renderer;
                }
            } else {
                if ((renderer !== "")) {
                    output += "||" + renderer;
                }
            }
            output += "]]";
            break;
        case 'action':
            output = "##" + fieldname + "##";
            break;
        default:
            output = fieldname;
            break;
    }
    return output;
};

M.mod_datalynx.tag_manager.insert_at_caret = function (Y, editordiv, html) {
    var sel, range, firstTop;
    var container = editordiv.one(".editor_atto_content_wrap");
    if (window.getSelection) {
        // IE9 and non-IE.
        sel = window.getSelection();
        if (sel.getRangeAt && sel.rangeCount) {
            range = sel.getRangeAt(0);
            firstTop = Y.one(range.commonAncestorContainer.parentNode);
            if (firstTop !== null && (container.compareTo(firstTop) || container.one("#" + firstTop.get("id")) !== null)) {
                range.deleteContents();

                var el = document.createElement("div");
                el.innerHTML = html;
                var frag = document.createDocumentFragment(),
                    node, lastNode;
                while ((node = el.firstChild)) {
                    lastNode = frag.appendChild(node);
                }
                range.insertNode(frag);

                // Preserve the selection.
                if (lastNode) {
                    range = range.cloneRange();
                    range.setStartAfter(lastNode);
                    range.collapse(true);
                    sel.removeAllRanges();
                    sel.addRange(range);
                }
            }
        }
    } else if (document.selection && document.selection.type != "Control") {
        // IE < 9.
        range = document.selection.createRange();
        firstTop = Y.one(range.parentElement());
        if (firstTop !== null && (container.compareTo(firstTop) || container.one("#" + firstTop.get("id")) !== null)) {
            document.selection.createRange().pasteHTML(html);
        }
    }
};

M.mod_datalynx.behaviors_helper = {};

M.mod_datalynx.behaviors_helper.toggle_image = function (img) {
    var src = img.get("src");
    if (src.search("-enabled") !== -1) {
        src = src.replace("-enabled", "-n");
    } else {
        src = src.replace("-n", "-enabled");
    }
    img.set("src", src);
};

M.mod_datalynx.behaviors_helper.event_handler = function (event, Y) {
    var img = event.target;
    var behaviorid = img.getAttribute('data-behavior-id');
    var permissionid = img.getAttribute('data-permission-id');
    var forproperty = img.getAttribute('data-for');
    var sesskey = Y.one('table.datalynx-behaviors').getAttribute('data-sesskey');
    var build_querystring;

    var callback = {
        timeout: 5000,
        method: 'POST',
        data: build_querystring({
            behaviorid: behaviorid,
            permissionid: permissionid,
            forproperty: forproperty,
            sesskey: sesskey
        }),
        on: {
            success: function (id, result) {
                Y.log("RAW JSON DATA: " + result.responseText);
                M.mod_datalynx.behaviors_helper.toggle_image(img);
            },

            failure: function (id, result) {
                Y.log("Async call failed!");
            }

        }
    };

    Y.io('behavior_edit_ajax.php', callback);
};

M.mod_datalynx.behaviors_helper.init = function (Y) {
    Y.all('table.datalynx-behaviors img[data-for]').on('click', M.mod_datalynx.behaviors_helper.event_handler, null, Y);
};

/**
 * select antries for multiactions
 * Used when editing datalynx entries
 */
function select_allnone(elem, checked) {
    var selectors = document.getElementsByName(elem + 'selector');
    for (var i = 0; i < selectors.length; i++) {
        selectors[i].checked = checked;
    }
}

/**
 * construct url for multiactions
 * Used when editing datalynx entries
 */
function bulk_action(elem, url, action, defaultval) {
    var selected = [];
    var selectors = document.getElementsByName(elem + 'selector');
    for (var i = 0; i < selectors.length; i++) {
        if (selectors[i].checked == true) {
            selected.push(selectors[i].value);
        }
    }

    // Send selected entries to processing.
    if (selected.length) {
        location.href = url + '&' + action + '=' + selected.join(',');

        // If no entries selected but there is default, send it.
    } else if (defaultval) {
        location.href = url + '&' + action + '=' + defaultval;
    }
}

M.mod_datalynx.field_gradeitem_form_init = function () {
    Y.one('#mform1').one('select[name="param1"]').set('value', Y.one('#mform1').one('input[type="hidden"][name="param1"]').get('value'));
};
