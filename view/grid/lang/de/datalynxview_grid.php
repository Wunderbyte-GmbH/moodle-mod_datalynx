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
 * German language strings for Datalynx Grid view.
 *
 * @package    datalynxview_grid
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['cols'] = 'Anzahl Spalten';
$string['customwrapperclass'] = 'Benutzerdefinierte Eintrags-Wrapper-CSS-Klassen';
$string['customwrapperclass_help'] = 'Geben Sie benutzerdefinierte CSS-Klassen durch Leerzeichen getrennt ein.';
$string['entrywrapper'] = 'Eintrags-Wrapper';
$string['entrywrapper_help'] = 'Wählen Sie, wie der Wrapper um jeden einzelnen Eintrag gestaltet werden soll. Bei Verwendung eines Wrappers umschließt datalynx automatisch das ##entries##-Tag im Ansichts-Template (normalerweise in einem Bootstrap-Row-Container) und umschließt jeden einzelnen Eintrag im Eintrags-Template mit den gewählten CSS-Klassen. Sie müssen diese umschließenden HTML-Tags nicht manuell in Ihren Templates hinzufügen.';
$string['gridsettings'] = 'Grid-Einstellungen';
$string['infoboxentrywrapper'] = 'Eintrags-Template-Wrapper (um jeden Eintrag)';
$string['infoboxheader'] = 'Angewendete Grid-Wrapper';
$string['infoboxintro'] = 'Basierend auf Ihrer Auswahl umschließt Moodle die Templates automatisch mit den folgenden HTML-Elementen:';
$string['infoboxlegacyviewdesc'] = 'Es wird kein grid-spezifischer Zeilen-Wrapper hinzugefügt; verwendet den Standard-Container.';
$string['infoboxnowrapper'] = 'Kein Wrapper angewendet';
$string['infoboxnowrapperdesc'] = 'Der rohe Template-Inhalt wird ohne umschließendes HTML-Tag gerendert.';
$string['infoboxviewwrapper'] = 'Ansichts-Template-Wrapper (um ##entries##)';
$string['pluginname'] = 'Grid';
$string['privacy:metadata'] = 'Die Grid-Ansicht speichert keine personenbezogenen Daten.';
$string['rows'] = 'Ausgerichtete Zeilen';

$string['wrapperbootstrapcol12'] = '1 Spalte pro Zeile (col-12)';
$string['wrapperbootstrapcol3'] = '4 Spalten pro Zeile (col-12 col-md-6 col-lg-3)';
$string['wrapperbootstrapcol4'] = '3 Spalten pro Zeile (col-12 col-md-6 col-lg-4)';
$string['wrapperbootstrapcol6'] = '2 Spalten pro Zeile (col-12 col-md-6)';
$string['wrapperbootstraprowcols'] = 'Bootstrap Grid Spalte (Empfohlen, passt sich den Spalten der übergeordneten Zeile an)';
$string['wrappercustom'] = 'Benutzerdefinierte CSS-Klassen';
$string['wrapperlegacy'] = 'Standard-Wrapper (entry)';
$string['wrappernone'] = 'Kein Wrapper-Tag (Umschließendes HTML-Element weglassen)';
