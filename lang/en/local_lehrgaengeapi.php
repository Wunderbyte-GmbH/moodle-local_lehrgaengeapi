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
$string['apikey'] = 'API key';
$string['apikeydesc'] = 'Enter the API key for this tenant.';
$string['apirequestfailed'] = 'External API request failed.';
$string['backtooverview'] = 'Back to overview';
$string['baseurl'] = 'Base URL';
$string['baseurldesc'] = 'Base URL of the external API, e.g. https://api.example.com';
$string['certificatefile'] = 'Client certificate filename';
$string['certificatefiledesc'] = 'Filename of the client certificate for this tenant. Moodle checks whether the file exists under the configured certificate path.';
$string['certificationpath'] = 'Certificate path';
$string['certificationpathdesc'] = 'Absolute path to a directory containing client certificate and key files. Moodle checks whether this path exists and is a directory.';
$string['colalreadyenrolled'] = 'Already enrolled';
$string['colalreadyunenrolled'] = 'Already unenrolled';
$string['colassignmentskipped'] = 'Assignment skipped';
$string['colcompleted'] = 'Completed';
$string['colcourse'] = 'Course';
$string['colcreated'] = 'Created';
$string['colended'] = 'Ended';
$string['colenrolled'] = 'Enrolled';
$string['colfailed'] = 'Failed';
$string['colnoop'] = 'No action';
$string['colskipped'] = 'Skipped';
$string['colstarted'] = 'Started';
$string['colstatus'] = 'Status';
$string['colsummary'] = 'Result';
$string['coltenant'] = 'Tenant';
$string['coltotal'] = 'Total';
$string['coltriggeredby'] = 'Triggered by';
$string['colunenrolled'] = 'Unenrolled';
$string['colusercreated'] = 'New users';
$string['coluserexisting'] = 'Existing users';
$string['coluserskipped'] = 'Users skipped';
$string['colyear'] = 'Year';
$string['completion'] = 'Completed';
$string['failedentry'] = 'Lehrgang {$a->id}: {$a->error}';
$string['failedlehrgaenge'] = 'Lehrgaenge with a failed participant sync';
$string['importbusy'] = 'Another sync is already running. Please try again in a few minutes.';
$string['importrunspagename'] = 'Import history';
$string['importstarted'] = 'The import for year {$a} has been started. Depending on the amount of data this can take a few minutes - you will be notified once it has finished.';
$string['intervallehrgaenge'] = 'Sync interval: Lehrgänge list (seconds)';
$string['intervallehrgaengedesc'] = 'How often the scheduled task should sync the Lehrgänge list.';
$string['intervalteilnehmer'] = 'Sync interval: Teilnehmer (seconds)';
$string['intervalteilnehmerdesc'] = 'How often the scheduled task should sync Teilnehmer for Lehrgänge.';
$string['keyfile'] = 'Client key filename';
$string['keyfiledesc'] = 'Filename of the client key for this tenant. Moodle checks whether the file exists under the configured certificate path.';
$string['lockbusy'] = 'Another Lehrgaenge sync is already running. This run will be retried automatically via faildelay.';
$string['manualimporterrorbody'] = 'The manual import of Lehrgaenge for year {$a} was aborted due to an error. See the import history for details.';
$string['manualimporterrorsubject'] = 'Lehrgaenge import {$a} failed';
$string['manualimportintro'] = 'Select a year and start the import. The import runs in the background and can take several minutes depending on the amount of data. You will be notified once it has finished, and can find the result in the import history afterwards.';
$string['manualimportpagename'] = 'Manual Lehrgaenge import';
$string['manualimportskippedbody'] = 'The manual import of Lehrgaenge for year {$a} could not start because another sync was already running. Please try again.';
$string['manualimportskippedsubject'] = 'Lehrgaenge import {$a} could not start';
$string['manualimportsuccessbody'] = 'The manual import of Lehrgaenge for year {$a} has completed. You can find the results in the import history.';
$string['manualimportsuccesssubject'] = 'Lehrgaenge import {$a} completed';
$string['messageprovider:importcomplete'] = 'Manual Lehrgaenge import completion notification';
$string['norunsyet'] = 'No import runs have been logged yet.';
$string['participantdetails'] = 'Participant details per course';
$string['pluginname'] = 'Lehrgaenge API';
$string['rawsummary'] = 'Raw data (JSON)';
$string['requestdelayms'] = 'Request delay between participant calls (ms)';
$string['requestdelaymsdesc'] = 'Milliseconds to wait before each /teilnehmer API request. Increase this value if the external API returns HTTP 429 (rate limited). Default: 500 ms.';
$string['rundetailheading'] = 'Import run #{$a}';
$string['runnotfound'] = 'This import run could not be found.';
$string['settingsheading'] = 'External API settings';
$string['startimport'] = 'Start import';
$string['startnewimport'] = 'Start new import';
$string['statuserror'] = 'Error';
$string['statusrunning'] = 'Running';
$string['statusskipped'] = 'Skipped';
$string['statussuccess'] = 'Success';
$string['summarytotals'] = '{$a->created} created, {$a->skipped} skipped, {$a->failed} failed, {$a->total} total';
$string['targetcourseid'] = 'Target course ID for syncing Lehrgaenge';
$string['targetcourseiddesc'] = 'Target course ID for syncing Lehrgaenge. This course is used as the master course.';
$string['taskmanualimportlehrgaenge'] = 'Manual Lehrgaenge import (year)';
$string['tasksynclehrgaenge'] = 'Sync Lehrgaenge (external API)';
$string['tenantdescription'] = 'For each tenant you can specify a custom API token, client certificate file and client key file.';
$string['tenantheading'] = 'Tenant settings';
$string['tenantserrors'] = 'Fetching the Lehrgaenge list failed for the following tenants (skipped, remaining tenants were still processed):';
$string['timeout'] = 'Request timeout (seconds)';
$string['timeoutdesc'] = 'HTTP request timeout in seconds for external API calls.';
$string['token'] = 'API token';
$string['tokendesc'] = 'Token used to authenticate against the external API. Stored in Moodle config.';
$string['triggeredbysystem'] = 'System (automatic)';
$string['triggeredbyunknown'] = 'Unknown user';
$string['viewdetails'] = 'View details';
$string['yearautomatic'] = 'Automatic';
$string['yearfield'] = 'Year';
$string['yearfield_help'] = 'All Lehrgaenge dated between 1 January and 31 December of the selected year will be imported or updated, across all configured tenants.';
