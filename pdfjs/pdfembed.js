// This file is part of the mod_coursecertificate plugin for Moodle - http://moodle.org/
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
 * @copyright   2023 David Bogner <david.bogner@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// import 'mod_datalynx/pdf';

function renderPDFfunction(url, canvasContainer) {

    console.log(pdfjsLib, pdfjsWorker);

    function renderPage(page) {
        var viewport = page.getViewport({scale: 1});
        var canvas = document.createElement('canvas');
        var ctx = canvas.getContext('2d');
        var renderContext = {
            canvasContext: ctx,
            viewport: viewport
        };
        // Calculate the scaling factors to fit the container's width and height
        var widthScale = canvasContainer.clientWidth / viewport.width;
        var heightScale = canvasContainer.clientHeight / viewport.height;

        // Use the minimum scale to ensure that the entire page fits within the container
        var scale = Math.min(widthScale, heightScale);

        // Apply the scaling factor
        canvas.width = viewport.width * scale;
        canvas.height = viewport.height * scale;

        // canvas.height = canvasContainer.clientHeight;
        // canvas.width = canvasContainer.clientWidth;

        // canvas.height = 800;
        // canvas.width = 1200;

        canvasContainer.appendChild(canvas);

        // eslint-disable-next-line no-console
        console.log(canvasContainer, canvas, canvas.height);

        page.render(renderContext);
    }

    function renderPages(pdfDoc) {
        for (var num = 1; num <= pdfDoc.numPages; num++)
            pdfDoc.getPage(num).then(renderPage);
    }

    pdfjsLib.GlobalWorkerOptions.workerSrc = pdfjsWorker;

    // eslint-disable-next-line no-console
    console.log(pdfjsLib.version, pdfjsWorker);

    pdfjsLib.getDocument(url).promise.then(renderPages);

}

// eslint-disable-next-line require-jsdoc
function renderPDF(pdfUrl, canvasContainerId) {

    // eslint-disable-next-line no-unused-vars
    const pdf = M.cfg.wwwroot + '/mod/datalynx/tests/turnen.pdf';
    const container = document.querySelector(`#${canvasContainerId}`);

    // eslint-disable-next-line no-console
    console.log(pdfjsLib);
    console.log(pdfUrl);
    console.log(container);

    renderPDFfunction(pdfUrl, container);
}