# Datalynx User Guide — Fields

## Why fields matter

Fields define **what information users can enter and what reviewers can see**.

In Datalynx, your field design determines:

- Data quality
- Workflow clarity
- Reporting options
- Learner/teacher user experience

> **Important Note**  
> Keep field names short and user-friendly. Teachers and learners will see these labels directly in forms and views.

---

## Open the Fields area

1. Open your Datalynx activity.
2. Go to the Datalynx management menu.
3. Click **Fields**.
4. Click **Add a field**.
5. Select the required field type.
6. Configure settings and click **Save changes**.

---

## Recommended starter field set

For most first deployments, begin with:

1. **Text** (title or short answer)
2. **Text area with editor** (rich description)
3. **File** or **Media files** (evidence upload)
4. **Time** (event date/time)
5. **Select** or **Checkbox** (status/category)
6. **Datalynx Comment Field** (review conversation)

---

## Field types available in this plugin

| Field Type | Best used for | Example in practice |
|---|---|---|
| **Text** | Short single-line input | Portfolio title |
| **Text area** | Multi-line plain text | Reflection summary |
| **Text area with editor** | Rich formatted content | Detailed report with formatting |
| **Number** | Numeric input | Budget, score, quantity |
| **Time** | Date/time values | Event start time |
| **Duration** | Length values | Session duration |
| **File** | File uploads | Attachments or documents |
| **Media files** | Image/media-based evidence | Portfolio screenshots |
| **Url** | External links | Evidence URL |
| **Select** | One option from list | Status |
| **Select (multiple)** | Multiple options | Skills list |
| **Checkbox** | Simple yes/no style choices | Completed checklist item |
| **Radio button** | Single-choice option set | Submission type |
| **Tag** | Flexible labeling | Topic tags |
| **Identifier** | Unique reference code | Internal tracking code |
| **Datalynx Comment Field** | Reviewer discussion | Teacher feedback thread |
| **Team member select** | Team assignment | Project team member |
| **Course group** | Group context | Course-based grouping |
| **User custom info** | Profile-linked data | Department/program info |
| **Datalynx Approve Field** | Approval workflow marker | Teacher approval status |
| **Datalynx Rating Field** | Scoring/review | Quality rating |
| **Datalynx Status Field** | Workflow stage display | Draft / Approved |

> **Pro-Tip**  
> Use **Select** and **Radio button** instead of free text whenever consistency is important for filtering and reporting.

---

## Key field configuration settings

| Setting Name | Description | Recommended Value |
|---|---|---|
| **Field name** | Label users see in forms/views | Use clear language (for example, “Project Title”) |
| **Description/Help text** | Guidance shown to users | Add a short instruction with one example |
| **Required** | Prevents empty submissions | Enable for critical fields only |
| **Visible by** | Controls who can view field output | Keep broad for student-facing data, restrict sensitive fields |
| **Editable by** | Controls who can edit field values | Restrict approval/status fields to teachers/managers |
| **Default value** | Pre-filled value for convenience | Use only when the same value applies often |
| **Display template** | How field value is shown in browse mode | Keep simple first; enhance after testing |
| **Edit template** | How input element appears in edit mode | Keep default unless strong UX reason |

> **Warning**  
> Changing visibility or edit permissions on live activities can immediately change what users can see and modify.

---

## Scenario: Booking Management System field design

### Goal
Track booking requests, approvals, and communication.

### Suggested field setup

1. Add **Text** field: “Booking title”.
2. Add **Time** field: “Requested date”.
3. Add **Team member select** field: “Responsible staff”.
4. Add **Datalynx Status Field**: “Workflow status”.
5. Add **Datalynx Comment Field**: “Internal comments”.
6. Add **File** field: “Supporting document”.

### How tags work in the final layout

When you place tags in templates, they render as user-facing content:

- `[[Text]]` shows the booking title value.
- `##comments##` shows the comment area for review collaboration.
- `##viewsmenu##` shows available views for authorized users.

Example review block:

- Request: `[[Text]]`
- Notes: `##comments##`
- Navigation: `##viewsmenu##`

---

## Field behaviors, layouts, and design options

Datalynx can be extended with additional building blocks:

| Area | What it is | Typical user-facing result |
|---|---|---|
| **Field behaviors** | Rules attached to fields (for example, making fields required in specific situations) | Smarter forms with guided completion |
| **Field layouts** | Reusable presentation structures for fields | Cleaner, consistent entry design |
| **Tools** | Extra actions such as creating entries in bulk or downloading files | Faster administration workflows |
| **Template patterns** | Tags and pattern snippets placed in templates | Dynamic output based on each entry |
| **CSS styling** | Visual formatting for view templates | Branded, readable layouts |
| **JavaScript enhancements** | Interactive behavior in templates/forms | Better usability for complex forms |

> **Pro-Tip**  
> Keep JavaScript and CSS enhancements minimal at first. Confirm workflow success before adding advanced styling or interactions.

---

## Pattern examples you can reuse

| Pattern | What users experience |
|---|---|
| `[[Text]]` | Shows the value entered in the Text field |
| `##comments##` | Shows the comments section for discussion |
| `##viewsmenu##` | Shows a view-switch menu if user has access |
| `[[FieldName@]]` | Shows field label/content style based on label/template setup |

---

## Quality checklist before going live

1. Every required field has clear help text.
2. Sensitive fields are visible/editable only by intended roles.
3. Option lists (Select/Radio) use consistent wording.
4. One full sample entry has been submitted and reviewed.
5. Teacher and student views are both checked with test accounts.

---

## Troubleshooting quick guide

| Problem | Likely cause | What to do |
|---|---|---|
| Users cannot edit a field | Edit permissions are restricted | Review **Editable by** settings |
| Field does not appear in view | Field not included in template or visibility restricted | Add pattern to view and verify visibility |
| Inconsistent values in reports | Free-text field used where controlled choices were needed | Replace with **Select** or **Radio button** |
| Too many incomplete submissions | Required fields not configured well | Mark key fields as required and add help text |

---

## Next steps

After your fields are stable, continue with:

- [User Guide — Views](user_guide_views.md)
- [User Guide — Rules](user_guide_rules.md)
- [User Guide — Managing Entries](user_guide_managing_entries.md)
- [User Guide — Permissions](user_guide_permissions.md)
- [User Guide — Patterns, Styling, and Tools](user_guide_patterns_and_styling.md)
