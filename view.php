<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @package mod
 * @subpackage datalynx
 * @copyright 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 *          The Datalynx has been developed as an enhanced counterpart
 *          of Moodle's Database activity module (1.9.11+ (20110323)).
 *          To the extent that Datalynx code corresponds to Database code,
 *          certain copyrights on the Database module may obtain.
 */
require_once('../../config.php');
require_once("$CFG->dirroot/mod/datalynx/mod_class.php");

$urlparams = new stdClass();
$urlparams->d = optional_param('d', 0, PARAM_INT); // datalynx id
$urlparams->id = optional_param('id', 0, PARAM_INT); // course module id

$urlparams->view = optional_param('view', 0, PARAM_INT); // current view id
$urlparams->filter = optional_param('filter', 0, PARAM_INT); // current filter (-1 for user filter)
$urlparams->pagelayout = optional_param('pagelayout', '', PARAM_ALPHAEXT);
$urlparams->refresh = optional_param('refresh', 0, PARAM_INT);

// Set a datalynx object with guest autologin
$datalynx = new datalynx($urlparams->d, $urlparams->id);

$pageparams = array('js' => true, 'css' => true, 'rss' => true, 'modjs' => true,
        'completion' => true, 'comments' => true, 'urlparams' => $urlparams
);

$datalynx->set_page('view', $pageparams);
$datalynx->set_content();

require_capability('mod/datalynx:viewentry', $datalynx->context);

$headerparams = array('heading' => 'true', 'tab' => 'browse', 'groups' => true, 'urlparams' => $urlparams);

$datalynx->print_header($headerparams);

$datalynx->display();

$datalynx->print_footer();
