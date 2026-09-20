<?php
// This file is part of Moodle - http://moodle.org/
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
 * German strings for datalynxfield_entrytime.
 *
 * @package datalynxfield_entrytime
 * @copyright 2026 onwards The datalynx contributors
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['fieldformat_desc'] = 'Steuert die Anzeige der Zeitstempel des Eintrags. Geben Sie unten das Datumsformat an.';
$string['fieldformatdateformat'] = 'Datumsformat';
$string['fieldformatdateformat_help'] = 'Geben Sie eine PHP-strftime-Formatzeichenkette ein, um die Darstellung von Datum und Uhrzeit festzulegen. Mit "timestamp" wird der rohe Unix-Zeitstempel ausgegeben. Leer lassen, um das Standardformat von Moodle zu verwenden.';
$string['fieldformatdateformatexamples'] = '<details class="mt-2 mb-3">
  <summary class="text-info" style="cursor: pointer;">Gängige strftime-Formatbeispiele (zum Aufklappen klicken)</summary>
  <table class="table table-sm table-bordered mt-2">
    <thead><tr><th>Formatzeichenkette</th><th>Beispielausgabe</th><th>Beschreibung</th></tr></thead>
    <tbody>
      <tr><td><code>%d.%m.%Y</code></td><td>06.06.2026</td><td>Tag.Monat.Jahr (deutsche Schreibweise)</td></tr>
      <tr><td><code>%Y-%m-%d</code></td><td>2026-06-06</td><td>Datum nach ISO 8601</td></tr>
      <tr><td><code>%d/%m/%Y %H:%M</code></td><td>06/06/2026 14:30</td><td>Datum und Uhrzeit</td></tr>
      <tr><td><code>%H:%M</code></td><td>14:30</td><td>Nur Uhrzeit (24 Stunden)</td></tr>
      <tr><td><code>%I:%M %p</code></td><td>02:30 PM</td><td>Nur Uhrzeit (12 Stunden)</td></tr>
      <tr><td><code>%A, %d %B %Y</code></td><td>Samstag, 06 Juni 2026</td><td>Langes Datum</td></tr>
      <tr><td><code>%b %Y</code></td><td>Jun 2026</td><td>Monat und Jahr</td></tr>
      <tr><td><code>%V/%Y</code></td><td>23/2026</td><td>Kalenderwoche/Jahr</td></tr>
      <tr><td><code>timestamp</code></td><td>1749254400</td><td>Roher Unix-Zeitstempel</td></tr>
    </tbody>
  </table>
</details>';
$string['pluginname'] = 'Datalynx Zeit-Feld';
$string['privacy:metadata'] = 'Das Feld Eintragszeit speichert selbst keine Daten. Die Zeitstempel sind Teil des Eintrags, den mod_datalynx exportiert und löscht.';
