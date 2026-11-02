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
$string['autoimporterrorbody'] = 'The automatic import of Lehrgaenge was aborted due to an error. See the import history for details.';
$string['autoimporterrorsubject'] = 'Automatic Lehrgaenge import failed';
$string['autoimportsuccessbody'] = 'The automatic import of Lehrgaenge has completed. You can find the results in the import history.';
$string['autoimportsuccesssubject'] = 'Automatic Lehrgaenge import completed';
$string['backtooverview'] = 'Back to overview';
$string['baseurl'] = 'Base URL';
$string['baseurldesc'] = 'Base URL of the external API, e.g. https://api.example.com';
$string['certificatefile'] = 'Client certificate filename';
$string['certificatefiledesc'] = 'Filename of the client certificate for this tenant. Moodle checks whether the file exists under the configured certificate path.';
$string['certificationpath'] = 'Certificate path';
$string['certificationpathdesc'] = 'Absolute path to a directory containing client certificate and key files. Moodle checks whether this path exists and is a directory.';
$string['colalreadyenrolled'] = 'Already enrolled';
$string['colalreadyunenrolled'] = 'Already unenrolled';
$string['colapistatus'] = 'API status';
$string['colassignmentskipped'] = 'Assignment skipped';
$string['colausbildungenupdated'] = 'Trainings updated';
$string['colbezeichnung'] = 'Designation';
$string['colbis'] = 'To';
$string['colcompleted'] = 'Completed';
$string['colcourse'] = 'Course';
$string['colcreated'] = 'Created';
$string['colended'] = 'Ended';
$string['colenrolled'] = 'Enrolled';
$string['colexternalid'] = 'Florix ID';
$string['colfailed'] = 'Failed';
$string['colkurzbezeichnung'] = 'Short designation';
$string['collectioncourse'] = 'Collection Lehrgang (no Moodle course)';
$string['collectionkurzbezeichnung'] = 'Short name of the Florix collection Lehrgang';
$string['collectionkurzbezeichnungdesc'] = 'Florix keeps people without a real Lehrgang (e.g. last Lehrgang before 2000) in a collection Lehrgang with this short name. For this Lehrgang the import creates no Moodle course and enrols nobody; it only takes over the people, their Ausbildungen and the tenant. Case does not matter. Leave empty to switch the recognition off.';
$string['collehrgaenge'] = 'Number of Lehrgänge';
$string['collehrgaengelist'] = 'Lehrgänge (tenant: short name (Florix id))';
$string['colmessage'] = 'Message';
$string['colnoop'] = 'No action';
$string['colort'] = 'Location';
$string['coloutcome'] = 'Outcome';
$string['colparticipantid'] = 'Participant id (Florix)';
$string['colruntype'] = 'Type';
$string['colshortname'] = 'Intended shortname';
$string['colskipped'] = 'Skipped';
$string['colsourceshortname'] = 'Original shortname';
$string['colstarted'] = 'Started';
$string['colstatus'] = 'Status';
$string['colsummary'] = 'Result';
$string['coltenant'] = 'Tenant';
$string['coltotal'] = 'Total';
$string['coltriggeredby'] = 'Triggered by';
$string['colunenrolled'] = 'Unenrolled';
$string['coluser'] = 'Moodle user';
$string['colusercreated'] = 'New users';
$string['coluserexisting'] = 'Existing users';
$string['coluserid'] = 'User ID';
$string['coluserskipped'] = 'Users skipped';
$string['colvon'] = 'From';
$string['colyear'] = 'Year';
$string['completion'] = 'Completed';
$string['failedentry'] = 'Lehrgang {$a->id}: {$a->error}';
$string['failedlehrgaenge'] = 'Lehrgaenge with a failed participant sync';
$string['filterall'] = 'All';
$string['filtercourse'] = 'Course shortname contains';
$string['filterexternalid'] = 'Florix id';
$string['filteroutcome'] = 'Outcome';
$string['filterremove'] = 'Remove filter';
$string['filtertenant'] = 'Tenant';
$string['filtertenantnone'] = 'Without tenant';
$string['filteruserid'] = 'User ID';
$string['importbusy'] = 'Another sync is already running. Please try again in a few minutes.';
$string['importresultscoursefilter'] = 'Only Lehrgänge of the course with id {$a}';
$string['importresultsheading'] = 'Import result of run {$a}';
$string['importresultslink'] = 'Result per course and Lehrgang';
$string['importresultspagename'] = 'Import result';
$string['importresultsviewcourse'] = 'Per Moodle course';
$string['importresultsviewlehrgang'] = 'Per Lehrgang';
$string['importrunspagename'] = 'Import history';
$string['importstarted'] = 'The import for year {$a} has been started. Depending on the amount of data this can take a few minutes - you will be notified once it has finished.';
$string['intervallehrgaenge'] = 'Sync interval: Lehrgänge list (seconds)';
$string['intervallehrgaengedesc'] = 'How often the scheduled task should sync the Lehrgänge list.';
$string['intervalteilnehmer'] = 'Sync interval: Teilnehmer (seconds)';
$string['intervalteilnehmerdesc'] = 'How often the scheduled task should sync Teilnehmer for Lehrgänge.';
$string['keyfile'] = 'Client key filename';
$string['keyfiledesc'] = 'Filename of the client key for this tenant. Moodle checks whether the file exists under the configured certificate path.';
$string['linkerrornomanualenrol'] = 'The course has no manual enrolment, the user could not be enrolled.';
$string['linkerrornomapping'] = 'There is no Florix id mapping for this user, the user could not be enrolled.';
$string['linkfailedusers'] = 'Linking stopped unexpectedly for the following users (the others were processed):';
$string['linkintro'] = 'Enrols every user into the matching completion course (_alt) based on their completed Ausbildungen (status BESTANDEN or TEILGENOMMEN) and marks the course as completed. The user\'s own tenants are searched first, then HLFS. Ausbildungen without a matching course are skipped. The run happens in the background; you are notified when it has finished and find the result per user and Ausbildung in the import history.';
$string['linkoutcome_already'] = 'Already linked';
$string['linkoutcome_error'] = 'Error';
$string['linkoutcome_linked_hlfs'] = 'Linked (HLFS)';
$string['linkoutcome_linked_own'] = 'Linked (own tenant)';
$string['linkoutcome_notcompleted'] = 'Not completed';
$string['linkoutcome_notfound'] = 'Course not found';
$string['linkoutcome_notype'] = 'No course type';
$string['linkoutcome_statusopen'] = 'Status open';
$string['linkoutcomecounts'] = 'Outcomes';
$string['linkpagename'] = 'Link Ausbildungen';
$string['linkresultsheading'] = 'Linking #{$a} – result per Ausbildung';
$string['linkresultspagename'] = 'Linking result';
$string['linkrunerrorbody'] = 'Linking the Ausbildungen was aborted due to an error. See the import history for details.';
$string['linkrunerrorsubject'] = 'Linking of Ausbildungen failed';
$string['linkrunskippedbody'] = 'Linking the Ausbildungen could not start because an import was running at that time. Please try again.';
$string['linkrunskippedsubject'] = 'Linking of Ausbildungen could not start';
$string['linkrunsuccessbody'] = 'Linking the Ausbildungen to the completion courses has completed. The result per user and Ausbildung is in the import history.';
$string['linkrunsuccesssubject'] = 'Linking of Ausbildungen completed';
$string['linkstarted'] = 'Linking the Ausbildungen has been started. You will be notified once it has finished.';
$string['linksummarytotals'] = '{$a->users} users, {$a->linked} linked, {$a->notfound} course not found, {$a->errors} errors';
$string['linkusers'] = 'Users processed';
$string['lockbusy'] = 'Another Lehrgaenge sync is already running. This run will be retried automatically via faildelay.';
$string['manualimporterrorbody'] = 'The manual import of Lehrgaenge for year {$a} was aborted due to an error. See the import history for details.';
$string['manualimporterrorsubject'] = 'Lehrgaenge import {$a} failed';
$string['manualimportintro'] = 'Select a year and start the import. The import runs in the background and can take several minutes depending on the amount of data. You will be notified once it has finished, and can find the result in the import history afterwards.';
$string['manualimportpagename'] = 'Manual Lehrgaenge import';
$string['manualimportskippedbody'] = 'The manual import of Lehrgaenge for year {$a} could not start because another sync was already running. Please try again.';
$string['manualimportskippedsubject'] = 'Lehrgaenge import {$a} could not start';
$string['manualimportsuccessbody'] = 'The manual import of Lehrgaenge for year {$a} has completed. You can find the results in the import history.';
$string['manualimportsuccesssubject'] = 'Lehrgaenge import {$a} completed';
$string['messageprovider:importcomplete'] = 'Lehrgaenge import completion notification (manual and automatic)';
$string['norunsyet'] = 'No import runs have been logged yet.';
$string['notifyusers'] = 'Notify on automatic import';
$string['notifyusersdesc'] = 'These users are notified when the automatic Lehrgaenge import has completed or aborted with an error. Users can be searched by name or email address, multiple selection is possible. The link in the notification opens the import history, which is only accessible with the permission to change the site configuration.';
$string['outcome_alreadyenrolled'] = 'Already enrolled';
$string['outcome_alreadyunenrolled'] = 'Already unenrolled (was not enrolled)';
$string['outcome_completed'] = ' + completed';
$string['outcome_enrolled'] = 'Enrolled';
$string['outcome_noop'] = 'No action (status without a rule)';
$string['outcome_skipped'] = 'Skipped';
$string['outcome_skipped_noid'] = 'Skipped: no participant id';
$string['outcome_skipped_nouser'] = 'Skipped: no Moodle user';
$string['outcome_unenrolled'] = 'Unenrolled';
$string['participantdetails'] = 'Participant details per course';
$string['participantoutcomes'] = 'Participants per course and Lehrgang (action)';
$string['pluginname'] = 'Lehrgaenge API';
$string['rawsummary'] = 'Raw data (JSON)';
$string['requestdelayms'] = 'Request delay between participant calls (ms)';
$string['requestdelaymsdesc'] = 'Milliseconds to wait before each /teilnehmer API request. Increase this value if the external API returns HTTP 429 (rate limited). Default: 500 ms.';
$string['rundetailheading'] = 'Import run #{$a}';
$string['runnotfound'] = 'This import run could not be found.';
$string['runtypeimport'] = 'Import';
$string['runtypelink'] = 'Linking';
$string['settingsheading'] = 'External API settings';
$string['startimport'] = 'Start import';
$string['startlink'] = 'Start linking';
$string['startnewimport'] = 'Start new import';
$string['statuserror'] = 'Error';
$string['statusrunning'] = 'Running';
$string['statusskipped'] = 'Skipped';
$string['statussuccess'] = 'Success';
$string['summarytotals'] = '{$a->created} created, {$a->skipped} skipped, {$a->failed} failed, {$a->total} total';
$string['targetcourseid'] = 'Target course ID for syncing Lehrgaenge';
$string['targetcourseiddesc'] = 'Target course ID for syncing Lehrgaenge. This course is used as the master course.';
$string['tasklinkausbildungen'] = 'Link Ausbildungen to completion courses';
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
$string['unfoundhlfscourses'] = 'HLFS Lehrgaenge without a course';
$string['unfoundhlfscoursesinfo'] = '{$a} HLFS Lehrgaenge could not be created because no template course, no previous-year course and no valid master course (target course id) was found, or a course with the intended shortname already exists.';
$string['viewdetails'] = 'View details';
$string['viewlinkresults'] = 'View result per Ausbildung';
$string['yearautomatic'] = 'Automatic';
$string['yearfield'] = 'Year';
$string['yearfield_help'] = 'All Lehrgaenge dated between 1 January and 31 December of the selected year will be imported or updated, across all configured tenants.';
