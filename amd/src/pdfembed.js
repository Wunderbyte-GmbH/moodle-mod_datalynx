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

import * as pdfjsLib from 'mod_datalynx/pdf';
import pdfjsWorker from 'mod_datalynx/pdf.worker';

function renderPDFfunction(url, canvasContainer, options) {

    var options = options || { scale: 1 };

    function renderPage(page) {
        var viewport = page.getViewport(options.scale);
        var canvas = document.createElement('canvas');
        var ctx = canvas.getContext('2d');
        var renderContext = {
            canvasContext: ctx,
            viewport: viewport
        };

        canvas.height = viewport.height;
        canvas.width = viewport.width;

        canvasContainer.appendChild(canvas);

        console.log(canvasContainer, canvas);

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
export function renderPDF() {

    const pdf = M.cfg.wwwroot + '/mod/datalynx/tests/turnen.pdf';
    const canvas = document.querySelector('#resourceobject').parentElement;

    // eslint-disable-next-line no-console
    console.log(pdfjsLib);

    renderPDFfunction(pdf, canvas);
};