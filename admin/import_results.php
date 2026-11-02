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
 * Result of an import run: per Moodle course (with its Lehrgänge) or per Lehrgang, filterable and downloadable.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_lehrgaengeapi\form\import_results_filter_form;
use local_lehrgaengeapi\local\repository\import_run_repository;
use local_lehrgaengeapi\table\course_result_table;
use local_lehrgaengeapi\table\course_summary_table;

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$runid = required_param('runid', PARAM_INT);
$view = optional_param('view', 'course', PARAM_ALPHA) === 'lehrgang' ? 'lehrgang' : 'course';
$filters = [
    'tenant' => optional_param('tenant', '', PARAM_TEXT),
    'course' => optional_param('course', '', PARAM_TEXT),
    'externalid' => optional_param('externalid', '', PARAM_TEXT),
    'courseid' => optional_param('courseid', 0, PARAM_INT),
];
$download = optional_param('download', '', PARAM_ALPHA);

// Shown under the import history in the admin navigation.
admin_externalpage_setup('local_lehrgaengeapi_import_runs');

$pageurl = new moodle_url(
    '/local/lehrgaengeapi/admin/import_results.php',
    ['runid' => $runid, 'view' => $view] + array_filter($filters)
);
$PAGE->set_url($pageurl);

$run = (new import_run_repository())->get($runid);
if (!$run || $run->runtype !== import_run_repository::RUNTYPE_IMPORT) {
    throw new moodle_exception('runnotfound', 'local_lehrgaengeapi');
}

$table = $view === 'lehrgang' ? new course_result_table($runid, $filters) : new course_summary_table($runid, $filters);
$table->define_baseurl($pageurl);
$table->is_downloading($download, 'import_' . $runid . '_' . $view, get_string('importresultspagename', 'local_lehrgaengeapi'));

if ($table->is_downloading()) {
    $table->out(500, false);
    exit;
}

$form = new import_results_filter_form($pageurl, null, 'get');
$form->set_data(['runid' => $runid, 'view' => $view] + $filters);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importresultsheading', 'local_lehrgaengeapi', $runid));

echo html_writer::link(
    new moodle_url('/local/lehrgaengeapi/admin/import_runs.php', ['id' => $runid]),
    get_string('backtooverview', 'local_lehrgaengeapi'),
    ['class' => 'btn btn-secondary mb-3']
);

// Switching the view keeps the filters.
$tabs = [];
foreach (['course' => 'importresultsviewcourse', 'lehrgang' => 'importresultsviewlehrgang'] as $tab => $label) {
    $tabs[] = new tabobject($tab, new moodle_url($pageurl, ['view' => $tab]), get_string($label, 'local_lehrgaengeapi'));
}
echo $OUTPUT->tabtree($tabs, $view);

if ($filters['courseid'] > 0) {
    $withoutcourse = new moodle_url($pageurl);
    $withoutcourse->remove_params('courseid');
    echo $OUTPUT->notification(
        get_string('importresultscoursefilter', 'local_lehrgaengeapi', $filters['courseid']) . ' '
            . html_writer::link($withoutcourse, get_string('filterremove', 'local_lehrgaengeapi')),
        \core\output\notification::NOTIFY_INFO,
        false
    );
}

$form->display();
$table->out(100, true);

echo $OUTPUT->footer();
