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
 * @param {any} editordiv HTMLElement
 */
M.mod_datalynx.tag_manager.add_tag_spans = function (editordiv) {
    var preg_quote = function (str, delimiter) {
        return String(str).replace(new RegExp('[.\\\\+*?\\[\\^\\]$(){}=!<>|:\\' + (delimiter || '') + '-]', 'g'), '\\$&');
    };
    var editor = editordiv.querySelector(".editor_atto .editor_atto_content");
    var textarea = editordiv.querySelector("textarea");

    // Field tags.
    var tagregex = /\[\[([^\|\]]+)(?:\|([^\|\]]*))?(?:\|([^\|\]]*))?]](@?)/g;
    var oldcontent = textarea.value;
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

    editor.innerHTML = newcontent;
    textarea.value = newcontent;
    textarea.dispatchEvent(new Event('change'));
};

/**
 * @param {any} editordiv HTMLElement
 */
M.mod_datalynx.tag_manager.remove_tag_spans = function (editordiv) {
    var editor = editordiv.querySelector(".editor_atto .editor_atto_content");
    var textarea = editordiv.querySelector("textarea");
    var newcontent = editor.innerHTML;
    var spans = editor.querySelectorAll("button.datalynx-field-tag");
    spans.forEach(function (span) {
        var field = span.getAttribute("data-datalynx-field");
        var behavior = span.getAttribute("data-datalynx-behavior");
        var renderer = span.getAttribute("data-datalynx-renderer");
        var replacement = M.mod_datalynx.tag_manager.create_raw_tag('field', field, behavior, renderer);
        newcontent = newcontent.replace(span.outerHTML, replacement);
    });

    spans = editor.querySelectorAll("button.datalynx-action-tag");
    spans.forEach(function (span) {
        var action = span.innerHTML;
        var replacement = M.mod_datalynx.tag_manager.create_raw_tag('action', action, '', '');
        newcontent = newcontent.replace(span.outerHTML, replacement);
    });

    editor.innerHTML = newcontent;
    textarea.value = newcontent;
    textarea.dispatchEvent(new Event('change'));
};

M.mod_datalynx.tag_manager.init_span_dialog = function () {
    var config = {
        draggable: false,
        modal: false,
        closeButton: true,
        width: '300px'
    };

    var dialog = M.mod_datalynx.tag_manager.dialog = new M.core.dialogue(config);
    var dialogcontent = document.createElement('div');
    dialogcontent.id = 'datalynx-tag-dialog-content';
    dialogcontent.innerHTML = '<div id="datalynx-field-tag-contols">' +
        '<p><label for="datalynx-tag-fieldtype">' + M.util.get_string('fieldtype', 'datalynx', null) +
        ':</label><span id="datalynx-tag-fieldtype"></span></p>' +
        '<p><label for="datalynx-tag-behavior-menu">' + M.util.get_string('behavior', 'datalynx', null) +
        ':</label><select id="datalynx-tag-behavior-menu"></select></p>' +
        '<p><label for="datalynx-tag-renderer-menu">' +
        M.util.get_string('renderer', 'datalynx', null) + ':</label><select id="datalynx-tag-renderer-menu"></select></p>' +
        '</div>' + '<button type="button" id="datalynx-tag-button-delete">' +
        M.util.get_string('deletetag', 'datalynx', null) + '</button>';
    var behaviorselect = dialogcontent.querySelector('#datalynx-tag-behavior-menu');
    var rendererselect = dialogcontent.querySelector('#datalynx-tag-renderer-menu');
    dialog.set('bodyContent', dialogcontent);
    dialog.on('click', function () {
        M.mod_datalynx.tag_manager.hidedialog = false;
    });

    document.body.addEventListener('click', function () {
        var dialoghide = M.mod_datalynx.tag_manager.hidedialog;
        if (dialoghide) {
            dialog.hide();
            M.mod_datalynx.tag_manager.currenttag = null;
        }
        M.mod_datalynx.tag_manager.hidedialog = true;
    });

    document.querySelector("#datalynx-tag-button-delete").addEventListener('click', function () {
        dialog.hide();
        M.mod_datalynx.tag_manager.hidedialog = true;
        M.mod_datalynx.tag_manager.currenttag.remove();
        M.mod_datalynx.tag_manager.currenttag = null;

        var attoeditors = document.querySelectorAll("#datalynx-view-edit-form div.editor_atto");
        attoeditors.forEach(function (attoeditor) {
            var editordiv = attoeditor.closest('.editor_atto');
            var editor = editordiv.querySelector(".editor_atto .editor_atto_content");
            var textarea = editordiv.querySelector("textarea");
            textarea.value = editor.innerHTML;
            editor.dispatchEvent(new Event('change'));
            textarea.dispatchEvent(new Event('change'));
        });
    });

    behaviorselect.addEventListener('click', function () {
        var bhvalue = behaviorselect.value;
        var targetid = dialog.get("target");
        document.querySelector('#' + targetid).setAttribute("data-datalynx-behavior", bhvalue);
    });

    rendererselect.addEventListener('click', function () {
        var bhvalue = rendererselect.value;
        var targetid = dialog.get("target");
        document.querySelector('#' + targetid).setAttribute("data-datalynx-renderer", bhvalue);
    });
};

M.mod_datalynx.tag_manager.show_tag_dialog = function (event) {
    var tag = M.mod_datalynx.tag_manager.currenttag = event.target;
    var dialog = M.mod_datalynx.tag_manager.dialog;
    var fieldname, tagtype;
    if (tag.classList.contains("datalynx-field-tag")) {
        document.querySelector('#datalynx-field-tag-contols').style.display = 'block';
        fieldname = tag.getAttribute("data-datalynx-field");
        tagtype = M.util.get_string('field', 'datalynx', null);
        dialog.set('headerContent', M.util.get_string('tagproperties', 'datalynx', { tagtype: tagtype, tagname: fieldname }));
        if (fieldname.indexOf(':') !== -1) {
            fieldname = fieldname.split(':')[0];
        }
        M.mod_datalynx.tag_manager.populate_select(dialog.bodyNode.querySelector("#datalynx-tag-behavior-menu"),
            M.mod_datalynx.tag_manager.behaviors, fieldname, tag.getAttribute("data-datalynx-behavior"));
        M.mod_datalynx.tag_manager.populate_select(dialog.bodyNode.querySelector("#datalynx-tag-renderer-menu"),
            M.mod_datalynx.tag_manager.renderers[fieldname], fieldname, tag.getAttribute("data-datalynx-renderer"));
    } else {
        document.querySelector('#datalynx-field-tag-contols').style.display = 'none';
        fieldname = tag.innerHTML;
        tagtype = M.util.get_string('action', 'datalynx', null);
        dialog.set('headerContent', M.util.get_string('tagproperties', 'datalynx', { tagtype: tagtype, tagname: fieldname }));
    }

    var event_target_id = tag.getAttribute("id");
    dialog.set("target", event_target_id);
    M.mod_datalynx.tag_manager.hidedialog = false;
    dialog.show();
};

M.mod_datalynx.tag_manager.create_advanced_tag = function (tagtype, fieldname, behavior, renderer) {
    var tag = M.mod_datalynx.tag_manager.create_raw_tag(tagtype, fieldname, behavior, renderer);
    var className = "datalynx-" + tagtype + "-tag";
    if (tagtype === "field") {
        tag = '<button class="' + className + '" data-datalynx-field="' + fieldname + '" data-datalynx-behavior="' +
            behavior + '" data-datalynx-renderer="' + renderer + '" type="button">' + tag + '</button>';
    } else if (tagtype === "action") {
        tag = '<button class="' + className + '" type="button">' + tag + '</button>';
    }

    return tag;
};

M.mod_datalynx.tag_manager.create_raw_tag = function (tagtype, fieldname, behavior, renderer) {
    if (tagtype === "field") {
        return "[[" + fieldname + "|" + behavior + "|" + renderer + "]]";
    } else if (tagtype === "action") {
        return "##" + fieldname + "##";
    }
};

M.mod_datalynx.tag_manager.populate_select = function (select, optionlist, fieldname, selected) {
    select.innerHTML = '';
    optionlist.forEach(function (option) {
        var opt = document.createElement("option");
        opt.value = option;
        opt.text = option;
        select.appendChild(opt);
    });

    if (selected !== '') {
        select.value = selected;
    }
};
