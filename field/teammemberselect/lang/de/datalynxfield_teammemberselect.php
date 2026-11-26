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
 * German strings for datalynxfield_teammemberselect.
 *
 * @package datalynxfield_teammemberselect
 * @copyright 2026 onwards The datalynx contributors
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['approval'] = 'Beitritt muss vom Autor bestätigt werden';
$string['approval_help'] = 'Nutzer/innen beantragen den Beitritt; der Autor des Eintrags (oder ein Manager) nimmt jede Anfrage an oder lehnt sie ab. Angenommene Personen werden Mitglied; die Teamgröße und die Teamgröße aus Feld gelten weiterhin. Ohne diese Einstellung treten Nutzer/innen direkt bei.';
$string['approve'] = 'Annehmen';
$string['confirmed'] = 'Bestätigt';
$string['decline'] = 'Ablehnen';
$string['declined'] = 'Abgelehnt';
$string['hasfreeplaces'] = 'hat freie Plätze';
$string['ihaverequested'] = 'von mir beantragt';
$string['leaveteam'] = 'Austragen';
$string['limitbelowmembers'] = 'Im Team dieses Eintrags sind bereits {$a} Personen; der Wert kann nicht kleiner sein.';
$string['limitfield'] = 'Teamgröße aus Feld';
$string['limitfield_help'] = 'Ein Zahlen- oder Auswahlfeld desselben Eintrags, das die Teamgröße jedes Eintrags festlegt, zum Beispiel die Plätze einer Fahrt. Die Teamgröße oben bleibt die Obergrenze. Ist das Feld leer, kann niemand beitreten. Nutzer/innen können dann nach Einträgen mit freien Plätzen suchen.';
$string['limitnone'] = 'Keines (die Teamgröße oben für jeden Eintrag)';
$string['nomember'] = 'Diese Person ist kein Mitglied.';
$string['norequest'] = 'Von dieser Person gibt es keine offene Anfrage.';
$string['notadmissible'] = 'Sie können diesem Team nicht beitreten.';
$string['nounsubscription'] = 'Sie können dieses Team nicht verlassen.';
$string['openrequests'] = 'Anfragen';
$string['pluginname'] = 'Teammitglieder-Auswahl';
$string['privacy:metadata'] = 'Das Feld Teammitglieder-Auswahl speichert selbst keine Daten. Die Nutzer-IDs der ausgewählten Teammitglieder sind Teil des Eintragsinhalts, den mod_datalynx auffindet, exportiert und entfernt.';
$string['protectmembers'] = 'Einträge mit Mitgliedern dürfen nur Manager löschen';
$string['protectmembers_help'] = 'Ein Eintrag, dessen Team Mitglieder hat, kann nicht von seinem Autor gelöscht werden, nur von Personen, die Einträge verwalten. Eine Fahrt mit bestätigten Mitfahrenden wird zum Beispiel abgesagt statt gelöscht.';
$string['remove'] = 'Entfernen';
$string['requestdeclined'] = 'Ihre Anfrage wurde abgelehnt; Sie können nicht erneut anfragen.';
$string['requested'] = 'Angefragt';
$string['requestjoin'] = 'Beitritt beantragen';
$string['requestlabel'] = 'Beschriftung des Antrags-Buttons';
$string['requestlabel_help'] = 'Der Text des Buttons, mit dem der Beitritt beantragt wird, wenn er vom Autor bestätigt werden muss, zum Beispiel „Mitfahrt beantragen“. Leer für die Standardbeschriftung.';
$string['teamfull'] = 'Ausgebucht';
$string['teammembersnotoffered'] = 'Diese Personen stehen hier nicht mehr zur Auswahl, z. B. weil sie nicht mehr eingeschrieben sind: {$a}. Beim Speichern des Eintrags werden sie aus dem Team entfernt und verlieren damit den Zugriff, den das Team ihnen gibt.';
$string['teammembersremoved'] = 'Aus dem Team in „{$a->field}“ entfernt, weil sie nicht mehr zur Auswahl stehen: {$a->users}.';
$string['teamoptionsfieldgroup'] = 'Dieses Feld ist ein Unterfeld einer Feldgruppe. Dort ist es eine einfache Personenliste: Die Optionen „Nutzer/in kann sich selbst hinzufügen“, „Teammitglieder benachrichtigen“, „Austragen erlauben“, das Teamfeld und „Teammitglieder dürfen den Eintrag bearbeiten“ haben keine Wirkung, ebenso wenig die Sichtbarkeit für Teammitglieder.';
$string['teamsubscriptionfieldgroup'] = 'Sie können sich hier nicht ein- oder austragen: In einer Feldgruppe ist dieses Feld eine einfache Personenliste, kein Team.';
$string['withdrawrequest'] = 'Anfrage zurückziehen';
