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
 * Stores and reads the per-Lehrgang results of an import run.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\repository;

/**
 * Stores and reads the per-Lehrgang results of an import run.
 * @package local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_result_repository {
    /** @var string DB table name */
    private const TABLE = 'local_lehrgaengeapi_course_result';

    /** @var array<string,int> Char columns and their length, see install.xml. */
    private const LENGTHS = [
        'tenantabbr' => 20,
        'courseshortname' => 255,
        'externalid' => 64,
        'kurzbezeichnung' => 100,
    ];

    /** @var string[] Counter columns. */
    private const COUNTERS = [
        'userscreated', 'usersexisting', 'usersskipped',
        'enrolled', 'alreadyenrolled', 'unenrolled', 'alreadyunenrolled',
        'noop', 'completed', 'assignskipped', 'total',
    ];

    /**
     * Store result rows of one run.
     *
     * @param int $runid Run id.
     * @param array[] $rows Rows as built by course_result_row::build().
     * @return void
     */
    public function save_rows(int $runid, array $rows): void {
        global $DB;

        if (!$rows) {
            return;
        }

        $now = time();
        $records = [];
        foreach ($rows as $row) {
            $record = [
                'runid' => $runid,
                'courseid' => (int)($row['courseid'] ?? 0),
                'timecreated' => $now,
            ];
            foreach (self::LENGTHS as $field => $length) {
                $value = $row[$field] ?? null;
                $record[$field] = $value === null ? null : \core_text::substr((string)$value, 0, $length);
            }
            // The column is NOT NULL; an id-less Lehrgang never reaches this point, but be safe.
            $record['externalid'] = $record['externalid'] ?? '';
            foreach (self::COUNTERS as $counter) {
                $record[$counter] = (int)($row[$counter] ?? 0);
            }
            $records[] = $record;
        }
        $DB->insert_records(self::TABLE, $records);
    }

    /**
     * Whether a run has stored results (new runs) or only the legacy JSON summary.
     *
     * @param int $runid
     * @return bool
     */
    public function has_rows(int $runid): bool {
        global $DB;
        return $DB->record_exists(self::TABLE, ['runid' => $runid]);
    }

    /**
     * Delete the results of all but the newest import runs.
     *
     * @param int $keep Number of newest import runs whose results are kept.
     * @return int Number of deleted rows.
     */
    public function purge_old_runs(int $keep): int {
        global $DB;

        $keepids = array_keys($DB->get_records(
            'local_lehrgaengeapi_import_run',
            ['runtype' => import_run_repository::RUNTYPE_IMPORT],
            'timestarted DESC, id DESC',
            'id',
            0,
            max(0, $keep)
        ));

        $select = '';
        $params = [];
        if ($keepids) {
            [$notin, $params] = $DB->get_in_or_equal($keepids, SQL_PARAMS_NAMED, 'keep', false);
            $select = 'runid ' . $notin;
        }

        $deleted = $DB->count_records_select(self::TABLE, $select, $params);
        if ($deleted > 0) {
            $DB->delete_records_select(self::TABLE, $select, $params);
        }
        return $deleted;
    }
}
