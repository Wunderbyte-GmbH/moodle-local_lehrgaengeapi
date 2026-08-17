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
                $service = factory::lehrgaenge_sync_service($apiendpoint['apikey']);
                $summary[$apiendpoint['abbr']] = $service->sync($apiendpoint);
            }
            mtrace('local_lehrgaengeapi: lehrgaenge sync summary: ' . json_encode($summary));
            $runs->mark_success($run->id, $summary);
        } catch (api_rate_limited_exception $e) {
            $retry = method_exists($e, 'get_retry_after_seconds') ? $e->get_retry_after_seconds() : null;
            mtrace('local_lehrgaengeapi: rate limited (429). Retry-After=' . ($retry ?? 'n/a'));
            $runs->mark_error($run->id, 'rate_limited: ' . $e->getMessage());
            // Let Moodle mark run as failed so faildelay kicks in.
            throw $e;
        } catch (api_unauthorized_exception $e) {
            mtrace('local_lehrgaengeapi: unauthorized (401). Check token setting.');
            $runs->mark_error($run->id, 'unauthorized: ' . $e->getMessage());
            throw $e;
        } catch (\Throwable $e) {
            mtrace('local_lehrgaengeapi: unexpected error: ' . $e->getMessage());
            $runs->mark_error($run->id, $e->getMessage());
            throw $e;
        } finally {
            $lock->release();
        }
    }
}
