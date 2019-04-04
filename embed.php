<?php
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
 *
 * @package mod_datalynx
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');
require_once("$CFG->dirroot/mod/datalynx/classes/datalynx.php");

$urlparams = new stdClass();
$urlparams->d = optional_param('d', 0, PARAM_INT); // Datalynx id.
$urlparams->id = optional_param('id', 0, PARAM_INT); // Course module id.

$urlparams->view = optional_param('view', 0, PARAM_INT); // Current view id.
$urlparams->filter = optional_param('filter', 0, PARAM_INT); // Current filter (-1 for user filter).
$urlparams->pagelayout = optional_param('pagelayout', '', PARAM_ALPHAEXT);
$urlparams->refresh = optional_param('refresh', 0, PARAM_INT);
$urlparams->eids = optional_param('eids', 0, PARAM_SEQUENCE);

// Set a datalynx object with guest autologin.
$df = new mod_datalynx\datalynx($urlparams->d, $urlparams->id);

require_login($df->data->course, false, $df->cm);

$pageparams = array('js' => true, 'css' => true, 'rss' => true, 'modjs' => true,
        'completion' => true, 'comments' => true, 'pagelayout' => 'embedded', 'urlparams' => $urlparams
);
$df->set_page('embed', $pageparams);

require_capability('mod/datalynx:viewentry', $df->context);

$df->set_content();

$headerparams = array('groups' => true, 'urlparams' => $urlparams);
$df->print_header($headerparams);

$df->display();

$df->print_footer();
