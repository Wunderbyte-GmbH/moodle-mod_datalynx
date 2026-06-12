# Datalynx User Guide — Patterns, Styling, and Tools

## Purpose of this guide

This guide covers advanced end-user configuration topics:

- Tools
- Field layouts
- Field behaviors
- Pattern tags (with examples)
- CSS integration
- JavaScript integration

Use this guide after your core fields, views, and permissions are stable.

---

## Tools in Datalynx

Datalynx includes helper tools for common admin workflows.

| Tool | What it helps with | Typical role |
|---|---|---|
| **Entry per user** | Creates one blank entry for each gradable user | Teacher, manager |
| **Download files of all entries** | Downloads entry files as a ZIP archive | Teacher, manager |

### How to run a tool

1. Open your Datalynx activity.
2. Click **Tools**.
3. Select the desired tool.
4. Review options and confirmation screen.
5. Click the execution button.
6. Verify results in the target view.

> **Important Note**  
> Run bulk-affecting tools in low-traffic times and verify output with a sample first.

---

## Field layouts (renderers)

Field layouts help standardize how field output appears across views.

### Typical use cases

- Consistent visual formatting for repeated field types
- Reusable output structure for teacher-facing lists
- Cleaner display text for student-facing entries

### Workflow

1. Open **Field layouts**.
2. Create or edit a layout.
3. Define display structure.
4. Save and apply in target views.
5. Verify with sample entries.

---

## Field behaviors

Field behaviors let you control how fields behave in entry forms.

Typical controls include:

- Required vs not required behavior.
- Visibility by Moodle capability.
- Editability by Moodle capability.
- **Dynamic Context checks**: Define dynamic visibility/editability rules bypassing capabilities:
  - **Dynamic Check: Author**: Limits visibility or editability of a field strictly to the creator/owner of the entry.
  - **Dynamic Check: Mentor**: Restricts visibility or editability of a field strictly to the mentors assigned to the entry's group or team.
- **Availability conditions**: Show and allow editing of a field only when the value of one or more *other* fields in the same entry matches conditions you define (see below).

### Availability conditions

Availability conditions make a field appear (and become editable) only when other fields in the same entry hold the values you specify. This is what lets you build **progressive, branching entry forms** — for example, ask follow-up questions only after an earlier answer warrants them.

How it works:

- In the **Availability conditions** section of a field behavior, first choose the match mode: **When all conditions are met** or **When any condition is met**.
- Add up to **five** condition rows. Each row compares a *source field* against a value:
  **source field** + **is / not** + **operator** + **value**. The operators and value input are the same ones used by the search filters, so a condition like *Diet contains "veg"* behaves exactly as it would in a filter.
- Source fields are limited to these field types: **Text**, **Radio button**, **Select**, **Team member select**, **Time**, and **Duration**.
- Conditions are evaluated when the entry is **displayed or edited**, against the **saved** value of the source field. Because the check uses the saved value, a value entered on an *earlier* view can decide whether a field shows on a *later* view in a multi-view edit flow.
- Attach the behavior to a field in a template with the usual `[[FieldName|behaviorname]]` pattern. Leaving the conditions empty applies no conditions (the field's visibility/editability rules still apply as normal).

> **Example**  
> Create a **Radio button** field `Gender` (`Male`/`Female`) on the first view. On a later view, add a behavior `b_femaleonly` with the condition `Gender` *is* `=` `Female`, then place the target field as `[[FemaleOnlyField|b_femaleonly]]`. The field only appears when the author selected *Female* earlier.

### Workflow

1. Open **Field behaviors**.
2. Click **Add** or edit an existing behavior.
3. Set visibility and editability rules using capability checkboxes and dynamic context checks.
4. Configure required behavior where needed.
5. (Optional) Add **availability conditions** to gate the field on other fields' values.
6. Save and test with role-based accounts.

> **Warning**  
> Overly strict behavior rules can prevent legitimate submissions. Test with realistic user journeys.

---

## Pattern tags: practical reference

Patterns are placeholders that Datalynx replaces with live content.

### Field patterns

| Pattern | Result |
|---|---|
| `[[Text]]` | Shows value of the Text field |
| `[[FieldName@]]` | Shows field label/template-aware output |
| `[[FieldName:formatname]]` | Shows field value rendered with a named **Field Format** (for example, `[[Description:excerpt]]` for a truncated excerpt) |
| `##author:formatname##` | Shows entry author info via a named **Field Format** (for example, `##author:firstname##`) |
| `##ratings:formatname##` | Shows rating output via a named **Field Format** (for example, `##ratings:avg##` for average) |

> **Important Note**  
> The `:formatname` suffix references a **Field Format** you define in the **Field Formats** management area. See [User Guide — Field Formats](user_guide_field_formats.md) for the full reference.

### Entry and action patterns

| Pattern | Result |
|---|---|
| `##entryid##` | Shows current entry ID |
| `##entryidzerofill##` | Shows the entry ID padded with leading zeros (for example `0042`) |
| `##edit##` | Shows edit action (if permitted) |
| `##delete##` | Shows delete action (if permitted) |
| `##duplicate##` | Shows duplicate-entry action (if permitted) |
| `##approve##` | Shows approval action (if permitted) |
| `##export##` | Shows export action for the single entry |
| `##select##` | Shows selection checkbox for bulk actions |
| `##anchor##` | Inserts an HTML anchor for the entry (link target) |
| `##more##` | Shows a "more"/detail link to the entry's single-entry view |
| `##coursevisible##` | Shows whether the course is visible to students |
| `##comments##` | Shows the comments area for the entry |
| `##comments:add##` | Shows the add-comment control |
| `##comments:count##` | Shows the number of comments |
| `##comments:inline##` | Shows comments inline |

### Author and group patterns

These resolve against the entry's author (the user who created it) and its group.

| Pattern | Result |
|---|---|
| `##author##` | Shows the author's full name |
| `##author:firstname##` | Shows the author's first name (other user fields such as `lastname`, `email`, `username`, `idnumber`, `institution`, `department` work the same way) |
| `##author:picture##` | Shows the author's profile picture |
| `##author:picturelarge##` | Shows the author's large profile picture |
| `##author:formatname##` | Shows author info rendered with a named **Field Format** |
| `##group:name##` | Shows the entry's group name |
| `##group:id##` | Shows the entry's group ID |
| `##group:picture##` | Shows the group picture |
| `##group:picturelarge##` | Shows the large group picture |
| `##group:edit##` | Shows the group edit control (if permitted) |

### Rating patterns

Available when ratings are enabled for the activity.

| Pattern | Result |
|---|---|
| `##ratings:rate##` | Shows the rating input control |
| `##ratings:view##` / `##ratings:viewinline##` | Shows the rating breakdown |
| `##ratings:avg##` / `##ratings:avgstar##` / `##ratings:avgbar##` | Shows the average rating (number, stars, or bar) |
| `##ratings:count##` | Shows the number of ratings |
| `##ratings:sum##` / `##ratings:min##` / `##ratings:max##` | Shows the sum / minimum / maximum rating |
| `##ratings:formatname##` | Shows rating output rendered with a named **Field Format** |

### View navigation and filter patterns

| Pattern | Result |
|---|---|
| `##viewsmenu##` | View switcher menu |
| `##filtersmenu##` | Filter menu |
| `##quicksearch##` | Quick search box |
| `##quickperpage##` | Per-page selector |
| `##advancedfilter##` | Advanced (custom) filter form |
| `##customfilter:NAME##` | A specific saved custom filter, by name |
| `##pagingbar##` | Pagination bar |
| `##numentriestotal##` | Total number of entries matching the filter |
| `##numentriesdisplayed##` | Number of entries shown on the current page |
| `##addnewentry##` / `##addnewentries##` | "Add new entry" link(s) (if permitted) |
| `##entries##` | Main entries container |

> **Important Note on Tag Placement**  
> View-level navigation and filter patterns (like `##viewsmenu##`, `##filtersmenu##`, `##quicksearch##`, `##quickperpage##`, `##pagingbar##`, and `##entries##`) are designed for use **only in the View template**. To prevent configuration errors, these patterns are explicitly excluded from the Entry template editor's general tag selection menu.

### View link patterns (`##viewlink##`, `##viewsesslink##`, `##viewurl##`)

These patterns build links and URLs to **other views** of the same Datalynx activity. They are the recommended way to wire navigation buttons (for example "Edit", "Review", "Add new entry") into entry cards and view templates.

| Pattern | Result |
|---|---|
| `##viewurl##` | URL of the **current** view |
| `##viewurl:VIEWNAME##` | URL of the named view (no link markup, just the URL) |
| `##viewcontent:VIEWNAME##` | Renders the content of the named view inline |
| `##viewlink:VIEWNAME;LINKTEXT;URLQUERY;CSSCLASS##` | A clickable link to the named view |
| `##viewsesslink:VIEWNAME;LINKTEXT;URLQUERY;CSSCLASS##` | Like `##viewlink##`, but also adds the **session key** (`sesskey`) |

**When to use `##viewsesslink##` vs `##viewlink##`**

Use `##viewsesslink##` whenever the link triggers an **action** that changes data — creating an entry (`new=1`) or editing one (`editentries=...`). These actions require a valid session key, which `##viewsesslink##` adds automatically. Use plain `##viewlink##` for read-only navigation.

**The four parts (separated by `;`)**

1. **VIEWNAME** — the exact name of the target view.
2. **LINKTEXT** — the visible link text. It may contain HTML (for example a Font Awesome icon: `<i class="fa fa-pencil"></i> Edit`).
3. **URLQUERY** — extra URL parameters (see below). Leave empty if not needed.
4. **CSSCLASS** — CSS classes applied to the link (for example `btn btn-primary btn-sm`).

**URL query parameters**

Separate several parameters with a **pipe character (`|`)**. The query may contain entry tags such as `##entryid##`, which are resolved for the current entry. Common parameters:

- `new=1` — open the target view in "create new entry" mode.
- `editentries=##entryid##` — open the target view editing the current entry.
- `filter=FILTERID` — apply a specific filter on the target view. This is the **numeric filter id** (not the filter name), for example `filter=12`. Use `filter=-1` for the user's own filter.

Examples:

```text
new=1
filter=12|editentries=##entryid##
```

> **Important Note**  
> The link is built to the target view using only its identifying parameters. The **current** page's filter, search, and paging state is **not** carried over. If you want the target view to apply a particular filter, add it explicitly in the URL query (for example `filter=12`).

> **Tip — finding the filter id**  
> Open the filter in the activity's **Filters** management area; the numeric id appears in the page URL (the `filter` / `fid` parameter). That number is what you put after `filter=`.

**Full examples**

```text
##viewsesslink:Schritt 1 – Antragstyp und Sektion;<i class="fa fa-pencil me-2"></i>Bearbeiten;editentries=##entryid##;btn btn-outline-primary btn-sm##
##viewsesslink:Schritt 1 – Antragstyp und Sektion;<i class="fa fa-plus me-2"></i>Neuen Antrag stellen;new=1;btn btn-primary btn-lg##
##viewlink:Overview;Back to overview;;btn btn-link##
```

> **Tip**  
> In the TinyMCE editor you can insert and configure these tags through the tag dialog instead of typing them by hand. The dialog's **URL query** field accepts the same `|`-separated syntax shown above.

### Bulk operation patterns

| Pattern | Result |
|---|---|
| `##multiedit##` | Bulk edit action |
| `##multidelete##` | Bulk delete action |
| `##multiapprove##` | Bulk approve action |
| `##multiduplicate##` | Bulk duplicate action |
| `##multiexport##` | Bulk export action |
| `##multiimport##` | Bulk import action |
| `##selectallnone##` | Select all/none control |

> **Tip**  
> Each bulk action also has an icon-only variant — append `:icon` (for example `##multiedit:icon##`, `##multidelete:icon##`, `##multiapprove:icon##`, `##multiduplicate:icon##`, `##multiexport:icon##`) to render a compact icon button instead of a text button.

### Notification/email patterns

| Pattern | Result |
|---|---|
| `##notificationentryurl##` | Link URL to relevant entry |
| `##notificationentrylink##` | Clickable entry link |
| `##notificationdatalynxurl##` | Link URL to activity |
| `##notificationdatalynxlink##` | Clickable activity link |

---

## Scenario-based pattern example (Booking workflow)

Template idea for teacher review cards:

1. Header with request title: `[[Text]]`
2. Operational actions: `##edit## | ##approve##`
3. Discussion area: `##comments##`
4. Navigation: `##viewsmenu##`

Result for users:

- Teachers can open and process requests quickly.
- Managers can navigate between overview/review/control views.
- Students see only role-allowed content and actions.

---

## Integrating CSS in Datalynx views

Custom CSS can improve readability and visual consistency across views.

### CSS workflow

1. Open the relevant view/template area.
2. Locate custom style settings (for example, activity/view style section).
3. Add styles for cards, labels, spacing, and status highlighting.
4. Save and test in student and teacher roles.

### CSS recommendations

| Goal | Recommendation |
|---|---|
| Readability | Use clear spacing and line-height |
| Status clarity | Use consistent color coding for states |
| Mobile support | Avoid fixed-width blocks for core content |
| Reuse | Keep one small style system for all views |

---

## Integrating JavaScript in Datalynx views

JavaScript can add interaction enhancements to advanced templates.

### JavaScript workflow

1. Open view/activity custom script settings.
2. Add small, focused behavior only (for example, toggles, helper interactions).
3. Save and test in all target roles.
4. Confirm no conflict with core Moodle navigation/forms.

### JavaScript safety checklist

1. Keep scripts minimal and task-specific.
2. Avoid hidden automatic actions users do not expect.
3. Test with real entry volumes.
4. Re-test after major Moodle/plugin upgrades.

> **Pro-Tip**  
> Prefer better template structure and CSS first. Add JavaScript only when it clearly improves usability.

---

## Final quality checklist for advanced customization

1. Patterns render correctly in all role-specific views.
2. Field behaviors do not block valid submissions.
3. Tools are documented for your teaching team.
4. CSS remains readable across devices.
5. JavaScript enhancements are optional, stable, and tested.

---

## Next

- [Documentation home](README.md)
- [User Guide — Field Formats](user_guide_field_formats.md)
- [User Guide — Views](user_guide_views.md)
- [User Guide — Permissions](user_guide_permissions.md)
