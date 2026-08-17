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
 * Trigger a manual year-filtered Lehrgaenge import.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_lehrgaengeapi\form\manual_import_form;
use local_lehrgaengeapi\task\manual_import_lehrgaenge_task;

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_lehrgaengeapi_manual_import');

$returnurl = new moodle_url('/local/lehrgaengeapi/admin/import_runs.php');

$form = new manual_import_form();

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    $year = (int)$data->year;

    // Best-effort probe only: the adhoc task re-checks the lock for real when it
    // actually runs (adhoc tasks are picked up by the next cron pass, not instantly),
    // so this can't fully prevent a race - it just gives immediate UI feedback for the
    // common case where a conflict is already visible right now.
    $lockfactory = \core\lock\lock_config::get_lock_factory('local_lehrgaengeapi');
    $lock = $lockfactory->get_lock('sync_lehrgaenge', 0);

    if ($lock) {
        $lock->release();

        $task = new manual_import_lehrgaenge_task();
        $task->set_custom_data(['year' => $year, 'userid' => $USER->id]);
        \core\task\manager::queue_adhoc_task($task);

        redirect(
            $returnurl,
            get_string('importstarted', 'local_lehrgaengeapi', $year),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        redirect(
            new moodle_url('/local/lehrgaengeapi/admin/manual_import.php'),
            get_string('importbusy', 'local_lehrgaengeapi'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manualimportpagename', 'local_lehrgaengeapi'));
echo $OUTPUT->notification(get_string('manualimportintro', 'local_lehrgaengeapi'), 'info');
$form->display();
echo $OUTPUT->footer();
