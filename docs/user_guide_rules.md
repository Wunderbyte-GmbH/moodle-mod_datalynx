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
| **Update field** | Automatically writes a fixed value into a field when the rule is triggered | Set a status to “In review” when an entry is submitted |
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

## Trigger conditions (only act when the entry matches)

Both **Event notification** and **Update field** rules can be limited with **Trigger conditions**, so the rule only fires when the entry that triggered the event matches what you specify. Leave the conditions empty to always run.

Each condition is one row. Add several rows to combine them:

| Row control | What it does |
|---|---|
| **AND / OR** | Combines this row with the previous one. The first row has no connector. **AND** = all combined rows must match; **OR** = any may match. |
| **Field** | The field to test (for example *Status*, *Approval*, a select field, or a text field). Leave empty to skip the row. |
| **NOT** | Inverts the row, so it matches when the condition is *not* met. |
| **Operator** | The comparison appropriate to the field (for example `=`, `>`, `between`, or *any of*). |
| **Value** | The value(s) to compare against, using the input that fits the field. |

> **Pro-Tip**  
> A common pattern is to notify only when a meaningful change happens — for example *Approval = Approved*, or *Status changed to Final submission* — instead of on every save. This keeps notifications relevant and avoids noise.

---

## Build an Update field rule (step-by-step)

An **Update field** rule sets a field to a fixed value automatically when its events fire and its trigger conditions match. Only text, dropdown (select / radio button) and checkbox / multi-select fields can be updated.

1. Click **Add a rule**.
2. Select **Update field**.
3. Enter a clear name (for example, “Set status to In review on submit”).
4. Select the trigger event(s) (for example, *Entry created* or *Entry updated*).
5. Add **Trigger conditions** if the update should only happen in certain cases (see above).
6. In the **Field update** section:
   - **Field to update** — choose the target field.
   - **New value** — choose the option (for dropdown/radio/checkbox fields) or type the value (for text fields).
7. (Optional) Tick **Trigger follow-up events** if other rules should react to this change as well.
8. Click **Save rule** and test with a sample entry.

> **Important Note**  
> Use **Trigger follow-up events** deliberately. It lets one rule’s change set off other rules, which is powerful for chained workflows but can cause loops if two rules update each other. Datalynx guards against infinite loops, but a clear, one-directional design is still best.

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
