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
 * German language strings for datalynxfield_itinerary.
 *
 * @package    datalynxfield_itinerary
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activeservices'] = 'Verwendete Kartendienste';
$string['addwaypoint'] = 'Station hinzufügen';
$string['default_zoom'] = 'Standard-Zoomstufe';
$string['display_both'] = 'Karte und Liste der Stationen';
$string['display_format'] = 'Anzeigeformat';
$string['display_list'] = 'Nur Liste der Stationen';
$string['display_map'] = 'Nur Karte';
$string['dropwaypoint'] = 'Diese Station entfernen';
$string['initialwaypoints'] = 'Zunächst angezeigte Stationen';
$string['itinerarysettings'] = 'Einstellungen des Routenfeldes';
$string['journeylength'] = 'Rund {$a} km';
$string['matchesroute'] = 'Route führt vorbei an';
$string['matchradius'] = 'Standard-Suchradius (km)';
$string['matchradius_help'] = 'Wie weit ein Zustiegs- oder Ausstiegsort von einer Route entfernt liegen darf und noch als Treffer gilt. Dient als Vorgabe im Suchformular; Suchende können den Wert ändern.';
$string['maxwaypoints'] = 'Maximale Anzahl an Stationen';
$string['maxwaypoints_help'] = 'Eine Route braucht mindestens zwei Stationen, um eine Fahrt zu beschreiben. Hier wird die Obergrenze festgelegt.';
$string['movedown'] = 'Diese Station nach hinten verschieben';
$string['moveup'] = 'Diese Station nach vorne verschieben';
$string['needtworoutepoints'] = 'Bitte mindestens einen Start und ein Ziel angeben.';
$string['nowaypoints'] = 'Keine Fahrt eingetragen';
$string['pluginname'] = 'Route (Fahrt mit Stationen)';
$string['precision'] = 'Ortsgenauigkeit für andere';
$string['precision_approximate'] = 'Ungefähr, bis ein Treffer bestätigt ist';
$string['precision_exact'] = 'Genau für alle, die den Eintrag sehen dürfen';
$string['precision_help'] = 'Bei „ungefähr“ sehen Suchende die Stationen auf etwa einen Kilometer gerundet und die genaue Position erst, wenn sie den Eintrag selbst verfasst haben oder bestätigte Mitfahrende sind. Der Abgleich erfolgt serverseitig in beiden Fällen mit den genauen Positionen.';
$string['privacy:metadata:datalynx_contents'] = 'Werte eines Routenfeldes, die zu einem Datalynx-Eintrag gespeichert werden.';
$string['privacy:metadata:datalynx_contents:waypoints'] = 'Die Stationen der Fahrt: jeweils ein Ortsname mit Koordinaten und optional eine Zeit. Sie beschreiben die Fahrt und nicht die reisende Person.';
$string['privacy:metadata:datalynx_waypoints'] = 'Eine abgeleitete Kopie der Routenkoordinaten, die nur dazu dient, Fahrten effizient miteinander abzugleichen. Sie enthält nichts, was nicht bereits im Eintrag steht, und wird daraus neu aufgebaut.';
$string['privacy:metadata:datalynx_waypoints:lat'] = 'Breitengrad einer Station.';
$string['privacy:metadata:datalynx_waypoints:lng'] = 'Längengrad einer Station.';
$string['privacy:metadata:datalynx_waypoints:timeplanned'] = 'Geplante Zeit an einer Station.';
$string['privacy:metadata:geocoder'] = 'Um einen Ortsnamen in Kartenkoordinaten umzuwandeln, wird der eingegebene Text an den für diese Website konfigurierten Geocoding-Dienst gesendet. Die Anfrage stellt die Website selbst; deine IP-Adresse wird diesem Dienst daher nicht offengelegt.';
$string['privacy:metadata:geocoder:address'] = 'Der für eine Station eingegebene Ortsname.';
$string['privacy:metadata:tileserver'] = 'Kartenbilder lädt dein Browser direkt vom für diese Website konfigurierten Grundkartendienst, der dadurch deine IP-Adresse erhält.';
$string['privacy:metadata:tileserver:ipaddress'] = 'Deine IP-Adresse, wie sie der Grundkartendienst sieht.';
$string['requiretimes'] = 'Zeit für jede Station verlangen';
$string['requiretimes_help'] = 'Zeiten ermöglichen es, Fahrten auszuschließen, die räumlich, aber nicht zeitlich zusammenpassen.';
$string['searchfrom'] = 'Fahrt von';
$string['searchradius'] = 'Im Umkreis von (km)';
$string['searchto'] = 'Fahrt nach';
$string['stopcount'] = '{$a->count} von höchstens {$a->max} Stationen';
$string['stopswithoutplace'] = 'Für {$a} Station(en) ist noch kein Ort gewählt; sie werden nicht gespeichert. Bitte einen Vorschlag auswählen oder in die Karte klicken.';
$string['timeatstop'] = 'Zeit';
$string['waypoint'] = 'Station {$a}';
$string['waypointaddress'] = 'Ort';
$string['waypointlimitreached'] = 'Diese Route hat bereits die maximale Anzahl von {$a} Stationen.';
