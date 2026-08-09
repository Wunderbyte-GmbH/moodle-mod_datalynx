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
 * Slider input for number fields rendered by a slider field format.
 *
 * The range input steps over the *positions* of the value list produced by
 * {@link datalynxfield_number\field_format::get_slider_values()}, not over the values
 * themselves, so that non-linear scales (Fibonacci, 1-2-5) work with a plain range
 * input. The number element added by the field renderer stays in the form and keeps
 * carrying the submitted value; this module only hides it and keeps it in sync. Without
 * JavaScript that number input remains visible and the field stays usable.
 *
 * @module      mod_datalynx/numberslider
 * @copyright   2026 David Bogner
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    region: '[data-region="datalynx-number-slider"]',
    slider: '[data-region="slider"]',
    readout: '[data-region="readout"]',
};

/** @type {boolean} Whether the AJAX content listener is already attached. */
let isListeningForUpdates = false;

/**
 * Read a JSON array out of a data attribute.
 *
 * @param {string|undefined} raw
 * @returns {Array}
 */
const parseList = (raw) => {
    try {
        const parsed = JSON.parse(raw || '[]');
        return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
        return [];
    }
};

/**
 * Position of the value closest to the one currently held by the number input.
 *
 * Values that are not on the scale — because the format was reconfigured after the entry
 * was saved, for instance — snap to the nearest available position.
 *
 * @param {Array<number>} values
 * @param {string} current
 * @returns {number}
 */
const closestIndex = (values, current) => {
    const value = parseFloat(current);
    if (isNaN(value)) {
        return 0;
    }

    let index = 0;
    let distance = Math.abs(values[0] - value);
    values.forEach((candidate, candidateIndex) => {
        const candidateDistance = Math.abs(candidate - value);
        if (candidateDistance < distance) {
            distance = candidateDistance;
            index = candidateIndex;
        }
    });

    return index;
};

/**
 * Hide the number input that carries the submitted value.
 *
 * The whole form item row is hidden rather than just the input, so that the empty label
 * column Moodle wraps every form element in does not leave a gap above the slider. A
 * hidden input is still submitted.
 *
 * @param {HTMLInputElement} input
 */
const hideValueInput = (input) => {
    const row = input.closest('.fitem');
    (row || input).classList.add('d-none');
};

/**
 * Wire one slider region to its number input.
 *
 * @param {HTMLElement} region
 */
const initRegion = (region) => {
    const input = document.getElementById(region.dataset.input);
    const slider = region.querySelector(SELECTORS.slider);
    const readout = region.querySelector(SELECTORS.readout);
    if (!input || !slider) {
        return;
    }

    const values = parseList(region.dataset.values);
    if (values.length < 2) {
        return;
    }
    // Formatted server side so the readout uses the language's decimal separator and the unit.
    const labels = parseList(region.dataset.labels);

    /**
     * Copy the slider position into the number input and the readout.
     */
    const apply = () => {
        const index = parseInt(slider.value, 10);
        const value = values[index];
        if (value === undefined) {
            return;
        }
        input.value = value;
        if (readout) {
            readout.textContent = labels[index] === undefined ? String(value) : labels[index];
        }
    };

    slider.max = values.length - 1;
    slider.value = closestIndex(values, input.value);
    apply();
    hideValueInput(input);

    slider.addEventListener('input', () => {
        apply();
        // Field behaviours condition on the values of other fields and listen for changes
        // on the real form element, which the user never touches directly here.
        input.dispatchEvent(new Event('change', {bubbles: true}));
    });
};

/**
 * Collect the not-yet-initialised slider regions inside a root, marking them.
 *
 * @param {Element|Document|DocumentFragment} root
 * @returns {Array<HTMLElement>}
 */
const claimRegions = (root) => {
    const found = [...root.querySelectorAll(SELECTORS.region)];

    if (typeof root.matches === 'function' && root.matches(SELECTORS.region)) {
        found.unshift(root);
    }

    return found.filter((region) => {
        if (region.dataset.sliderInitialised) {
            return false;
        }
        region.dataset.sliderInitialised = '1';

        return true;
    });
};

/**
 * Initialise every slider inside the given root.
 *
 * Safe to call repeatedly and on overlapping roots: each region is wired up at most once.
 *
 * @param {Element|Document} [root=document] Scope to search for sliders.
 */
export const init = (root = document) => {
    const scope = root && typeof root.querySelectorAll === 'function' ? root : document;

    claimRegions(scope).forEach(initRegion);

    if (!isListeningForUpdates) {
        document.addEventListener('mod_datalynx:viewContentUpdated', (event) => {
            const target = event.detail?.target;
            if (target instanceof Element || target instanceof DocumentFragment || target instanceof Document) {
                claimRegions(target).forEach(initRegion);
            }
        });
        isListeningForUpdates = true;
    }
};

export default {init};
