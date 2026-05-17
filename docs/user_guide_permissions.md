# Datalynx User Guide — Permissions

## Why permissions are critical

Permissions control who can:

- View entries and views
- Add or edit entries
- Approve content
- Export data
- Manage templates and settings

A safe permission setup protects privacy and prevents accidental workflow changes.

---

## Common role model in Datalynx

| Role | Typical purpose in Datalynx |
|---|---|
| **Student** | Submit records and view permitted content |
| **Teacher** | Review, comment, approve, and monitor submissions |
| **Non-editing Teacher** | Review and moderate content without full activity editing rights |
| **Course Manager** | Configure advanced workflows and manage operational governance |

> **Important Note**  
> The final behavior depends on both Moodle role permissions and Datalynx field/view privilege settings.

---

## Permission setup workflow

1. Open the course and activity context permissions.
2. Confirm role capabilities for Students, Teachers, and Managers.
3. Open Datalynx and review field visibility/editability rules.
4. Review view visibility by role.
5. Test with real role-based test accounts.
6. Adjust before opening the workflow to learners.

---

## High-impact capability areas

| Capability area | Why it matters | Typical owner |
|---|---|---|
| Add instance / activity configuration | Controls creation and structural setup | Editing teacher, manager |
| Manage templates | Can modify presentation and behavior broadly | Editing teacher, manager |
| Manage entries | Can perform wide operational actions | Editing teacher, manager |
| Approve entries | Controls publication/moderation flow | Teacher, editing teacher, manager |
| Write entries | Allows user submissions | Student and higher roles (as configured) |
| Manage comments/ratings | Controls review interactions | Teacher and above |
| Export entries | Controls data extraction | Teacher/manager according to policy |

---

## View privilege links (role-aware view behavior)

Datalynx supports role-targeted view links and privilege mapping so different users can land in different views.

### Recommended approach

1. Create separate views per audience (student/teacher/manager).
2. Configure role-appropriate visibility.
3. Use role-aware links/navigation in templates where needed.
4. Validate that each role lands in the correct target view.

> **Pro-Tip**  
> Name views with role prefixes, such as “Student — Submission”, “Teacher — Review”, and “Manager — Control”.

---

## Approval workflow safety

### Suggested setup

1. Students can submit but not approve.
2. Teachers can review and approve.
3. Managers can override and audit where needed.
4. Sensitive fields (internal notes/status) are hidden from students.

### Approval checklist

1. Verify who can see unapproved records.
2. Verify who can change approval status.
3. Verify notification recipients for approval events.
4. Verify export permissions for personal data.

> **Warning**  
> Changing permissions mid-term can immediately alter visibility of existing entries.

---

## Field-level and view-level permission design

| Layer | Control type | Example |
|---|---|---|
| **Field** | Visible by / Editable by | Teachers can edit status; students cannot |
| **View** | View visibility by role | Teacher review view hidden from students |
| **Entry actions** | Edit/delete/approve availability | Approval action shown only to reviewers |

---

## Scenario: Portfolio moderation permissions

1. Student submits portfolio entry.
2. Student sees only own submission views.
3. Teacher sees review view with `##comments##` and approval actions.
4. Manager sees summary/control view for oversight and export.

---

## Permission troubleshooting

| Problem | Likely cause | What to do |
|---|---|---|
| Students can see internal comments | Field/view visibility too broad | Restrict field and view visibility |
| Teacher cannot approve entries | Missing approval permission | Review course/activity role capabilities |
| Export button missing for manager | Export capability not granted in context | Check role overrides at course/module level |
| User sees wrong default view | Role-targeted view mapping misconfigured | Re-check view visibility and target links |

---

## Next

- [User Guide — Patterns, Styling, and Tools](user_guide_patterns_and_styling.md)
