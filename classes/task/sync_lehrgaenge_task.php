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
 * Scheduled task: sync lehrgaenge.
 *
 * @package   local_lehrgaengeapi
 * @author    Jacob Viertel
 * @copyright   2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\task;

use local_lehrgaengeapi\factory;
use local_lehrgaengeapi\local\tenants\tenants;
use local_lehrgaengeapi\local\repository\import_run_repository;
use local_lehrgaengeapi\api\exceptions\api_rate_limited_exception;
use local_lehrgaengeapi\api\exceptions\api_unauthorized_exception;
use local_lehrgaengeapi\api\exceptions\api_exception;
use local_lehrgaengeapi\local\notification\import_notifier;

/**
 * Scheduled task: sync lehrgaenge.
 * @package local_lehrgaengeapi
 * @author Jacob Viertel
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sync_lehrgaenge_task extends \core\task\scheduled_task {
    /**
     * Get the task name shown in admin UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('tasksynclehrgaenge', 'local_lehrgaengeapi');
    }

    /**
     * Execute the scheduled task.
     *
     * @return void
     */
    public function execute(): void {
        // Prevent overlapping runs.
        $factory = \core\lock\lock_config::get_lock_factory('local_lehrgaengeapi');
        $lock = $factory->get_lock('sync_lehrgaenge', 0);

        if (!$lock) {
            // A manual import (or another run of this task) currently holds the lock.
            // Throw so Moodle applies faildelay and retries automatically instead of
            // silently skipping until the next scheduled slot.
            mtrace('local_lehrgaengeapi: sync_lehrgaenge already running - will retry via faildelay.');
            throw new \moodle_exception('lockbusy', 'local_lehrgaengeapi');
        }

        $runs = new import_run_repository();
        $run = $runs->start(null, null);

        try {
            // Foreach here.
            $allapiendpoints = tenants::get_all_with_keys();
            $summary = [];
            foreach ($allapiendpoints as $apiendpoint) {
                try {
                    $service = factory::lehrgaenge_sync_service($apiendpoint['apikey']);
                    $summary[$apiendpoint['abbr']] = $service->sync($apiendpoint);
                } catch (api_rate_limited_exception | api_unauthorized_exception $e) {
                    // Not tenant-specific: retrying the remaining tenants would just hammer
                    // the API. Abort so the outer handler records it and faildelay applies.
                    throw $e;
                } catch (\Throwable $e) {
                    // A single tenant failing (e.g. HTTP 400 for an organisation the remote
                    // side does not know) must not abort the remaining tenants - the failure
                    // is recorded in the summary and the run continues.
                    $tenantdetail = $e->getMessage();
                    if ($e instanceof api_exception && $e->get_response_body() !== '') {
                        $tenantdetail .= ' | Response: ' . $e->get_response_body();
                    }
                    mtrace('local_lehrgaengeapi: tenant ' . $apiendpoint['abbr']
                        . ' failed: ' . $tenantdetail);
                    $summary[$apiendpoint['abbr']] = [
                        'created' => 0,
                        'skipped' => 0,
                        'total' => 0,
                        'failed' => [],
                        'userreport' => [],
                        'unfoundhlfscourses' => [],
                        'error' => $tenantdetail,
                    ];
                }
                $runs->update_progress($run->id, $summary);
            }
            mtrace('local_lehrgaengeapi: lehrgaenge sync summary: ' . json_encode($summary));
            $runs->mark_success($run->id, $summary);
            import_notifier::notify_configured_users(import_run_repository::STATUS_SUCCESS);
        } catch (api_rate_limited_exception $e) {
            $retry = method_exists($e, 'get_retry_after_seconds') ? $e->get_retry_after_seconds() : null;
            mtrace('local_lehrgaengeapi: rate limited (429). Retry-After=' . ($retry ?? 'n/a'));
            $detail = 'rate_limited: ' . $e->getMessage();
            if ($e->get_response_body() !== '') {
                $detail .= ' | Response: ' . $e->get_response_body();
            }
            $runs->mark_error($run->id, $detail);
            import_notifier::notify_configured_users(import_run_repository::STATUS_ERROR);
            // Let Moodle mark run as failed so faildelay kicks in.
            throw $e;
        } catch (api_unauthorized_exception $e) {
            mtrace('local_lehrgaengeapi: unauthorized (401). Check token setting.');
            $detail = 'unauthorized: ' . $e->getMessage();
            if ($e->get_response_body() !== '') {
                $detail .= ' | Response: ' . $e->get_response_body();
            }
            $runs->mark_error($run->id, $detail);
            import_notifier::notify_configured_users(import_run_repository::STATUS_ERROR);
            throw $e;
        } catch (api_exception $e) {
            mtrace('local_lehrgaengeapi: unexpected error: ' . $e->getMessage());
            $detail = $e->getMessage();
            if ($e->get_response_body() !== '') {
                $detail .= ' | Response: ' . $e->get_response_body();
            }
            $runs->mark_error($run->id, $detail);
            import_notifier::notify_configured_users(import_run_repository::STATUS_ERROR);
            throw $e;
        } catch (\Throwable $e) {
            mtrace('local_lehrgaengeapi: unexpected error: ' . $e->getMessage());
            $runs->mark_error($run->id, $e->getMessage());
            import_notifier::notify_configured_users(import_run_repository::STATUS_ERROR);
            throw $e;
        } finally {
            $lock->release();
        }
    }
}
