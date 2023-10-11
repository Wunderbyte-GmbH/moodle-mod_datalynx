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
import {pdfjsLib} from 'pdf';
// eslint-disable-next-line no-console
console.log(pdfjsLib);
define(['mod_datalynx/pdf'], function (pdfjsLib) {
    var MyPDFModule = {
        renderPDF: function (pdfUrl, containerId) {
            pdfjsLib.getDocument(pdfUrl).promise.then(function (pdf) {
                pdf.getPage(1).then(function (page) {
                    var scale = 1.5; // Adjust the scale as needed.
                    var viewport = page.getViewport({ scale: scale });
                    var canvas = document.createElement('canvas');
                    var context = canvas.getContext('2d');
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;

                    var renderContext = {
                        canvasContext: context,
                        viewport: viewport,
                    };

                    page.render(renderContext).promise.then(function () {
                        var container = document.getElementById(containerId);
                        container.appendChild(canvas);
                    });
                });
            });
        }
    };
    return MyPDFModule;
});