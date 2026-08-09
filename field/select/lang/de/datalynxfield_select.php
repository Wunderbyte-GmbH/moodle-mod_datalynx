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
 * @package datalynxfield_select
 * @subpackage select
 * @copyright 2026 David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['fieldformatoption'] = 'Anzeigeformat';
$string['fieldformatoption_help'] = 'Was in Ansichten anstelle des Tags "[[feldname:formatname]]" ausgegeben wird.

Mit "den Auswahlmöglichkeiten" sind die Optionen gemeint, die Sie für dieses Feld selbst festgelegt haben, unter *Verwalten > Felder > (Ihr Auswahlfeld) > Optionen* — eine pro Zeile. Jede Option hat eine Position (1 für die erste Zeile, 2 für die zweite und so weiter); gespeichert wird in einem Eintrag genau diese Position.

Beispiel: ein Feld mit den Optionen *Entwurf*, *In Prüfung* und *Freigegeben*, in einem Eintrag mit der Auswahl *In Prüfung*:

* **Nur Bezeichnung** — "In Prüfung". Die Bezeichnung der gewählten Option; das ist in fast allen Ansichten die richtige Wahl.
* **Schlüssel-Wert-Paare** — "0 Entwurf,1 In Prüfung,0 Freigegeben". Alle Optionen der Reihe nach, jeweils mit 1 davor, wenn sie ausgewählt ist, und mit 0, wenn nicht, getrennt durch Kommas. Nützlich für Exporte und für CSS oder JavaScript, das auch die *nicht* gewählten Optionen kennen muss.
* **Schlüssel/Index** — "2". Nur die gespeicherte Position der gewählten Option, ohne Bezeichnung. Nützlich für Exporte und zum Vergleich von Werten über mehrere Ansichten hinweg. Ist nichts ausgewählt, wird nichts ausgegeben.

Beachten Sie, dass die Position gespeichert wird und nicht die Bezeichnung: Wenn Sie die Optionen später umsortieren oder umbenennen, behalten bestehende Einträge ihre Nummer und ändern damit ihre Bedeutung.';
$string['fieldformatoptiondefault'] = 'Nur Bezeichnung';
$string['fieldformatoptionkey'] = 'Schlüssel/Index der gewählten Option';
$string['fieldformatoptionoptions'] = 'Schlüssel-Wert-Paare';
$string['fieldformatscope'] = 'Dieses Format ändert die Darstellung des Feldes <strong>nur in Ansichten (Anzeigemodus)</strong>. Im Eintragsformular ist das Feld immer eine Auswahlliste der für das Feld definierten Optionen — daran kann ein Format nichts ändern.';
$string['matchesmyprofilefield'] = 'Stimmt mit meinem Profilfeld überein';
$string['matchesmyprofilefield_help'] = 'Zeigt nur Einträge an, deren ausgewählte Option mit dem Wert des gewählten Profilfelds der aktuell angemeldeten Person übereinstimmt. Die Bezeichnung der Option wird (ohne Berücksichtigung der Groß-/Kleinschreibung) mit dem Profilwert verglichen. Kombinieren Sie dies mit dem Kriterium "Autor/in bin ich", um das Ergebnis auf die eigenen Einträge zu beschränken.';
$string['pluginname'] = 'Auswahl';
$string['privacy:metadata'] = 'Auswahlfelder speichern keine personenbezogenen Daten.';
