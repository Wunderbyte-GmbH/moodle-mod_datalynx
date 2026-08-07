# Datalynx Location Field Guide (`datalynxfield_location`)

The **`location`** field type enables interactive map integration, autocomplete geocoding, reverse-geocoding, and distance-based radius searching within Datalynx instances.

---

## 🚀 Features

- **Interactive Maps & Geocoding**: Powered by OpenStreetMap (Leaflet & Nominatim) with optional Google Maps API fallback.
- **Data Persistence**: Stores formatted address in `content`, Latitude in `content1`, and Longitude in `content2`.
- **Haversine Geo-Query**: Performs mathematical distance calculation directly in database SQL queries.
- **Display Modes**: Supports plain text, interactive mini-maps, or external route links.
- **Geolocation**: One-click "Use Current Location" button leveraging the browser Geolocation API.

---

## ⚙️ Configuration Parameters

| Parameter | Field Name | Options / Description |
| :--- | :--- | :--- |
| `param1` | Map & Geocoding Provider | `osm` (Leaflet/Nominatim) or `google` (Google Maps API) |
| `param2` | Geocoding API Base URL | Base URL for Nominatim server (Default: `https://nominatim.openstreetmap.org`) |
| `param3` | API Key | Optional API Key for Google Maps or custom tile servers |
| `param4` | Default Zoom Level | Zoom level integer (1 to 18) |
| `param5` | Default Search Radius | Search radius in km for filters (Default: 5 km) |
| `param6` | Display Format | `address_only`, `map_mini`, or `route_link` |
| `param7` | Country Restrictions | Comma-separated ISO country codes (e.g. `de,at,ch`) |

---

## 🔍 Search & Filtering Mechanics

When used in custom filters or standard search bars, the location field provides:
1. Address autocomplete text input.
2. Radius dropdown (1 km, 2 km, 5 km, 10 km, 20 km, 50 km).
3. Automatic Haversine SQL formula generation for filtering entries within the chosen radius.
