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
 * Moves the per-Lehrgang results of a tenant summary into the course result table.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\services;

use local_lehrgaengeapi\local\repository\course_result_repository;

/**
 * Moves the per-Lehrgang results of a tenant summary into the course result table.
 *
 * Used by the scheduled and the manual import, so the run log keeps only the totals.
 * @package local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class import_result_recorder {
    /** @var int Number of newest import runs whose per-Lehrgang results are kept. */
    public const KEEP_RUNS = 10;

    /** @var course_result_repository */
    private course_result_repository $results;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->results = new course_result_repository();
    }

    /**
     * Store the rows of one tenant summary and return the summary without them.
     *
     * @param int $runid Import run id.
     * @param array $tenantsummary Result of lehrgaenge_sync_service::sync() for one tenant.
     * @return array The summary as it goes into the run log.
     */
    public function record(int $runid, array $tenantsummary): array {
        $this->results->save_rows($runid, $tenantsummary['courseresults'] ?? []);
        unset($tenantsummary['courseresults']);
        return $tenantsummary;
    }

    /**
     * Remove the results of older import runs.
     *
     * @return int Number of deleted rows.
     */
    public function purge_old_runs(): int {
        return $this->results->purge_old_runs(self::KEEP_RUNS);
    }
}
