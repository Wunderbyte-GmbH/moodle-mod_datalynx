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
 * Strings for the schedule field type.
 *
 * @package    datalynxfield_schedule
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['allowedmodes'] = 'Erlaubte Muster';
$string['anytime'] = 'egal wann';
$string['arrival'] = 'Ankunft';
$string['askarrival'] = 'Auch nach der Ankunftszeit fragen';
$string['askarrival_help'] = 'Fügt ein zweites Zeitfeld hinzu. Die Ankunft wird angezeigt, aber nie für den Abgleich oder die Suche verwendet – dafür zählt nur die Abfahrt.';
$string['departure'] = 'Abfahrt';
$string['errnodate'] = 'Wählen Sie das Datum aus, an dem das stattfindet.';
$string['errnodays'] = 'Wählen Sie mindestens einen Wochentag aus.';
$string['errnoschedule'] = 'Geben Sie an, wann das stattfindet.';
$string['erruntilbeforefrom'] = 'Das Ende des Zeitraums liegt vor seinem Beginn.';
$string['everyweek'] = 'Jeden {$a}';
$string['granularity'] = 'Zeitraster (Minuten)';
$string['granularity_help'] = 'Wie weit die angebotenen Uhrzeiten auseinanderliegen. Eine halbe Stunde passt zu einer Mitfahrbörse, fünf Minuten zu einem Fahrplan.';
$string['hideexpired'] = 'Abgelaufene Termine ausblenden';
$string['hideexpired_help'] = 'Lässt Einträge, deren Zeitraum vorbei ist, aus der Suche und aus dem Abgleich heraus. Auf einer Plattform mit Archiv ausschalten.';
$string['matchtolerance'] = 'Gilt als dieselbe Fahrt innerhalb von';
$string['matchtolerance_help'] = 'Wie weit zwei Abfahrtszeiten auseinanderliegen dürfen und trotzdem als dieselbe Fahrt gelten. Der ganze Tag ist bewusst voreingestellt: Es geht darum, zwei Menschen zusammenzubringen, die gemeinsam fahren könnten – die genaue Uhrzeit klären sie selbst. Nur dort enger stellen, wo es tatsächlich mehrere Abfahrten pro Tag gibt.';
$string['mode'] = 'Wie oft';
$string['modeboth'] = 'Einmalig und regelmäßig';
$string['modeonce'] = 'Einmalig';
$string['modeweekly'] = 'Jede Woche';
$string['ondate'] = 'Am';
$string['ondays'] = 'An diesen Tagen';
$string['pluginname'] = 'Termin';
$string['privacy:metadata'] = 'Das Termin-Feld speichert keine personenbezogenen Daten.';
$string['runson'] = 'Findet statt am';
$string['searchdate'] = 'Unterwegs am';
$string['searchoverlaps'] = 'zur selben Zeit wie dieser Eintrag';
$string['searchtime'] = 'gegen';
$string['searchtolerance'] = 'plus/minus';
$string['toleranceday'] = 'irgendwann an dem Tag';
$string['tolerancehours'] = '{$a} Std.';
$string['toleranceminutes'] = '{$a} Min.';
$string['untildate'] = 'bis {$a}';
$string['validfrom'] = 'Gilt ab';
$string['validuntil'] = 'Gilt bis';
