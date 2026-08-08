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
 * Leaflet maps and Nominatim geocoding for the datalynxfield_location field type.
 *
 * Every widget is described entirely by data attributes on its container, so the
 * same `init()` entry point works for server rendered pages and for the regions
 * that {@link module:mod_datalynx/viewbrowser} fills in over AJAX. Initialising
 * twice is harmless: containers are flagged once they are wired up.
 *
 * @module      mod_datalynx/location
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Config from 'core/config';
import {getStrings} from 'core/str';

/**
 * Base URL of the Leaflet copy vendored in this plugin.
 *
 * Deliberately not a CDN: see mod/datalynx/leaflet/readme_moodle.txt.
 *
 * @type {string}
 */
const LEAFLET_BASE = `${Config.wwwroot}/mod/datalynx/leaflet`;

/** @type {string} RequireJS module id this plugin registers Leaflet under. */
const LEAFLET_MODULE = 'mod_datalynx_leaflet';

// Several helpers below are exported so that mod_datalynx/itinerary can build on
// the same Leaflet loading, sizing, tile and address-lookup behaviour instead of
// duplicating it. They are shared implementation, not a public API.

/** @type {Object} Centre used when the site configured no default. */
export const WORLD_VIEW = {lat: 20, lng: 0, zoom: 2};

/** @type {Object} Selectors for the regions this module owns. */
const SELECTORS = {
    map: '[data-region="datalynx-location-map"]',
    picker: '[data-region="datalynx-location-picker"]',
    search: '[data-region="datalynx-location-search"]',
    pickermap: '[data-region="map"]',
};

/** @type {?Promise<Object>} Shared promise resolving to the Leaflet namespace. */
let leafletPromise = null;

/** @type {?Promise<Object>} Shared promise resolving to the translated strings. */
let stringsPromise = null;

/**
 * Fetch and cache every string this module shows to the user.
 *
 * @returns {Promise<Object>}
 */
export const getLabels = () => {
    if (!stringsPromise) {
        const keys = [
            'geolocating',
            'geolocationdenied',
            'geolocationunsupported',
            'maploadfailed',
            'nosuggestions',
            'use_current_location',
        ];
        stringsPromise = getStrings(keys.map((key) => ({key, component: 'datalynxfield_location'})))
            .then((values) => Object.fromEntries(keys.map((key, index) => [key, values[index]])));
    }

    return stringsPromise;
};

/**
 * Add a stylesheet to the document head once and resolve when it settled.
 *
 * @param {string} href
 * @returns {Promise<void>}
 */
const loadStylesheet = (href) => new Promise((resolve) => {
    if (document.querySelector('link[data-datalynx-leaflet]')) {
        resolve();
        return;
    }

    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    link.dataset.datalynxLeaflet = '1';
    // A missing stylesheet degrades the map, it does not break it, so never reject.
    link.addEventListener('load', () => resolve());
    link.addEventListener('error', () => resolve());
    document.head.appendChild(link);
});

/**
 * Fetch Leaflet through RequireJS and resolve with its namespace.
 *
 * Leaflet ships as a UMD bundle. RequireJS is always present on a Moodle page,
 * so Leaflet's wrapper takes the AMD branch and calls define() anonymously —
 * which means injecting it with a plain script tag makes RequireJS throw
 * "Mismatched anonymous define()" and leaves window.L undefined. Letting
 * RequireJS load it is what pairs that anonymous define() with a module id, the
 * same way Moodle itself configures jQuery and jQuery UI.
 *
 * @returns {Promise<Object>}
 */
const loadLeafletModule = () => new Promise((resolve, reject) => {
    // RequireJS appends the .js extension itself, so the path must not carry one.
    window.requirejs.config({
        paths: {[LEAFLET_MODULE]: `${LEAFLET_BASE}/leaflet`},
    });
    window.requirejs([LEAFLET_MODULE], resolve, reject);
});

/**
 * Resolve the Leaflet namespace, loading the library on first use.
 *
 * @returns {Promise<Object>}
 */
export const getLeaflet = () => {
    if (!leafletPromise) {
        leafletPromise = (async () => {
            await loadStylesheet(`${LEAFLET_BASE}/leaflet.css`);

            // window.L is only set when something outside RequireJS already
            // loaded Leaflet; otherwise the AMD module value is the namespace.
            const L = window.L || await loadLeafletModule();
            if (!L || !L.map) {
                throw new Error('Leaflet loaded but did not expose its namespace');
            }

            // Leaflet guesses its image path from the script tag, which is not
            // where RequireJS puts it, so point at the marker icons explicitly.
            delete L.Icon.Default.prototype._getIconUrl;
            L.Icon.Default.mergeOptions({
                iconUrl: `${LEAFLET_BASE}/images/marker-icon.png`,
                iconRetinaUrl: `${LEAFLET_BASE}/images/marker-icon-2x.png`,
                shadowUrl: `${LEAFLET_BASE}/images/marker-shadow.png`,
            });

            return L;
        })();
    }

    return leafletPromise;
};

/**
 * Resolve once the element occupies space, so Leaflet can measure it.
 *
 * Datalynx renders maps into cards and collapsibles that are still being laid
 * out when this runs, and a Leaflet map created against a zero sized container
 * never paints its tiles.
 *
 * @param {HTMLElement} element
 * @param {number} timeout Give up waiting after this many milliseconds.
 * @returns {Promise<void>}
 */
export const whenVisible = (element, timeout = 5000) => new Promise((resolve) => {
    const hasSize = () => element.offsetWidth > 0 && element.offsetHeight > 0;

    if (hasSize()) {
        resolve();
        return;
    }

    let timer = null;
    const observer = new ResizeObserver(() => {
        if (hasSize()) {
            observer.disconnect();
            window.clearTimeout(timer);
            resolve();
        }
    });

    // Resolve anyway on timeout: a collapsed container is better served by a map
    // that repairs itself on the next resize than by no map at all.
    timer = window.setTimeout(() => {
        observer.disconnect();
        resolve();
    }, timeout);

    observer.observe(element);
});

/**
 * Keep a Leaflet map in sync with a container whose size changes after creation.
 *
 * @param {Object} map
 * @param {HTMLElement} element
 */
export const trackSize = (map, element) => {
    const observer = new ResizeObserver(() => map.invalidateSize({animate: false}));
    observer.observe(element);
    map.on('unload', () => observer.disconnect());
};

/**
 * Create a Leaflet map with the site's configured basemap on the given element.
 *
 * The tile source comes from the site settings via data attributes rather than
 * being hardcoded, so an admin can point at a self-hosted or commercial tile
 * server without touching this module.
 *
 * @param {Object} L
 * @param {HTMLElement} element
 * @param {Object} tiles Basemap settings: tileurl, attribution, maxzoom.
 * @param {Object} options Leaflet map options.
 * @returns {Object} The Leaflet map.
 */
export const createMap = (L, element, tiles, options) => {
    const map = L.map(element, options);

    L.tileLayer(tiles.tileurl, {
        maxZoom: tiles.maxzoom,
        attribution: tiles.attribution,
    }).addTo(map);

    trackSize(map, element);

    return map;
};

/**
 * Read the basemap settings a region carries.
 *
 * @param {HTMLElement} element
 * @returns {Object}
 */
export const readTiles = (element) => ({
    tileurl: element.dataset.tileurl,
    attribution: element.dataset.attribution || '',
    maxzoom: parseInt(element.dataset.maxzoom, 10) || 19,
});

/**
 * Read where a picker should open when the field holds no location yet.
 *
 * Comes from the site's default centre setting, so an institution can open its
 * maps on its own region instead of a world view.
 *
 * @param {HTMLElement} element
 * @returns {{lat: number, lng: number, zoom: number}}
 */
export const readDefaultView = (element) => {
    const lat = parseFloat(element.dataset.defaultlat);
    const lng = parseFloat(element.dataset.defaultlng);

    if (Number.isNaN(lat) || Number.isNaN(lng)) {
        return WORLD_VIEW;
    }

    return {lat, lng, zoom: parseInt(element.dataset.defaultzoom, 10) || WORLD_VIEW.zoom};
};

/**
 * Show a non-blocking failure notice in place of a map.
 *
 * @param {HTMLElement} element
 * @param {string} message
 */
const showMapError = async (element, message) => {
    element.classList.add('datalynx-location-map--failed');
    element.textContent = message || (await getLabels()).maploadfailed;
};

/**
 * Read the coordinate pair a container carries, if it carries a usable one.
 *
 * @param {HTMLElement} element
 * @returns {?{lat: number, lng: number}}
 */
const readCoords = (element) => {
    const lat = parseFloat(element.dataset.lat);
    const lng = parseFloat(element.dataset.lng);

    if (Number.isNaN(lat) || Number.isNaN(lng)) {
        return null;
    }

    return {lat, lng};
};

/**
 * Ask this site to geocode an address query.
 *
 * The geocoding provider is never contacted from here. It is reached through
 * Moodle so that the request carries the User-Agent the providers require, is
 * cached and rate limited site-wide, and so that an API key stays on the server.
 * Which provider answers is a site setting the browser never learns.
 *
 * @param {number} fieldid
 * @param {string} query
 * @returns {Promise<Array>} Places, empty when nothing matched or the service failed.
 */
export const geocodeSearch = async (fieldid, query) => {
    try {
        const response = await Ajax.call([{
            methodname: 'mod_datalynx_geocode_search',
            args: {fieldid, query},
        }])[0];

        return response.places || [];
    } catch (error) {
        return [];
    }
};

/**
 * Ask this site for the address at a coordinate pair.
 *
 * @param {number} fieldid
 * @param {number} lat
 * @param {number} lng
 * @returns {Promise<string>} The address, or an empty string when unavailable.
 */
export const geocodeReverse = async (fieldid, lat, lng) => {
    try {
        const response = await Ajax.call([{
            methodname: 'mod_datalynx_geocode_reverse',
            args: {fieldid, lat, lng},
        }])[0];

        return response.found ? response.address : '';
    } catch (error) {
        return '';
    }
};

/**
 * Ask this site to route a journey through the given stops.
 *
 * Same reasoning as the geocoder: the routing service is reached through Moodle so
 * the request identifies itself, is cached and rate limited site-wide, and any key
 * stays on the server.
 *
 * @param {number} fieldid
 * @param {Array<{lat: number, lng: number}>} stops Ordered stops, at least two.
 * @returns {Promise<?{distance: number, duration: number, polyline: string}>} Null when unavailable.
 */
export const routeCalculate = async (fieldid, stops) => {
    if (!Array.isArray(stops) || stops.length < 2) {
        return null;
    }

    try {
        const response = await Ajax.call([{
            methodname: 'mod_datalynx_route_calculate',
            args: {fieldid, stops},
        }])[0];

        return response.found ? response : null;
    } catch (error) {
        return null;
    }
};

/**
 * Decode an encoded polyline (precision 5) into coordinate pairs.
 *
 * Both supported routing engines return route geometry in this format, which is
 * an order of magnitude smaller than the equivalent coordinate array.
 *
 * @param {string} encoded
 * @returns {Array<Array<number>>} [lat, lng] pairs.
 */
/* eslint-disable no-bitwise */
export const decodePolyline = (encoded) => {
    const points = [];
    let index = 0;
    let lat = 0;
    let lng = 0;

    while (index < encoded.length) {
        let result = 1;
        let shift = 0;
        let byte;
        do {
            byte = encoded.charCodeAt(index++) - 63 - 1;
            result += byte << shift;
            shift += 5;
        } while (byte >= 0x1f && index < encoded.length);
        lat += (result & 1) ? ~(result >> 1) : (result >> 1);

        result = 1;
        shift = 0;
        do {
            byte = encoded.charCodeAt(index++) - 63 - 1;
            result += byte << shift;
            shift += 5;
        } while (byte >= 0x1f && index < encoded.length);
        lng += (result & 1) ? ~(result >> 1) : (result >> 1);

        points.push([lat * 1e-5, lng * 1e-5]);
    }

    return points;
};
/* eslint-enable no-bitwise */

/**
 * Debounce a function so that bursts of calls issue a single request.
 *
 * @param {Function} callback
 * @param {number} wait
 * @returns {Function}
 */
export const debounce = (callback, wait) => {
    let timer = null;

    return (...args) => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => callback(...args), wait);
    };
};

/**
 * Turn a text input into an address picker backed by the site's geocoder.
 *
 * Whether suggestions appear as the user types depends on the configured
 * provider: Nominatim's usage policy forbids querying it per keystroke, so with
 * that engine the lookup waits for the user to press Enter or click the search
 * button. `typeahead` carries that decision down from the server.
 *
 * @param {HTMLInputElement} input
 * @param {Object} config
 * @param {number} config.fieldid Location field the lookups are attributed to.
 * @param {boolean} config.typeahead Whether to search while typing.
 * @param {number} config.minlength Shortest query worth sending.
 * @param {?HTMLElement} config.trigger Button that submits the query manually.
 * @param {Function} config.onSelect Called with the chosen place.
 */
export const attachAutocomplete = (input, {fieldid, typeahead, minlength, trigger, onSelect}) => {
    const anchor = input.closest('.felement') || input.parentElement;
    if (!anchor) {
        return;
    }
    anchor.classList.add('datalynx-location-autocomplete');

    const list = document.createElement('div');
    list.className = 'datalynx-location-suggestions list-group d-none';
    list.setAttribute('role', 'listbox');
    anchor.appendChild(list);

    const close = () => {
        list.replaceChildren();
        list.classList.add('d-none');
    };

    const renderMessage = (message) => {
        list.replaceChildren();

        const empty = document.createElement('div');
        empty.className = 'list-group-item small text-muted';
        empty.textContent = message;
        list.appendChild(empty);
        list.classList.remove('d-none');
    };

    const render = (places) => {
        list.replaceChildren();

        places.forEach((place) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'list-group-item list-group-item-action small text-wrap';
            option.setAttribute('role', 'option');
            option.textContent = place.address;
            option.addEventListener('click', () => {
                close();
                onSelect(place);
            });
            list.appendChild(option);
        });

        list.classList.remove('d-none');
    };

    const lookup = async () => {
        const query = input.value.trim();
        if (query.length < minlength) {
            close();
            return;
        }

        const places = await geocodeSearch(fieldid, query);
        if (places.length) {
            render(places);
        } else {
            renderMessage((await getLabels()).nosuggestions);
        }
    };

    input.setAttribute('autocomplete', 'off');

    if (typeahead) {
        input.addEventListener('input', debounce(lookup, 350));
    }

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
            return;
        }
        if (event.key === 'Enter') {
            // The address field sits inside the entry form; searching must not
            // submit it.
            event.preventDefault();
            lookup();
        }
    });

    if (trigger) {
        trigger.addEventListener('click', lookup);
    }

    document.addEventListener('click', (event) => {
        if (event.target !== input && event.target !== trigger && !list.contains(event.target)) {
            close();
        }
    });
};

/**
 * Render one read-only mini map for a stored location.
 *
 * @param {HTMLElement} element
 * @returns {Promise<void>}
 */
const initDisplayMap = async (element) => {
    const coords = readCoords(element);
    if (!coords) {
        return;
    }

    try {
        const L = await getLeaflet();
        await whenVisible(element);

        const map = createMap(L, element, readTiles(element), {
            center: [coords.lat, coords.lng],
            zoom: parseInt(element.dataset.zoom, 10) || WORLD_VIEW.zoom,
            zoomControl: false,
            attributionControl: false,
            scrollWheelZoom: false,
            dragging: false,
            keyboard: false,
            doubleClickZoom: false,
        });

        const marker = L.marker([coords.lat, coords.lng]).addTo(map);
        if (element.dataset.address) {
            marker.bindPopup(element.dataset.address);
        }
    } catch (error) {
        await showMapError(element);
    }
};

/**
 * Wire up one interactive picker: map, marker, geolocation and autocomplete.
 *
 * @param {HTMLElement} element
 * @returns {Promise<void>}
 */
const initPickerRegion = async (element) => {
    const {addressid, latid, lngid} = element.dataset;
    const addressInput = addressid ? document.getElementById(addressid) : null;
    const latInput = latid ? document.getElementById(latid) : null;
    const lngInput = lngid ? document.getElementById(lngid) : null;
    const mapElement = element.querySelector(SELECTORS.pickermap);

    if (!addressInput || !mapElement) {
        return;
    }

    const fieldid = parseInt(element.dataset.fieldid, 10);
    const stored = readCoords(element);
    const zoom = parseInt(element.dataset.zoom, 10) || WORLD_VIEW.zoom;
    const fallback = readDefaultView(element);

    /**
     * Write a location back into the form.
     *
     * @param {number} lat
     * @param {number} lng
     * @param {?string} address Leave null to keep the current address text.
     */
    const store = (lat, lng, address = null) => {
        if (latInput) {
            latInput.value = lat.toFixed(6);
        }
        if (lngInput) {
            lngInput.value = lng.toFixed(6);
        }
        if (address !== null) {
            addressInput.value = address;
        }
    };

    let map = null;
    let marker = null;

    /**
     * Move the marker and the viewport to a location and persist it.
     *
     * @param {number} lat
     * @param {number} lng
     * @param {?string} address
     * @param {?number} targetzoom
     */
    const moveTo = (lat, lng, address = null, targetzoom = null) => {
        store(lat, lng, address);

        if (!map) {
            return;
        }
        marker.setLatLng([lat, lng]);
        map.setView([lat, lng], targetzoom || map.getZoom());
    };

    /**
     * Persist a coordinate pair and fill the address in from the geocoder.
     *
     * @param {number} lat
     * @param {number} lng
     */
    const adopt = async (lat, lng) => {
        moveTo(lat, lng);
        const address = await geocodeReverse(fieldid, lat, lng);
        if (address) {
            store(lat, lng, address);
        }
    };

    attachAutocomplete(addressInput, {
        fieldid,
        typeahead: element.dataset.typeahead === '1',
        minlength: parseInt(element.dataset.minlength, 10) || 3,
        trigger: element.querySelector('[data-action="search"]'),
        onSelect: (place) => moveTo(place.lat, place.lng, place.address, 16),
    });

    const geolocate = element.querySelector('[data-action="geolocate"]');
    if (geolocate) {
        geolocate.addEventListener('click', async () => {
            const labels = await getLabels();

            if (!navigator.geolocation) {
                geolocate.disabled = true;
                geolocate.textContent = labels.geolocationunsupported;
                return;
            }

            const original = geolocate.innerHTML;
            geolocate.disabled = true;
            geolocate.textContent = labels.geolocating;

            const restore = () => {
                geolocate.disabled = false;
                geolocate.innerHTML = original;
            };

            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    await adopt(position.coords.latitude, position.coords.longitude);
                    if (map) {
                        map.setZoom(16);
                    }
                    restore();
                },
                () => {
                    restore();
                    geolocate.setAttribute('title', labels.geolocationdenied);
                },
                {enableHighAccuracy: true, timeout: 10000, maximumAge: 0}
            );
        });
    }

    const clear = element.querySelector('[data-action="clear"]');
    if (clear) {
        clear.addEventListener('click', () => {
            addressInput.value = '';
            if (latInput) {
                latInput.value = '';
            }
            if (lngInput) {
                lngInput.value = '';
            }
            addressInput.focus();
        });
    }

    try {
        const L = await getLeaflet();
        await whenVisible(mapElement);

        const centre = stored || fallback;
        map = createMap(L, mapElement, readTiles(element), {
            center: [centre.lat, centre.lng],
            zoom: stored ? zoom : fallback.zoom,
        });

        marker = L.marker([centre.lat, centre.lng], {draggable: true}).addTo(map);
        marker.on('dragend', () => {
            const position = marker.getLatLng();
            adopt(position.lat, position.lng);
        });
        map.on('click', (event) => adopt(event.latlng.lat, event.latlng.lng));
    } catch (error) {
        await showMapError(mapElement);
    }
};

/**
 * Wire up the address autocomplete of one location filter.
 *
 * @param {HTMLElement} element
 */
const initSearchRegion = (element) => {
    const {addressid, latid, lngid} = element.dataset;
    const addressInput = addressid ? document.getElementById(addressid) : null;
    const latInput = latid ? document.getElementById(latid) : null;
    const lngInput = lngid ? document.getElementById(lngid) : null;

    if (!addressInput) {
        return;
    }

    attachAutocomplete(addressInput, {
        fieldid: parseInt(element.dataset.fieldid, 10),
        typeahead: element.dataset.typeahead === '1',
        minlength: parseInt(element.dataset.minlength, 10) || 3,
        trigger: null,
        onSelect: (place) => {
            addressInput.value = place.address;
            if (latInput) {
                latInput.value = place.lat.toFixed(6);
            }
            if (lngInput) {
                lngInput.value = place.lng.toFixed(6);
            }
        },
    });

    // A filter without coordinates falls back to a plain text match, so drop
    // stale coordinates as soon as the user edits the address by hand.
    addressInput.addEventListener('input', () => {
        if (latInput) {
            latInput.value = '';
        }
        if (lngInput) {
            lngInput.value = '';
        }
    });
};

/**
 * Collect the not-yet-initialised regions matching a selector, marking them.
 *
 * @param {Element|Document|DocumentFragment} root
 * @param {string} selector
 * @returns {Array<HTMLElement>}
 */
export const claimRegions = (root, selector) => {
    const found = [...root.querySelectorAll(selector)];

    if (typeof root.matches === 'function' && root.matches(selector)) {
        found.unshift(root);
    }

    return found.filter((element) => {
        if (element.dataset.locationInitialised) {
            return false;
        }
        element.dataset.locationInitialised = '1';

        return true;
    });
};

/**
 * Initialise every location widget inside the given root.
 *
 * Safe to call repeatedly and on overlapping roots: each container is wired up
 * at most once.
 *
 * @param {Element|Document} [root=document] Scope to search for widgets.
 */
export const init = (root = document) => {
    const scope = root && typeof root.querySelectorAll === 'function' ? root : document;

    claimRegions(scope, SELECTORS.map).forEach(initDisplayMap);
    claimRegions(scope, SELECTORS.picker).forEach(initPickerRegion);
    claimRegions(scope, SELECTORS.search).forEach(initSearchRegion);
};
