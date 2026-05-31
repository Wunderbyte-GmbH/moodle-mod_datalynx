# Datalynx User Guide — Rules

## Why rules matter

Rules let you automate repetitive actions and keep workflows consistent.

Typical rule outcomes include:

- Sending notifications when data changes
- Triggering follow-up actions for operational workflows
- Synchronizing or forwarding data in external processes

---

## Open the Rules area

1. Open your Datalynx activity.
2. Open the Datalynx management menu.
3. Click **Rules**.
4. Click **Add a rule**.
5. Choose a rule type.
6. Configure conditions and recipients.
7. Click **Save rule**.

---

## Rule types available

| Rule Type | Primary purpose | Typical use |
|---|---|---|
| **Event notification** | Sends notifications based on entry events | Alert reviewers when a new submission is added |
| **FTP Sync Data** | Synchronizes data/files to FTP destination workflows | Integrate with external downstream process |

> **Important Note**  
> Keep rule logic easy to understand. One clear rule per goal is better than one large mixed rule.

---

## Build a notification rule (step-by-step)

### Scenario: Notify reviewers when a new student portfolio entry is submitted

1. Click **Add a rule**.
2. Select **Event notification**.
3. Enter a clear name (for example, “Notify teachers on new entry”).
4. Select the trigger event (entry added).
5. Select recipients (teachers/managers or selected roles).
6. Write a clear subject and message body.
7. Add relevant tags (for example, `[[Text]]`, `##entryid##`, and view links).
8. Click **Save rule**.
9. Submit a test entry to verify the message.

---

## Rule message design best practices

| Message element | Why it matters | Recommendation |
|---|---|---|
| Subject | Helps recipients triage fast | Include activity + action (for example, “Portfolio: New submission”) |
| Entry link | Reduces click path for reviewers | Include a direct view link in the message |
| Key fields | Gives context immediately | Include title, author, and status fields |
| Call-to-action | Clarifies next step | Example: “Please review and approve today” |

> **Pro-Tip**  
> Use short, action-oriented notification text. Long emails reduce response speed.

---

## Event-driven automation examples

### Example A — Submission review workflow

1. Trigger on **entry added**.
2. Notify teachers.
3. Teacher opens review view and adds comments.
4. Teacher updates approval/status.

### Example B — Change tracking workflow

1. Trigger on **entry updated**.
2. Notify manager role.
3. Manager checks if update affects publication.
4. Manager confirms or reverts status.

### Example C — Comment activity workflow

1. Trigger on **comment added**.
2. Notify entry owner and assigned reviewer.
3. Continue review conversation in Datalynx comments.

---

## Recommended rule governance

| Governance item | Description | Recommended approach |
|---|---|---|
| Naming convention | Makes rule purpose clear | Start with trigger + audience (for example, “Entry Added → Teachers”) |
| Scope control | Avoid over-notification | Target only roles that need action |
| Testing process | Prevent noisy production alerts | Test with 2–3 sample entries before full activation |
| Review cadence | Keep rules relevant | Review active rules at least once per term |

---

## Common mistakes and prevention

| Mistake | Impact | Prevention |
|---|---|---|
| Too many recipients in one rule | Notification fatigue | Split by audience and action responsibility |
| Vague subject lines | Slow response from staff | Use clear action-focused subjects |
| No post-setup testing | Broken automation in live workflow | Always test with sample data |
| Duplicate rules for same trigger | Repeated messages | Keep a rule inventory and remove duplicates |

> **Warning**  
> Before enabling high-volume rules, confirm your institution’s communication policy and expected notification frequency.

---

## Next

- [User Guide — Managing Entries](user_guide_managing_entries.md)
- [User Guide — Permissions](user_guide_permissions.md)
- [User Guide — Patterns, Styling, and Tools](user_guide_patterns_and_styling.md)
