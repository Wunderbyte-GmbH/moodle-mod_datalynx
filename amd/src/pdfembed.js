// This file is part of mod_datalynx for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This module provides functionality for embedding PDFs using pdf.js.
 *
 * @module      mod_datalynx/pdfembed
 * @copyright   2025 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Config from 'core/config';
import Notification from 'core/notification';

/**
 * Returns the vendored PDF.js module and configures its worker path.
 *
 * @returns {Promise<*>}
 */
const getPdfJsModule = async() => {
    if (!window.modDatalynxPdfJsModulePromise) {
        throw new Error('PDF.js module was not preloaded.');
    }

    const pdfJs = await window.modDatalynxPdfJsModulePromise;
    pdfJs.GlobalWorkerOptions.workerSrc = `${Config.wwwroot}/mod/datalynx/pdfjs/pdf.worker.mjs`;

    return pdfJs;
};

/**
 * Renders a single page of a PDF document onto a canvas element.
 *
 * @param {*} page The PDF.js page object to render.
 * @param {HTMLElement} canvasContainer The container element where the canvas is appended.
 * @param {number} customScale The custom scale for rendering the PDF.
 */
const renderPage = (page, canvasContainer, customScale) => {
    const viewport = page.getViewport({scale: 1});
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');
    const desiredWidth = 595;
    const desiredHeight = 841;
    const transform = customScale !== 1 ? [customScale, 0, 0, customScale, 0, 0] : null;
    const renderContext = {
        canvasContext: context,
        transform,
        viewport,
    };

    canvas.height = desiredHeight * customScale;
    canvas.width = desiredWidth * customScale;
    canvas.style.width = `${desiredWidth * customScale}px`;
    canvas.style.height = `${desiredHeight * customScale}px`;

    canvasContainer.appendChild(canvas);

    return page.render(renderContext).promise;
};

/**
 * Renders all pages of a PDF document into the target container.
 *
 * @param {*} pdfDocument The PDF.js document object.
 * @param {HTMLElement} canvasContainer The container element where the canvases are appended.
 * @param {number} customScale The custom scale for rendering the PDF.
 * @returns {Promise<void>}
 */
const renderPdfDocument = async(pdfDocument, canvasContainer, customScale) => {
    for (let pageNumber = 1; pageNumber <= pdfDocument.numPages; pageNumber++) {
        const page = await pdfDocument.getPage(pageNumber);
        await renderPage(page, canvasContainer, customScale);
    }
};

/**
 * Renders a PDF document into the target container.
 *
 * @param {string} url The URL of the PDF document to render.
 * @param {HTMLElement} canvasContainer The container element where the canvases are appended.
 * @param {number} customScale The custom scale for rendering the PDF.
 * @returns {Promise<void>}
 */
const renderPdf = async(url, canvasContainer, customScale) => {
    const pdfJs = await getPdfJsModule();
    const pdfDocument = await pdfJs.getDocument(url).promise;
    await renderPdfDocument(pdfDocument, canvasContainer, customScale);
};

/**
 * Renders a PDF document onto a specified container.
 *
 * @param {string} pdfUrl The URL of the PDF document to render.
 * @param {string} canvasContainerId The ID of the container element where the canvas will be appended.
 * @param {number} customScale The custom scale for rendering the PDF.
 * @returns {Promise<void>}
 */
export const renderPDF = async(pdfUrl, canvasContainerId, customScale = 1) => {
    const container = document.querySelector(`#${canvasContainerId}`);
    if (!container) {
        return;
    }

    container.innerHTML = '';

    try {
        await renderPdf(pdfUrl, container, customScale);
    } catch (error) {
        Notification.exception(error);
    }
};
