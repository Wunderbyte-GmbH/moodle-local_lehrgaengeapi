<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     local_lehrgaengeapi
 * @category    string
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
$string['apikey'] = 'API-Schlüssel';
$string['apikeydesc'] = 'Geben Sie den API-Schlüssel für diesen Mandanten ein.';
$string['apirequestfailed'] = 'Externe API-Anfrage ist fehlgeschlagen.';
$string['backtooverview'] = 'Zurück zur Übersicht';
$string['baseurl'] = 'Basis-URL';
$string['baseurldesc'] = 'Basis-URL der externen API, z. B. https://api.example.com';
$string['certificatefile'] = 'Dateiname des Client-Zertifikats';
$string['certificatefiledesc'] = 'Dateiname des Client-Zertifikats für diesen Mandanten. Moodle prüft, ob die Datei im konfigurierten Zertifikatspfad vorhanden ist.';
$string['certificationpath'] = 'Zertifikatspfad';
$string['certificationpathdesc'] = 'Absoluter Pfad zu einem Verzeichnis mit Client-Zertifikats- und Schlüsseldateien. Moodle prüft, ob dieser Pfad existiert und ein Verzeichnis ist.';
$string['colcreated'] = 'Erstellt';
$string['colended'] = 'Beendet am';
$string['colfailed'] = 'Fehlgeschlagen';
$string['colskipped'] = 'Übersprungen';
$string['colstarted'] = 'Gestartet am';
$string['colstatus'] = 'Status';
$string['colsummary'] = 'Ergebnis';
$string['coltenant'] = 'Mandant';
$string['coltotal'] = 'Gesamt';
$string['coltriggeredby'] = 'Ausgelöst von';
$string['colyear'] = 'Jahr';
$string['completion'] = 'Abschluss';
$string['failedentry'] = 'Lehrgang {$a->id}: {$a->error}';
$string['failedlehrgaenge'] = 'Lehrgänge mit fehlgeschlagenem Teilnehmer-Sync';
$string['importbusy'] = 'Es läuft bereits ein anderer Sync. Bitte versuche es in ein paar Minuten erneut.';
$string['importrunspagename'] = 'Import-Historie';
$string['importstarted'] = 'Import für das Jahr {$a} wurde gestartet. Das kann je nach Datenmenge einige Minuten dauern — du erhältst eine Benachrichtigung, sobald er fertig ist.';
$string['intervallehrgaenge'] = 'Synchronisierungsintervall: Lehrgänge-Liste (Sekunden)';
$string['intervallehrgaengedesc'] = 'Legt fest, wie oft die geplante Aufgabe die Lehrgänge-Liste synchronisieren soll.';
$string['intervalteilnehmer'] = 'Synchronisierungsintervall: Teilnehmer (Sekunden)';
$string['intervalteilnehmerdesc'] = 'Legt fest, wie oft die geplante Aufgabe Teilnehmer für Lehrgänge synchronisieren soll.';
$string['keyfile'] = 'Dateiname des Client-Schlüssels';
$string['keyfiledesc'] = 'Dateiname des Client-Schlüssels für diesen Mandanten. Moodle prüft, ob die Datei im konfigurierten Zertifikatspfad vorhanden ist.';
$string['lockbusy'] = 'Ein anderer Lehrgaenge-Sync läuft bereits. Dieser Lauf wird automatisch per faildelay wiederholt.';
$string['manualimporterrorbody'] = 'Der manuelle Import der Lehrgaenge für das Jahr {$a} ist mit einem Fehler abgebrochen. Details findest du in der Import-Historie.';
$string['manualimporterrorsubject'] = 'Lehrgaenge-Import {$a} fehlgeschlagen';
$string['manualimportintro'] = 'Wähle ein Jahr aus und starte den Import. Der Import läuft im Hintergrund und kann je nach Datenmenge mehrere Minuten dauern. Du wirst benachrichtigt, sobald er abgeschlossen ist, und findest das Ergebnis danach in der Import-Historie.';
$string['manualimportpagename'] = 'Manueller Lehrgaenge-Import';
$string['manualimportskippedbody'] = 'Der manuelle Import der Lehrgaenge für das Jahr {$a} konnte nicht starten, da zu diesem Zeitpunkt bereits ein anderer Sync lief. Bitte versuche es erneut.';
$string['manualimportskippedsubject'] = 'Lehrgaenge-Import {$a} konnte nicht gestartet werden';
$string['manualimportsuccessbody'] = 'Der manuelle Import der Lehrgaenge für das Jahr {$a} ist abgeschlossen. Die Ergebnisse findest du in der Import-Historie.';
$string['manualimportsuccesssubject'] = 'Lehrgaenge-Import {$a} abgeschlossen';
$string['messageprovider:importcomplete'] = 'Benachrichtigung über abgeschlossenen manuellen Lehrgaenge-Import';
$string['norunsyet'] = 'Es wurden noch keine Import-Läufe protokolliert.';
$string['pluginname'] = 'Lehrgaenge API';
$string['rawsummary'] = 'Rohdaten (JSON)';
$string['requestdelayms'] = 'Verzögerung zwischen Teilnehmer-Anfragen (ms)';
$string['requestdelaymsdesc'] = 'Wartezeit in Millisekunden vor jeder /teilnehmer-API-Anfrage. Erhöhen Sie diesen Wert, wenn die externe API HTTP 429 (Rate-Limit) zurückgibt. Standard: 500 ms.';
$string['rundetailheading'] = 'Import-Lauf #{$a}';
$string['runnotfound'] = 'Dieser Import-Lauf wurde nicht gefunden.';
$string['settingsheading'] = 'Einstellungen der externen API';
$string['startimport'] = 'Import starten';
$string['startnewimport'] = 'Neuen Import starten';
$string['statuserror'] = 'Fehler';
$string['statusrunning'] = 'Läuft';
$string['statusskipped'] = 'Übersprungen';
$string['statussuccess'] = 'Erfolgreich';
$string['summarytotals'] = '{$a->created} erstellt, {$a->skipped} übersprungen, {$a->failed} fehlgeschlagen, {$a->total} gesamt';
$string['targetcourseid'] = 'Zielkurs-ID für die Synchronisierung von Lehrgaengen';
$string['targetcourseiddesc'] = 'Zielkurs-ID für die Synchronisierung von Lehrgaengen. Dieser Kurs wird als Masterkurs verwendet.';
$string['taskmanualimportlehrgaenge'] = 'Manueller Lehrgaenge-Import (Jahr)';
$string['tasksynclehrgaenge'] = 'Lehrgaenge synchronisieren (externe API)';
$string['tenantdescription'] = 'Für jeden Mandanten können Sie ein eigenes API-Token, ein Client-Zertifikat und einen Client-Schlüssel angeben.';
$string['tenantheading'] = 'Mandanten-Einstellungen';
$string['timeout'] = 'Zeitlimit für Anfragen (Sekunden)';
$string['timeoutdesc'] = 'HTTP-Zeitlimit in Sekunden für externe API-Aufrufe.';
$string['token'] = 'API-Token';
$string['tokendesc'] = 'Token zur Authentifizierung gegenüber der externen API. Wird in der Moodle-Konfiguration gespeichert.';
$string['triggeredbysystem'] = 'System (automatisch)';
$string['triggeredbyunknown'] = 'Unbekannter Benutzer';
$string['viewdetails'] = 'Details ansehen';
$string['yearautomatic'] = 'Automatisch';
$string['yearfield'] = 'Jahr';
$string['yearfield_help'] = 'Es werden alle Lehrgaenge mit Datum vom 1. Januar bis 31. Dezember des ausgewählten Jahres importiert bzw. aktualisiert, über alle konfigurierten Mandanten hinweg.';
