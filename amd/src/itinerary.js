// This file is part of mod_datalynx for Moodle - http://moodle.org/
//
// It is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// It is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Itinerary builder and route display for the datalynxfield_itinerary field type.
 *
 * Shares its Leaflet loading, sizing, tile handling and address lookup with
 * {@link module:mod_datalynx/location}; only the multi-stop behaviour lives here.
 * Every widget is described by data attributes, so the same `init()` works for
 * server rendered pages and for regions that mod_datalynx/viewbrowser fills in
 * over AJAX. Initialising twice is harmless.
 *
 * @module      mod_datalynx/itinerary
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';
import {
    attachAutocomplete,
    claimRegions,
    createMap,
    debounce,
    decodePolyline,
    geocodeReverse,
    getLeaflet,
    readDefaultView,
    readTiles,
    routeCalculate,
    whenVisible,
    WORLD_VIEW,
} from 'mod_datalynx/location';

/** @type {Object} Selectors for the regions this module owns. */
const SELECTORS = {
    map: '[data-region="datalynx-itinerary-map"]',
    picker: '[data-region="datalynx-itinerary-picker"]',
    search: '[data-region="datalynx-itinerary-search"]',
};

/** @type {Object} Leaflet options for the straight-segment route line. */
const LINE_STYLE = {color: '#0f6cbf', weight: 4, opacity: 0.8};

/** @type {Object} Leaflet options for a real routed line, drawn a little heavier. */
const ROUTE_STYLE = {color: '#0f6cbf', weight: 5, opacity: 0.85};

/**
 * Format a duration in seconds as a short "2 h 10 min" style label.
 *
 * @param {number} seconds
 * @returns {Promise<string>}
 */
const formatDuration = async (seconds) => {
    const minutes = Math.round(seconds / 60);
    if (minutes < 60) {
        return getString('durationminutes', 'datalynxfield_itinerary', minutes);
    }

    return getString('durationhours', 'datalynxfield_itinerary', {
        hours: Math.floor(minutes / 60),
        minutes: minutes % 60,
    });
};

/**
 * Convert a unix timestamp to the value a datetime-local input expects.
 *
 * The input is naive local time, so this uses the browser's timezone rather than
 * the user's Moodle timezone. The two usually agree; where they do not, the stop
 * time is what the traveller typed on their own device, which is the intent.
 *
 * @param {?number} timestamp
 * @returns {string}
 */
const toLocalInput = (timestamp) => {
    if (!timestamp) {
        return '';
    }
    const date = new Date(timestamp * 1000);
    const pad = (value) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
        + `T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

/**
 * Convert a datetime-local value back to a unix timestamp.
 *
 * @param {string} value
 * @returns {?number}
 */
const fromLocalInput = (value) => {
    if (!value) {
        return null;
    }
    const parsed = Date.parse(value);

    return Number.isNaN(parsed) ? null : Math.floor(parsed / 1000);
};

/**
 * Draw a read-only route: a marker per stop joined by straight segments.
 *
 * @param {HTMLElement} element
 * @returns {Promise<void>}
 */
const initDisplayMap = async (element) => {
    let stops;
    try {
        stops = JSON.parse(element.dataset.waypoints || '[]');
    } catch (error) {
        stops = [];
    }

    const points = stops
        .filter((stop) => Number.isFinite(stop.lat) && Number.isFinite(stop.lng))
        .map((stop) => [stop.lat, stop.lng]);

    if (points.length < 2) {
        return;
    }

    try {
        const L = await getLeaflet();
        await whenVisible(element);

        // Leaflet refuses a tile layer before a view is set, so open on the first
        // stop; fitBounds() below then widens it to the whole journey.
        const map = createMap(L, element, readTiles(element), {
            center: points[0],
            zoom: parseInt(element.dataset.zoom, 10) || WORLD_VIEW.zoom,
            zoomControl: false,
            attributionControl: false,
            scrollWheelZoom: false,
            dragging: false,
            keyboard: false,
            doubleClickZoom: false,
        });

        // A stored route means the engine's real geometry; without one the stops are
        // joined by straight segments, which is what the field showed before routing.
        const routed = element.dataset.polyline ? decodePolyline(element.dataset.polyline) : [];
        if (routed.length > 1) {
            L.polyline(routed, ROUTE_STYLE).addTo(map);
        } else {
            L.polyline(points, LINE_STYLE).addTo(map);
        }

        stops.forEach((stop, index) => {
            if (!Number.isFinite(stop.lat) || !Number.isFinite(stop.lng)) {
                return;
            }
            const marker = L.marker([stop.lat, stop.lng]).addTo(map);
            if (stop.address) {
                marker.bindPopup(`${index + 1}. ${stop.address}`);
            }
        });

        // The whole journey has to fit, so the configured zoom is only a ceiling.
        map.fitBounds(L.latLngBounds(points).pad(0.15), {
            maxZoom: parseInt(element.dataset.zoom, 10) || WORLD_VIEW.zoom,
        });
    } catch (error) {
        element.classList.add('datalynx-location-map--failed');
        element.textContent = await getString('maploadfailed', 'datalynxfield_location');
    }
};

/**
 * Wire up one itinerary builder.
 *
 * The hidden input is the single source of truth for the server; this rewrites it
 * on every change. Structural edits rebuild the row DOM, while typing updates
 * state in place so focus is never stolen.
 *
 * @param {HTMLElement} element
 * @returns {Promise<void>}
 */
const initPicker = async (element) => {
    const input = document.getElementById(element.dataset.inputid);
    const routeInput = element.dataset.routeinputid
        ? document.getElementById(element.dataset.routeinputid)
        : null;
    const list = element.querySelector('[data-region="stops"]');
    const rowTemplate = element.querySelector('[data-region="stoptemplate"]');
    const mapElement = element.querySelector('[data-region="map"]');
    const status = element.querySelector('[data-region="status"]');
    const routeLabel = element.querySelector('[data-region="route"]');

    if (!input || !list || !rowTemplate || !mapElement) {
        return;
    }

    const fieldid = parseInt(element.dataset.fieldid, 10);
    const maxstops = parseInt(element.dataset.maxwaypoints, 10) || 10;
    const initialstops = parseInt(element.dataset.initialwaypoints, 10) || 2;
    const requiretimes = element.dataset.requiretimes === '1';
    const typeahead = element.dataset.typeahead === '1';
    const minlength = parseInt(element.dataset.minlength, 10) || 3;

    /** @type {Array<{address: string, lat: ?number, lng: ?number, time: ?number}>} */
    let stops = [];
    try {
        stops = (JSON.parse(input.value || '[]') || []).map((stop) => ({
            address: stop.address || '',
            lat: Number.isFinite(stop.lat) ? stop.lat : null,
            lng: Number.isFinite(stop.lng) ? stop.lng : null,
            time: stop.time || null,
        }));
    } catch (error) {
        stops = [];
    }

    // A new itinerary starts with empty rows so there is something to type into.
    while (stops.length < Math.min(initialstops, maxstops)) {
        stops.push({address: '', lat: null, lng: null, time: null});
    }

    let map = null;
    let line = null;
    let markers = [];
    let routeline = null;
    let lastroutekey = '';

    /**
     * Persist the current state into the hidden input.
     *
     * Stops without coordinates are kept here and dropped server-side, so a
     * half-finished row is never silently promoted to a real stop.
     */
    const save = () => {
        input.value = JSON.stringify(stops.map((stop) => ({
            address: stop.address,
            lat: stop.lat,
            lng: stop.lng,
            time: stop.time,
        })));
        // Every change funnels through here, so this is the one place the route has
        // to be kept in step with the stops.
        // eslint-disable-next-line no-use-before-define
        scheduleRoute();
    };

    /**
     * Ask the server to route the current stops, and show what it says.
     *
     * The summary travels back to the server in its own hidden input, so the entry
     * stores the distance, the travel time and the geometry alongside the stops and
     * neither the display nor the browse view has to route anything again.
     *
     * @returns {Promise<void>}
     */
    const refreshRoute = async () => {
        if (!routeInput) {
            return;
        }

        const located = stops
            .filter((stop) => stop.lat !== null && stop.lng !== null)
            .map((stop) => ({lat: stop.lat, lng: stop.lng}));

        const key = JSON.stringify(located);
        if (key === lastroutekey) {
            return;
        }
        lastroutekey = key;

        // A journey that is not one yet has no route, and neither has the input.
        if (located.length < 2) {
            routeInput.value = '';
            if (routeLabel) {
                routeLabel.textContent = '';
            }
            if (routeline) {
                routeline.remove();
                routeline = null;
            }
            return;
        }

        const route = await routeCalculate(fieldid, located);
        if (key !== lastroutekey) {
            // The stops moved on while this request was in flight.
            return;
        }

        if (!route) {
            routeInput.value = '';
            if (routeLabel) {
                routeLabel.textContent = '';
            }
            return;
        }

        routeInput.value = JSON.stringify({
            distance: route.distance,
            duration: route.duration,
            polyline: route.polyline,
        });

        if (routeLabel) {
            routeLabel.textContent = await getString('routesummary', 'datalynxfield_itinerary', {
                distance: (route.distance / 1000).toFixed(1),
                duration: await formatDuration(route.duration),
            });
        }

        if (map && route.polyline) {
            const points = decodePolyline(route.polyline);
            if (routeline) {
                routeline.remove();
            }
            routeline = points.length > 1
                ? map.datalynxLeaflet.polyline(points, ROUTE_STYLE).addTo(map)
                : null;
        }
    };

    const scheduleRoute = debounce(refreshRoute, 600);

    /**
     * Update the status line, warning about rows that cannot be stored.
     *
     * @returns {Promise<void>}
     */
    const updateStatus = async () => {
        if (!status) {
            return;
        }
        const located = stops.filter((stop) => stop.lat !== null && stop.lng !== null);
        const pending = stops.length - located.length;

        if (pending > 0) {
            status.textContent = await getString('stopswithoutplace', 'datalynxfield_itinerary', pending);
            status.classList.add('text-danger');
        } else {
            status.textContent = await getString(
                'stopcount',
                'datalynxfield_itinerary',
                {count: located.length, max: maxstops}
            );
            status.classList.remove('text-danger');
        }
    };

    /**
     * Redraw the route line and markers from the current state.
     */
    const redrawMap = () => {
        if (!map) {
            return;
        }

        markers.forEach((marker) => marker.remove());
        markers = [];
        if (line) {
            line.remove();
            line = null;
        }
        // The routed line belongs to the previous set of stops; refreshRoute() draws
        // the new one as soon as the server answers.
        if (routeline) {
            routeline.remove();
            routeline = null;
        }

        const L = map.datalynxLeaflet;
        const points = [];
        stops.forEach((stop, index) => {
            if (stop.lat === null || stop.lng === null) {
                return;
            }
            points.push([stop.lat, stop.lng]);

            const marker = L.marker([stop.lat, stop.lng], {draggable: true}).addTo(map);
            marker.bindTooltip(String(index + 1), {permanent: true, direction: 'top'});
            marker.on('dragend', async () => {
                const position = marker.getLatLng();
                stops[index].lat = position.lat;
                stops[index].lng = position.lng;
                const address = await geocodeReverse(fieldid, position.lat, position.lng);
                if (address) {
                    stops[index].address = address;
                }
                save();
                // eslint-disable-next-line no-use-before-define
                renderRows();
            });
            markers.push(marker);
        });

        if (points.length >= 2) {
            line = L.polyline(points, LINE_STYLE).addTo(map);
        }
        if (points.length) {
            map.fitBounds(L.latLngBounds(points).pad(0.2), {
                maxZoom: parseInt(element.dataset.zoom, 10) || 13,
            });
        }
    };

    /**
     * Move a stop within the journey.
     *
     * @param {number} from
     * @param {number} to
     */
    const move = (from, to) => {
        if (to < 0 || to >= stops.length) {
            return;
        }
        const [moved] = stops.splice(from, 1);
        stops.splice(to, 0, moved);
    };

    /**
     * Rebuild the visible rows from state.
     */
    const renderRows = () => {
        list.replaceChildren();

        stops.forEach((stop, index) => {
            const row = rowTemplate.content.firstElementChild.cloneNode(true);
            row.dataset.index = String(index);

            row.querySelector('[data-region="seq"]').textContent = String(index + 1);

            const address = row.querySelector('[data-region="address"]');
            address.value = stop.address;
            address.addEventListener('input', () => {
                // Typing invalidates the coordinates until a place is chosen again.
                stops[index].address = address.value;
                stops[index].lat = null;
                stops[index].lng = null;
                save();
                updateStatus();
            });
            attachAutocomplete(address, {
                fieldid,
                typeahead,
                minlength,
                trigger: null,
                onSelect: (place) => {
                    stops[index] = {...stops[index], address: place.address, lat: place.lat, lng: place.lng};
                    address.value = place.address;
                    save();
                    updateStatus();
                    redrawMap();
                },
            });

            const time = row.querySelector('[data-region="time"]');
            time.value = toLocalInput(stop.time);
            time.addEventListener('change', () => {
                stops[index].time = fromLocalInput(time.value);
                save();
            });

            row.querySelector('[data-action="up"]').addEventListener('click', () => {
                move(index, index - 1);
                save();
                renderRows();
                redrawMap();
            });
            row.querySelector('[data-action="down"]').addEventListener('click', () => {
                move(index, index + 1);
                save();
                renderRows();
                redrawMap();
            });
            row.querySelector('[data-action="remove"]').addEventListener('click', () => {
                stops.splice(index, 1);
                save();
                renderRows();
                redrawMap();
            });

            list.appendChild(row);
        });

        updateStatus();
    };

    const addButton = element.querySelector('[data-action="add"]');
    if (addButton) {
        addButton.addEventListener('click', async () => {
            if (stops.length >= maxstops) {
                if (status) {
                    status.textContent = await getString(
                        'waypointlimitreached',
                        'datalynxfield_itinerary',
                        maxstops
                    );
                    status.classList.add('text-danger');
                }
                return;
            }
            stops.push({address: '', lat: null, lng: null, time: null});
            save();
            renderRows();
            list.querySelector('[data-region="stop"]:last-child [data-region="address"]')?.focus();
        });
    }

    if (requiretimes) {
        element.classList.add('datalynx-itinerary-picker--times');
    }

    save();
    renderRows();

    try {
        const L = await getLeaflet();
        await whenVisible(mapElement);

        const fallback = readDefaultView(element);
        map = createMap(L, mapElement, readTiles(element), {
            center: [fallback.lat, fallback.lng],
            zoom: fallback.zoom,
        });
        // Stash the namespace on the map so redrawMap() does not have to await it.
        map.datalynxLeaflet = L;

        // Clicking the map appends a stop, which is the quickest way to sketch a
        // route without typing every place name.
        map.on('click', async (event) => {
            if (stops.length >= maxstops) {
                return;
            }
            const {lat, lng} = event.latlng;
            const address = await geocodeReverse(fieldid, lat, lng);
            stops.push({address: address || '', lat, lng, time: null});
            save();
            renderRows();
            redrawMap();
        });

        redrawMap();
    } catch (error) {
        mapElement.classList.add('datalynx-location-map--failed');
        mapElement.textContent = await getString('maploadfailed', 'datalynxfield_location');
    }
};

/**
 * Wire up the two address lookups of one corridor filter.
 *
 * A corridor needs both ends, so a filter with only one place chosen is left for
 * the field to reject rather than being guessed at here.
 *
 * @param {HTMLElement} element
 */
const initSearchRegion = (element) => {
    const fieldid = parseInt(element.dataset.fieldid, 10);
    const prefix = element.dataset.prefix;
    const typeahead = element.dataset.typeahead === '1';
    const minlength = parseInt(element.dataset.minlength, 10) || 3;

    ['from', 'to'].forEach((end) => {
        const address = document.getElementById(`${prefix}_${end}address`);
        const lat = document.getElementById(`${prefix}_${end}lat`);
        const lng = document.getElementById(`${prefix}_${end}lng`);

        if (!address) {
            return;
        }

        attachAutocomplete(address, {
            fieldid,
            typeahead,
            minlength,
            trigger: null,
            onSelect: (place) => {
                address.value = place.address;
                if (lat) {
                    lat.value = place.lat.toFixed(6);
                }
                if (lng) {
                    lng.value = place.lng.toFixed(6);
                }
            },
        });

        // Editing by hand drops the coordinates, so a stale pair can never be
        // submitted with a different address.
        address.addEventListener('input', () => {
            if (lat) {
                lat.value = '';
            }
            if (lng) {
                lng.value = '';
            }
        });
    });
};

/**
 * Initialise every itinerary widget inside the given root.
 *
 * Safe to call repeatedly and on overlapping roots.
 *
 * @param {Element|Document} [root=document] Scope to search for widgets.
 */
export const init = (root = document) => {
    const scope = root && typeof root.querySelectorAll === 'function' ? root : document;

    claimRegions(scope, SELECTORS.map).forEach(initDisplayMap);
    claimRegions(scope, SELECTORS.picker).forEach(initPicker);
    claimRegions(scope, SELECTORS.search).forEach(initSearchRegion);
};
