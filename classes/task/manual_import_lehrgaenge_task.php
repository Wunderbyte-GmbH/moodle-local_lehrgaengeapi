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
 * Adhoc task: manual year-filtered Lehrgaenge import.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\task;

use local_lehrgaengeapi\factory;
use local_lehrgaengeapi\local\tenants\tenants;
use local_lehrgaengeapi\local\repository\import_run_repository;
use local_lehrgaengeapi\api\exceptions\api_exception;

/**
 * Adhoc task: manual year-filtered Lehrgaenge import, triggered from the admin UI.
 * @package local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manual_import_lehrgaenge_task extends \core\task\adhoc_task {
    /**
     * Get the task name shown in admin UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskmanualimportlehrgaenge', 'local_lehrgaengeapi');
    }

    /**
     * Execute the manual import.
     *
     * @return void
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        $year = isset($data->year) ? (int)$data->year : 0;
        $userid = isset($data->userid) ? (int)$data->userid : 0;

        $runs = new import_run_repository();
        $run = $runs->start($year, $userid ?: null);

        // Same lock as the scheduled task: mutually exclusive, but no retry here -
        // this is a one-shot, user-triggered action, not a recurring background job.
        $factory = \core\lock\lock_config::get_lock_factory('local_lehrgaengeapi');
        $lock = $factory->get_lock('sync_lehrgaenge', 0);

        if (!$lock) {
            mtrace('local_lehrgaengeapi: manual import ' . $year . ' skipped - another sync is running.');
            $runs->mark_skipped($run->id, 'Ein anderer Sync-Lauf war zum Startzeitpunkt aktiv.');
            $this->notify_user($userid, $year, import_run_repository::STATUS_SKIPPED);
            return;
        }

        $searchcriteria = [
            'lehrgangVon' => sprintf('%04d-01-01', $year),
            'lehrgangBis' => sprintf('%04d-12-31', $year),
        ];

        try {
            $allapiendpoints = tenants::get_all_with_keys();
            $summary = [];
            foreach ($allapiendpoints as $apiendpoint) {
                try {
                    $service = factory::lehrgaenge_sync_service($apiendpoint['apikey']);
                    $summary[$apiendpoint['abbr']] = $service->sync($apiendpoint, $searchcriteria);
                } catch (\Throwable $e) {
                    $tenantdetail = $e->getMessage();
                    if ($e instanceof api_exception && $e->get_response_body() !== '') {
                        $tenantdetail .= ' | Response: ' . $e->get_response_body();
                    }
                    mtrace('local_lehrgaengeapi: manual import ' . $year . ' - tenant '
                        . $apiendpoint['abbr'] . ' failed: ' . $tenantdetail);
                    $summary[$apiendpoint['abbr']] = [
                        'created' => 0,
                        'skipped' => 0,
                        'total' => 0,
                        'failed' => [],
                        'userreport' => [],
                        'error' => $tenantdetail,
                    ];
                }
                $runs->update_progress($run->id, $summary);
            }
            mtrace('local_lehrgaengeapi: manual import ' . $year . ' summary: ' . json_encode($summary));
            $runs->mark_success($run->id, $summary);
            $this->notify_user($userid, $year, import_run_repository::STATUS_SUCCESS);
        } catch (\Throwable $e) {
            $detail = $e->getMessage();
            if ($e instanceof api_exception && $e->get_response_body() !== '') {
                $detail .= ' | Response: ' . $e->get_response_body();
            }
            mtrace('local_lehrgaengeapi: manual import ' . $year . ' failed: ' . $detail);
            $runs->mark_error($run->id, $detail);
            $this->notify_user($userid, $year, import_run_repository::STATUS_ERROR);
            // Deliberately not rethrown: the user already gets a notification with the
            // outcome. Letting Moodle's adhoc task retry mechanism silently re-run a
            // failed one-shot import later would surprise them with an unrequested run.
        } finally {
            $lock->release();
        }
    }

    /**
     * Notify the triggering user that their manual import finished (or could not run).
     *
     * @param int $userid Moodle user.id, 0/negative if unknown.
     * @param int $year Year that was imported.
     * @param string $status One of import_run_repository::STATUS_*.
     * @return void
     */
    private function notify_user(int $userid, int $year, string $status): void {
        if ($userid <= 0) {
            return;
        }
        $userto = \core_user::get_user($userid);
        if (!$userto) {
            return;
        }

        $message = new \core\message\message();
        $message->component = 'local_lehrgaengeapi';
        $message->name = 'importcomplete';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $userto;
        $message->subject = get_string('manualimport' . $status . 'subject', 'local_lehrgaengeapi', $year);
        $body = get_string('manualimport' . $status . 'body', 'local_lehrgaengeapi', $year);
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = nl2br(s($body));
        $message->smallmessage = $body;
        $message->notification = 1;
        $message->contexturl = (new \moodle_url('/local/lehrgaengeapi/admin/import_runs.php'))->out(false);
        $message->contexturlname = get_string('importrunspagename', 'local_lehrgaengeapi');

        message_send($message);
    }
}
