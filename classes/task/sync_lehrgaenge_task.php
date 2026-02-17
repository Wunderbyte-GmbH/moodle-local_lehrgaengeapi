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
            mtrace('local_lehrgaengeapi: sync_lehrgaenge already running - skipping.');
            return;
        }

        try {
            $service = factory::lehrgaenge_sync_service();
            $summary = $service->sync();
            mtrace('local_lehrgaengeapi: lehrgaenge sync summary: ' . json_encode($summary));
        } catch (api_rate_limited_exception $e) {
            $retry = method_exists($e, 'get_retry_after_seconds') ? $e->get_retry_after_seconds() : null;
            mtrace('local_lehrgaengeapi: rate limited (429). Retry-After=' . ($retry ?? 'n/a'));
            // Let Moodle mark run as failed so faildelay kicks in.
            throw $e;
        } catch (api_unauthorized_exception $e) {
            mtrace('local_lehrgaengeapi: unauthorized (401). Check token setting.');
            throw $e;
        } catch (\Throwable $e) {
            mtrace('local_lehrgaengeapi: unexpected error: ' . $e->getMessage());
            throw $e;
        } finally {
            $lock->release();
        }
    }
}
