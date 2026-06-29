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
 * Strings for component 'datalynxrule_updatefield', language 'de'.
 *
 * @package datalynxrule_updatefield
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action'] = 'Feldaktualisierung';
$string['err_nofield'] = 'Bitte wählen Sie ein Feld aus, das aktualisiert werden soll.';
$string['err_novalue'] = 'Bitte geben Sie den zu setzenden Wert an.';
$string['newvalue'] = 'Neuer Wert';
$string['newvalue_help'] = 'Der Wert, der in das ausgewählte Feld geschrieben wird, wenn die Regel ausgelöst wird. Bei Auswahl-, Radiobutton- und Checkbox-Feldern wählen Sie aus den Optionen des Feldes; bei Textfeldern geben Sie den Wert ein.';
$string['newvaluefor'] = 'Neuer Wert ({$a})';
$string['onlyonchange'] = 'Nur auslösen, wenn sich diese Feldwerte ändern';
$string['onlyonchange_help'] = 'Beliebige Felder auswählen. Die Regel wird nur ausgeführt, wenn mindestens eines dieser Felder seinen Wert bei der auslösenden Aktualisierung tatsächlich geändert hat – nicht schon, wenn der aktuelle Wert zufällig einer Bedingung entspricht. Dies gilt unabhängig von den Auslösebedingungen oben. Hat keine Wirkung bei „Eintrag erstellt"-Ereignissen.';
$string['pluginname'] = 'Feld aktualisieren';
$string['privacy:metadata'] = 'Das Plugin datalynxrule_updatefield speichert keine personenbezogenen Daten.';
$string['targetfield'] = 'Zu aktualisierendes Feld';
$string['targetfield_help'] = 'Das Feld, dessen Wert beim Auslösen der Regel gesetzt wird. Es können nur Text-, Auswahl- (Select/Radiobutton) und Checkbox-/Mehrfachauswahl-Felder aktualisiert werden.';
$string['triggerfollowupevents'] = 'Folgeereignisse auslösen';
$string['triggerfollowupevents_help'] = 'Wenn aktiviert, wird nach der Wertänderung ein Ereignis „Eintrag aktualisiert" ausgelöst, sodass andere Regeln und Benachrichtigungen darauf reagieren können. Die Aktualisierung wird stillschweigend übersprungen, wenn sich der Wert nicht tatsächlich geändert hat, und eine Wiedereintrittssperre verhindert Schleifen. Lassen Sie die Option deaktiviert (Standard) für eine vollständig stille Aktualisierung.';
