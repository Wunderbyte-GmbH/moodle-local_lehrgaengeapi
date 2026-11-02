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
 * Admin page: start linking the Ausbildungen to the _alt completion courses.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_lehrgaengeapi\task\link_ausbildungen_task;

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_lehrgaengeapi_link_ausbildungen');

$pageurl = new moodle_url('/local/lehrgaengeapi/admin/link_ausbildungen.php');
$returnurl = new moodle_url('/local/lehrgaengeapi/admin/import_runs.php');

if (optional_param('start', 0, PARAM_BOOL)) {
    require_sesskey();

    // Best-effort probe for immediate feedback; the task re-checks the lock when it runs.
    $lock = \core\lock\lock_config::get_lock_factory('local_lehrgaengeapi')->get_lock('sync_lehrgaenge', 0);
    if (!$lock) {
        redirect($pageurl, get_string('importbusy', 'local_lehrgaengeapi'), null, \core\output\notification::NOTIFY_WARNING);
    }
    $lock->release();

    $task = new link_ausbildungen_task();
    $task->set_custom_data(['userid' => $USER->id]);
    \core\task\manager::queue_adhoc_task($task);

    redirect($returnurl, get_string('linkstarted', 'local_lehrgaengeapi'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('linkpagename', 'local_lehrgaengeapi'));
echo $OUTPUT->notification(get_string('linkintro', 'local_lehrgaengeapi'), 'info');
echo $OUTPUT->single_button(
    new moodle_url($pageurl, ['start' => 1, 'sesskey' => sesskey()]),
    get_string('startlink', 'local_lehrgaengeapi'),
    'post',
    ['type' => single_button::BUTTON_PRIMARY]
);
echo $OUTPUT->footer();
