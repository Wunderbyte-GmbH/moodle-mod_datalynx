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
 * @package datalynxrule
 * @subpackage eventnotification
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['event'] = 'Datalynx Ereignis';
$string['pluginname'] = 'Ereignisbenachrichtigung';
$string['triggerspecificevent'] = 'Nur wenn folgendes Feld eine Bedingung erfüllt senden';
$string['regex'] = 'Regulärer Ausdruck, der verwendet wird, um die Kennung aus dem Dateinamen zu extrahieren';
$string['regex_desc'] = 'Wenn leer gelassen, wird es standardmäßig auf /^(\d+)_/ gesetzt.';
$string['messagecontent'] = 'Felder deren Inhalt in der Nachricht inkludiert wird';
$string['condition'] = 'Der Wert, den das ausgewählte Feld haben muss';
$string['condition_help'] = 'Der Wert, den das ausgewählte Feld erfüllen muss, um die Bedingung zu erfüllen.
Bei Checkboxen müssen die ausgewählten Zeilen eingegeben werden, getrennt durch einen Beistrich: Für erste Zeile und dritte Zeile: 1,3
Bei Optionsfeldern: 1 für die erste Auswahl, 2 für die zweite.';
