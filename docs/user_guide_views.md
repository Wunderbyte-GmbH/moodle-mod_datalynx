# Datalynx User Guide — Views

## Why views are central in Datalynx

Views define **how entries are displayed, searched, and managed** for each audience.

A good view strategy usually includes:

1. A student-facing submission/browse view.
2. A teacher review view.
3. A manager/control view.

> **Important Note**  
> In Datalynx, one activity can have multiple views. You can expose different views to different roles.

---

## Open the Views area

1. Open your Datalynx activity.
2. Open the Datalynx management menu.
3. Click **Views**.
4. Click **Add a view**.
5. Select the required view type.
6. Configure templates and settings.
7. Click **Save view**.

---

## Available view types

In Moodle 4.5+, Datalynx views use **progressive AJAX-based browse loading** powered by Mustache templates and Web Services. This allows you to page, filter, and sort entries dynamically without performing a full page reload, resulting in a much faster and smoother browsing experience.

| View Type | Best used for | Typical audience |
|---|---|---|
| **Grid** | Card-style layout with visual grouping and automatic grid column wrapping | Students, teachers |
| **Tabular** | Row/column list for operational work | Teachers, managers |
| **Report** | Structured reporting and export-oriented display | Managers, teachers |
| **Csv** | Data extraction and CSV-centric workflows | Managers |
| **PDF** | Printable and formal output | Teachers, managers |
| **Email** | Notification and message templates | Teachers, managers |

---

## Practical layout model: Grid, List, and Single-style output

Even when exact labels vary by site configuration, most teams build three layout intents:

- **Grid View**: visual cards or tiles for easy browsing.
- **List View**: compact rows for comparison and filtering.
- **Single View**: detailed per-entry display (often via “more” links or detail-focused templates).

### How to build a Grid-style view

1. In **Views**, click **Add a view**.
2. Select **Grid**.
3. In **Grid settings**, select the appropriate **Entry wrapper** to configure how your cards are wrapped:
   - **Bootstrap Grid Column (Recommended)**: Wraps entries in a responsive Bootstrap row-cols layout (`col`). The columns automatically match the parent row settings.
   - **Columns per row**: Force format entries into 1, 2, 3, or 4 columns per row (e.g. `col-12`, `col-12 col-md-6`, etc.).
   - **Legacy Datalynx wrapper (entry)**: Wraps each entry in the standard `entry` class div.
   - **Custom CSS classes**: Specify custom space-separated CSS classes. If they contain `col-`, an outer row container is automatically generated.
   - **No wrapper tag**: Omit wrapper elements completely for raw output rendering.
4. Check the **Applied Grid Wrappers** infobox directly in the settings form to see the live HTML tag preview that will be generated for your view and entry templates.
5. In your layout section, insert entry tags such as `[[Text]]` and `##comments##`. (Note: Since wrappers are automatically applied, you do not need to wrap your templates in Bootstrap rows/columns manually).
6. Add navigation components such as `##viewsmenu##` and `##pagingbar##`.
7. Click **Save view** and test with sample entries.

> **Important Note on Obsolete Table-based Settings**  
> The old table-based layout configurations (the **Cols** and **Rows** parameters that forced entries into HTML table markup) have been **completely removed**. The `##begintablecell##` tag is no longer supported and has been deprecated. Grid layouts are now fully responsive and rely on CSS/Bootstrap grid wrappers.


### How to build a List-style view

1. In **Views**, click **Add a view**.
2. Select **Tabular** (or **Report** for report-focused lists).
3. Place key columns first (for example, title, status, owner, date).
4. Add action tags where required (for example, edit/delete/approve in teacher views).
5. Click **Save view** and confirm sorting/filtering behavior.

### How to build a Single-style detailed output

1. Create a dedicated detail-oriented view (often **Grid**, **Report**, or **PDF**, depending on your output goal).
2. Include full entry content and comment/review sections.
3. Add navigation using `##viewsmenu##` and entry links.
4. Test with student and teacher accounts.

> **Pro-Tip**  
> Keep student views simple and task-focused. Keep teacher views operational with filters and actions.

---

## Core template tags used in views

| Tag | What it does in the interface |
|---|---|
| `[[Text]]` | Prints a field value (example: entry title) |
| `##entries##` | Renders the entry list/content block |
| `##viewsmenu##` | Shows the view switch menu |
| `##filtersmenu##` | Shows the filter selector (limited to the view's permitted filters — see [Controlling which filters users can apply](#controlling-which-filters-users-can-apply)) |
| `##quicksearch##` | Adds quick search input |
| `##quickperpage##` | Adds entries-per-page selector |
| `##pagingbar##` | Adds pagination controls |
| `##comments##` | Shows comments section |
| `##addnewentry##` | Displays add-entry action where allowed |

---

## Scenario: Student Portfolio view set

### View 1 — Student Submission View

1. Add a **Grid** view.
2. Include student-facing fields (title, reflection, attachment).
3. Add `##addnewentry##` and `##quicksearch##`.
4. Save with clear naming, such as “Student Portfolio Submission”.

### View 2 — Teacher Review View

1. Add a **Tabular** or **Report** view.
2. Include fields needed for review decisions.
3. Add `##comments##` and approval-related actions.
4. Add `##filtersmenu##` for workflow slicing (for example, pending review).

### View 3 — Management Summary View

1. Add a **Report** or **PDF** view.
2. Focus on summary/status indicators.
3. Add export options where required.
4. Verify visibility for managers only.

---

## Recommended settings checklist for each view

| Setting Name | Description | Recommended Value |
|---|---|---|
| **View name** | Name shown in menu | Role + purpose (for example, “Teacher Review”) |
| **Default view** | First view shown to users | Student-facing browse/submission view |
| **Visible by** | Which roles can see this view | Restrict review/admin views |
| **Default filter** | Filter applied when the view opens | Most restrictive filter for the audience |
| **Permitted filters** | Extra filters users may switch to | Leave empty to lock; list a few for curated switching |
| **Filter area** | Search/filter controls in layout | Enable for teacher/manager workflows |
| **Paging controls** | Entries per page and navigation | Enable when entry volume grows |

> **Warning**  
> If a view is visible to learners and includes teacher-only actions or fields, sensitive workflow information may be exposed.

---

## Controlling which filters users can apply

A view's **filter** decides which entries a user can see. For example, a student view can use a filter that only shows the entries the student authored, so controlling filters is an important part of keeping data private.

Each view has three filter settings, shown when you edit the view:

| Setting | What it does |
|---|---|
| **Default filter** | The filter applied when the view first opens. With no other settings, the view is locked to this filter and users cannot change it. |
| **Permitted filters** | An optional list of *additional* filters users may switch to in view mode (using the `##filtersmenu##` dropdown or the `filter` URL parameter). Only the default filter and the filters you list here can be selected — any other filter is rejected and the default is applied instead. Leave empty to lock the view to the default filter. |
| **Allow all filters** | When enabled, users can switch to *any* visible filter, including ones created later. This overrides the Permitted filters list. Use with care — it can expose entries you intended to hide. |

> **Important Note**  
> If you select one or more **Permitted filters**, you must also choose a **Default filter**. The default filter should be the most restrictive one, because it is what applies when no specific filter is requested.

### How the filtering choices behave

- **Locked view (default filter only):** Leave *Permitted filters* empty and *Allow all filters* off. The view always uses the default filter and no filter dropdown is shown. This is the safest setting for student-facing views.
- **Curated switching:** Set a *Default filter* and add one or more *Permitted filters*. A filter dropdown appears listing only those filters, and users can switch between them. A request for any other filter falls back to the default.
- **Open switching:** Enable *Allow all filters*. Users can pick any visible filter. The *Permitted filters* list is ignored while this is on.

> **Pro-Tip**  
> While a *Permitted filters* list is in force, personal (saved) filters and ad-hoc custom/advanced searches that would *replace* the base filter are blocked, so they cannot be used to widen what a user sees. Quick search and per-entry links still work — they only narrow the results further.

> **Building a search view?**  
> The same protection is what stops a `##customfilter:NAME##` search form from working, and it fails in two different ways: a locked view hides the form altogether, while a view offering a choice of *Permitted filters* renders the form but ignores what people type into it. A view whose purpose *is* searching therefore needs **Allow all filters** on and **Permitted filters** empty. Its *Default filter* still decides what is listed until somebody searches, so that remains the way to scope the view. (A view with no default filter at all searches fine too — the conflict only arises once a filter is being enforced.)

### Upgrade note

Existing views are unaffected. After upgrading:

- Views that were already locked to a filter keep exactly the same behavior — they now list that one filter as both the **Default filter** and the single **Permitted filter**.
- Views that previously allowed users to override the filter keep **Allow all filters** enabled and continue to allow switching to any filter.

---

## HTML/CSS design patterns for clean layouts

1. Start with semantic sections (header, body, actions area).
2. Use compact field labels for list views.
3. Keep card spacing consistent in grid-style views.
4. Highlight status clearly (approved/pending/not approved).
5. Test the view on desktop and smaller screens.

> **Pro-Tip**  
> For consistency, reuse one layout structure across similar activities instead of redesigning every view from scratch.

---

## Troubleshooting views

| Problem | Likely cause | What to do |
|---|---|---|
| View appears empty | Missing `##entries##` in template area | Add `##entries##` and save |
| Users cannot switch views | `##viewsmenu##` not present or no permission | Add tag and verify role visibility |
| Search box not visible | `##quicksearch##` not in template | Add and save template |
| Teachers cannot find pending records | No review filter in view | Add filters and save a review-specific view |
| Layout broken or cells not rendering properly | Legacy `##begintablecell##` tag or cols/rows used | Obsolete table-based layout settings were removed. Configure the new **Entry wrapper** in Grid settings to automatically generate Bootstrap row/columns wrappers instead. |

---

## Next

- [User Guide — Rules](user_guide_rules.md)
- [User Guide — Managing Entries](user_guide_managing_entries.md)
- [User Guide — Permissions](user_guide_permissions.md)
