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
| Edit after final submission (`mod/datalynx:editfinalsubmission`) | Allows changing an entry *after* it has reached **Final submission** status | Editing teacher, manager |
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

### Locking entries at final submission

When an entry reaches the **Final submission** status, it is locked so authors can no longer change it. This protects handed-in work from further edits.

- Users with **`mod/datalynx:editfinalsubmission`** (editing teachers and managers by default) can still edit finally-submitted entries — useful for corrections or unlocking.
- Users with **Manage entries** can also edit them.
- Individual fields can be allowed to remain editable after final submission by enabling the field behavior’s *editable after final* option.

> **Pro-Tip**  
> Grant `mod/datalynx:editfinalsubmission` sparingly. It is flagged as a spam-risk capability because it lets a user change records that learners consider “submitted and final”.

---

## Field-level and view-level permission design

Permission layers in Datalynx are strictly integrated with Moodle's capability system rather than hardcoding visibility to specific roles:

| Layer | Control type | Description & Capability Mapping |
|---|---|---|
| **Field** | Visible to / Editable by | Mapped to specific view/edit capabilities (e.g. `mod/datalynx:viewprivilegestudent` or `mod/datalynx:editprivilegestudent`). Checked individually. |
| **View** | View visibility | Controls who can view a view layout, checking Moodle capabilities (e.g. `mod/datalynx:viewprivilegeteacher` for review views). |
| **Entry actions** | Edit/delete/approve | Governed by capabilities (e.g. `mod/datalynx:approve` for moderation, `mod/datalynx:writeentry` for additions). |

### Dynamic feedback on permissions

When configuring visibility and editability settings for fields, behaviors, or views:
- **Allowed Roles badges**: Beside each capability checkbox, Datalynx displays dynamic feedback badges listing the actual roles (e.g., *Student*, *Teacher*) that currently have that capability allowed in the course context.
- **Warning badges**: If a capability checkbox is checked but no Moodle roles in the course are currently granted that permission, Datalynx displays a prominent warning badge: `Warning: No roles have this capability assigned in this course!`.

### Dynamic context checks

In addition to capability checkboxes, **Field behaviors** support special dynamic, context-aware permission checks that bypass standard capabilities:
- **Dynamic Check: Author**: Automatically grants visibility or editability to the user who created or owns the entry. (Useful for allowing students to edit their own submissions but not others).
- **Dynamic Check: Mentor**: Automatically grants visibility or editability to users who are registered mentors for the entry's group or team.

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
