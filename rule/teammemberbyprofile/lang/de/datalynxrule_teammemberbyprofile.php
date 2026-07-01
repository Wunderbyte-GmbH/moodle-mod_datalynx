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
 * @package datalynxrule_teammemberbyprofile
 * @subpackage teammemberbyprofile
 * @copyright 2026 David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['mode'] = 'Vorhandene Teammitglieder';
$string['mode_help'] = 'Wie mit bereits im Teammitglieder-Feld vorhandenen Mitgliedern umgegangen wird, wenn die Regel ausgeführt wird.

"Überschreiben" ersetzt das Feld durch genau die übereinstimmenden Personen (nicht übereinstimmende Mitglieder werden entfernt). "Nur hinzufügen" behält die vorhandenen Mitglieder und fügt die übereinstimmenden hinzu.';
$string['modemerge'] = 'Nur hinzufügen (vorhandene Mitglieder behalten)';
$string['modeoverwrite'] = 'Überschreiben (durch übereinstimmende Personen ersetzen)';
$string['pluginname'] = 'Teammitglieder anhand Profilfeld hinzufügen';
$string['privacy:metadata'] = 'Die Regel „Teammitglieder anhand Profilfeld hinzufügen“ speichert nur ihre eigene Konfiguration und keine personenbezogenen Daten.';
$string['privilege'] = 'Erforderliche Berechtigung';
$string['privilege_help'] = 'Nur Personen, die in dieser Aktivität die entsprechende Datalynx-Ansichtsberechtigung besitzen, kommen als Teammitglieder in Frage.';
$string['privilegeguest'] = 'Gast';
$string['privilegemanager'] = 'Manager/in';
$string['privilegestudent'] = 'Teilnehmer/in';
$string['privilegeteacher'] = 'Trainer/in';
$string['profilefield'] = 'Nutzerprofilfeld';
$string['profilefield_help'] = 'Das Nutzerprofilfeld, dessen Wert mit der Bezeichnung der ausgewählten Option des Auswahlfeldes verglichen wird. Es stehen sowohl Standardfelder (z. B. Abteilung oder Institution) als auch benutzerdefinierte Profilfelder zur Verfügung. Beim Vergleich werden Groß-/Kleinschreibung und umgebende Leerzeichen ignoriert.';
$string['selectfield'] = 'Auswahlfeld';
$string['selectfield_help'] = 'Das Einfachauswahl-Feld des Eintrags, dessen ausgewählte Option mit dem gewählten Nutzerprofilfeld verglichen wird.';
$string['teammemberfield'] = 'Teammitglieder-Feld';
$string['teammemberfield_help'] = 'Das Teammitglieder-Feld, das beim Erstellen oder Aktualisieren eines Eintrags mit den übereinstimmenden Personen befüllt wird.';
