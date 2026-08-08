# Datalynx User Guide — Location Field

## What it is

The **Location** field stores a single place: an address, plus the coordinates behind it. It
gives entry authors a map picker with address search, and it lets you filter entries by distance
— "everything within 10 km of here".

Use it for venue and room addresses, placement and internship locations, event locations, or any
catalogue where "where is it?" is a real question.

For a *journey* — several stops in order, with route matching — use the
[Itinerary field](user_guide_itinerary_field.md) instead.

> **Before you start**
> A site administrator must have configured map services once — see
> [Map Services](user_guide_map_services.md). Which map, which address-lookup service and which
> API keys are **site settings**, not field settings.

---

## Add the field

1. Your Datalynx activity → **Manage** → **Fields**.
2. **Add a field** → **Location (Map / Geocoding)**.
3. Name it as users should see it, e.g. `Venue`.
4. Configure the settings below and save.

The settings page shows which map services the site is using, with a link to the administration
page if you have the rights to change them.

---

## Settings

| Setting | What it controls | Default |
|---|---|---|
| **Default zoom level** | Zoom used once a location is known | Site default |
| **Default search radius (km)** | Pre-filled radius in filter forms | `5` |
| **Display format** | How a saved location appears in views | Address + mini-map |
| **Country restrictions** | Overrides the site-wide country restriction for this field's address lookup — comma-separated ISO codes such as `de,at,ch` | empty (site default) |

### Display formats

| Format | Shows |
|---|---|
| **Address text only** | Just the address — lightest, good for dense tables |
| **Address + interactive mini-map** | Address with a small map beneath it |
| **Address + clickable route link** | Address linking to directions on openstreetmap.org |

> **Changed in this release.** The map provider, geocoding API URL and API key used to be
> configured per field (`param1`–`param3`). They are now **site settings**, so every Location and
> Itinerary field on the site uses the same services and one place holds the API keys. Existing
> fields were migrated automatically; their old per-field values were cleared. Zoom, radius,
> display format and country restrictions are unchanged and still per field.

---

## What users see

**Filling in the form.** An address box, a map, and two buttons:

- Type an address and pick a suggestion. Depending on the site's geocoding service, suggestions
  appear as you type, or after you press Enter or use **Search address** — the form adapts and
  tells you which.
- **Use Current Location** asks the browser for the device location.
- **Clear** empties the field.
- Clicking the map, or dragging the marker, sets the location and fills the address in
  automatically.

If the address cannot be resolved to a point, the coordinates are still stored when you click
the map — so a place with no postal address is fine.

**Reading an entry.** Whichever display format you chose above.

---

## Using the field in a view

```
[[Venue]]
```

An entry with no location renders "No location selected", so the tag is safe to place
unconditionally.

---

## Filtering by distance

Add the field to a filter and the search form offers an address box and a radius (1–100 km).
Pick a suggestion so the search has coordinates, then Datalynx returns every entry whose
location falls inside that circle.

Two things to know:

- **Pick a suggestion.** Typing an address without choosing a suggestion leaves the search
  without coordinates, and it falls back to a plain text match on the stored address.
- Distance is calculated as the crow flies, not by road.

---

## Notes for administrators

Values live in `datalynx_contents`: the address in `content`, latitude in `content1`, longitude
in `content2`. Distance filtering is a Haversine expression evaluated in SQL.

Those coordinate columns are unindexed text, so distance filtering scans the field's content
rows. At course scale this is irrelevant. If you plan a site-wide directory of many thousands of
locations, be aware that this is where it would start to cost.

---

## Next steps

- [User Guide — Map Services](user_guide_map_services.md) — the site-level setup.
- [User Guide — Itinerary Field](user_guide_itinerary_field.md) — journeys rather than points.
- [User Guide — Fields](user_guide_fields.md) — all field types.
