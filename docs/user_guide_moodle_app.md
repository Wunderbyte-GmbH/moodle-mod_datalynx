# Datalynx User Guide — Using Datalynx in the Moodle App

## What this guide covers

Learners and teachers can open a Datalynx activity in the official **Moodle App**. This guide
explains what works there, how the app decides what to show, and how to build views that work
well on a phone.

The short version: **the app shows your Grid and Tabular views and follows the tags of those views.**
You do not configure anything separately for the app. A button is offered in the app exactly where
the same view offers it on the web.

> **Important Note**  
> Datalynx in the app needs a connection. Entries are not stored on the phone for offline use.

---

## What people can do in the app

| Action | Offered when | Notes |
|---|---|---|
| **Browse entries** | The view is a **Grid** or **Tabular** view | Other view types show an **Open in browser** button |
| **Switch view** | The user can see more than one view | All views the user may see are listed |
| **Search** | The view does not force its filter | The same quick search as on the web |
| **Switch filter** | The view does not force its filter and saved filters exist | Respects **Permitted filters** of the view |
| **Custom filter** | The view contains `##customfilter:NAME##` | Shown as chips above the entries; tap to open the form |
| **Add an entry** | The view contains `##addnewentry##` or `##addnewentries##`, and the user may add entries | The button appears where the tag is; the entry limit of the activity applies |
| **Edit an entry** | The view contains `##edit##`, and the user may edit the entry | See *Editing in the app* below |
| **Delete an entry** | The view contains `##delete##`, and the user may delete the entry | Asks for confirmation; one entry at a time |
| **Approve an entry** | Approval is on, the user may approve, and the view contains `##approve##` | Buttons **Approve** and **Withdraw approval** |
| **Follow a link to another view** | The view contains `##viewlink:…##` or `##viewsesslink:…##` | Opens in the app; see *Links between views* below |
| **Open files and images** | The view shows file or picture fields | Files open in the app; images are shown in the entry |

Rights are checked by the server, exactly as on the web. A student who cannot edit an entry on the
web cannot edit it in the app either.

---

## Which view the app opens

1. The app opens the activity's **default view**.
2. If the default view is not a Grid or Tabular view, or the user cannot see it, the app opens the
   first Grid or Tabular view the user can see.
3. The user can switch to any other view they may see. A view type the app cannot show offers
   **Open in browser**.

> **Pro-Tip**  
> If your default view is a Report or PDF view, create a simple Grid view for the app and make sure
> learners can see it.

---

## How a view looks in the app

The app shows the view's **view template** with its text, headings and cards. The entries are
listed where the template has `##entries##`. A view whose template has no `##entries##` lists no
entries, just like on the web. Such a view works as a start page of buttons (see
*Links between views*).

The web page's own controls are not shown in the template, because the app has its own controls
above the view. These are the view and filter menus, quick search, entries per page, the paging
bar, custom filter forms and the bulk actions. The app offers search, filters and custom filters
only on views that list entries.

## How entries look in the app

Each entry is shown with the view's own **entry template**, so it looks the way you designed it.
Some things are removed because they cannot work inside the app:

- scripts, `<style>` blocks, forms, input fields and buttons in the template;
- action links such as edit, delete, duplicate or select. The app shows its own buttons for these
  under each entry instead.

Layout is adapted to a narrow screen:

- Bootstrap columns (`row`, `col-*`) are stacked.
- Wide tables can be scrolled sideways.
- Images and videos are never wider than the screen.
- Links styled as buttons appear as plain links.

**Maps** are not drawn in the app. An **Itinerary** field lists its stops with the road distance
and travel time, and a link opens the route in the phone's maps app: Google Maps when the site
routes or looks up addresses with Google, OpenStreetMap otherwise (which routes from the first to
the last stop only). A **Location** field shows its address with a link for directions to it.
When the itinerary field rounds positions for others, the link carries the rounded positions too;
the stop names are shown as entered.

A **Tabular** view is not drawn as a table in the app. Its entries are listed one below the other,
with the name of each field above its value.

The app loads 20 entries at a time and loads more as the user scrolls, up to 200. If a search
finds more entries than that, the app offers **Open in browser**.

---

## Links between views

Activities built of several views, such as the Mitfahrbörse, link their views with
`##viewlink:…##` and `##viewsesslink:…##`. In the app each of these links is a button that opens
in the app. The rules are the same as on the web: the user must be able to see the target view,
and a link with `new=1` is only offered to users who may add entries.

| The link's URL query | Opens in the app |
|---|---|
| empty, `filter=…`, `eids=…` | The target view as a list. The filter is applied if the target view offers it. |
| `new=1` | The form of the target view for a new entry |
| `editentries=##entryid##` (one entry) | The form of the target view for that entry |
| anything else, e.g. `delete=…` | The web page, in the browser |

A link to a view type the app cannot show (for example CSV or PDF) opens in the browser.
`##addnewentry##` becomes the **Add a new entry** button at its place in the template.

How the button looks follows the link's CSS classes. `btn btn-primary` gives a filled button,
`btn-outline-…` an outlined one, and `w-100` makes it full width. Links without button classes
look like links.

Use the **back** arrow of the app to return to the previous view.

> **Pro-Tip**  
> A start page with one card per task and a button in each card (as in the Mitfahrbörse) works well
> on a phone. In the app each button appears directly below the text of its card.

---

## Editing in the app

When someone taps **Add a new entry** or **Edit**, the app opens a form with the fields of the view.
The settings of the view and its field behaviors apply, just as in the web form:

- hidden fields are not shown;
- read-only fields are shown as text;
- required fields are marked with `*` and must be filled in.

The form looks like the view's **entry template**: its headings, step badges, hints and info boxes
are shown, and each input stands where its field tag is. Where a field layout puts a question
before `#input`, that question is the label of the input (otherwise the field name is). Controls
of the web page, such as `##edit##` or `##reset##`, are left out; comments show their number.

These field types can be edited in the app:

| Field type | Control in the app |
|---|---|
| Text, Number, URL | Text input (URL with an optional link text); a number shown with a slider format is a slider |
| Text area | Multi-line input |
| Single choice (select), Radio buttons | Choice list |
| Multiple choice, Checkboxes | Choice list with several selections |
| Date / time | A row reading **Choose a date** that opens a calendar, with a time if the field stores a time; an optional date can be removed with the cross |
| Duration | Number of minutes |
| Itinerary (route) | One address per stop, with suggestions while typing; stops can be added, removed and moved. On saving, each address is looked up, and the place found names the stop |
| Schedule | A switch between **Once** and **Every week**; the date or the weekdays (buttons to tap on and off) and the dates it runs from and until, each picked in a calendar; the departure (and arrival) time from a list |
| Status | **Save draft** and **Submit** buttons |
| Identifier | Filled in automatically |
| Grade item | Shown as text |

Other field types, such as the text editor, files and pictures, team members and location, are
edited on the web. An itinerary is also edited on the web when the site has no address lookup
service configured.

- If such a field is **optional**, the app saves the other fields and lists the ones to complete
  on the web.
- If such a field is **required**, the app does not open the form. It offers **Open in browser**
  instead, so nobody can save an incomplete entry.

If the server rejects a value, the app shows the message at the field and keeps what was typed.
An address that cannot be found is such an error: type a town, a station or a street with its town.

The save button carries the text of the view's `##submit:text=…##` tag.

**Forms in several steps.** A view whose **Redirect on submit** names another view continues
there after saving, as on the web. With **continue editing**, the same entry opens in the next
view's form (the next step), otherwise the next view's list. This is how the Mitfahrbörse offer
wizard runs in the app, step by step, ending on "Meine Fahrten".

> **Important Note**  
> After saving, go back to the list and pull down to refresh it. The list does not update by itself.

---

## Custom filters in the app

Add the tag `##customfilter:NAME##` to a view, and that custom filter is offered in the app.

1. The user taps the filter's chip above the entries.
2. The app shows the filter's fields, sort field and sort direction.
3. **Apply** filters the list, **Clear** empties the form.
4. Tapping the chip again switches the custom filter off.

Most field types can be searched in the app: text, number, date, entry time, single and multiple
choice, tags, files (present or missing), approval, status and team members.

- **Itinerary** (route search): the user types where they start and where they want to go, and
  picks a radius. The site's address lookup finds the place for each and shows it under the
  input ("Found: …"); an address it cannot find is named in a note and left out of the search.
  Without an address lookup service configured for the site, the route can only be searched on
  the web.
- **Schedule**: a day, optionally a time of day, and how far off the time may be, as on the web.

Fields that cannot be searched in the app are named under the form.

As on the web, a search replaces the view's own filter: a ride board that lists only offers until
someone searches lists everything that matches the search. **Clear** shows the view's list again.

---

## Building views that work well on a phone

| Do | Why |
|---|---|
| Build at least one **Grid** view that learners can see | Other view types open in the browser |
| Put the action tags (`##edit##`, `##delete##`, `##approve##`, `##addnewentry##`) in the view | The app offers exactly the actions the view offers |
| Link views with `##viewlink##` and `##viewsesslink##` using `new=1`, `editentries=`, `eids=` or `filter=` | These links stay in the app |
| Keep the entry template simple: headings, short paragraphs, a few fields | Phones are about 375 px wide |
| Avoid fixed widths in inline styles (`style="width: 600px"`) | Fixed widths make the page scroll sideways |
| Do not rely on JavaScript or forms inside the entry template | They are removed in the app |
| Give pictures a meaningful alt text | Screen readers in the app read it out |
| Check the view with a test student account in the app | Rights and behaviors differ by role |

---

## Troubleshooting

| Problem | Likely cause | What to do |
|---|---|---|
| "This view cannot be shown in the app" | The view is not a Grid or Tabular view | Switch view, or add a Grid view for the app |
| No **Add**, **Edit** or **Delete** button | The view lacks the action tag, or the user lacks the right | Add the tag to the view; check permissions and **Editable by** |
| No **Approve** button | Approval is off, the view lacks `##approve##`, or the user may not approve | Check the activity's approval setting and the view template |
| The form only offers **Open in browser** | A required field cannot be edited in the app | Make the field optional for this view, or edit on the web |
| A new entry does not appear after saving | The list does not update by itself | Go back and pull down to refresh |
| Search and filters are missing | The view forces its filter, or its template has no `##entries##` | See *Permitted filters* in [Views](user_guide_views.md) |
| A link opens the browser | The link's URL query has a parameter the app does not handle, or the target view is not a Grid or Tabular view | See *Links between views* |
| New buttons or texts are missing after a Datalynx update | The app keeps the plugin's app definition for a while | An administrator purges all caches; users pull down to refresh, or log out and in again |

---

## Next

- [User Guide — Views](user_guide_views.md)
- [User Guide — Managing Entries](user_guide_managing_entries.md)
- [Developer notes — Moodle App](developer_moodle_app.md)
