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
 * @package datalynxrule
 * @subpackage ftpsyncfiles
 * @copyright 2023 Thomas Winkler
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['event'] = 'Datalynx Ereignis';
$string['pluginname'] = 'FTP Sync Data';
$string['triggerspecificevent'] = 'Nur bei markierter Checkbox senden';

$string['sftpsettings'] = 'SFTP Settings';
$string['sftpserver'] = 'STFP Server';
$string['sftpusername'] = 'STFP Username';
$string['sftppassword'] = 'STFP Passwort';
$string['sftppath'] = 'STFP Path';
$string['sftpport'] = 'STFP Port';

$string['matchfields'] = 'Importierte Daten entsprechend den Formularfeldern zuordnen';
$string['identifier'] = 'Im Dateinamen verwendetes Identifikationsmerkmal, um die Datei dem richtigen User zuzuordnen';
$string['teammemberfield'] = 'Formularfeld, das verwendet wird um den User zu bestimmen, dem die Datei zugeordnet werden soll';
$string['manager'] = 'Der User, der die Einträge verwaltet';
$string['filefield'] = 'Feld in dem die Datei gespeichert werden soll';
$string['regex'] = 'Regulärer Ausdruck, der verwendet wird, um die Kennung aus dem Dateinamen zu extrahieren.
Wenn leer gelassen, wird standardmäßig /^(\d+)_/ verwendet.';
