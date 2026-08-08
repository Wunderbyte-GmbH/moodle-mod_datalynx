# Datalynx User Guide — Building a Ridesharing Activity (worked example)

## What you will build

A carpooling board for a course or a whole institution:

- Members post either **a ride they are offering** or **a ride they are looking for**, each with
  a route of two or more stops.
- Anyone can search "who can take me from A to B?" and get matches that include drivers passing
  *near* both places, not only those starting and ending exactly there.
- When a new offer and a new request overlap, **both authors are notified automatically** — they
  do not have to keep checking the board.
- Passengers **claim a seat** on an offer, capacity is enforced, and the driver is notified.

Everything below is built from existing Datalynx parts. There is no separate ridesharing
plugin: you are assembling fields, a view, a filter and two rules.

**Time needed:** about 30 minutes for the core, plus 10 for the polish at the end.

> **Prerequisite**
> A site administrator must have configured map services once — see
> [Map Services](user_guide_map_services.md). Nothing on this page works until they have.

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
| Searching | Saved **filter** | "Find a ride from A to B" |
| Match alerts | **Ride match** rule | Notifies both authors when routes overlap |
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

The rule in step 5 refers to these two options by name, so keep the labels stable — if you
rename one later, update the rule to match.

### 2.2 `Route` — Itinerary

- Type: **Itinerary (route with stops)**
- Name: `Route`
- **Maximum number of stops**: `8`
- **Stops shown initially**: `2`
- **Default match radius (km)**: `10` — generous for intercity trips; use `3`–`5` for a city
- **Require a time for every stop**: **Yes**

Turn times on. Matching uses them to rule out journeys that overlap geographically but happen
days apart, and without them the rule cannot tell Tuesday from Friday.

Leave **Location precision for others** at *Approximate until matched* — see
[the Itinerary field guide](user_guide_itinerary_field.md#location-precision--read-this-one-before-going-live).

### 2.3 `Seats` — Number

- Type: **Number**
- Name: `Seats`

Only meaningful on offers; step 7 hides it on requests.

### 2.4 `Passengers` — Team member select

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

### 2.5 `Notes` — Text area

- Type: **Text area**
- Name: `Notes`

### 2.6 `Contact preference` — Select (optional)

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

## Step 4 — Add the "find a ride" filter

**Manage → View Filters → Add a filter.** Name it `Find a ride`.

Add a custom search condition on **Route**. The form gives **Travelling from**, **Travelling to**
and **Within (km)**. Save it, and it becomes selectable in the **Current filter** menu above the
entries.

Optionally add a second filter `Offers only` with a condition `Ride type` = `Offer a ride`, so
passengers can browse just the offers.

**How the route search behaves** — the two properties that surprise people:

- **It is directional.** A journey Vienna → Graz answers a search Vienna → Graz, and *not* Graz
  → Vienna. The drop-off must come after the pickup along the route.
- **It finds journeys you would otherwise miss.** Searching Wiener Neustadt → Graz matches a
  journey Vienna → Wiener Neustadt → Graz, because the search only needs *some* stop near each
  end.

Matching compares stops, not the drawn line — so tell drivers to list the intermediate stops
they are genuinely willing to serve.

---

## Step 5 — Add the match notification rule

This is the piece that makes the board feel alive.

**Manage → Rules → Add a rule → Notify matching ride offers and requests.**

| Setting | Value |
|---|---|
| Name | `Tell both sides about a match` |
| Enabled | Yes |
| Triggers | **Entry created** and **Entry updated** |
| Itinerary field | `Route` |
| Ride type field | `Ride type` |
| Value meaning "offering a ride" | `Offer a ride` |
| Value meaning "looking for a ride" | `Looking for a ride` |
| Match radius (km) | `10` (or leave empty to use the field's own default) |
| Departure tolerance (hours) | `12` |

> **About those two values.** Enter the option labels exactly as you typed them in step 2.1.
> Select and radio button fields store an option's *position* rather than its label, so `1` and
> `2` work equally well — the rule accepts either and resolves labels for you. If you rename an
> option later, come back and update these, or use the positions instead.

**What the rule does when an entry is saved:**

1. Works out whether the entry offers or requests a ride.
2. Looks at entries of the **opposite** kind in the same activity — skipping the author's own
   entries, unapproved entries, journeys whose areas do not overlap, and departures outside the
   tolerance.
3. Runs the corridor match on what survives.
4. For each **new** pair, notifies **both** authors with a link to the other entry.

**Two behaviours to tell your users about:**

- **Notifications arrive with the next cron run, not instantly.** Matching runs in the
  background on purpose — a sweep across every counterpart entry inside the save would slow
  every submission down. On a normal Moodle (cron each minute) the delay is a minute or two.
- **A pair is announced once, and only once.** Editing an entry does not re-notify people who
  already know about each other. If a route changes so the two no longer match, the pair is
  forgotten — so if a later edit brings them back together, that counts as news again and is
  announced.

### Check it works

1. As user A, post an offer `Vienna → Wiener Neustadt → Graz`, tomorrow 08:00.
2. As user B, post a request `Wiener Neustadt → Graz`, tomorrow 09:00.
3. Run cron (`php admin/cli/cron.php`, or wait).
4. Both A and B should have one notification each.

Nothing arrived? Work down this list:

| Check | Why |
|---|---|
| Both entries approved? | Unapproved entries are skipped |
| Different authors? | Your own entries are never matched against each other |
| Both routes have **at least two stops**? | One stop is not a journey |
| Departure times within the tolerance? | 12 hours apart by default; a day apart will not match |
| Are the two `Ride type` values *different* entries? | Two offers never match each other |
| Do the rule's offer/request values match the option labels? | A typo here means the rule silently matches nothing |
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
> **Finding a ride.** Use the *Find a ride* filter with where you are starting and where you
> are going. You will also be notified automatically when a matching ride is posted, so you do
> not have to keep checking.
>
> **Agreeing where to meet.** The board does not store a pickup point. Once you have found each
> other, click the other person's name to open their profile and send a message, then agree the
> exact spot between you.

---

## What this build does not do

Stated plainly so you can plan around it:

- **No road routes or travel times.** Map lines are straight between stops and the distance is
  straight-line — treat it as a lower bound. Real routing is planned.
- **No detour ranking.** Matches are not ordered by how far a driver would deviate.
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
- [ ] Times required on the Itinerary field
- [ ] `Ride type` options match the rule's offer/request values exactly
- [ ] Match rule enabled, with **Entry created** *and* **Entry updated** as triggers
- [ ] Cron running — no cron, no match notifications
- [ ] `mod/datalynx:teamsubscribe` granted to whoever may claim seats
- [ ] End-to-end test done with two real accounts (step 5)
- [ ] Location precision left approximate, unless you decided otherwise deliberately
- [ ] Activity description explains posting, finding, and agreeing a meeting point

---

## Next steps

- [User Guide — Itinerary Field](user_guide_itinerary_field.md) — every setting in detail.
- [User Guide — Rules](user_guide_rules.md) — more automation.
- [User Guide — Views](user_guide_views.md) — presentation and layout.
- [User Guide — Permissions](user_guide_permissions.md) — roles and capabilities.
