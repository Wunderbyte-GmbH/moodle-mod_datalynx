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
 * @package datalynxrule_eventnotification
 * @subpackage eventnotification
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['condition'] = 'Der Wert, den das ausgewählte Feld haben muss';
$string['condition_help'] = 'Der Wert, den das ausgewählte Feld erfüllen muss, um die Bedingung zu erfüllen.
Bei Checkboxen müssen die ausgewählten Zeilen eingegeben werden, getrennt durch einen Beistrich: Für erste Zeile und dritte Zeile: 1,3
Bei Optionsfeldern: 1 für die erste Auswahl, 2 für die zweite.';
$string['emailtemplate'] = 'E-Mail-Vorlage';
$string['emailtemplate_help'] = 'Wählen Sie eine interne E-Mail-Ansicht aus, um den Nachrichtentext zu erzeugen. Wenn keine Vorlage ausgewählt ist, wird weiterhin der bisherige Nachrichtentext verwendet.';
$string['emailtemplatenone'] = 'Keine Vorlage';
$string['errmatchradius'] = 'Geben Sie einen Umkreis von 0 oder mehr Kilometern an. Bei 0 wird der am Reiseweg-Feld eingestellte Umkreis verwendet.';
$string['errmatchreload'] = 'Wählen Sie aus, wie dieses Feld verglichen werden soll. Mit „Neu laden" werden die für das gewählte Feld möglichen Beziehungen geladen.';
$string['errmatchtolerance'] = 'Geben Sie eine Toleranz größer als null an.';
$string['event'] = 'Datalynx Ereignis';
$string['matchauthors'] = 'Verfasser/innen der passenden Einträge';
$string['matchauthors_help'] = 'Alle Verfasser/innen passender Einträge über den Eintrag informieren, der die Regel ausgelöst hat.';
$string['matchdedupe'] = 'Jedes Paar nur einmal melden';
$string['matchdedupe_help'] = 'Merken, welche Einträge einander bereits vorgestellt wurden, damit beim erneuten Speichern nicht dieselben Personen noch einmal benachrichtigt werden. Ausschalten, um bei jedem Speichern zu benachrichtigen.';
$string['matchdedupescope'] = 'Gemeinsam mit';
$string['matchdedupescope_help'] = 'Leer lassen, damit die gemeldeten Paare nur für diese Regel gemerkt werden. Denselben Namen in zwei Regeln eintragen – etwa in einer Regel, die von der Angebotsseite sucht, und einer, die von der Gesuchsseite sucht – damit beide sich den Merkspeicher teilen und ein Paar einmal insgesamt statt einmal je Regel gemeldet wird.';
$string['matchdirection'] = 'Richtung';
$string['matchdirection_help'] = 'Welche Route welche mitnehmen können muss. „Beliebig" trifft auch dann zu, wenn der auslösende Eintrag derjenige ist, der mitnimmt – genau das braucht eine einzelne Regel, die beide Seiten abdeckt.';
$string['matchdirectioneither'] = 'beide Routen können einander mitnehmen';
$string['matchdirectionforward'] = 'die passende Route kann diesen Eintrag mitnehmen';
$string['matchdirectionreverse'] = 'die Route dieses Eintrags kann die passende mitnehmen';
$string['matchforgetstale'] = 'Nicht mehr passende Paare vergessen';
$string['matchforgetstale_help'] = 'Wenn ein Paar nicht mehr zusammenpasst – die Abfahrtszeit wurde verschoben, die Route geändert –, wird vergessen, dass es gemeldet wurde. Passt es später wieder, gilt es erneut als Neuigkeit.';
$string['matchingentries'] = 'Passende Einträge';
$string['matchingentries_help'] = 'Nach anderen Einträgen suchen, die zu dem vom Ereignis betroffenen Eintrag passen, und die Personen hinter beiden benachrichtigen. Jede Zeile vergleicht ein Feld mit dem Wert, den der auslösende Eintrag dort hat. Beachten Sie: Ein Eintrag ganz ohne Wert in diesem Feld gilt als abweichender Wert.';
$string['matchmax'] = 'Höchstzahl an Treffern je Speichervorgang';
$string['matchmax_help'] = 'Obergrenze dafür, wie viele passende Einträge ein Speichervorgang meldet, damit weit gefasste Kriterien keine unbegrenzte Zahl an Benachrichtigungen auslösen können.';
$string['matchradius'] = 'Umkreis (km)';
$string['matchradius_help'] = 'Wie weit ein Halt von der Route entfernt liegen darf und trotzdem als auf dem Weg gilt. Bei 0 wird der am Reiseweg-Feld eingestellte Umkreis verwendet.';
$string['matchreldifferent'] = 'hat einen anderen Wert als dieser Eintrag';
$string['matchrelroute'] = 'passt zur Route dieses Eintrags';
$string['matchrelsame'] = 'hat denselben Wert wie dieser Eintrag';
$string['matchrelwithin'] = 'liegt innerhalb von';
$string['matchrequireapproved'] = 'Nur genehmigte Einträge';
$string['matchsubjectauthor'] = 'Verfasser/in dieses Eintrags, je passendem Eintrag einmal';
$string['matchsubjectauthor_help'] = 'Der verfassenden Person des auslösenden Eintrags je passendem Eintrag eine Nachricht senden, die jeweils auf den passenden Eintrag verweist statt auf den eigenen.';
$string['matchunitdays'] = 'Tagen';
$string['matchunithours'] = 'Stunden';
$string['matchunitminutes'] = 'Minuten';
$string['matchunitvalue'] = 'des Wertes';
$string['messagecontent'] = 'Felder deren Inhalt in der Nachricht inkludiert wird';
$string['onlyonchange'] = 'Nur auslösen, wenn sich diese Feldwerte ändern';
$string['onlyonchange_help'] = 'Beliebige Felder auswählen. Die Benachrichtigung wird nur gesendet, wenn mindestens eines dieser Felder seinen Wert bei der auslösenden Aktualisierung tatsächlich geändert hat – nicht schon, wenn der aktuelle Wert zufällig einer Bedingung entspricht. Dies gilt unabhängig von den Auslösebedingungen oben. Hat keine Wirkung bei „Eintrag erstellt"-Ereignissen.';
$string['pluginname'] = 'Ereignisbenachrichtigung';
$string['regex'] = 'Regulärer Ausdruck, der verwendet wird, um die Kennung aus dem Dateinamen zu extrahieren';
$string['regex_desc'] = 'Wenn leer gelassen, wird es standardmäßig auf /^(\d+)_/ gesetzt.';
$string['roleshelpinfo'] = 'Hinweis: Empfänger in den ausgewählten Rollen müssen die entsprechende Benachrichtigungsberechtigung (z. B. "mod/datalynx:notifyentryadded" für die Erstellung von Einträgen) in ihren Moodle-Rechten aktiviert haben, um diese Benachrichtigungen zu erhalten.';
$string['taskfindmatches'] = 'Passende Einträge zu einem gespeicherten Eintrag suchen';
$string['triggerconditions'] = 'Auslösebedingungen';
$string['triggerconditions_help'] = 'Die Benachrichtigung wird nur gesendet, wenn der Eintrag, der das Ereignis ausgelöst hat, diese Bedingungen erfüllt. Leer lassen, um immer zu senden. Mehrere Zeilen lassen sich mit UND/ODER kombinieren, und mit NICHT kann eine Zeile negiert werden. Jedes Feld bietet die passenden Operatoren und Eingabefelder (z. B. Genehmigung, Einfachauswahl oder Text).';
$string['triggerspecificevent'] = 'Nur wenn folgendes Feld eine Bedingung erfüllt senden';
