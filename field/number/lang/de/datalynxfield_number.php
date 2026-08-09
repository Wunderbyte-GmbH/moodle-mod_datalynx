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
 * @package datalynxfield_number
 * @subpackage number
 * @copyright 2026 David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['decimals'] = 'Nachkommastellen';
$string['errorslidernovalues'] = 'Diese Konfiguration ergibt keine zwei auswählbaren Werte. Erweitern Sie den Bereich oder wählen Sie eine kleinere Schrittweite.';
$string['errorsliderrange'] = 'Der Endwert muss größer als der Startwert sein.';
$string['errorsliderstep'] = 'Die Schrittweite muss eine Zahl größer als 0 sein.';
$string['errorslidertoomanyvalues'] = 'Diese Konfiguration ergibt {$a} oder mehr Schiebereglerpositionen, das sind zu viele. Wählen Sie eine größere Schrittweite oder einen kleineren Bereich.';
$string['fieldformatdecimals'] = 'Nachkommastellen';
$string['fieldformatdecimals_help'] = 'Anzahl der Nachkommastellen, mit der die Zahl angezeigt wird. Leer lassen, um die Einstellung des Feldes selbst zu verwenden. Der Wert 1 zeigt beispielsweise 3,5 an, der Wert 0 zeigt 4 an.';
$string['fieldformatinputtype'] = 'Eingabe im Bearbeitungsmodus';
$string['fieldformatinputtype_help'] = 'Wie das Feld im Eintragsformular dargestellt wird.

* **Zahleneingabe** — das übliche Textfeld, unverändert.
* **Schieberegler** — ein Schieberegler, den die Nutzer/innen ziehen; der gewählte Wert wird in das Zahlenfeld geschrieben und wie gewohnt gespeichert.

Diese Einstellung wirkt sich nur auf den Bearbeitungsmodus aus. In Ansichten wird der Wert immer als Zahl ausgegeben, mit den oben eingestellten Nachkommastellen.';
$string['fieldformatinputtypeslider'] = 'Schieberegler';
$string['fieldformatinputtypetext'] = 'Zahleneingabe';
$string['fieldformatsliderend'] = 'Endwert';
$string['fieldformatsliderend_help'] = 'Der Wert am rechten Ende des Schiebereglers. Bei den Skalen Fibonacci und 1-2-5 ist er eine Obergrenze und nicht unbedingt selbst auswählbar: Die höchste Position ist der letzte Wert der Folge, der ihn nicht überschreitet. 0 bis 50 endet auf der Fibonacci-Skala daher bei 34.';
$string['fieldformatsliderscale'] = 'Skala';
$string['fieldformatsliderscale_help'] = 'Welche Werte zwischen Start- und Endwert am Schieberegler eingestellt werden können.

* **Linear** — gleichmäßige Schritte in der unten eingestellten Schrittweite: 0, 1, 2, 3, ...
* **Fibonacci** — der Startwert, gefolgt von den Fibonacci-Zahlen bis zum Endwert: 0, 1, 2, 3, 5, 8, 13, 21, 34
* **1-2-5-Reihe** — der Startwert, gefolgt von 1, 2, 5, 10, 20, 50, 100, ... bis zum Endwert

Die nichtlinearen Skalen eignen sich, wenn kleine Werte genau unterschieden werden sollen, große Werte aber nur grob geschätzt werden. Der Schieberegler rastet immer auf einem dieser Werte ein.';
$string['fieldformatsliderscalefibonacci'] = 'Fibonacci';
$string['fieldformatsliderscalelinear'] = 'Linear';
$string['fieldformatsliderscaleonetwofive'] = '1-2-5-Reihe';
$string['fieldformatsliderstart'] = 'Startwert';
$string['fieldformatsliderstart_help'] = 'Der Wert am linken Ende des Schiebereglers. Er ist auf jeder Skala auswählbar.';
$string['fieldformatsliderstep'] = 'Schrittweite';
$string['fieldformatsliderstep_help'] = 'Der Abstand zwischen zwei Positionen des Schiebereglers auf der linearen Skala, zum Beispiel 1, 5 oder 0,5. Die Skalen Fibonacci und 1-2-5 erzeugen ihre Schritte selbst und ignorieren diese Einstellung.';
$string['fieldformatsliderticks'] = 'Start- und Endwert anzeigen';
$string['fieldformatsliderticks_help'] = 'Zeigt Start- und Endwert unterhalb der Enden des Schiebereglers an, damit der verfügbare Bereich ohne Ziehen erkennbar ist.';
$string['fieldformatsliderunit'] = 'Einheit';
$string['fieldformatsliderunit_help'] = 'Text, der an die neben dem Schieberegler angezeigte Zahl angehängt wird, zum Beispiel " km" oder " %". Ein führendes Leerzeichen bitte mit eingeben. Die Einheit ist reine Anzeigehilfe — gespeichert wird weiterhin eine reine Zahl.';
$string['outputemptystring'] = 'Wenn das Feld leer bleibt: leere Zeichenkette statt 0 ausgeben.';
$string['pluginname'] = 'Zahl';
$string['privacy:metadata'] = 'Zahlenfelder speichern keine personenbezogenen Daten.';
