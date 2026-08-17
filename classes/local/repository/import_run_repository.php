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
 * Repository for import run log entries.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\repository;

use stdClass;

/**
 * Repository for import run log entries (manual and automatic Lehrgaenge syncs).
 * @package local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class import_run_repository {
    /** @var string DB table name */
    private const TABLE = 'local_lehrgaengeapi_import_run';

    /** @var string Run is currently in progress */
    public const STATUS_RUNNING = 'running';

    /** @var string Run finished without errors */
    public const STATUS_SUCCESS = 'success';

    /** @var string Run finished with an unrecoverable error */
    public const STATUS_ERROR = 'error';

    /** @var string Run did not execute because another run held the lock */
    public const STATUS_SKIPPED = 'skipped';

    /**
     * Start a new run and persist it as "running".
     *
     * @param int|null $year Year filter, null for an automatic/unfiltered run.
     * @param int|null $triggeredby Moodle user.id who triggered a manual run, null for cron.
     * @return stdClass Created run row.
     */
    public function start(?int $year, ?int $triggeredby): stdClass {
        global $DB;

        $record = (object)[
            'year' => $year,
            'status' => self::STATUS_RUNNING,
            'triggeredby' => $triggeredby,
            'timestarted' => time(),
            'timeended' => null,
            'summary' => null,
        ];

        $record->id = $DB->insert_record(self::TABLE, $record);
        return $DB->get_record(self::TABLE, ['id' => $record->id], '*', MUST_EXIST);
    }

    /**
     * Mark a run as successfully finished.
     *
     * @param int $runid Run id returned by start().
     * @param array $summary Per-tenant summary, will be JSON-encoded.
     * @return void
     */
    public function mark_success(int $runid, array $summary): void {
        $this->finish($runid, self::STATUS_SUCCESS, $summary);
    }

    /**
     * Mark a run as failed.
     *
     * @param int $runid Run id returned by start().
     * @param string $errormessage Error description.
     * @return void
     */
    public function mark_error(int $runid, string $errormessage): void {
        $this->finish($runid, self::STATUS_ERROR, ['error' => $errormessage]);
    }

    /**
     * Mark a run as skipped because another run held the lock.
     *
     * @param int $runid Run id returned by start().
     * @param string $reason Human-readable reason.
     * @return void
     */
    public function mark_skipped(int $runid, string $reason): void {
        $this->finish($runid, self::STATUS_SKIPPED, ['reason' => $reason]);
    }

    /**
     * Update the summary of a still-running run without changing its status or end time.
     *
     * Used to surface progress (e.g. per-tenant results so far) while a run is in
     * progress, so the admin UI can show something other than a static "running" row.
     *
     * @param int $runid Run id.
     * @param array $summary Partial per-tenant summary collected so far, will be JSON-encoded.
     * @return void
     */
    public function update_progress(int $runid, array $summary): void {
        global $DB;

        $DB->update_record(self::TABLE, (object)[
            'id' => $runid,
            'summary' => json_encode($summary, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Persist the final status, end time and summary for a run.
     *
     * @param int $runid Run id.
     * @param string $status One of the STATUS_* constants.
     * @param array $summary Will be JSON-encoded into the summary column.
     * @return void
     */
    private function finish(int $runid, string $status, array $summary): void {
        global $DB;

        $DB->update_record(self::TABLE, (object)[
            'id' => $runid,
            'status' => $status,
            'timeended' => time(),
            'summary' => json_encode($summary, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Get a single run by id.
     *
     * @param int $runid Run id.
     * @return stdClass|null
     */
    public function get(int $runid): ?stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $runid]) ?: null;
    }

    /**
     * Get the most recent runs, newest first.
     *
     * @param int $limit Maximum number of rows.
     * @return stdClass[]
     */
    public function get_recent(int $limit = 50): array {
        global $DB;
        return $DB->get_records(self::TABLE, null, 'timestarted DESC', '*', 0, $limit);
    }

    /**
     * Decode a run's summary column back into an array.
     *
     * @param stdClass $run Run row as returned by get()/get_recent().
     * @return array
     */
    public static function decode_summary(stdClass $run): array {
        if (empty($run->summary)) {
            return [];
        }
        $decoded = json_decode((string)$run->summary, true);
        return is_array($decoded) ? $decoded : [];
    }
}
