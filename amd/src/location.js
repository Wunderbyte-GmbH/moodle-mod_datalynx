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
 * Location field Leaflet & Nominatim map picker and geocoding integration.
 *
 * @module      mod_datalynx/location
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Debounce helper to limit API query frequency.
 *
 * @param {Function} func
 * @param {number} wait
 * @returns {Function}
 */
const debounce = (func, wait) => {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => func(...args), wait);
    };
};

/**
 * Ensure Leaflet CSS is injected into the document head if not present.
 */
const ensureLeafletCss = () => {
    if (!document.querySelector('link[href*="leaflet.css"]')) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        document.head.appendChild(link);
    }
};

/**
 * Configure Leaflet default icon paths to unpkg CDN.
 *
 * @param {Object} L
 */
const configureLeafletIcons = (L) => {
    if (L && L.Icon && L.Icon.Default) {
        delete L.Icon.Default.prototype._getIconUrl;
        L.Icon.Default.mergeOptions({
            iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
            iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
            shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
        });
    }
};

/**
 * Promise helper to load Leaflet JS library reliably across Moodle pages.
 *
 * @returns {Promise<Object>}
 */
const loadLeaflet = () => {
    return new Promise((resolve, reject) => {
        ensureLeafletCss();

        // Already loaded.
        if (typeof window.L !== 'undefined') {
            configureLeafletIcons(window.L);
            resolve(window.L);
            return;
        }

        // Guard against double-resolution.
        let resolved = false;
        const done = (L) => {
            if (!resolved) {
                resolved = true;
                configureLeafletIcons(L);
                resolve(L);
            }
        };
        const fail = (msg) => {
            if (!resolved) {
                resolved = true;
                reject(new Error(msg));
            }
        };

        // Remove any existing Leaflet script that might have been added by
        // Moodle's $PAGE->requires->js() — those get AMD-shimmed and don't
        // set window.L.
        let script = document.querySelector('script[src*="leaflet"][src*=".js"]');
        if (script && typeof window.L === 'undefined') {
            // Script exists but L is not defined — it was probably shimmed.
            // Create a fresh one.
            script = null;
        }

        if (!script) {
            script = document.createElement('script');
            script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            script.crossOrigin = 'anonymous';
            document.head.appendChild(script);
        }

        script.addEventListener('load', () => {
            if (typeof window.L !== 'undefined') {
                done(window.L);
            }
        });

        script.addEventListener('error', () => {
            fail('Failed to load Leaflet script from CDN');
        });

        // Polling fallback: handles race conditions where script was already
        // loading from a previous call, or loaded synchronously from cache.
        let retries = 0;
        const interval = setInterval(() => {
            if (typeof window.L !== 'undefined') {
                clearInterval(interval);
                done(window.L);
            } else if (++retries > 100) {
                clearInterval(interval);
                fail('Leaflet loading timed out after 10s');
            }
        }, 100);
    });
};

/**
 * Reverse geocode latitude and longitude to address text via Nominatim.
 *
 * @param {number} lat
 * @param {number} lng
 * @param {string} apiurl
 * @returns {Promise<string>}
 */
const reverseGeocode = async (lat, lng, apiurl) => {
    const url = `${apiurl.replace(/\/$/, '')}/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`;
    try {
        const response = await fetch(url);
        if (!response.ok) {
            return '';
        }
        const data = await response.json();
        return data.display_name || '';
    } catch (e) {
        return '';
    }
};

/**
 * Forward geocode address query text via Nominatim.
 *
 * @param {string} query
 * @param {string} apiurl
 * @param {string} countries
 * @returns {Promise<Array>}
 */
const searchLocations = async (query, apiurl, countries) => {
    if (!query || query.length < 3) {
        return [];
    }
    let url = `${apiurl.replace(/\/$/, '')}/search?format=json&q=${encodeURIComponent(query)}&limit=5&addressdetails=1`;
    if (countries) {
        url += `&countrycodes=${encodeURIComponent(countries)}`;
    }
    try {
        const response = await fetch(url);
        if (!response.ok) {
            return [];
        }
        return await response.json();
    } catch (e) {
        return [];
    }
};

/**
 * Initialize interactive map picker for entry creation and editing.
 *
 * @param {Object} options
 */
export const initPicker = async (options) => {
    const { fieldid, entryid, apiurl, zoom, countries, initialLat, initialLng } = options;

    const prefix       = `field_${fieldid}_${entryid}`;
    const addressInput = document.getElementById(`${prefix}_address`);
    const latInput     = document.getElementById(`${prefix}_lat`);
    const lngInput     = document.getElementById(`${prefix}_lng`);
    const mapElement   = document.getElementById(`map_${fieldid}_${entryid}`);
    const suggestions  = document.getElementById(`${prefix}_suggestions`);
    const geoBtn       = document.getElementById(`${prefix}_geolocate_btn`);

    if (!addressInput || !mapElement) {
        return;
    }

    // Helper to update form values.
    const updateCoords = (lat, lng, address = null) => {
        if (latInput) {
            latInput.value = lat.toFixed(6);
        }
        if (lngInput) {
            lngInput.value = lng.toFixed(6);
        }
        if (address !== null && addressInput) {
            addressInput.value = address;
        }
    };

    // Attach Geolocation Button Handler immediately.
    if (geoBtn) {
        geoBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by your browser.');
                return;
            }
            geoBtn.disabled = true;
            const originalHtml = geoBtn.innerHTML;
            geoBtn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Locating...';

            navigator.geolocation.getCurrentPosition(
                async (pos) => {
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;
                    updateCoords(lat, lng);

                    if (window.datalynxMapPicker && window.datalynxMapPicker[`${fieldid}_${entryid}`]) {
                        const { map, marker } = window.datalynxMapPicker[`${fieldid}_${entryid}`];
                        map.setView([lat, lng], 15);
                        marker.setLatLng([lat, lng]);
                        map.invalidateSize();
                    }

                    const addr = await reverseGeocode(lat, lng, apiurl);
                    if (addr) {
                        updateCoords(lat, lng, addr);
                    }
                    geoBtn.disabled = false;
                    geoBtn.innerHTML = originalHtml;
                },
                (err) => {
                    alert('Could not retrieve your location: ' + err.message);
                    geoBtn.disabled = false;
                    geoBtn.innerHTML = originalHtml;
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        });
    }

    /**
     * Wait for a DOM element to have actual layout dimensions.
     * Leaflet needs a non-zero-sized container to render tiles correctly.
     *
     * @param {HTMLElement} el
     * @param {number} maxWait
     * @returns {Promise<void>}
     */
    const waitForSize = (el, maxWait = 5000) => new Promise((resolve) => {
        const start = Date.now();
        const check = () => {
            if (el.offsetWidth > 0 && el.offsetHeight > 0) {
                resolve();
            } else if (Date.now() - start < maxWait) {
                requestAnimationFrame(check);
            } else {
                // Timeout — resolve anyway and let Leaflet try its best.
                resolve();
            }
        };
        check();
    });

    // Load Leaflet and initialize map.
    try {
        const L = await loadLeaflet();
        const startLat = initialLat || 52.52;
        const startLng = initialLng || 13.405;

        // Wait until the container has actual pixel dimensions before creating the map.
        await waitForSize(mapElement);

        // Create Leaflet Map.
        const map = L.map(mapElement, {
            center: [startLat, startLng],
            zoom: zoom || 13,
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Add Draggable Marker.
        const marker = L.marker([startLat, startLng], { draggable: true }).addTo(map);

        window.datalynxMapPicker = window.datalynxMapPicker || {};
        window.datalynxMapPicker[`${fieldid}_${entryid}`] = { map, marker };

        // Force Leaflet to recalculate bounds aggressively.
        // Moodle templates can shift layout for several hundred milliseconds after initial render.
        const forceResize = () => {
            try {
                map.invalidateSize({ animate: false });
            } catch (ignore) {
                // Map may have been removed.
            }
        };
        forceResize();
        setTimeout(forceResize, 100);
        setTimeout(forceResize, 300);
        setTimeout(forceResize, 600);
        setTimeout(forceResize, 1200);
        setTimeout(forceResize, 2500);

        // Marker Drag End Handler.
        marker.on('dragend', async () => {
            const pos = marker.getLatLng();
            map.panTo(pos);
            updateCoords(pos.lat, pos.lng);
            const addr = await reverseGeocode(pos.lat, pos.lng, apiurl);
            if (addr) {
                updateCoords(pos.lat, pos.lng, addr);
            }
        });

        // Map Click Handler.
        map.on('click', async (e) => {
            const { lat, lng } = e.latlng;
            marker.setLatLng([lat, lng]);
            map.panTo([lat, lng]);
            updateCoords(lat, lng);
            const addr = await reverseGeocode(lat, lng, apiurl);
            if (addr) {
                updateCoords(lat, lng, addr);
            }
        });
    } catch (e) {
        // eslint-disable-next-line no-console
        console.error('Failed to load Leaflet map:', e);
    }

    // Autocomplete Suggestions.
    if (suggestions) {
        const renderSuggestions = (items) => {
            suggestions.innerHTML = '';
            if (!items || items.length === 0) {
                suggestions.classList.add('d-none');
                return;
            }

            items.forEach((item) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'list-group-item list-group-item-action text-truncate small';
                btn.textContent = item.display_name;

                btn.addEventListener('click', () => {
                    const lat = parseFloat(item.lat);
                    const lng = parseFloat(item.lon);
                    updateCoords(lat, lng, item.display_name);

                    if (window.datalynxMapPicker && window.datalynxMapPicker[`${fieldid}_${entryid}`]) {
                        const { map, marker } = window.datalynxMapPicker[`${fieldid}_${entryid}`];
                        map.setView([lat, lng], 15);
                        marker.setLatLng([lat, lng]);
                        map.invalidateSize();
                    }

                    suggestions.classList.add('d-none');
                });

                suggestions.appendChild(btn);
            });

            suggestions.classList.remove('d-none');
        };

        addressInput.addEventListener('input', debounce(async (e) => {
            const query = e.target.value.trim();
            if (query.length < 3) {
                suggestions.classList.add('d-none');
                return;
            }
            const results = await searchLocations(query, apiurl, countries);
            renderSuggestions(results);
        }, 350));

        document.addEventListener('click', (e) => {
            if (!suggestions.contains(e.target) && e.target !== addressInput) {
                suggestions.classList.add('d-none');
            }
        });
    }
};

/**
 * Initialize Mini-Map display for entry detailed and list view.
 *
 * @param {Object} options
 */
export const initMiniMap = async (options) => {
    const { containerId, lat, lng, address, zoom } = options;
    const container = document.getElementById(containerId);

    if (!container) {
        return;
    }

    /**
     * Wait for element to have layout dimensions.
     * @param {HTMLElement} el
     * @param {number} maxWait
     * @returns {Promise<void>}
     */
    const waitForSize = (el, maxWait = 5000) => new Promise((resolve) => {
        const start = Date.now();
        const check = () => {
            if (el.offsetWidth > 0 && el.offsetHeight > 0) {
                resolve();
            } else if (Date.now() - start < maxWait) {
                requestAnimationFrame(check);
            } else {
                resolve();
            }
        };
        check();
    });

    try {
        const L = await loadLeaflet();
        await waitForSize(container);

        const map = L.map(container, {
            zoomControl: false,
            attributionControl: false,
            center: [lat, lng],
            zoom: zoom || 13,
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19
        }).addTo(map);

        const marker = L.marker([lat, lng]).addTo(map);
        if (address) {
            marker.bindPopup(`<b>${address}</b>`);
        }

        // Aggressive invalidateSize to handle Moodle's dynamic layout.
        const forceResize = () => {
            try {
                map.invalidateSize({ animate: false });
            } catch (ignore) {
                // Container may have been removed.
            }
        };
        forceResize();
        setTimeout(forceResize, 100);
        setTimeout(forceResize, 300);
        setTimeout(forceResize, 600);
        setTimeout(forceResize, 1200);
        setTimeout(forceResize, 2500);
    } catch (e) {
        // eslint-disable-next-line no-console
        console.error('Failed to load mini map:', e);
    }
};

/**
 * Initialize search autocomplete for location filter.
 *
 * @param {Object} options
 */
export const initSearch = (options) => {
    const { fieldname, apiurl, countries } = options;

    const addressInput = document.getElementById(`${fieldname}_address`);
    const latInput     = document.getElementById(`${fieldname}_lat`);
    const lngInput     = document.getElementById(`${fieldname}_lng`);

    if (!addressInput) {
        return;
    }

    // Create dynamic suggestion box.
    const suggestions = document.createElement('div');
    suggestions.className = 'list-group position-absolute z-3 w-100 shadow-sm d-none';
    addressInput.parentNode.style.position = 'relative';
    addressInput.parentNode.appendChild(suggestions);

    const renderSuggestions = (items) => {
        suggestions.innerHTML = '';
        if (!items || items.length === 0) {
            suggestions.classList.add('d-none');
            return;
        }

        items.forEach((item) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action text-truncate small';
            btn.textContent = item.display_name;

            btn.addEventListener('click', () => {
                addressInput.value = item.display_name;
                if (latInput) {
                    latInput.value = parseFloat(item.lat).toFixed(6);
                }
                if (lngInput) {
                    lngInput.value = parseFloat(item.lon).toFixed(6);
                }
                suggestions.classList.add('d-none');
            });

            suggestions.appendChild(btn);
        });

        suggestions.classList.remove('d-none');
    };

    addressInput.addEventListener('input', debounce(async (e) => {
        const query = e.target.value.trim();
        if (query.length < 3) {
            suggestions.classList.add('d-none');
            if (latInput) {
                latInput.value = '';
            }
            if (lngInput) {
                lngInput.value = '';
            }
            return;
        }
        const results = await searchLocations(query, apiurl, countries);
        renderSuggestions(results);
    }, 350));

    document.addEventListener('click', (e) => {
        if (!suggestions.contains(e.target) && e.target !== addressInput) {
            suggestions.classList.add('d-none');
        }
    });
};
