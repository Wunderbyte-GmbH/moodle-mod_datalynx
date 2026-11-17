# Datalynx User Guide — Managing Entries

## What this guide covers

This guide explains daily entry operations for teachers and course managers:

- Adding and editing records
- Searching and filtering
- Working in the Moodle App
- Bulk actions
- Import/export workflows

---

## Add a new entry

1. Open your Datalynx activity and choose the appropriate entry view.
2. Click **Add new entry** (or **Add new entries**, depending on view setup).
3. Complete required fields.
4. Upload files if needed.
5. Click **Save**.

> **Important Note**  
> If your site uses approval workflows, a saved entry may not be fully visible to all users until approved.

---

## Edit an existing entry

1. Open a view where entry actions are available.
2. Locate the entry.
3. Click **Edit**.
4. Update fields.
5. Click **Save**.

---

## Search and filter entries

### Quick search

1. Open a view that includes `##quicksearch##`.
2. Enter a keyword.
3. Press Enter to update results.

### Filter menu

1. Open a view that includes `##filtersmenu##`.
2. Select an existing filter.
3. Review the filtered results.

### Custom filters

1. Open a view that includes `##customfilter:NAME##`.
2. Fill in the fields of the search form (for example a text, a date range or several choices).
3. Choose a sort field and direction if the form offers them.
4. Apply the filter and verify the result set.

Custom filters are set up under **Manage → Custom Filters**. The same filter works in the Moodle App.

---

## Entries in the Moodle App

Learners and teachers can browse, search, add, edit, delete and approve entries in the Moodle App.
The app follows the tags of the view: it offers **Edit** where the view has `##edit##`,
**Approve** where it has `##approve##`, and so on. See
[User Guide — Moodle App](user_guide_moodle_app.md).

---

## Bulk actions for teachers and managers

Bulk actions are typically available in operational views.

1. Open a view that includes row selection and bulk controls.
2. Select records individually or use **Select all/none**.
3. Run the required bulk action.

Common bulk operations include:

- Bulk edit
- Bulk delete
- Bulk duplicate
- Bulk approve
- Bulk export

| Bulk action | Use case | Caution |
|---|---|---|
| **Bulk edit** | Apply same change to many records | Validate target set before applying |
| **Bulk delete** | Remove obsolete test/duplicate records | Confirm backup/export first |
| **Bulk approve** | Move pending entries to approved | Ensure review policy is complete |
| **Bulk export** | Share or archive selected records | Respect privacy rules |

> **Warning**  
> Bulk operations can affect many entries instantly. Always verify your selection before confirming.

---

## Import entries

1. Open a view or action area with import enabled.
2. Click the import action.
3. Upload your file in the required format.
4. Confirm the import.
5. Review import results and correct errors if needed.

### Import preparation checklist

1. Ensure field names and values match your Datalynx configuration.
2. Use consistent date and status formats.
3. Test with a small sample file first.

---

## Export entries

1. Open a view with export actions.
2. Apply filters if you need a subset.
3. Select export scope (current page, selected, or full set depending on permissions/view).
4. Download and verify file contents.

Common export targets:

- CSV for spreadsheet/reporting
- PDF for printable or formal review documents

---

## Scenario: Booking Management System operations

### Daily teacher routine

1. Open **Teacher Review** view.
2. Use **Quick search** to locate today’s requests.
3. Filter status to pending items.
4. Add comments and approve selected entries.
5. Export approved list for operational handover.

### Course manager routine

1. Open management summary view.
2. Check totals and pending workload.
3. Run bulk updates where policy allows.
4. Export monthly archive.

---

## Role-focused entry management table

| Role | Typical allowed actions | Typical restricted actions |
|---|---|---|
| **Student** | Add and edit own entries (if enabled), comment where allowed | Bulk moderation, global export, admin-level actions |
| **Teacher** | Review, comment, approve, filter/search, operational export | Site-level configuration and manager-only workflows |
| **Course Manager** | Full operational management and advanced exports | Actions restricted to site administrators |

---

## Troubleshooting entry operations

| Problem | Likely cause | What to do |
|---|---|---|
| Add button is missing | View does not include add action or role lacks permission | Check view template and role permissions |
| Cannot edit a record | Entry action not visible or edit permission restricted | Verify view actions and **Editable by** settings |
| Filter returns no results | Filter conditions too strict | Remove one condition and re-test |
| Add button is missing although the view has the tag | The user has reached the maximum number of entries | Check **Maximum entries** in the activity settings; managers are not limited |
| Export option unavailable | Role permission or view configuration limitation | Ask manager/admin to review export capabilities |

---

## Next

- [User Guide — Permissions](user_guide_permissions.md)
- [User Guide — Moodle App](user_guide_moodle_app.md)
- [User Guide — Patterns, Styling, and Tools](user_guide_patterns_and_styling.md)
