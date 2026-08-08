# Datalynx User Guide — Map Services (site administration)

## Why this page exists

Two Datalynx field types put maps into your entries: **Location** (a single place) and
**Itinerary** (a journey with several stops). Neither works until a site administrator has
decided *which* map services the site uses.

This is the one Datalynx page aimed at administrators rather than teachers. Teachers do not
configure map providers — the choice carries API keys, usage limits and data-protection
consequences, so it belongs to whoever runs the site. A teacher adding a Location or Itinerary
field simply sees which services are in use, with a link here.

---

## Open the settings

**Site administration → Plugins → Activity modules → Datalynx**, then scroll to
**Map services**.

---

## The three services, and why they are separate

"Map provider" sounds like one choice. It is really three, and they are rarely the same
company:

| Service | What it does | Who contacts it |
|---|---|---|
| **Basemap (tiles)** | The map picture itself | The **visitor's browser**, directly |
| **Geocoding** | Turns a typed place name into coordinates, and back | **Your Moodle server**, on the visitor's behalf |
| **Routing** | Real road routes and travel times | **Your Moodle server**, on the visitor's behalf |

The split matters for data protection. Because geocoding and routing run on your server, those
services never see your users' IP addresses — only the place names typed and the coordinates of
a journey's stops, sent from your site. The basemap is the exception: browsers must fetch map
images themselves, so the tile operator does see visitors' IP addresses. All three transfers
are declared in Moodle's privacy registry.

> **Why geocoding cannot run in the browser**
> It is not a stylistic choice. OpenStreetMap's Nominatim usage policy forbids implementing
> autocomplete against its API from the client side, these services require an identifying
> `User-Agent` header that browsers are not allowed to send, results must be cached, and an
> API key must never be shipped to a browser. Routing all of it through the server satisfies
> every one of those at once.

---

## Choosing a geocoding service

| Setting | Meaning |
|---|---|
| **Geocoding service** | Which service translates place names |
| **Geocoding service URL** | Leave empty to use that service's public endpoint; point it at your own installation to remove rate limits |
| **Geocoding API key** | Required by Google and by commercial providers. Never sent to the browser |
| **Restrict to countries** | Comma-separated ISO codes such as `de,at,ch`. Individual fields can override this |
| **Cache lifetime for address lookups** | How long a result may be reused. Default 30 days |
| **Maximum requests per second** | Applies to requests your server makes. Leave at 1 for public OpenStreetMap services |

The three services behave differently in ways your users will notice:

| Service | API key | Search as you type | Notes |
|---|---|---|---|
| **Photon** (default) | No | **Yes** | komoot's OpenStreetMap geocoder, hosted in Germany, open source and self-hostable |
| **Nominatim** | No | **No** — its usage policy forbids it | The search box gains a **Search address** button and waits for Enter instead |
| **Google Geocoding API** | Yes | No | Geocoding only. Google's terms do not allow its map tiles outside the Google Maps JavaScript API, so the map itself still renders with the basemap configured below |

Photon is the default because it is the only one of the three designed for search-as-you-type
and needs no key. Switching to Nominatim is legitimate — the interface adapts automatically,
no extra configuration needed.

Choosing **None** turns geocoding off: address boxes become plain text, and users set
locations by clicking the map instead.

---

## Choosing a routing service

Routing turns a journey's stops into a **road route**: the real driving distance, the travel
time, and the line that follows the roads instead of cutting across country. Only the
[Itinerary field](user_guide_itinerary_field.md) uses it.

| Setting | Meaning |
|---|---|
| **Routing service** | Which service works out road routes |
| **Routing service URL** | Leave empty to use that service's public endpoint; point it at your own installation to remove rate limits |
| **Routing API key** | Required by Google and by commercial providers. Never sent to the browser |

| Service | API key | Notes |
|---|---|---|
| **OSRM** (default) | No | The open-source routing engine for OpenStreetMap data. Self-hostable |
| **Google Directions API** | Yes | Directions only. As with geocoding, the map itself still renders with the basemap configured below |
| **None** | — | Journeys fall back to straight-line distance and no travel time |

Choosing **None** is a legitimate configuration, not a degraded one: the Itinerary field worked
that way before routing existed, and everything except the travel time keeps working. Route
matching does not depend on routing either — it compares stops, not road geometry.

Routes are cached like address lookups (same lifetime setting) and paced by the same rate
limit, because a journey's route only changes when its stops do.

> **Where the request happens matters.** A route is worked out **once, while someone is filling
> in a journey**, and stored with the entry. Browsing a board of fifty rides therefore makes no
> routing requests at all. If a journey was entered before routing was configured — or by CSV
> import — it simply shows the straight-line distance until someone edits and re-saves it.

---

## Choosing a basemap

| Setting | Meaning |
|---|---|
| **Basemap tile URL** | Tile URL template, e.g. `https://tile.openstreetmap.org/{z}/{x}/{y}.png`. A `{key}` placeholder is replaced with the key below |
| **Basemap attribution** | Shown in the corner of every map. Most operators, OpenStreetMap included, require it |
| **Tile API key** | Substituted into `{key}`. Unlike the geocoding key, this one *does* reach the browser |
| **Maximum zoom level** | The highest zoom your tile source offers |
| **Default map centre** | Where maps open before a location is chosen, as `latitude,longitude` — e.g. `48.2082,16.3738`. Empty means a world view |
| **Default zoom level** | Used together with the default centre |

Setting a **default map centre** near your institution is the single biggest usability
improvement available here: without it, every new map opens on a world view and users have to
zoom in from space.

---

## Before you go live

The defaults use free, community-run endpoints so that the fields work the moment you install
them. Those endpoints are for evaluation, not production — they carry no availability
guarantee, and their usage policies do not permit production traffic. While any of them is
configured, the settings page shows a warning.

This applies to all three services. The routing default, the public OSRM demo server, is the
clearest case: it is explicitly a demonstration instance with no availability guarantee.

For production, pick one of:

1. **Self-host.** Photon runs from two downloaded files; OSRM needs a prepared extract of your
   region; a self-hosted tile server is the most work of the three. Point the URL settings at
   your own instances and the rate limits disappear.
2. **Use a commercial provider.** Set the URL and API key. Check where they process data
   before sending your users' addresses there.

Also set **Contact address** (it defaults to your site support address). It goes into the
`User-Agent` your server sends, which the OpenStreetMap policies require so an operator can
reach you rather than simply blocking you.

---

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| Map area is blank | Check the browser console. The map library is bundled with the plugin, so a failure is usually the tile URL or a network block |
| "The map could not be loaded." | The plugin could not load its map library — check that `/mod/datalynx/leaflet/` is served and not blocked |
| No address suggestions while typing | Expected with Nominatim and Google: use the **Search address** button or press Enter. With Photon, check the service URL |
| "No matching address found" for a real place | Check **Restrict to countries** — a country restriction hides everything outside it |
| Suggestions are slow the first time, instant after | Working as intended: results are cached, and the rate limiter paces the first request |
| No travel time appears while entering a journey | Routing is set to **None**, or the service is unreachable. Google also needs a key |
| Some journeys show a travel time and others do not | The others were saved before routing was configured, or imported. Editing and saving them fills it in |
| Travel time looks wrong for the route shown | The stored route belongs to the stops as they were when saved. Changing the stops discards it and works out a new one |

---

## Next steps

- [User Guide — Location Field](user_guide_location_field.md) for a single place per entry.
- [User Guide — Itinerary Field](user_guide_itinerary_field.md) for journeys with several stops.
- [User Guide — Ridesharing Example](user_guide_ridesharing.md) for a complete worked build.
