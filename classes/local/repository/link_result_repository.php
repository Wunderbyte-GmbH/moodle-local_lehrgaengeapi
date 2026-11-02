<?php
// (Standard-GPL-Header)

/**
 * Stores and reads the per-Ausbildung results of a linking run.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\repository;

/**
 * Stores and reads the per-Ausbildung results of a linking run.
 * @package local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class link_result_repository {
    /** @var string DB table name */
    private const TABLE = 'local_lehrgaengeapi_link_result';

    /** @var array<string,int> Char columns and their length, see install.xml. */
    private const LENGTHS = [
        'tenantabbr' => 20,
        'bezeichnung' => 255,
        'kurzbezeichnung' => 100,
        'von' => 10,
        'bis' => 10,
        'apistatus' => 64,
        'outcome' => 30,
    ];

    /**
     * Store the report rows of one user.
     *
     * @param int $runid Run id (local_lehrgaengeapi_import_run.id).
     * @param int $userid Moodle user id.
     * @param array[] $rows Rows as returned by ausbildungen_linker::link_user().
     * @return void
     */
    public function save_rows(int $runid, int $userid, array $rows): void {
        global $DB;

        if (!$rows) {
            return;
        }

        $now = time();
        $records = [];
        foreach ($rows as $row) {
            $record = [
                'runid' => $runid,
                'userid' => $userid,
                'courseid' => isset($row['courseid']) ? (int)$row['courseid'] : null,
                'message' => isset($row['message']) ? (string)$row['message'] : null,
                'timecreated' => $now,
            ];
            foreach (self::LENGTHS as $field => $length) {
                $value = $row[$field] ?? null;
                $record[$field] = $value === null ? null : \core_text::substr((string)$value, 0, $length);
            }
            $records[] = $record;
        }
        $DB->insert_records(self::TABLE, $records);
    }

    /**
     * Number of rows per outcome of one run.
     *
     * @param int $runid
     * @return array<string,int> Outcome => count, sorted by outcome.
     */
    public function count_by_outcome(int $runid): array {
        global $DB;

        $records = $DB->get_records_sql(
            'SELECT outcome, COUNT(1) AS cnt
               FROM {' . self::TABLE . '}
              WHERE runid = :runid
           GROUP BY outcome
           ORDER BY outcome',
            ['runid' => $runid]
        );

        $counts = [];
        foreach ($records as $record) {
            $counts[(string)$record->outcome] = (int)$record->cnt;
        }
        return $counts;
    }

    /**
     * Delete the results of all but the newest linking runs.
     *
     * The run rows themselves stay in the import history; only their per-Ausbildung
     * results are removed, as they grow by thousands of rows per run.
     *
     * @param int $keep Number of newest linking runs whose results are kept.
     * @return int Number of deleted result rows.
     */
    public function purge_old_runs(int $keep): int {
        global $DB;

        $keepids = array_keys($DB->get_records(
            'local_lehrgaengeapi_import_run',
            ['runtype' => import_run_repository::RUNTYPE_LINK],
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
