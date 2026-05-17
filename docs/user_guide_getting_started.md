# Datalynx User Guide — Getting Started

## What is Datalynx?

**Datalynx** is a flexible Moodle activity for collecting, reviewing, and presenting structured information.

You can use it when a standard assignment, forum, or database activity is too limited for your workflow.

Typical examples include:

- A **Booking Management System** (registrations, approvals, schedules, comments)
- A **Student Portfolio** (artifacts, reflections, teacher feedback)
- A **Project Tracker** (status updates, files, team responsibilities)

> **Important Note**  
> Datalynx is designed for configurable workflows. Plan your fields and views before learners start entering data.

---

## When to use Datalynx instead of Moodle Database

| Need | Moodle Database | Datalynx |
|---|---|---|
| Basic list of entries with simple templates | Good fit | Good fit |
| Advanced role-based visibility and editing | Limited | Strong |
| Multiple specialized views for different audiences | Limited | Strong |
| Workflow-style review with comments and status | Limited | Strong |
| Flexible patterns/tags in templates | Basic | Advanced |

> **Pro-Tip**  
> If your process includes review cycles, internal comments, or role-specific views, start directly with Datalynx.

---

## Before you create your first Datalynx activity

Use this quick checklist:

1. Define your goal (for example, “collect internship reports”).
2. Decide who will add entries (students, teachers, or both).
3. Decide who can approve or edit entries.
4. List the information you need (text, files, date, team, comments).
5. Decide how people should view records (grid, tabular, PDF/email export).

---

## Create a new Datalynx activity

1. Open your Moodle course and turn editing on.
2. In the target section, click **Add an activity or resource**.
3. Select **Datalynx**.
4. Fill in **Name** and optional **Description**.
5. Configure relevant activity settings.
6. Click **Save and display**.

After saving, you can start configuring:

- **Fields** (what information is collected)
- **Views** (how entries are displayed)
- **Rules** (automated actions)
- **Tools** (bulk/support actions)

---

## Recommended starter settings

| Setting Name | Description | Recommended Value |
|---|---|---|
| **Name** | The activity title shown in the course | Clear process name (for example, “Student Portfolio 2026”) |
| **Description** | Short instructions for participants | Explain what to submit and by when |
| **Entries required before view** | Controls if users must submit before browsing | Enable for portfolio/evidence workflows |
| **Approval workflow** | Controls moderation before entries become visible | Enable when quality control is needed |
| **Team settings** | Allows team-oriented contribution scenarios | Enable only if group contribution is needed |

> **Warning**  
> Do not enable broad visibility before checking permissions. Otherwise learners may see draft or internal records.

---

## First-use scenario: Student Portfolio

Use this as a practical starter example.

### Goal
Students submit portfolio entries. Teachers review and comment. Course managers track progress.

### Setup steps

1. Create the Datalynx activity using **Save and display**.
2. Add fields: **Text**, **Text area with editor**, **File**, **Time**, and **Datalynx Comment Field**.
3. Create one view for students (submission-focused) and one for teachers (review-focused).
4. Add comment and status patterns to the teacher view.
5. Test with one sample student account before full rollout.

### Example template behavior (what users see)

In view templates, tags are replaced with live data:

- `[[Text]]` → the submitted text value (for example, “My internship reflection”)
- `##comments##` → the comments section in the entry view
- `##viewsmenu##` → the view switcher menu shown to users with access

Example teacher row layout:

- Title: `[[Text]]`
- Reviewer area: `##comments##`
- Navigation: `##viewsmenu##`

> **Pro-Tip**  
> Start with a simple layout first. Add advanced tags and styling only after your team confirms the workflow.

---

## Suggested launch process for teachers and course managers

1. Build and test with 2–3 sample entries.
2. Confirm role permissions with a non-editing teacher account.
3. Review what students can see before publishing.
4. Publish clear instructions in the activity **Description**.
5. Monitor first submissions and refine fields/views after week one.

---

## Common beginner mistakes (and how to avoid them)

| Mistake | Why it happens | How to avoid it |
|---|---|---|
| Creating too many fields at once | Trying to model every edge case on day one | Start with a minimal field set; add later |
| Mixing student and teacher layouts in one view | No role-specific planning | Create separate views for each audience |
| Unclear submission instructions | Activity description is too short | Add clear steps and examples in **Description** |
| Changing permissions after entries exist | Workflow was not tested end-to-end | Test with sample accounts before rollout |

---

## What to read next

- [User Guide — Fields](user_guide_fields.md)
- [User Guide — Views](user_guide_views.md)
- [User Guide — Rules](user_guide_rules.md)
- [User Guide — Managing Entries](user_guide_managing_entries.md)
- [User Guide — Permissions](user_guide_permissions.md)
- [User Guide — Patterns, Styling, and Tools](user_guide_patterns_and_styling.md)
