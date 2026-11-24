# Datalynx Developer Notes — Moodle App

This page is for developers working on the Moodle App support of `mod_datalynx`. For what teachers
and learners see, read [Using Datalynx in the Moodle App](user_guide_moodle_app.md).

---

## Architecture in one paragraph

Datalynx uses the **site plugin** route of the Moodle App: `db/mobile.php` registers a course
module handler, `\mod_datalynx\output\mobile` returns server-rendered Ionic templates
(`templates/mobile/*.mustache`), and the app calls web services of the plugin that are part of
`MOODLE_OFFICIAL_MOBILE_SERVICE`. There is no TypeScript in the app and no offline mode.

The rule that shapes everything: **no parallel structures for the app.** The app goes through the
same code as the web, and app code only adds the projection the app needs.

| Concern | Shared code used by web and app |
|---|---|
| Browsing a view | `local\view\manager\view_manager` (open, filter, load) and `external\browse_request` |
| Search criteria | `local\filter\search_criteria` (strict JSON contract) |
| Custom filters | `local\filter\customfilter_controls` (one description, one parser), `customfilter_search`, and `view\base::get_offered_customfilters()` (which a view offers) |
| Saving an entry | `local\entry\entry_submission_service` and the input contract of each field type |
| Edit controls | `datalynxfield_renderer::get_edit_control()` per field type |
| Edit form | `local\entry\entry_edit_form` |
| Next view after a save | `view\base::get_next_step()` (Redirect on submit + continue editing): the web redirect, `submit_entry` and the app |
| Addresses of an itinerary | `datalynxfield_itinerary\field::resolve_stops()`: a stop sent with an address only is looked up on save (validation and storage) |
| Rights | Through a view: `view\base::user_can_add_entry()` (`offers_adding()`, also the web's `new=1`), `user_can_act_on_entry('edit'\|'delete', $entry)`, `user_can_approve_through_view()` (the `##approve##` switch, the app's button and `toggle_entry_approval`); below them `datalynx::user_can_manage_entry()`, `user_can_add_new_entry()`, `user_can_approve_entries()`, `view\base::confirm_view_action()` |
| Filter menu | `view\base::get_filter_menu()` (web filter menu, app menu, app links) |
| Dates of inputs | `local\date_input` (YYYY-MM-DD[THH:MM] in the user's timezone; custom filters and the app form) |
| Links to other views | `datalynxview_patterns::get_view_link()` (every rule of `##viewlink##` / `##viewsesslink##`) |
| View template | `view\base::get_section_html()` (the section as `display()` shows it, without changing the view) |

App-only code:

| File | Purpose |
|---|---|
| `classes/output/mobile.php` | `mobile_course_view()` (browse) and `mobile_edit_entry()` (add/edit) |
| `classes/local/mobile/entries_payload_builder.php` | Projects entries onto the app's shape (`html`, `fields`, `canedit`, …) |
| `classes/local/mobile/html_sanitiser.php` | Removes scripts, forms and controls from entry HTML |
| `classes/local/mobile/customfilter_form.php` | Custom filters offered by a view, and the app's filter inputs |
| `classes/local/mobile/app_links.php` | Turns links to other views into app buttons and cuts HTML into parts |
| `templates/mobile/view_page.mustache`, `edit_entry.mustache` | Ionic templates |
| `mobileapp/styles.css` | Styles loaded by the app, scoped to `.mod-datalynx-mobile` |

---

## Links between views and the view template

The app shows the view template (section) and the entry template as **parts**: HTML, a link button,
HTML, and so on, plus one part in the section that marks where the entries are listed.

1. `entries_payload_builder` renders both templates with two render options from
   `app_links::render_options()`. These options pass through `datalynxview_patterns::get_replacements()`:
   - `linkrenderer`: a callable that receives each resolved link (`get_view_link()`, also used for
     `##addnewentry##`) and returns its HTML. `app_links` keeps the link and returns a placeholder.
     Without the option the link is the web's anchor.
   - `pagecontrols => false`: the controls of the web page render empty. These are the menus,
     quick search, per page, paging bar, custom filter forms and bulk actions.
2. `app_links::parts()` cuts the HTML at the placeholders and at `##entries##`. Each HTML part is
   cleaned on its own (`html_sanitiser`); end tags a part does not open are dropped first.
3. A link opens `mobile_course_view` (a list), `mobile_edit_entry` (`new=1`, or `editentries=` with
   one entry) or, for anything else, the web page through `core-link`. A `filter=` is passed only
   where the target view offers it (`view\base::get_filter_menu()`, also the web's filter menu).

The template draws every link from one `ng-template` (`#dlxlink`), and the section and the
entries share it. `core-site-plugins-new-content` opens a new page, so the app's back button
returns, and `getFromCache: false` makes a view show entries saved in the meantime.

---

## Web services in the mobile service

| Function | Use |
|---|---|
| `mod_datalynx_get_mobile_entries` | Entries of a view, with search, filters and paging |
| `mod_datalynx_get_entry_edit_form` | The form to add or edit an entry, with a control per field |
| `mod_datalynx_submit_entry` | Save an entry through a view, with the rules of its entry form |
| `mod_datalynx_delete_entry` | Delete one entry through a view |
| `mod_datalynx_toggle_entry_approval` | Approve or disapprove an entry through a view with `##approve##` (also the web's switch); runs the web's approval (`process_entries`) |

Files and images need no extra service: entry HTML carries absolute `pluginfile.php` URLs of the
activity, and the app's `core-format-text` fetches them with the user's token.

---

## Development workflow

1. **After every change to `db/mobile.php`**, purge all caches. The app caches the plugin
   definition (`tool_mobile` plugin info), including the list of language strings.
2. **After every change to `db/services.php`**, bump `version.php` and run the upgrade. Otherwise
   the function is not added to the mobile service.
3. **A new string used in an app template** must be added to the `lang` list in `db/mobile.php`,
   in English and German.
4. In the app, open **Settings → About**, tap the app version several times to unlock the
   developer options, then turn on:
   - **Always show site plugin content errors**, so template errors are shown instead of an empty page;
   - **Force reload site plugins**, or log out and in again after a change.
5. The Moodle App in a desktop browser works well for development: run the app's Docker image and
   open it in Chrome with the device toolbar set to 375 px.

---

## Template rules (Ionic Mustache)

- The delimiters are switched to `<% %>` so that `{{ }}` stays Angular's. Only `cmid` and
  `courseid` are written into the source; everything else is bound from `CONTENT_OTHERDATA.state`.
- **otherdata values are strings.** The app JSON-parses a value only when it starts with `{` or
  `[`. The whole page state therefore goes as one JSON object, `otherdata.state`.
- Search uses `core-search-box` (it needs `searchLabel`). It submits its text again when it starts,
  so guard `onSubmit` with `$event !== state.search`, or the page reloads forever.
- Endless lists use `core-infinite-loading`.
- Forms: `core-site-plugins-new-content` with `[samePage]="true"` and `form="<name>"` posts the
  form's inputs as arguments. A multiple `ion-select` posts a comma-separated string.
- After a save that leads to another view (a chain of steps), `mobile_edit_entry` answers with one
  line of JavaScript, `this.updateContent(args, component, method)`. It replaces the page with the
  next step, and with arguments that no longer hold the form, so pulling to refresh does not save
  again. A content method cannot switch to another method without it.
- Rows the user adds or moves (the stops of an itinerary) live in `CONTENT_OTHERDATA.state` and are
  changed by template expressions (`splice`), not by JavaScript.
- Entry HTML is bound with `[text]` on `core-format-text`, never written into the template source.
- `moodle-plugin-ci mustache` fails on warnings. The validator rejects `*ngIf` on native `p`, `div`
  and `form`, and Angular bindings on native `input`; use `ng-container` or an Ionic element.

---

## Accessibility

- Every input has a visible `label` (Ionic `label` with `labelPlacement="stacked"`).
- Icons next to text are `aria-hidden="true"`.
- The action buttons of an entry are grouped (`role="group"`) and named after the entry, so a
  screen reader announces "Edit" together with the entry it belongs to.
- Custom filter chips are toggle buttons (`role="button"`, `aria-pressed`).
- The entry count is a polite live region, so a search result is announced.

---

## Tests

| Kind | Where |
|---|---|
| PHPUnit | `tests/output/mobile_test.php`, `tests/output/mobile_edit_template_test.php` (entry template around the inputs), `tests/external/*` (one per service), `tests/local/mobile/*` (links: `app_links_test.php`), `tests/local/field/edit_control_test.php` |
| App acceptance (Behat `@app`) | `tests/behat/app_browse.feature`, `app_customfilter.feature`, `app_edit.feature`, `app_delete.feature`, `app_approve.feature`, `app_navigation.feature`, `app_edit_template.feature` |

The `@app` features need the plugin `local_moodleappbehat` in the Moodle root (same version as the
app) and an app container; see
[Acceptance testing for the Moodle App](https://moodledev.io/general/app/development/testing/acceptance-testing).
Without them Moodle skips the `@app` features.

Steps that work well:

- `Given I entered the course "Course 1" as "student1" in the app` and then
  `I press "<activity name>" in the app`;
- `I press "Edit" near "Fruit: Apple" in the app`;
- `I pull to refresh in the app` after saving;
- `the header should be "<view name>" in the app` after following a view link, `I go back in the app` to return.

Gotchas:

- The datalynx Behat field step names fields "Datalynx field X"; templates must use `[[Datalynx field X]]`.
- The editor step drops a leading `##`; start a view section with a space or an HTML tag.
- The editor step does not take `\"` inside its text; use single quotes for HTML attributes.
