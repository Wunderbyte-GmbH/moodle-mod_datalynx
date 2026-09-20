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
 * German strings for datalynxview_pdf.
 *
 * @package datalynxview_pdf
 * @copyright 2026 onwards The datalynx contributors
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['A4'] = 'A4';
$string['LETTER'] = 'LETTER';
$string['auto'] = 'Automatisch';
$string['certification'] = 'Zertifizierung';
$string['certinfocontact'] = 'Info: Kontakt';
$string['certinfoloc'] = 'Info: Ort';
$string['certinfoname'] = 'Info: Name';
$string['certinforeason'] = 'Info: Grund';
$string['certpassword'] = 'Kennwort';
$string['certperm2'] = 'Formulare ausfüllen, Seitenvorlagen instanziieren, signieren';
$string['certperm3'] = 'Formulare ausfüllen, Seitenvorlagen instanziieren, signieren, Anmerkungen verwalten';
$string['certtype'] = 'Berechtigungen';
$string['destd'] = 'Als Download senden';
$string['destf'] = 'Als Datei auf dem Server speichern';
$string['destfd'] = 'Als Datei auf dem Server speichern, an den Browser senden und Download erzwingen';
$string['destfi'] = 'Als Datei auf dem Server speichern und an den Browser senden';
$string['desti'] = 'An den Browser senden';
$string['destination'] = 'Ziel';
$string['docname'] = 'Dokumentname';
$string['docname_help'] = 'Ein Muster für den Namen des heruntergeladenen PDF-Dokuments. Das Muster kann aus beliebigen Feld-Tags bestehen, die in der Ansicht enthalten sind.';
$string['enabled'] = 'Aktiviert';
$string['format'] = 'Format';
$string['image'] = 'Bild';
$string['imagetypes'] = 'jpg|jpeg|gif|png|tif';
$string['landscape'] = 'Querformat';
$string['margin'] = 'Rand';
$string['marginkeep'] = 'Beibehalten';
$string['marginleft'] = 'Links';
$string['marginright'] = 'Rechts';
$string['margintop'] = 'Oben';
$string['orientation'] = 'Ausrichtung';
$string['pagebreak'] = 'Seitenumbruch';
$string['pdffooter'] = 'PDF-Fußzeile';
$string['pdfframe'] = 'PDF-Rahmen';
$string['pdfframe_help'] = 'PDF-Rahmen';
$string['pdfframefirstpageonly'] = 'Rahmen nur auf der ersten Seite verwenden';
$string['pdfheader'] = 'PDF-Kopfzeile';
$string['pdfmargins'] = 'PDF-Ränder und Seitenumbruch';
$string['pdfprotection'] = 'PDF-Schutz';
$string['pdfsettings'] = 'PDF-Einstellungen';
$string['pdfsignature'] = 'Digitale Signatur';
$string['pdftoc'] = 'PDF-Inhaltsverzeichnis';
$string['pdfwmark'] = 'PDF-Wasserzeichen';
$string['pdfwmark_help'] = 'PDF-Wasserzeichen';
$string['permannot-forms'] = 'Anmerkungen und Formulare verwalten';
$string['permassemble'] = 'Zusammenstellen';
$string['permcopy'] = 'Inhalt kopieren/extrahieren';
$string['permextract'] = 'Inhalt extrahieren';
$string['permfill-forms'] = 'Formulare ausfüllen';
$string['permmodify'] = 'Inhalt ändern';
$string['permowner'] = 'Schutz verwalten (umgekehrte Logik – nur für Public-Key)';
$string['permprint'] = 'Dokument drucken';
$string['permprint-high'] = 'In hoher Qualität drucken';
$string['pluginname'] = 'PDF';
$string['portrait'] = 'Hochformat';
$string['privacy:metadata'] = 'Die PDF-Ansicht speichert keine personenbezogenen Daten.';
$string['protmode'] = 'Modus';
$string['protmode0'] = 'RSA 40 Bit';
$string['protmode1'] = 'RSA 128 Bit';
$string['protmode2'] = 'AES 128 Bit';
$string['protmode3'] = 'AES 256 Bit';
$string['protownerpass'] = 'Eigentümerkennwort';
$string['protperms'] = 'Verweigerte Berechtigungen';
$string['protuserpass'] = 'Benutzerkennwort';
$string['tocname'] = 'Name des Inhaltsverzeichnisses';
$string['tocname_help'] = 'Name des Inhaltsverzeichnisses';
$string['tocpage'] = 'Seite des Inhaltsverzeichnisses';
$string['tocpage_help'] = 'Seitenzahl (z. B. 1). Die Seite, auf der das Inhaltsverzeichnis angezeigt werden soll.
Standardmäßig (Seitenzahl leer oder 0) wird kein Inhaltsverzeichnis angezeigt.
<p>Lesezeichen können im wiederholten Eintragsbereich mit Tags in folgendem Format gesetzt werden: #@Lesezeichentyp:Lesezeichenebene:Lesezeichentext@#</p>
<p><b>Lesezeichentyp:</b> PDF-GBM für Gruppenlesezeichen, PDF-BM für normale Lesezeichen. Gruppenlesezeichen zeigen in einer Folge
gleicher Lesezeichentexte nur das erste Vorkommen an.<br />
<b>Lesezeichenebene:</b> 0 - n. Wird die HTML-Vorlage für das Inhaltsverzeichnis verwendet,
muss sie für jede im Inhalt verwendete Lesezeichenebene eine Vorlagendefinition enthalten.<br />
<b>Lesezeichentext:</b> Der Text, der für das Lesezeichen im Inhaltsverzeichnis angezeigt wird.
Mit Feld-Tags kann der Text aus dem Inhalt des Eintrags übernommen werden.</p>
<p>Beispiel:</p>
<p>#@PDF-GBM:0:##author:name##@#<br />
#@PDF-BM:1:[[Date masked]]@#</p>
';
$string['toctitle'] = 'Titel des Inhaltsverzeichnisses';
$string['toctitle_help'] = 'HTML für den Titel, der über dem Inhaltsverzeichnis angezeigt wird.
Zum Beispiel zeigt &lt;h1&gt;INHALTSVERZEICHNIS&lt;/h1&gt; die Seite des Inhaltsverzeichnisses mit dem Titel \'INHALTSVERZEICHNIS\'
als Überschrift 1 an.';
$string['toctmpl'] = 'Vorlage für das Inhaltsverzeichnis';
$string['toctmpl_help'] = 'Die Vorlage legt das HTML fest, mit dem die Lesezeichen im Inhaltsverzeichnis dargestellt werden.
#TOC_DESCRIPTION# und #TOC_PAGE_NUMBER# müssen in span-Tags stehen.
<p>Zum Beispiel:</p>
<p>&lt;div&gt;&lt;span style="color:#FF0000;"&gt;#TOC_DESCRIPTION#&lt;/span&gt; ... &lt;span&gt;#TOC_PAGE_NUMBER#&lt;/span&gt;&lt;/div&gt;<br />
&lt;div&gt;&lt;span style="color:#00FF00;"&gt;#TOC_DESCRIPTION#&lt;/span&gt; ... &lt;span&gt;#TOC_PAGE_NUMBER#&lt;/span&gt;&lt;/div&gt;</p>
<p>Mit dieser einfachen Vorlage zeigt die PDF-Datei das Inhaltsverzeichnis mit der ersten Lesezeichenebene in Rot und der zweiten in Grün an.
Die Seitenzahlen werden durch Auslassungspunkte von den Lesezeichentexten getrennt dargestellt.</p>
<p>Leer lassen, um ein Standard-Inhaltsverzeichnis ohne HTML anzuzeigen.</p>';
$string['transparency'] = 'Transparenz des Wasserzeichens';
$string['transparency_help'] = 'Transparenz des Wasserzeichens';
$string['unit'] = 'Einheit';
$string['unitcm'] = 'Zentimeter';
$string['unitin'] = 'Zoll';
$string['unitmm'] = 'Millimeter';
$string['unitpt'] = 'Punkt';
