# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What datalynx is

A flexible data-management activity module for Moodle. Users create typed fields, arrange them into views, and submit/browse entries — essentially a configurable database form. Key use-cases: program applications, course catalogs, conference management. Frankenstyle component: `mod_datalynx`.

## Commands

Run only the datalynx test suite:
```bash
vendor/bin/phpunit --testsuite mod_datalynx_testsuite
```

Lint a specific file:
```bash
vendor/bin/phpcs --standard=phpcs.xml mod/datalynx/classes/local/field/datalynxfield_renderer.php
```

Build JS for datalynx only (run from Moodle root):
```bash
npx grunt amd --root=mod/datalynx
```

See parent `CLAUDE.md` for all other commands (PHPUnit, Behat, Grunt watch, etc.).

## Core data model

| Table | Purpose |
|---|---|
| `datalynx` | Activity instance settings (grading, approval, defaults) |
| `datalynx_fields` | Field definitions (`type`, `name`, `param1`–`param10` for config) |
| `datalynx_entries` | Entry records (author, timestamps, status, group) |
| `datalynx_contents` | Sparse field values (`entryid` + `fieldid` → `content`) |
| `datalynx_views` | View definitions (`type`, `patterns`, `param1`–`param10`) |
| `datalynx_filters` | Saved search/sort/groupby configurations |
| `datalynx_behaviors` | Per-field visibility/editability/condition rules |
| `datalynx_renderers` | Field layout templates (5 templates per row — see below) |
| `datalynx_field_formats` | Display modifier plugins per field type |

All field values land in `datalynx_contents` as strings. A missing row means no value (never a NULL row).

## Rendering pipeline

### Display mode
`view.php` → view class `display()` → scan patterns for `[[fieldname|behavior|renderer]]` tags → `renderer->replacements()` → check behavior (visibility/conditions) → select layout template → replace `#value` token → return HTML.

### Edit mode
Entry form (`datalynxview_entries_form`) → `view->definition_to_form($mform)` → for each field tag, `renderer->replacements()` returns a deferred callback `[[$renderer, 'prerender_edit_mode'], [$entry, $options]]` → `prerender_edit_mode()` splits the layout's `edittemplate` on `#input` into prefix/suffix, wraps form elements with those, then delegates to `render_edit_mode()` → `render_edit_mode()` calls `$mform->addElement(...)`.

**Critical:** every `$mform->addElement('text'|'select'|...)` call is wrapped by Moodle's `lib/form/templates/element-template.mustache`, which always renders a `col-md-3 col-form-label` label column + `col-md-9` input column, even when the label is `null`. This produces the `fitem femptylabel` outer div visible in the rendered HTML. The prefix/suffix HTML from the custom `edittemplate` wraps *outside* the already-wrapped form element.

### Pattern tag syntax
```
[[fieldname]]                   # default behavior and layout
[[fieldname|behaviourname]]     # named behavior
[[fieldname|behaviourname|layoutname]]  # named behavior + named layout
[[fieldname:formatname]]        # with field format applied
```

## Field type plugin system

Each field type is a Moodle sub-plugin living under `field/<typename>/`. Required files:

```
field/<typename>/
  version.php              # $plugin->component = 'datalynxfield_<typename>'
  classes/field.php        # extends datalynxfield_base
  classes/renderer.php     # extends mod_datalynx\local\field\datalynxfield_renderer
  classes/form.php         # field settings form
```

### Key base classes
- `datalynxfield_base` (`classes/local/field/datalynxfield_base.php`) — field definition, DB record, `param1`–`param10` accessors
- `datalynxfield_renderer` (`classes/local/field/datalynxfield_renderer.php`) — rendering contract

Methods a renderer **must** implement or override:

| Method | Purpose |
|---|---|
| `render_display_mode($entry, $options): string` | Returns HTML for view mode |
| `render_edit_mode(&$mform, $entry, $options)` | Adds form element(s) to `$mform` |
| `render_search_mode(&$mform, $i, $value): array` | Adds filter form element(s) |
| `validate($entryid, $tags, $formdata): array` | Returns `[fieldname => errormsg]` |

`prerender_edit_mode()` is `final` — do not override it; override `render_edit_mode()` instead.

`replacements()` orchestrates everything: it calls `prerender_edit_mode` for edit mode and `render_display_mode` for display mode after resolving behavior, conditions, and layout template.

## Field layout system (`datalynx_renderers`)

A "field layout" (called "renderer" in the DB and classes) stores 5 independent templates for one field:

| DB column | Constant | Meaning |
|---|---|---|
| `notvisibletemplate` | `NOT_VISIBLE_SHOW_NOTHING` / `NOT_VISIBLE_SHOW_CUSTOM` | Visibility condition fails |
| `displaytemplate` | `DISPLAY_MODE_TEMPLATE_NONE` / `DISPLAY_MODE_TEMPLATE_CUSTOM` | View mode, has value |
| `novaluetemplate` | `NO_VALUE_SHOW_NOTHING` / `NO_VALUE_SHOW_DISPLAY_MODE_TEMPLATE` / `NO_VALUE_SHOW_CUSTOM` | View mode, empty value |
| `edittemplate` | `EDIT_MODE_TEMPLATE_NONE` / `EDIT_MODE_TEMPLATE_AS_DISPLAY_MODE` / `EDIT_MODE_TEMPLATE_CUSTOM` | Edit mode |
| `noteditabletemplate` | `NOT_EDITABLE_SHOW_NOTHING` / `NOT_EDITABLE_SHOW_AS_DISPLAY_MODE` / `NOT_EDITABLE_SHOW_DISABLED` / `NOT_EDITABLE_SHOW_CUSTOM` | Not editable in edit mode |

Sentinel values `___0___`, `___1___`, `___2___`, `___3___` encode the non-custom modes. When a template column holds a custom string, it contains freeform HTML with token(s):
- `#value` — replaced with the rendered field value (display mode)
- `#input` — replaced with the rendered form element (edit mode)
- `#name` — replaced with the field's name

Constants are on `mod_datalynx\local\field\datalynxfield_layout`. UI managed at `fieldlayout/layout_edit.php`.

**The Moodle wrapper problem and fix:** When `edittemplate` contains a custom HTML string with `#input`, `prerender_edit_mode()` splits on `#input` to get prefix/suffix and adds a `datalynx-no-fitem-wrapper` CSS class to the outer wrapper div. CSS rules in `styles.css` then suppress the `fitem` Bootstrap grid (`col-md-3` label column + `col-md-9` input column) via `display: contents` / `display: none`, so the form element sits directly at the `#input` position in the template. `$mform->setElementTemplate('{element}', $fieldname)` does NOT work in Moodle 4.x — `core_renderer::mform_element()` uses Mustache templates and bypasses the PEAR template path entirely for standard element types.

## View type plugin system

Sub-plugins under `view/<typename>/`. Required files:

```
view/<typename>/
  version.php              # $plugin->component = 'datalynxview_<typename>'
  classes/view.php         # extends mod_datalynx\local\view\base
  classes/form.php         # view settings form
  classes/view_patterns.php
```

Built-in types: `tabular`, `grid`, `csv`, `pdf`, `email`, `report`.

## Field behavior

`datalynx_behaviors` rows control visibility (`is_visible_to_user()`), editability (`is_editable_by_user()`), required status (`is_required()`), and conditional availability (`passes_conditions()`). A field without an explicit behavior record gets a default behavior (always visible, always editable, not required, no conditions). When `passes_conditions()` returns false the field is hidden in both view and edit mode and cannot be required.

## Field format

`datalynx_field_formats` rows apply display modifiers to rendered values. Used via `[[fieldname:formatname]]` tags. Base class: `mod_datalynx\local\field_format\base`. Manager: `mod_datalynx\local\field_format\manager`. Field types opt in by implementing `field_format.php` in their `classes/` directory.

## Key entry points

| File | Purpose |
|---|---|
| `view.php` | All entry browsing and editing (query params: `d`, `view`, `filter`, `editentries`, `eids`) |
| `fieldlayout/index.php` | Manage field layouts |
| `fieldlayout/layout_edit.php` | Create/edit a single field layout |
| `mod_form.php` | Module instance settings (Moodle standard) |

## JavaScript AMD modules

Source in `amd/src/`, compiled to `amd/build/`. Key modules:

- `layouteditor` — field layout editor (WYSIWYG with `#input`/`#value` token insertion)
- `patterndialogue` — tag picker for inserting `[[field|...]]` patterns into view templates
- `behaviorform` — visibility/editability/condition rule editor
- `fieldgroups` — repeatable fieldgroup row UI
- `bulkactions` — bulk select/edit/delete
- `tabularbulkedit` — inline editing in tabular views
- `datalynxloadviews` — lazy-load view content via AJAX

## Non-obvious conventions

- `param1`–`param10` on both fields and views store arbitrary config as plain text with no schema — check the field type's `form.php` to understand what each param means for a given type.
- Internal fields (e.g., `_entry`, `_user`, `_time`) are not stored in `datalynx_contents`; they are read directly from the entry record. `datalynxfield_base::is_internal()` returns `true` for these.
- Status field (`datalynxfield_status`) has special locking logic: once an entry reaches `STATUS_FINAL_SUBMISSION`, only behaviors with `editable_after_final = true` allow further editing, regardless of the user's role (unless they have `manageentries`).
- Never edit `db/install.xml` by hand — use the XMLDB editor at `Site admin > Development > XMLDB editor`.
- Always provide both `lang/en/mod_datalynx.php` and `lang/de/mod_datalynx.php` string additions.
