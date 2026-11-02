<?php
// (Standard-GPL-Header)

/**
 * Result of a linking run: one row per user and Ausbildung, filterable and downloadable.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_lehrgaengeapi\form\link_results_filter_form;
use local_lehrgaengeapi\local\repository\import_run_repository;
use local_lehrgaengeapi\local\repository\link_result_repository;
use local_lehrgaengeapi\table\link_result_table;

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$runid = required_param('runid', PARAM_INT);
$filters = [
    'outcome' => optional_param('outcome', '', PARAM_ALPHANUMEXT),
    'tenant' => optional_param('tenant', '', PARAM_TEXT),
    'userid' => optional_param('userid', 0, PARAM_INT),
];
$download = optional_param('download', '', PARAM_ALPHA);

// Shown under the import history in the admin navigation.
admin_externalpage_setup('local_lehrgaengeapi_import_runs');

$pageurl = new moodle_url('/local/lehrgaengeapi/admin/link_results.php', ['runid' => $runid] + array_filter($filters));
$PAGE->set_url($pageurl);

$run = (new import_run_repository())->get($runid);
if (!$run || $run->runtype !== import_run_repository::RUNTYPE_LINK) {
    throw new moodle_exception('runnotfound', 'local_lehrgaengeapi');
}

$table = new link_result_table($runid, $filters);
$table->define_baseurl($pageurl);
$table->is_downloading($download, 'verknuepfung_' . $runid, get_string('linkresultspagename', 'local_lehrgaengeapi'));

if ($table->is_downloading()) {
    $table->out(500, false);
    exit;
}

$form = new link_results_filter_form($pageurl, null, 'get');
$form->set_data(['runid' => $runid] + $filters);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('linkresultsheading', 'local_lehrgaengeapi', $runid));

echo html_writer::link(
    new moodle_url('/local/lehrgaengeapi/admin/import_runs.php', ['id' => $runid]),
    get_string('backtooverview', 'local_lehrgaengeapi'),
    ['class' => 'btn btn-secondary mb-3']
);

// Counts per outcome as a quick overview, independent of the filters.
$counts = (new link_result_repository())->count_by_outcome($runid);
$overview = new html_table();
$overview->attributes['class'] = 'generaltable table-sm w-auto';
$overview->head = [get_string('coloutcome', 'local_lehrgaengeapi'), get_string('coltotal', 'local_lehrgaengeapi')];
foreach (link_result_table::outcome_options() as $outcome => $label) {
    if (!empty($counts[$outcome])) {
        $link = html_writer::link(new moodle_url($pageurl, ['outcome' => $outcome]), $label);
        $overview->data[] = [$link, $counts[$outcome]];
    }
}
if ($overview->data) {
    echo html_writer::table($overview);
}

$form->display();
$table->out(100, true);

echo $OUTPUT->footer();
