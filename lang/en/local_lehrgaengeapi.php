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
 * @copyright   2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
$string['apikey'] = 'API key';
$string['apikeydesc'] = 'Enter the API key for this mandant.';
$string['apirequestfailed'] = 'External API request failed.';
$string['baseurl'] = 'Base URL';
$string['baseurldesc'] = 'Base URL of the external API, e.g. https://api.example.com';
$string['intervallehrgaenge'] = 'Sync interval: Lehrgänge list (seconds)';
$string['intervallehrgaengedesc'] = 'How often the scheduled task should sync the Lehrgänge list.';
$string['intervalteilnehmer'] = 'Sync interval: Teilnehmer (seconds)';
$string['intervalteilnehmerdesc'] = 'How often the scheduled task should sync Teilnehmer for Lehrgänge.';
$string['pluginname'] = 'Lehrgaenge API';
$string['requestdelayms'] = 'Request delay between participant calls (ms)';
$string['requestdelaymsdesc'] = 'Milliseconds to wait before each /teilnehmer API request. Increase this value if the external API returns HTTP 429 (rate limited). Default: 500 ms.';
$string['settingsheading'] = 'External API settings';
$string['targetcourseid'] = 'Target course ID for syncing Lehrgaenge';
$string['targetcourseiddesc'] = 'Target course ID for syncing Lehrgaenge, which is used as master course.';
$string['tasksynclehrgaenge'] = 'Sync Lehrgaenge (external API)';
$string['tenantdescription'] = 'For each tenant you can specify a custom API token.';
$string['tenantheading'] = 'Tenant Settings';
$string['timeout'] = 'Request timeout (seconds)';
$string['timeoutdesc'] = 'HTTP request timeout in seconds for external API calls.';
$string['token'] = 'API token';
$string['tokendesc'] = 'Token used to authenticate against the external API. Stored in Moodle config.';
