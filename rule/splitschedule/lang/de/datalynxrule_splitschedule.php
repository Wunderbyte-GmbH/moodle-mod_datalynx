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
 * Strings for component 'datalynxrule_splitschedule', language 'de'.
 *
 * @package    datalynxrule_splitschedule
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action'] = 'Aufteilen';
$string['errnofield'] = 'Bitte ein Terminfeld wählen.';
$string['maxmonths'] = 'Höchstens Monate im Voraus';
$string['maxmonths_help'] = 'Einträge entstehen für die Termine bis so viele Monate ab heute, nie über das Enddatum des Termins hinaus.';
$string['pluginname'] = 'Wöchentlichen Termin in Einzeleinträge aufteilen';
$string['privacy:metadata'] = 'Das Plugin datalynxrule_splitschedule speichert keine personenbezogenen Daten. Die Einträge, die es anlegt, gehören dem Autor des Ausgangseintrags.';
$string['schedulefield'] = 'Terminfeld';
$string['schedulefield_help'] = 'Löst ein Eintrag mit wöchentlichem Termin die Regel aus, wird er zum Eintrag des ersten Termins, und für jeden weiteren Termin entsteht eine Kopie: gleicher Autor, gleiche Inhalte (ohne Teammitglieder), jeweils mit einem einmaligen Termin an ihrem Datum. Jede Kopie löst „Eintrag erstellt“ aus, sodass andere Regeln für jeden Termin laufen. Einträge mit einmaligem Termin bleiben unverändert.';
