<?php
// (Standard-GPL-Header)

/**
 * Adhoc task: link the Ausbildungen of all users to the _alt completion courses.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\task;

use local_lehrgaengeapi\local\notification\import_notifier;
use local_lehrgaengeapi\local\repository\import_run_repository;
use local_lehrgaengeapi\local\repository\link_result_repository;
use local_lehrgaengeapi\local\services\ausbildungen_linker;
use local_lehrgaengeapi\local\users\ausbildungen_profile_field;

/**
 * Adhoc task: link the Ausbildungen of all users to the _alt completion courses.
 *
 * Triggered from the admin UI. Shares the lock with the import, so the two never run at
 * the same time; like the manual import it is a one-shot action and never retried.
 * @package local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class link_ausbildungen_task extends \core\task\adhoc_task {
    /** @var int Number of newest linking runs whose per-Ausbildung results are kept. */
    private const KEEP_RUNS = 10;

    /**
     * Get the task name shown in admin UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('tasklinkausbildungen', 'local_lehrgaengeapi');
    }

    /**
     * Execute the linking run.
     *
     * @return void
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        $userid = isset($data->userid) ? (int)$data->userid : 0;

        $runs = new import_run_repository();
        $run = $runs->start(null, $userid ?: null, import_run_repository::RUNTYPE_LINK);

        $factory = \core\lock\lock_config::get_lock_factory('local_lehrgaengeapi');
        $lock = $factory->get_lock('sync_lehrgaenge', 0);
        if (!$lock) {
            mtrace('local_lehrgaengeapi: linking skipped - an import is running.');
            $runs->mark_skipped($run->id, 'Ein Import war zum Startzeitpunkt aktiv.');
            $this->notify($userid, import_run_repository::STATUS_SKIPPED);
            return;
        }

        $results = new link_result_repository();
        try {
            $linker = new ausbildungen_linker();
            $users = 0;
            $failedusers = [];
            foreach ($this->get_userids() as $linkuserid) {
                try {
                    $results->save_rows((int)$run->id, $linkuserid, $linker->link_user($linkuserid));
                } catch (\Throwable $e) {
                    // One broken user must not stop the run; the others are still linked.
                    $failedusers[$linkuserid] = $e->getMessage();
                }
                $users++;
                if ($users % 500 === 0) {
                    mtrace('local_lehrgaengeapi: linking - ' . $users . ' users processed.');
                }
            }

            $runs->mark_success($run->id, [
                'users' => $users,
                'outcomes' => $results->count_by_outcome((int)$run->id),
                'failedusers' => $failedusers,
            ]);
            $purged = $results->purge_old_runs(self::KEEP_RUNS);
            mtrace('local_lehrgaengeapi: linking finished - ' . $users . ' users, '
                . $purged . ' result rows of older runs removed.');

            $this->notify($userid, import_run_repository::STATUS_SUCCESS);
        } catch (\Throwable $e) {
            mtrace('local_lehrgaengeapi: linking failed: ' . $e->getMessage());
            $runs->mark_error($run->id, $e->getMessage());
            $this->notify($userid, import_run_repository::STATUS_ERROR);
            // Deliberately not rethrown: a silent adhoc retry would surprise the user.
        } finally {
            $lock->release();
        }
    }

    /**
     * Ids of all non-deleted users with a filled Ausbildungen field.
     *
     * @return int[]
     */
    private function get_userids(): array {
        global $DB;

        $fieldid = ausbildungen_profile_field::get_fieldid();
        if ($fieldid === null) {
            return [];
        }

        $sql = 'SELECT d.userid
                  FROM {user_info_data} d
                  JOIN {user} u ON u.id = d.userid AND u.deleted = 0
                 WHERE d.fieldid = :fieldid
                   AND ' . $DB->sql_isnotempty('user_info_data', 'd.data', false, true) . '
              ORDER BY d.userid';

        return array_map('intval', $DB->get_fieldset_sql($sql, ['fieldid' => $fieldid]));
    }

    /**
     * Notify the triggering user about the outcome of the run.
     *
     * @param int $userid Moodle user id, 0 if unknown.
     * @param string $status One of import_run_repository::STATUS_*.
     * @return void
     */
    private function notify(int $userid, string $status): void {
        import_notifier::notify_user(
            $userid,
            get_string('linkrun' . $status . 'subject', 'local_lehrgaengeapi'),
            get_string('linkrun' . $status . 'body', 'local_lehrgaengeapi')
        );
    }
}
