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
 * German language strings for datalynxrule_ridematch.
 *
 * @package    datalynxrule_ridematch
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['itineraryfield'] = 'Routenfeld';
$string['itineraryfield_help'] = 'Das Routenfeld, in dem die Fahrt steht. Mitfahrangebote und Mitfahrgesuche müssen dasselbe Feld verwenden.';
$string['messagebody'] = 'Jemand in „{$a->datalynxname}“ fährt eine Route, die zu deiner passt.

Deine Fahrt: {$a->yourroute}
Die andere Fahrt: {$a->matchedroute}

Öffne den Eintrag, um Details zu sehen und Kontakt aufzunehmen: {$a->url}';
$string['messagebodyhtml'] = '<p>Jemand in <strong>{$a->datalynxname}</strong> fährt eine Route, die zu deiner passt.</p><ul><li>Deine Fahrt: {$a->yourroute}</li><li>Die andere Fahrt: {$a->matchedroute}</li></ul><p><a href="{$a->url}">Eintrag öffnen</a>, um Details zu sehen und Kontakt aufzunehmen.</p>';
$string['messageprovider:ridematch'] = 'Benachrichtigung, dass ein Mitfahrangebot und ein Mitfahrgesuch zusammenpassen';
$string['messagesubjectoffer'] = 'Jemand sucht eine Mitfahrgelegenheit entlang deiner Route';
$string['messagesubjectrequest'] = 'Jemand bietet eine Mitfahrgelegenheit entlang deiner Route';
$string['offervalue'] = 'Wert für „biete Mitfahrgelegenheit“';
$string['offervalue_help'] = 'Welche Option des Feldes für die Fahrtart „Ich biete eine Mitfahrgelegenheit“ bedeutet. Den Optionstext genau so eintragen, wie er in den Feldeinstellungen steht, z. B. „Biete Mitfahrgelegenheit“. Auswahl- und Optionsfelder speichern die Position einer Option und nicht ihren Text; daher wird auch die Position (1, 2, …) akzeptiert.';
$string['pluginname'] = 'Passende Mitfahrangebote und -gesuche melden';
$string['privacy:metadata:core_message'] = 'Die Regel sendet beiden Verfasser/innen eine Benachrichtigung, wenn ihre Fahrten zusammenpassen.';
$string['privacy:metadata:datalynx_ride_matches'] = 'Ein Vermerk darüber, welche Paare von Fahrt-Einträgen bereits gemeldet wurden, damit niemand zweimal über denselben Treffer benachrichtigt wird.';
$string['privacy:metadata:datalynx_ride_matches:offerentryid'] = 'Der Eintrag, der eine Mitfahrgelegenheit anbietet.';
$string['privacy:metadata:datalynx_ride_matches:requestentryid'] = 'Der Eintrag, der eine Mitfahrgelegenheit sucht.';
$string['privacy:metadata:datalynx_ride_matches:timenotified'] = 'Wann die beiden Verfasser/innen über einander informiert wurden.';
$string['radiuskmlabel'] = 'Suchradius (km)';
$string['radiuskmlabel_help'] = 'Wie weit ein Start- oder Zielort von einer Route entfernt liegen darf und noch als Treffer gilt. Leer lassen, um den Standardwert des Routenfeldes zu verwenden.';
$string['requestvalue'] = 'Wert für „suche Mitfahrgelegenheit“';
$string['requestvalue_help'] = 'Welche Option des Feldes für die Fahrtart „Ich suche eine Mitfahrgelegenheit“ bedeutet. Den Optionstext genau so eintragen, wie er in den Feldeinstellungen steht, oder dessen Position (1, 2, …).';
$string['ridetypefield'] = 'Feld für die Fahrtart';
$string['ridetypefield_help'] = 'Das Auswahl- oder Optionsfeld, das angibt, ob ein Eintrag eine Mitfahrgelegenheit anbietet oder sucht. Die Regel durchsucht immer die jeweils andere Art.';
$string['routesummary'] = '{$a->from} nach {$a->to}';
$string['ruleintro'] = 'Wird ein Fahrt-Eintrag gespeichert, sucht diese Regel Einträge der jeweils anderen Art mit überschneidender Route und benachrichtigt beide Verfasser/innen über jeden neuen Treffer. Ein Paar wird nur einmal gemeldet; das Bearbeiten eines Eintrags benachrichtigt dieselben Personen also nicht erneut. Der Abgleich läuft im Hintergrund, die Benachrichtigungen kommen daher mit dem nächsten Cron-Lauf und nicht sofort.';
$string['taskfindmatches'] = 'Passende Mitfahrangebote und -gesuche suchen';
$string['timetolerance'] = 'Zeittoleranz für die Abfahrt (Stunden)';
$string['timetolerance_help'] = 'Wie weit zwei Abfahrtszeiten auseinanderliegen dürfen und noch als dieselbe Fahrt gelten. Einträge ohne Zeitangabe werden nie aufgrund der Zeit ausgeschlossen.';
$string['valuesmustdiffer'] = 'Die Werte für Angebot und Gesuch müssen unterschiedlich sein, sonst würde jeder Eintrag auf seine eigene Art passen.';
$string['viewmatchedride'] = 'Passende Fahrt ansehen';
