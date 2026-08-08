# Datalynx User Guide — Itinerary Field

## What it is

The **Itinerary** field stores a *journey*: an ordered list of two or more stops, each with a
place name, coordinates and an optional time. Where the [Location field](user_guide_location_field.md)
answers "where is this?", the Itinerary field answers "where does this go, via where, and when?"

Use it for carpooling and ridesharing, excursion and field-trip planning, delivery or
collection rounds, tour and route catalogues — anything where the *path* matters and not just
a single point.

Its distinguishing feature is **corridor search**: you can ask "which journeys could take me
from A to B?" and get answers that include journeys passing *near* both places, not only those
that start and end exactly there.

> **Before you start**
> A site administrator must have configured map services once —
> see [Map Services](user_guide_map_services.md). Until then, maps and address lookup
> will not work.

---

## Add the field

1. Open your Datalynx activity → **Manage** → **Fields**.
2. **Add a field** → **Itinerary (route with stops)**.
3. Name it something users will read in the form, e.g. `Route`.
4. Configure the settings below and save.

The field settings page shows which map services the site is using, with a link to the
administration page if you have the rights to change them.

---

## Settings

| Setting | What it controls | Sensible default |
|---|---|---|
| **Maximum number of stops** | Upper limit per journey. A journey needs at least 2 | `10` |
| **Stops shown initially** | Empty rows offered on a new entry | `2` |
| **Display format** | Map and list, map only, or list only | Map and list |
| **Default zoom level** | Zoom once stops are known | Site default |
| **Default match radius (km)** | Pre-filled radius in the search form; searchers can change it | `5` |
| **Location precision for others** | Exact, or approximate until matched | Approximate |
| **Country restrictions** | Overrides the site-wide country restriction for this field's address lookup | empty |
| **Require a time for every stop** | Forces a time on each stop | No, unless you match on time |

### Location precision — read this one before going live

With **Approximate until matched**, people browsing entries see stops rounded to roughly a
kilometre; exact positions appear only to the entry's author (and to site staff who can manage
entries). Matching always uses the exact coordinates on the server, so accuracy is never
sacrificed — only what is *displayed* changes.

Two points worth understanding:

- A stop describes the **journey, not the person**. It is wherever that trip passes through — a
  station, a campus, a car park. Nothing is stored on anyone's user profile, and nothing
  accumulates a picture of where a person lives across several entries.
- Even so, if participants habitually start journeys at home, a course-wide list of exact
  coordinates is more disclosure than most cohorts expect. Leave the default alone unless you
  have a reason.

### Times

Times are optional by default. Turn on **Require a time for every stop** when you intend to
match journeys against each other: without times, two routes that overlap geographically but
happen a week apart look like a match. See the
[ridesharing example](user_guide_ridesharing.md) for how the tolerance works.

---

## What users see

**Filling in the form.** A numbered list of stops, each with a place box and an optional time,
plus one shared map. Users can:

- type a place and pick a suggestion (or press Enter / use **Search address**, depending on the
  configured geocoding service);
- click the map to drop the next stop;
- drag a marker to move a stop — the place name is refilled automatically;
- reorder stops with the arrows, remove them with ✕, and add more with **Add a stop**.

A counter reads "2 of at most 10 stops". If a row has text typed but no place chosen, it warns
that the stop will not be saved — that row has no coordinates, so there is nothing to store or
match. Pick a suggestion or click the map to resolve it.

**Reading an entry.** The stops in travel order, with distinct markers for start, intermediate
stops and destination; the approximate straight-line length; a note when coordinates are shown
approximately; and a map with the stops joined by a line.

> The line is straight between stops, not a road route. Real road geometry and travel times
> need a routing engine, which is planned but not yet built. The distance is likewise
> straight-line, so treat it as a lower bound on the driving distance.

---

## Using the field in a view

Reference it in a view template like any other field:

```
[[Route]]
```

An entry with no journey renders "No journey entered", so you can safely put the tag in a
Grid card without checking first.

---

## Searching along a route

Add the field to a filter and the search form offers **Travelling from**, **Travelling to** and
**Within (km)**. Both places are needed: a corridor has two ends, and a half-filled form is
ignored rather than guessed at.

The match rule, stated precisely:

> A journey matches when one of its stops is within the radius of **Travelling from**, **and a
> later stop in the same journey** is within the radius of **Travelling to**.

Two consequences worth knowing, because they surprise people:

- **It is directional.** A journey Vienna → Graz matches a search Vienna → Graz, and does *not*
  match a search Graz → Vienna. That is deliberate: the drop-off has to come after the pickup
  along the route, or you would be offered rides going the wrong way.
- **It finds journeys you would otherwise miss.** Searching Wiener Neustadt → Graz matches a
  journey Vienna → Wiener Neustadt → Graz, because the search only needs *some* stop near each
  end. Plain start-and-destination matching would have missed it, and that case — a passenger
  living along the way — is most of the value of carpooling.

Matching compares stops, not the drawn line. A journey listing only Vienna and Graz will not
match a pickup halfway between them even though the line passes nearby: add the intermediate
stops you are genuinely willing to serve.

---

## Notes for administrators

The field stores the journey as one row in `datalynx_contents` (the stops as JSON, plus a
cached bounding box, length and earliest departure). Alongside it, a derived table
`datalynx_waypoints` holds one indexed numeric row per stop, used purely to make matching fast.

Nothing authoritative lives in that table — it is rebuilt from the entries — so if you ever
suspect it has drifted, rebuilding is safe and loses nothing.

---

## Next steps

- [User Guide — Ridesharing Example](user_guide_ridesharing.md) — a complete build using this field.
- [User Guide — Views](user_guide_views.md) — presenting entries.
- [User Guide — Managing Entries](user_guide_managing_entries.md) — filters and saved searches.
