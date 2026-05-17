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

| View Type | Best used for | Typical audience |
|---|---|---|
| **Grid** | Card-style layout with visual grouping | Students, teachers |
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
3. In your layout section, insert entry tags such as `[[Text]]` and `##comments##`.
4. Add navigation components such as `##viewsmenu##` and `##pagingbar##`.
5. Click **Save view** and test with sample entries.

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
| `##filtersmenu##` | Shows saved/custom filters |
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
| **Filter area** | Search/filter controls in layout | Enable for teacher/manager workflows |
| **Paging controls** | Entries per page and navigation | Enable when entry volume grows |

> **Warning**  
> If a view is visible to learners and includes teacher-only actions or fields, sensitive workflow information may be exposed.

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

---

## Next

- [User Guide — Rules](user_guide_rules.md)
- [User Guide — Managing Entries](user_guide_managing_entries.md)
- [User Guide — Permissions](user_guide_permissions.md)
