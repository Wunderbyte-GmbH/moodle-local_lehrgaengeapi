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
 * History of manual and automatic Lehrgaenge import/sync runs.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_lehrgaengeapi\local\repository\import_run_repository;

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_lehrgaengeapi_import_runs');

$id = optional_param('id', 0, PARAM_INT);
$runs = new import_run_repository();

$run = $id ? $runs->get($id) : null;
$recent = $id ? [] : $runs->get_recent(100);

// Auto-refresh while something is still running, so progress is visible without
// manual reloads - no JS needed, this is Moodle's own periodic meta-refresh.
if ($run && $run->status === import_run_repository::STATUS_RUNNING) {
    $PAGE->set_periodic_refresh_delay(5);
} else if (!$id && array_filter($recent, fn($r) => $r->status === import_run_repository::STATUS_RUNNING)) {
    $PAGE->set_periodic_refresh_delay(10);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importrunspagename', 'local_lehrgaengeapi'));

echo html_writer::link(
    new moodle_url('/local/lehrgaengeapi/admin/manual_import.php'),
    get_string('startnewimport', 'local_lehrgaengeapi'),
    ['class' => 'btn btn-primary mb-3']
);

if ($id) {
    if (!$run) {
        echo $OUTPUT->notification(get_string('runnotfound', 'local_lehrgaengeapi'), 'error');
        echo $OUTPUT->footer();
        exit;
    }

    echo $OUTPUT->heading(get_string('rundetailheading', 'local_lehrgaengeapi', $run->id), 3);

    $meta = new html_table();
    $meta->attributes['class'] = 'generaltable';
    $meta->data = [
        [get_string('colyear', 'local_lehrgaengeapi'), $run->year ?: get_string('yearautomatic', 'local_lehrgaengeapi')],
        [get_string('colstatus', 'local_lehrgaengeapi'), local_lehrgaengeapi_status_badge($run->status)],
        [get_string('coltriggeredby', 'local_lehrgaengeapi'), local_lehrgaengeapi_triggeredby_name($run->triggeredby)],
        [get_string('colstarted', 'local_lehrgaengeapi'), userdate($run->timestarted)],
        [get_string('colended', 'local_lehrgaengeapi'), $run->timeended ? userdate($run->timeended) : '-'],
    ];
    echo html_writer::table($meta);

    $decoded = import_run_repository::decode_summary($run);

    if (in_array($run->status, [import_run_repository::STATUS_SUCCESS, import_run_repository::STATUS_RUNNING], true)) {
        $tenanttable = new html_table();
        $tenanttable->attributes['class'] = 'generaltable';
        $tenanttable->head = [
            get_string('coltenant', 'local_lehrgaengeapi'),
            get_string('colcreated', 'local_lehrgaengeapi'),
            get_string('colskipped', 'local_lehrgaengeapi'),
            get_string('colfailed', 'local_lehrgaengeapi'),
            get_string('coltotal', 'local_lehrgaengeapi'),
        ];
        $failedentries = [];
        foreach ($decoded as $tenantabbr => $tenantresult) {
            $tenantfailed = $tenantresult['failed'] ?? [];
            $tenanttable->data[] = [
                s($tenantabbr),
                (int)($tenantresult['created'] ?? 0),
                (int)($tenantresult['skipped'] ?? 0),
                count($tenantfailed),
                (int)($tenantresult['total'] ?? 0),
            ];
            foreach ($tenantfailed as $failure) {
                $failedentries[] = html_writer::tag('li', s($tenantabbr) . ' – ' . get_string('failedentry', 'local_lehrgaengeapi', (object)[
                    'id' => $failure['id'] ?? '?',
                    'error' => $failure['error'] ?? '',
                ]));
            }
        }
        echo html_writer::table($tenanttable);
        if ($failedentries) {
            echo $OUTPUT->heading(get_string('failedlehrgaenge', 'local_lehrgaengeapi'), 4);
            echo html_writer::tag('ul', implode('', $failedentries));
        }
        $tenanterrors = [];
        foreach ($decoded as $tenantabbr => $tenantresult) {
            if (!empty($tenantresult['error'])) {
                $tenanterrors[] = html_writer::tag('li', s($tenantabbr) . ' – ' . s($tenantresult['error']));
            }
        }
        if ($tenanterrors) {
            echo $OUTPUT->notification(get_string('tenantserrors', 'local_lehrgaengeapi'), 'warning');
            echo html_writer::tag('ul', implode('', $tenanterrors));
        }

        $participanttable = new html_table();
        $participanttable->attributes['class'] = 'generaltable';
        $participanttable->head = [
            get_string('coltenant', 'local_lehrgaengeapi'),
            get_string('colcourse', 'local_lehrgaengeapi'),
            get_string('colusercreated', 'local_lehrgaengeapi'),
            get_string('coluserexisting', 'local_lehrgaengeapi'),
            get_string('coluserskipped', 'local_lehrgaengeapi'),
            get_string('colenrolled', 'local_lehrgaengeapi'),
            get_string('colalreadyenrolled', 'local_lehrgaengeapi'),
            get_string('colunenrolled', 'local_lehrgaengeapi'),
            get_string('colalreadyunenrolled', 'local_lehrgaengeapi'),
            get_string('colnoop', 'local_lehrgaengeapi'),
            get_string('colcompleted', 'local_lehrgaengeapi'),
            get_string('colassignmentskipped', 'local_lehrgaengeapi'),
        ];
        foreach ($decoded as $tenantabbr => $tenantresult) {
            foreach (($tenantresult['userreport'] ?? []) as $courseid => $report) {
                $users = $report['users'] ?? [];
                $assignments = $report['assignments'] ?? [];
                $participanttable->data[] = [
                    s($tenantabbr),
                    html_writer::link(
                        new moodle_url('/course/view.php', ['id' => (int)$courseid]),
                        (string)($report['florixid'] ?? $courseid)
                    ),
                    (int)($users['created'] ?? 0),
                    (int)($users['existing'] ?? 0),
                    (int)($users['skipped'] ?? 0),
                    (int)($assignments['enrolled'] ?? 0),
                    (int)($assignments['alreadyenrolled'] ?? 0),
                    (int)($assignments['unenrolled'] ?? 0),
                    (int)($assignments['alreadyunenrolled'] ?? 0),
                    (int)($assignments['noop'] ?? 0),
                    (int)($assignments['completed'] ?? 0),
                    (int)($assignments['skipped'] ?? 0),
                ];
            }
        }
        if ($participanttable->data) {
            echo html_writer::tag(
                'details',
                html_writer::tag('summary', get_string('participantdetails', 'local_lehrgaengeapi'))
                . html_writer::tag('div', html_writer::table($participanttable), ['style' => 'max-height: 400px; overflow-y: auto;']),
                ['class' => 'mt-3']
            );
        }
    } else if ($run->status === import_run_repository::STATUS_ERROR) {
        echo $OUTPUT->notification(s($decoded['error'] ?? ''), 'error');
    } else if ($run->status === import_run_repository::STATUS_SKIPPED) {
        echo $OUTPUT->notification(s($decoded['reason'] ?? ''), 'warning');
    }

    echo html_writer::tag('details', html_writer::tag('summary', get_string('rawsummary', 'local_lehrgaengeapi'))
        . html_writer::tag('pre', s($run->summary ?? '')), ['class' => 'mt-3']);

    echo html_writer::link(
        new moodle_url('/local/lehrgaengeapi/admin/import_runs.php'),
        get_string('backtooverview', 'local_lehrgaengeapi'),
        ['class' => 'btn btn-secondary mt-3']
    );
} else {
    if (!$recent) {
        echo $OUTPUT->notification(get_string('norunsyet', 'local_lehrgaengeapi'), 'info');
        echo $OUTPUT->footer();
        exit;
    }

    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [
        get_string('colyear', 'local_lehrgaengeapi'),
        get_string('colstatus', 'local_lehrgaengeapi'),
        get_string('coltriggeredby', 'local_lehrgaengeapi'),
        get_string('colstarted', 'local_lehrgaengeapi'),
        get_string('colended', 'local_lehrgaengeapi'),
        get_string('colsummary', 'local_lehrgaengeapi'),
        '',
    ];

    foreach ($recent as $run) {
        $table->data[] = [
            $run->year ?: get_string('yearautomatic', 'local_lehrgaengeapi'),
            local_lehrgaengeapi_status_badge($run->status),
            local_lehrgaengeapi_triggeredby_name($run->triggeredby),
            userdate($run->timestarted),
            $run->timeended ? userdate($run->timeended) : '-',
            local_lehrgaengeapi_summarise_run($run),
            html_writer::link(
                new moodle_url('/local/lehrgaengeapi/admin/import_runs.php', ['id' => $run->id]),
                get_string('viewdetails', 'local_lehrgaengeapi')
            ),
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();

/**
 * Render a small status badge.
 *
 * @param string $status One of import_run_repository::STATUS_*.
 * @return string
 */
function local_lehrgaengeapi_status_badge(string $status): string {
    $classmap = [
        import_run_repository::STATUS_RUNNING => 'badge bg-info',
        import_run_repository::STATUS_SUCCESS => 'badge bg-success',
        import_run_repository::STATUS_ERROR => 'badge bg-danger',
        import_run_repository::STATUS_SKIPPED => 'badge bg-warning text-dark',
    ];
    $class = $classmap[$status] ?? 'badge bg-secondary';
    return html_writer::tag('span', get_string('status' . $status, 'local_lehrgaengeapi'), ['class' => $class]);
}

/**
 * Resolve a display name for the user who triggered a run.
 *
 * @param int|null $userid Moodle user.id, null/0 for automatic/cron runs.
 * @return string
 */
function local_lehrgaengeapi_triggeredby_name(?int $userid): string {
    static $cache = [];

    if (empty($userid)) {
        return get_string('triggeredbysystem', 'local_lehrgaengeapi');
    }
    if (!isset($cache[$userid])) {
        $namefields = implode(',', \core_user\fields::for_name()->get_required_fields());
        $user = \core_user::get_user($userid, 'id, ' . $namefields);
        $cache[$userid] = $user ? fullname($user) : get_string('triggeredbyunknown', 'local_lehrgaengeapi');
    }
    return $cache[$userid];
}

/**
 * Build a one-line summary for a run row.
 *
 * @param stdClass $run Run row.
 * @return string
 */
function local_lehrgaengeapi_summarise_run(stdClass $run): string {
    $decoded = import_run_repository::decode_summary($run);

    if (in_array($run->status, [import_run_repository::STATUS_SUCCESS, import_run_repository::STATUS_RUNNING], true)) {
        $totals = ['created' => 0, 'skipped' => 0, 'failed' => 0, 'total' => 0];
        foreach ($decoded as $tenantresult) {
            $totals['created'] += (int)($tenantresult['created'] ?? 0);
            $totals['skipped'] += (int)($tenantresult['skipped'] ?? 0);
            $totals['failed'] += count($tenantresult['failed'] ?? []);
            $totals['total'] += (int)($tenantresult['total'] ?? 0);
        }
        return get_string('summarytotals', 'local_lehrgaengeapi', (object)$totals);
    }
    if ($run->status === import_run_repository::STATUS_ERROR) {
        return s($decoded['error'] ?? '');
    }
    if ($run->status === import_run_repository::STATUS_SKIPPED) {
        return s($decoded['reason'] ?? '');
    }
    return '';
}
