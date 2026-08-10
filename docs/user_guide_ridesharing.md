# Datalynx User Guide — Building a Ridesharing Activity (worked example)

## What you will build

A carpooling board for a course or a whole institution:

- Members post either **a ride they are offering** or **a ride they are looking for**, each with
  a route of two or more stops.
- Anyone can search "who can take me from A to B?" and get matches that include drivers passing
  *near* both places, not only those starting and ending exactly there.
- Each ride shows its **road distance and travel time**, and its route drawn along the roads,
  provided a routing service is configured for the site.
- When a new offer and a new request overlap, **both authors are notified automatically** — they
  do not have to keep checking the board.
- Passengers **claim a seat** on an offer, capacity is enforced, and the driver is notified.

Everything below is built from existing Datalynx parts. There is no separate ridesharing
plugin: you are assembling fields, a view, a filter and a handful of rules.

**Time needed:** about 30 minutes for the core, plus 10 for the polish at the end.

> **Prerequisite**
> A site administrator must have configured map services once — see
> [Map Services](user_guide_map_services.md). Nothing on this page works until they have.

> **Shortcut for administrators with shell access**
> A ready-made build of this design, including a five-step entry form, ships with the plugin:
> ```bash
> php mod/datalynx/cli/create_ridesharing_instance.php --course=<id>
> php mod/datalynx/cli/seed_ridesharing_entries.php --dataid=<id>   # example rides, optional
> ```
> It creates the activity, its fields and layouts, the behaviors, the filters, the search form,
> the match rules and nine views. Use it as a starting point to adapt, or read the rest of this
> page to build the same thing by hand. `--reset` rebuilds from scratch, deleting the previous
> activity of that name in the course, so do not point it at one holding real entries.

---

## Design overview

| Piece | Datalynx feature | Job |
|---|---|---|
| Offer or request? | **Select** field | Splits the board into two kinds of entry |
| The route | **Itinerary** field | Stops, times, and the corridor matching |
| Seats | **Number** field | How many passengers the driver can take |
| Passengers | **Team member select** field | Claiming a seat, with capacity enforced |
| Notes | **Text area** field | "Non-smoker", "space for a bike", … |
| Browsing | **Grid** view | One card per ride |
| Searching | **Custom filter** | "Find a ride from A to B", as a search form on the page |
| Long forms | A **chain of views** | Splitting posting a ride into steps (optional, step 10) |
| Timing | **Schedule** field | One date, or a weekday pattern - one field for both |
| Match alerts | Two **Event notification** rules | Notify both authors when routes and days overlap |
| Seat alerts | **Event notification** rule | Notifies the driver when someone joins or leaves |

One activity holds both offers and requests. That is what makes automatic matching possible:
the rule searches the *opposite* kind within the same activity.

---

## Step 1 — Create the activity

1. Turn editing on in your course → **Add an activity or resource** → **Datalynx**.
2. Name it, e.g. `Rideshare board`.
3. Recommended settings:
   - **Require approval?** — *Not required*. Approval delays matching, because unapproved
     entries are skipped until someone approves them.
   - **Maximum entries** — leave unlimited; people post several trips.
   - **Allow anonymous entries** — *No*, if the setting is offered. Ridesharing needs a person
     to contact. (It only appears when the site administrator has enabled anonymous entries.)
4. Save and display.

---

## Step 2 — Create the fields

Open **Manage → Fields** and add these six.

### 2.1 `Ride type` — Select

The field that splits the board.

- Type: **Select**
- Name: `Ride type`
- Options, **one per line and in this order**:

  ```
  Offer a ride
  Looking for a ride
  ```

The rules in step 5 select these options, so keep them stable — if you rename or reorder one
later, open the rules and check their trigger conditions still name the right side.

### 2.2 `Route` — Itinerary

- Type: **Itinerary (route with stops)**
- Name: `Route`
- **Maximum number of stops**: `8`
- **Stops shown initially**: `2`
- **Default match radius (km)**: `10` — generous for intercity trips; use `3`–`5` for a city

The route says **where** a journey goes and in what order. It says nothing about when: a stop
carries no date and no time. When a ride happens is the Schedule field's job (step 2.3), because a
date on a stop cannot describe a commute that runs every Monday — and a ride described that way
used to be invisible to the time check, which meant it matched every request on its route.

Leave **Location precision for others** at *Approximate until matched* — see
[the Itinerary field guide](user_guide_itinerary_field.md#location-precision--read-this-one-before-going-live).

### 2.3 `Termin` — Schedule

- Type: **Schedule**
- Name: `Termin` (or `When`)
- **Time steps (minutes)**: `30`
- **Allowed patterns**: *One-off and recurring*
- **Counts as the same journey within**: *anywhere that day*
- **Hide schedules that have run out**: **Yes**

One field, two shapes. A traveller either picks a date — "13 August, 07:00" — or a set of weekdays
and a time — "every Monday and Friday at 18:00, until 31 January". The field carries which of the
two it is, so nothing has to be told which other field to read, and a search or a match can compare
one against the other.

**That comparison is the point.** Asking for *Monday 10 August* finds the one-off ride that day
**and** every commute that runs on Mondays, because the 10th is a Monday. Splitting the same
information across a pattern radio button, a weekday multi-select and a time select cannot do that:
a one-off entry has no weekday value and a recurring one has no date, so the two never meet.

> **Leave the tolerance at "anywhere that day".** The rule exists to introduce two people who might
> travel together; the exact hour is theirs to settle. Narrow it only on a board that really does
> run several departures a day.

**Runs until** is worth explaining to drivers: it is how they park a commute for the summer without
deleting it. A single period, so "not in July and August" means ending it in June and re-opening it
in September; there is no exception list.

### 2.4 `Seats` — Number

- Type: **Number**
- Name: `Seats`

Only meaningful on offers; step 7 hides it on requests.

### 2.5 `Passengers` — Team member select

This is the seat-claiming mechanism, and it already does the work.

- Type: **Team member select**
- Name: `Passengers`
- **Maximum team size**: the most you will ever allow, e.g. `4` (this is the hard cap — see the
  caveat below)
- **Minimum team size**: `0`
- **Admissible roles**: tick **Student** (and any other role that may travel)
- **User can add him/herself**: **ticked** — this is what lets a passenger claim a seat
- **Allow manual unsubscription**: **ticked** — so they can withdraw again
- **Notify team members**: ticked, so existing passengers hear about changes

> **Caveat worth knowing up front.** Capacity comes from the field's **Maximum team size**,
> which is one number for the whole activity — it is *not* read from each driver's `Seats` value.
> So `Seats` documents the driver's intent to readers, while the field-level cap is what is
> actually enforced. Set **Maximum team size** to the largest number of passengers you want to
> permit anywhere, and treat `Seats` as information rather than a limit.

### 2.6 `Notes` — Text area

- Type: **Text area**
- Name: `Notes`

### 2.7 `Contact preference` — Select (optional)

- Options: `Moodle message`, `Phone (in notes)`, `Either`

Pickup points are settled between the two people, not stored by the system — this tells the
other side how to reach you.

---

## Step 3 — Build the browse view

**Manage → Views → Add a view → Grid.**

Name it `Browse rides`. In the entry template, put:

```
<div class="card h-100 shadow-sm">
  <div class="card-body">
    <h5>[[Ride type]]</h5>
    [[Route]]
    <p><strong>Seats:</strong> [[Seats]]</p>
    <p>[[Notes]]</p>
    <p><strong>Passengers:</strong> [[Passengers]]</p>
  </div>
  <div class="card-footer d-flex justify-content-end gap-2">
    ##edit## ##delete##
  </div>
</div>
```

Put `##addnewentry##` in the view's **section** template so people can post a ride, and set
this view as the activity default.

Passenger names render as links to their Moodle profiles, so a driver reaches **Message** in two
clicks. That is how pickup points get agreed — see step 9.

---

## Step 4 — Add the "find a ride" search form

Give passengers a search box on the page rather than a fixed saved search.

**Manage → Custom Filters → Add a custom filter.** Name it `Find a ride`, tick **Visible**, and
under **User defined fields** tick **Route** — plus `Ride type` and anything else worth
narrowing by. Then put its tag in the browse view's section template:

```
##customfilter:Find a ride##
```

The form offers **Travelling from**, **Travelling to** and **Within (km)**, with the same
address lookup as the entry form.

> **Two view settings decide whether this works at all.** A view that *forces* one filter hides
> its search form, and a **Permitted filters** whitelist blocks ad-hoc searches outright. In the
> view's settings turn on **Allow all filters** and leave **Permitted filters** empty. The
> view's own filter still decides what is listed until somebody searches — so set the view
> filter to `Offers only` and passengers see offers by default, then search within them.

Also add a saved filter `Offers only` (**Manage → View Filters**, condition `Ride type` =
`Offer a ride`) to use as that view filter, and a matching `Requests only` if you want a second
board of people looking for a lift.

> **A search replaces the view's filter, it does not add to it.** Once somebody presses
> **Search**, what they typed *is* the filter — so an `Offers only` view starts out showing
> offers, but the results of a route search include requests too. That is why `Ride type`
> belongs in the search form: it lets people narrow back down to offers themselves. Seeing who
> else is looking for the same trip is often useful anyway.

**How the route search behaves** — the two properties that surprise people:

- **It is directional.** A journey Vienna → Graz answers a search Vienna → Graz, and *not* Graz
  → Vienna. The drop-off must come after the pickup along the route.
- **It finds journeys you would otherwise miss.** Searching Wiener Neustadt → Graz matches a
  journey Vienna → Wiener Neustadt → Graz, because the search only needs *some* stop near each
  end.

Matching compares stops, not the drawn line — so tell drivers to list the intermediate stops
they are genuinely willing to serve.

---

## Step 5 — Add the match notification rules

This is the piece that makes the board feel alive. It takes **two rules**, one per side: the rule
that fires when an offer is saved looks for the requests that offer's route can carry, and the
rule that fires when a request is saved looks for the offers that can carry it. The corridor is
tested the opposite way round in each, which is why they cannot be one rule — unless you pick the
looser "either route can carry the other", which one rule can do on its own.

**Manage → Rules → Add a rule → Event notification.**

Both rules share everything except their trigger condition and their direction:

| Setting | Value |
|---|---|
| Enabled | Yes |
| Triggers | **Entry created**, **Entry updated** and **Entry deleted** |
| Trigger conditions | `Ride type` — *Any of* — the side this rule is for |
| Recipients | **Authors of matching entries** and **This entry's author, once per matching entry** |
| Matching entries, row 1 | `Ride type` — *has a different value from this entry* |
| Matching entries, row 2 | `Route` — *matches this entry's route* — radius `10` — direction as below |
| Matching entries, row 3 | `Termin` — *is within* — *anywhere that day* |
| Only approved entries | Yes |
| Announce each pair only once | Yes, **Shared with** `rideshare` in *both* rules |
| Forget pairs that stop matching | Yes |

| Rule | Trigger condition | Direction |
|---|---|---|
| `Offers looking for passengers` | `Ride type` is `Offer a ride` | *this entry's route can carry the matched one* |
| `Requests looking for a driver` | `Ride type` is `Looking for a ride` | *the matched route can carry this entry* |

> **Why "Shared with".** Each rule keeps its own record of which pairs it has already announced.
> Giving both the same name makes them share one record, so a pair is introduced once between them
> instead of once by each. Any name will do as long as it is the same in both.

> **A note on the field selectors.** Pick the field first, then press **Reload**: which controls a
> row needs — a tolerance, a radius, a direction — depends on how you are matching, so they appear
> once the relation is known.

**What a rule does when an entry is saved:**

1. Checks its trigger condition, so only the entries on its own side of `Ride type` go further.
2. Reads the values that entry holds, and searches for the other entries that relate to them —
   skipping the author's own entries and unapproved ones.
3. Narrows what is left by the corridor and by the schedule: the two have to run on the same day,
   which for a commute means the other side's date falls on one of its weekdays, inside its
   period. An entry with no schedule matches nothing rather than everything.
4. For each **new** pair, notifies **both** authors with a link to the other entry.

**Two behaviours to tell your users about:**

- **Notifications arrive with the next cron run, not instantly.** Matching runs in the
  background on purpose — a sweep across every counterpart entry inside the save would slow
  every submission down. On a normal Moodle (cron each minute) the delay is a minute or two.
- **A pair is announced once, and only once.** Editing an entry does not re-notify people who
  already know about each other. If a route changes so the two no longer match, the pair is
  forgotten — so if a later edit brings them back together, that counts as news again and is
  announced.

### Give the notification a body worth reading

Without a template the message is the generic datalynx text and a link. An **Email view** turns it
into the ride that was found:

1. **Manage → Views → Add a view → Email**, name it e.g. `Match notification`.
2. In the entry template, reference the fields with their field layouts, so each line carries its
   own label and disappears when empty: `[[Route||l_route]]`, `[[Departure||l_departure]]` and so
   on. A request then simply leaves out the vehicle and seat lines instead of listing them blank.
3. Add the links. `##notificationentrylink##` points at the entry the message is showing, and
   `##notificationotherentrylink##` at its counterpart — so the reader sees the ride that was
   found and can still get back to their own:

   ```
   <p>##notificationentrylink##</p>
   <p>Your own entry: ##notificationotherentrylink##</p>
   ```
4. Select it under **Email template** in both match rules, and give them a **Message subject** of
   their own — otherwise the message is titled after whichever event triggered it ("Datalynx entry
   updated"), which tells a traveller nothing.

Each side is sent the *other* ride: the driver gets the request, the passenger gets the offer.

### Check it works

1. As user A, post an offer `Vienna → Wiener Neustadt → Graz`, tomorrow 08:00.
2. As user B, post a request `Wiener Neustadt → Graz`, tomorrow 09:00.
3. Run cron (`php admin/cli/cron.php`, or wait).
4. Both A and B should have one notification each.

Nothing arrived? Work down this list:

| Check | Why |
|---|---|
| Both entries approved? | Unapproved entries are skipped unless you turn that off |
| Different authors? | Your own entries are never matched against each other |
| Both routes have **at least two stops**? | One stop is not a journey |
| Do both entries have a `Termin`? | An entry that does not say when it runs matches nothing |
| Do the two rides run on the same day? | A one-off matches a commute when its date falls on one of the commute's weekdays, and only inside the commute's period |
| Are the two `Ride type` values *different* entries? | Two offers never match each other |
| Does each rule's trigger condition name the right side? | A rule whose condition never holds simply never fires |
| Is the direction the right way round for that side? | An offer carries a request, not the other way round |
| Radius large enough? | Stops must be within it of both search ends |

---

## Step 6 — Add the seat notification rule

**Manage → Rules → Add a rule → Event notification.**

| Setting | Value |
|---|---|
| Name | `Passenger joined or left` |
| Triggers | **Team updated** |
| Recipients | **Author** (the driver), and the team members of `Passengers` |
| Message | "The passenger list for your ride has changed." plus a link to the entry |

Add a second event-notification rule on **Entry updated**, restricted with **only on change** to
the `Route` field, so passengers hear about it when a driver changes the route after they have
booked.

---

## Step 7 — Hide what does not apply

`Seats` and `Passengers` are meaningless on a request. Use **field behaviors** (Manage →
Fields → the behavior column) to add a condition:

- Behavior `offers only`, condition: `Ride type` **equals** `Offer a ride`.
- Apply it to `Seats` and `Passengers` by referencing them in the view template as
  `[[Seats|offers only]]` and `[[Passengers|offers only]]`.

Now a request form does not ask for seats, and a request card does not show an empty passenger
list.

> **One caveat worth knowing.** A behavior condition is evaluated against the *saved* entry, so on
> a brand new one - where nothing is saved yet - every conditional field is shown. The hiding takes
> effect from the first save onward. That is why the wizard in step 10 asks "offer or request?" on
> its own step: by the time the vehicle step is reached, the answer is on record.

### Separate ways in, instead of one form that hides half of itself

Hiding fields gets you a long way, but an offer and a request are different enough that a single
form serves neither well. Giving each its own way in is usually clearer:

- **Offer** — the multi-step wizard of step 10, which keeps the ride type question on its first
  step and preselects "Offer a ride" as the field's default.
- **Request** — a single page with the route, the pattern and, for a recurring ride, the weekdays
  and times. A request has so few fields that a wizard would only add clicks.

Then put both on your portal page, side by side:

```
##viewsesslink:Offer step 1 – Route;Offer a ride;new=1;btn btn-primary##
##viewsesslink:Create a request;Look for a ride;new=1;btn btn-outline-primary##
```

**Setting the ride type without asking twice.** A field carries one default, so the request page
cannot preselect the other option. Leave the ride type off the request form entirely and add an
**Update field** rule to fill it in:

| Setting | Value |
|---|---|
| Triggers | **Entry created** |
| Trigger conditions | `Ride type` — *is empty* |
| Target field | `Ride type` |
| Value | the "looking for a ride" option |
| Trigger follow-up events | **on** |

Follow-up events matter: the matching rules select on the ride type, so they have to see the entry
again once it has one. Without that, a request created this way is never matched.

The convention this rests on is "an entry with no ride type is a request", so if you ever add a
third way in, give it a ride type of its own on the form.

---

## Step 8 — Permissions

| Capability | Who | Why |
|---|---|---|
| `mod/datalynx:viewentry` | everyone taking part | See the board and use address lookup |
| `mod/datalynx:writeentry` | everyone taking part | Post a ride |
| `mod/datalynx:teamsubscribe` | passengers | **Required to claim a seat** |
| `mod/datalynx:manageentries` | staff | Moderate, and see exact coordinates |

`mod/datalynx:teamsubscribe` is the one people forget. Without it the seat control does
nothing, with no visible explanation.

See [Permissions](user_guide_permissions.md) for the full picture.

---

## Step 9 — Tell participants how it works

Put this in the activity description. It covers the three things users otherwise get wrong.

> **Posting a ride.** Choose whether you are offering or looking. Add your stops in travel
> order — start, any places you can pick up along the way, destination — with a time for each.
> Add the intermediate stops you are genuinely willing to serve: that is how people along your
> route find you.
>
> **Finding a ride.** Use the *Find a ride* search with where you are starting and where you
> are going. Post your trip as a request as well: you are then notified automatically when a
> matching ride appears, so you do not have to keep checking.
>
> **Agreeing where to meet.** The board does not store a pickup point. Once you have found each
> other, click the other person's name to open their profile and send a message, then agree the
> exact spot between you.

---

## Step 10 — Optional: split the form into steps

A ride offer with vehicle details, a schedule and cost sharing is a long form. Datalynx has no
wizard setting; the way to build one is a **chain of views**, and it takes no JavaScript.

Make one Grid view per step, each showing only some of the fields, and in each view's
**Redirect on submit** settings:

- **Target view** — the next step.
- **Redirect and continue editing** — on, so the same entry travels to that view in edit mode.

The last step points at your browse view with *continue editing* off, which ends the chain.
Give every step view a filter of "my entries only" so people only ever see their own.

Two things make the result feel like a real wizard:

- **Put the labels in the field layout, not the view template.** Add a field layout
  (Manage → Layouts) whose edit template is your label plus `#input`. A field hidden by a
  behavior condition then takes its label with it, instead of leaving an orphaned caption.
- **Order the steps so each choice precedes what depends on it.** Conditions are evaluated
  against the *saved* entry, so a field can react to a choice made on an earlier step, but not
  to one made two boxes above it on the same step. Ask "offer or request?" in step 1, and the
  vehicle fields in step 3 then know whether to appear at all.

For navigation back, put a link tag in each step's template:

```
##viewsesslink:Step 1 – Route;1. Route;editentries=##entryid##|eids=##entryid##;badge bg-success##
```

On the final step, reference every field with a behavior that nobody may edit — that turns the
step into a read-only summary, because the layout shows the value instead of an input. Two
details keep that summary tidy:

- In the field layout, set **When not editable** to *Use display template if content is present,
  otherwise display nothing*. A field nobody filled in then leaves no empty row behind.
- Give those read-only behaviors the **same conditions** as their editable twins. Otherwise a
  request's summary lists the vehicle fields it was never asked for.

---

## What this build does not do

Stated plainly so you can plan around it:

- **No detour ranking.** Matches are not ordered by how far a driver would deviate, even though
  road routes are now known.
- **No waitlist or driver approval.** Seats are first-come; a full ride simply cannot be joined.
  Cancellations free a seat but nobody is promoted automatically.
- **Capacity is per field, not per ride** — see the caveat in step 2.4.
- **No stored pickup point.** By design: it is agreed between the two people.
- **No combined map of all rides.** Each entry has its own map; a single map view of the whole
  board is planned.

---

## Quality checklist before launch

- [ ] Map services configured, and *not* still on the free community endpoints if this is production
- [ ] A **default map centre** set near your institution, so maps do not open on a world view
- [ ] Every entry carries a `Termin`; one without says nothing about when it runs, and matches nothing
- [ ] Each match rule's trigger condition names the right `Ride type` option
- [ ] Both match rules enabled, with **Entry created** *and* **Entry updated** as triggers
- [ ] Both match rules carry the same **Shared with** name
- [ ] Both match rules point at the Email view and carry their own **Message subject**
- [ ] If offers and requests have separate ways in: the request form omits `Ride type`, and the
      **Update field** rule that stamps it has **trigger follow-up events** on
- [ ] Cron running — no cron, no match notifications
- [ ] `mod/datalynx:teamsubscribe` granted to whoever may claim seats
- [ ] End-to-end test done with two real accounts (step 5)
- [ ] Search view set to **Allow all filters**, with **Permitted filters** empty (step 4)
- [ ] Location precision left approximate, unless you decided otherwise deliberately
- [ ] Activity description explains posting, finding, and agreeing a meeting point

---

## Next steps

- [User Guide — Itinerary Field](user_guide_itinerary_field.md) — every setting in detail.
- [User Guide — Rules](user_guide_rules.md) — more automation.
- [User Guide — Views](user_guide_views.md) — presentation and layout.
- [User Guide — Permissions](user_guide_permissions.md) — roles and capabilities.
