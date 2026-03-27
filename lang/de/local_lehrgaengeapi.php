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
$string['baseurl'] = 'Basis-URL';
$string['baseurldesc'] = 'Basis-URL der externen API, z. B. https://api.example.com';
$string['certificatefile'] = 'Dateiname des Client-Zertifikats';
$string['certificatefiledesc'] = 'Dateiname des Client-Zertifikats für diesen Mandanten. Moodle prüft, ob die Datei im konfigurierten Zertifikatspfad vorhanden ist.';
$string['certificationpath'] = 'Zertifikatspfad';
$string['certificationpathdesc'] = 'Absoluter Pfad zu einem Verzeichnis mit Client-Zertifikats- und Schlüsseldateien. Moodle prüft, ob dieser Pfad existiert und ein Verzeichnis ist.';
$string['completion'] = 'Abschluss';
$string['intervallehrgaenge'] = 'Synchronisierungsintervall: Lehrgänge-Liste (Sekunden)';
$string['intervallehrgaengedesc'] = 'Legt fest, wie oft die geplante Aufgabe die Lehrgänge-Liste synchronisieren soll.';
$string['intervalteilnehmer'] = 'Synchronisierungsintervall: Teilnehmer (Sekunden)';
$string['intervalteilnehmerdesc'] = 'Legt fest, wie oft die geplante Aufgabe Teilnehmer für Lehrgänge synchronisieren soll.';
$string['keyfile'] = 'Dateiname des Client-Schlüssels';
$string['keyfiledesc'] = 'Dateiname des Client-Schlüssels für diesen Mandanten. Moodle prüft, ob die Datei im konfigurierten Zertifikatspfad vorhanden ist.';
$string['pluginname'] = 'Lehrgaenge API';
$string['requestdelayms'] = 'Verzögerung zwischen Teilnehmer-Anfragen (ms)';
$string['requestdelaymsdesc'] = 'Wartezeit in Millisekunden vor jeder /teilnehmer-API-Anfrage. Erhöhen Sie diesen Wert, wenn die externe API HTTP 429 (Rate-Limit) zurückgibt. Standard: 500 ms.';
$string['settingsheading'] = 'Einstellungen der externen API';
$string['targetcourseid'] = 'Zielkurs-ID für die Synchronisierung von Lehrgaengen';
$string['targetcourseiddesc'] = 'Zielkurs-ID für die Synchronisierung von Lehrgaengen. Dieser Kurs wird als Masterkurs verwendet.';
$string['tasksynclehrgaenge'] = 'Lehrgaenge synchronisieren (externe API)';
$string['tenantdescription'] = 'Für jeden Mandanten können Sie ein eigenes API-Token, ein Client-Zertifikat und einen Client-Schlüssel angeben.';
$string['tenantheading'] = 'Mandanten-Einstellungen';
$string['timeout'] = 'Zeitlimit für Anfragen (Sekunden)';
$string['timeoutdesc'] = 'HTTP-Zeitlimit in Sekunden für externe API-Aufrufe.';
$string['token'] = 'API-Token';
$string['tokendesc'] = 'Token zur Authentifizierung gegenüber der externen API. Wird in der Moodle-Konfiguration gespeichert.';
