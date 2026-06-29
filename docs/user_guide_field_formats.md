# Datalynx User Guide — Field Formats

## What are Field Formats?

**Field Formats** are named, reusable display configurations you can attach to individual field tags inside your view templates.

Instead of hardcoding rendering options directly into a template tag (such as `##author:firstname##`), you define a named format once — for example `"firstname"` on the **Entryauthor** field — and then reference it in any template using the `:formatname` suffix syntax:

```
##author:firstname##          ← entryauthor with format named "firstname"
[[ProjectTitle:short]]        ← text field with format named "short"
##ratings:avg##               ← rating field with format named "avg"
```

This allows you to:

- **Reuse** the same rendering rule across multiple views without repeating configuration.
- **Name** display variants clearly (for example `firstname`, `fullname`, `short`, `avg`).
- **Centrally manage** how fields are displayed, without editing every template individually.

> **Important Note**  
> Field Formats replace the old pattern of hardcoding format options directly in template tags. The old approach (for example, `##author:firstname##` relying on an internal field-type mapping) is now handled entirely through the Field Formats API and the **Field Formats** management tab.

---

## Open the Field Formats area

1. Open your Datalynx activity.
2. Go to the Datalynx management menu.
3. Click **Field Formats**.
4. The index page lists all formats defined for this Datalynx instance, grouped by field type.

---

## Adding a Field Format

1. On the **Field Formats** index page, find the **Add field format** dropdown.
2. Select the field type you want to configure a format for (for example, "Entryauthor", "Time", "Number").
3. Enter a short **alphanumeric name** for this format (for example, `firstname`, `short`, `avg`).
   - Names must be alphanumeric only (letters and digits, no spaces or special characters).
   - The name is case-sensitive and must be unique within the Datalynx instance.
4. Fill in the format-specific options (see field-by-field reference below).
5. Click **Save changes**.

> **Warning**  
> The format name is part of the template tag syntax. Renaming a format after it has been placed in templates will break those tags. Agree on names before deploying to live activities.

---

## Referencing a Field Format in Templates

Once a format is defined, reference it in any view template using the colon suffix:

| Tag style | Syntax | Example |
|---|---|---|
| Special field (system) | `##fieldname:formatname##` | `##author:firstname##` |
| Regular field | `[[FieldName:formatname]]` | `[[ProjectTitle:short]]` |

The colon and format name are appended directly to the field tag with no spaces.

> **Pro-Tip**  
> The **Field Formats** tab provides a usage hint directly on the index page:  
> *"After creating a format, reference it in the template by appending :formatname to the field tag, e.g. `##author:myformat##` or `[[fieldname:myformat]]`."*

---

## Field-by-field format options

Each field type defines its own set of format options. Below is a complete reference for all supported types.

### Entry Author (`entryauthor`)

Displays information about the user who created the entry.

| Option value | What it renders |
|---|---|
| `name` | Full name (first + last) |
| `firstname` | First name only |
| `lastname` | Last name only |
| `username` | Moodle username |
| `id` | User ID (numeric) |
| `idnumber` | User ID number (institutional) |
| `email` | Email address |
| `institution` | Institution profile field |
| `department` | Department profile field |
| `picture` | Profile picture (small) |
| `picturelarge` | Profile picture (large) |
| `badges` | User badges |
| `edit` | **Author selector** — in edit mode, lets a user with `mod/datalynx:manageentries` reassign the entry to a different author (a user dropdown); otherwise falls back to the linked author name |
| *(custom profile field shortname)* | Displays — and optionally **inline-edits** — any custom user profile field with an alphanumeric shortname (see *Inline editing of custom profile fields* below) |

**Example tags:** `##author:firstname##`, `##author:email##`, `##author:idnumber##`

> **Important — the `edit` option is not a default**  
> `##author:edit##` is **no longer a built-in tag**. To use it you must explicitly create an
> Entryauthor field format with the option set to `edit` (you can name the format `edit`). Without a
> matching format the tag renders nothing.

#### Inline editing of custom profile fields

When the **option** is a *custom user profile field shortname* (not one of the built-in options above),
two extra settings become available. These turn the entry author's profile field into an inline-editable
widget — this is the feature that previously lived in the separate **User Info** field type (see below).

| Setting | What it does |
|---|---|
| **Editable** (`editable`) | Renders an inline edit widget for the author's profile field. The submitted value is saved to the **user's profile** (via `profile_save_data`), *not* to Datalynx entry content. Editing is gated by Moodle's `moodle/user:editprofile` (or `moodle/user:editownprofile`) capability. |
| **Mandatory** (`mandatory`) | Requires a non-empty value when editing inline. Only available when **Editable** is ticked. |

The widget type follows the targeted profile field's type — text input, dropdown menu, checkbox, or date
selector. When **Editable** is off, the format displays the profile field value read-only.

---

### Entry Time (`entrytime`)

Controls the display format of an entry timestamp.

| Setting | What it configures |
|---|---|
| **Date format** | A PHP `date()` format string, for example `d.m.Y` or `Y-m-d H:i` |

The `entrytime` format applies to three built-in timestamp tags:

| Tag | Shows |
|---|---|
| `[[timecreated]]` | When the entry was created |
| `[[timemodified]]` | When the entry was last modified |
| `[[timesubmitted]]` | **Time of final submission** — set when the entry reaches *Final submission* status, and cleared if it is moved back from that status |

**Example:** Create a format named `short` with date format `d.m.Y`, then use `##timecreated:short##`, `##timemodified:short##`, or `##timesubmitted:short##`.

> **Pro-Tip**  
> `[[timesubmitted]]` is empty until an entry is finally submitted, which makes it a clean way to show — or filter by — only entries that have actually been handed in.

---

### Number (`number`)

Controls decimal precision for numeric field output.

| Setting | What it configures |
|---|---|
| **Decimal places** | Number of decimal digits to display (integer) |

**Example:** Create a format named `2dec` with 2 decimal places, then use `[[Budget:2dec]]`.

> **Legacy migration note:** The old syntax `[[FieldName:2]]` (a plain numeric suffix) is automatically migrated to a format with `decimals = 2`.

---

### Text (`text`)

Controls truncation for short text field output.

| Setting | What it configures |
|---|---|
| **Truncate length** | Maximum number of characters to display |

**Example:** Create a format named `excerpt` with truncate length `100`, then use `[[Description:excerpt]]`.

> **Legacy migration note:** The old syntax `[[FieldName:80]]` (a plain numeric suffix) is automatically migrated to a format with `maxlength = 80`.

---

### Text Area (`textarea`)

Same truncation control as the Text field.

| Setting | What it configures |
|---|---|
| **Truncate length** | Maximum number of characters to display |

---

### Select (`select`)

Controls the rendering mode for single-select option fields.

| Setting | What it configures |
|---|---|
| **Output mode** | How the selected option value is rendered |

---

### Multi-select (`multiselect`)

Controls the rendering mode for multiple-selection option fields.

| Setting | What it configures |
|---|---|
| **Output mode** | How the selected option values are rendered |

---

### Radio Button (`radiobutton`)

Controls rendering for radio button fields, set via the **Display format** option.

| Display format | What it renders |
|---|---|
| **Label only** | The human-readable option label |
| **Key-value pairs** | The label and stored key together, as `label=key` |
| **Key/index** | Only the stored key or index value |
| **Stepper progress** | The options as a horizontal progress *stepper* — a row of numbered (or icon) steps with the selected option highlighted, ideal for showing workflow stages such as approval steps |

When **Stepper progress** is selected, an extra setting appears:

| Setting | What it configures |
|---|---|
| **Stepper icons** | An optional comma-separated list of FontAwesome icon classes (for example `shopping-cart, cogs, medal, car, home`) shown inside each step’s circle. Leave empty to show step numbers instead. |

> **Pro-Tip**  
> The stepper turns a status-like radio field (for example *Draft → In review → Approved*) into a clear visual progress indicator in your views.

---

### Rating (`rating`)

Controls what aspect of a rating is shown.

| Option value | What it renders |
|---|---|
| `rate` | Rating widget (interactive) |
| `view` | View link |
| `viewurl` | View URL (raw link text) |
| `viewinline` | Inline ratings table |
| `count` | Number of ratings submitted |
| `avg` | Average value (text) |
| `avgbar` | Average as a progress bar |
| `avgstar` | Average as star icons |
| `max` | Maximum rating |
| `min` | Minimum rating |
| `sum` | Sum of all ratings |

**Example:** Create a format named `avg` with option `avg`, then use `##ratings:avg##`.

---

### Time (`time`)

Controls the date/time display format for time-type fields.

| Setting | What it configures |
|---|---|
| **Date format** | A PHP `date()` format string, for example `d.m.Y H:i` or `Y-m-d` |

---

### URL (`url`)

Controls the rendering of URL fields.

| Setting | What it configures |
|---|---|
| **Output mode** | How the URL is rendered (for example, as a hyperlink, as a raw URL string, or as a truncated excerpt) |

---

### User Info — replaced by Entry Author formats

The standalone **User Info (`userinfo`)** field type has been **removed**. Its job — displaying and
inline-editing a custom user profile field of the entry author — is now handled entirely by an
**Entry Author** field format:

1. Add an **Entryauthor** field format and set its **option** to the custom profile field's shortname.
2. Tick **Editable** (and optionally **Mandatory**) to allow inline editing of that profile field.
3. Reference it in templates as `##author:{formatname}##`.

See *Inline editing of custom profile fields* under [Entry Author](#entry-author-entryauthor) above.

> **Automatic migration**  
> Existing activities are converted automatically on upgrade: legacy `userinfo` fields become Entryauthor
> field formats, and their `##userinfo:xyz##` template tags are rewritten to `##author:xyz##` with the
> editable/mandatory settings preserved.

---

### Submit (`submit`)

Configures the appearance of the submit-button field (used with the `##submit##` tag).

| Setting | What it configures |
|---|---|
| **Button text** | Label shown on the button |
| **CSS classes** | Additional CSS classes applied to the button element |
| **Show arrow** | Whether to display an arrow icon next to the button label |

---

### Gradeitem (`gradeitem`)

Configures the rendering mode for grade item fields.

| Setting | What it configures |
|---|---|
| **Output mode** | How the grade is rendered (for example, raw value, letter grade, or percentage) |

---

### Checkbox (`checkbox`)

Configures the rendering of checkbox fields.

| Setting | What it configures |
|---|---|
| **Show as excerpt** | When enabled, shows an excerpt/summary instead of the full value |

---

### Other system fields

The following field types support the Field Formats API but do not expose additional configurable options to the UI (`has_options()` returns `false`). They participate in the format system for forward compatibility but do not appear in the **Add field format** dropdown:

- **Approve** (`approve`)
- **Cancel** (`cancel`)
- **Comment** (`comment`)
- **Datalynx View** (`datalynxview`)
- **Entry** (`entry`)
- **Entry Group** (`entrygroup`)
- **Field Group** (`fieldgroup`)
- **Identifier** (`identifier`)
- **Status** (`status`)
- **Tag** (`tag`)

---

## Deleting a Field Format

1. On the **Field Formats** index page, click **Delete** next to the format you want to remove.
2. Datalynx checks all view templates to make sure the format name is not currently referenced.
3. If the format name is found in one or more view templates (as `##fieldname:formatname##` or `[[FieldName:formatname]]`), the deletion is **blocked** with an error listing the affected views.
4. Remove the format reference from all listed views first, then retry deletion.

> **Warning**  
> You cannot delete a format while it is in use in a view template. This protects against broken rendering after deletion.

---

## Automatic migration from legacy tags

When upgrading from a version of Datalynx that used hardcoded format suffixes in templates (such as `##author:firstname##` with no format definition, or `[[Field:2]]` for numeric precision), Datalynx automatically scans all view templates and **creates the corresponding Field Format records** on your behalf.

This migration runs during the Moodle upgrade step and is transparent to administrators.

**What the migration does:**

1. Scans all view template fields for tags using the `:formatname` colon suffix syntax.
2. Identifies the field type for each detected field name.
3. Creates a Field Format record for each unique combination of field type + format name that does not yet exist.
4. Applies sensible default settings based on the detected name — for example, `firstname` becomes `option: firstname` for entryauthor; a numeric `2` becomes `decimals: 2` for number fields.

The following special field names are automatically resolved to their internal types:

| Template field name | Resolved field type |
|---|---|
| `author` | `entryauthor` |
| `group` | `entrygroup` |
| `timecreated` | `entrytime` |
| `timemodified` | `entrytime` |
| `ratings` | `rating` |
| `comments` | `comment` |

**Removed `userinfo` field migration:** In addition to the tag-suffix scan above, any legacy `userinfo`
field is converted into an Entryauthor field format that targets the same custom profile field (preserving
its editable/mandatory behavior), and its `##userinfo:...##` template tags are rewritten to `##author:...##`.

> **Pro-Tip**  
> After upgrading, visit **Field Formats** in each Datalynx activity to review the automatically created formats and adjust settings if needed.

---

## Backup and restore

Field Format definitions are included in Moodle activity backup and restore. When you restore a Datalynx activity to another course or site, all defined Field Formats are restored alongside fields, views, and entries.

---

## Scenario: Displaying entry author details in multiple formats

### Goal

Show the author's full name in one column and their institutional ID in another column of a tabular review view.

### Steps

1. Go to **Field Formats**.
2. Click **Add field format**, select **Entryauthor**.
3. Enter name `name`, set option to `name` (Full name). Click **Save changes**.
4. Click **Add field format**, select **Entryauthor** again.
5. Enter name `idnumber`, set option to `idnumber`. Click **Save changes**.
6. In the view template, insert: `##author:name##` and `##author:idnumber##` in the appropriate columns.

### Result

Each entry row shows the author's full name and institutional ID number in the correct columns, sourced from the user's Moodle profile.

---

## Scenario: Truncating long text fields in a list view

### Goal

Show only an 80-character excerpt of a long description field in a compact list view.

### Steps

1. Go to **Field Formats**.
2. Click **Add field format**, select **Text**.
3. Enter name `excerpt`, set truncate length to `80`. Click **Save changes**.
4. In the list view template, replace `[[Description]]` with `[[Description:excerpt]]`.

### Result

The list view displays a short excerpt of each entry's description instead of the full text, keeping rows compact and readable.

---

## Quality checklist

1. All format names are agreed upon before views go live.
2. Each format has been tested with a real entry to confirm correct rendering.
3. No format is deleted while it is still referenced in a template.
4. After upgrading, auto-migrated formats have been reviewed and adjusted.
5. Backup/restore has been verified in at least one staging environment.

---

## Troubleshooting

| Problem | Likely cause | What to do |
|---|---|---|
| Format tag shows as plain text | Format name in template does not match the defined format name | Check spelling and case in both the format definition and the template tag |
| Cannot delete a format | Format is still referenced in one or more view templates | Remove or update the tag in all affected views first |
| Field type not in "Add" dropdown | Field type does not expose custom options (`has_options()` = false) | These fields are not user-configurable through the UI |
| Auto-migration did not create expected formats | View templates use a non-standard field name or an unrecognized pattern | Create the format manually via the UI |
| Upgraded activity shows broken rendering | Auto-migration ran but default settings do not match the intended display | Visit Field Formats, find the affected format, and correct the option value |
| Name validation error | Format name contains spaces, hyphens, or special characters | Use only letters and digits (alphanumeric) in format names |

---

## Next steps

- [User Guide — Fields](user_guide_fields.md)
- [User Guide — Views](user_guide_views.md)
- [User Guide — Patterns, Styling, and Tools](user_guide_patterns_and_styling.md)
- [Documentation home](README.md)
