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

- Required vs not required behavior
- Visibility by permission/role
- Editability by permission/role

### Workflow

1. Open **Field behaviors**.
2. Click **Add** or edit an existing behavior.
3. Set visibility and editability rules.
4. Configure required behavior where needed.
5. Save and test with role-based accounts.

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
| `[[FieldName:date]]` | Shows a date-format variant for time-like fields |

### Entry and action patterns

| Pattern | Result |
|---|---|
| `##entryid##` | Shows current entry ID |
| `##edit##` | Shows edit action (if permitted) |
| `##delete##` | Shows delete action (if permitted) |
| `##approve##` | Shows approval action (if permitted) |
| `##select##` | Shows selection checkbox for bulk actions |

### View navigation and filter patterns

| Pattern | Result |
|---|---|
| `##viewsmenu##` | View switcher menu |
| `##filtersmenu##` | Filter menu |
| `##quicksearch##` | Quick search box |
| `##quickperpage##` | Per-page selector |
| `##pagingbar##` | Pagination bar |
| `##entries##` | Main entries container |

### Bulk operation patterns

| Pattern | Result |
|---|---|
| `##multiedit##` | Bulk edit action |
| `##multidelete##` | Bulk delete action |
| `##multiapprove##` | Bulk approve action |
| `##multiexport##` | Bulk export action |
| `##selectallnone##` | Select all/none control |

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
- [User Guide — Views](user_guide_views.md)
- [User Guide — Permissions](user_guide_permissions.md)
