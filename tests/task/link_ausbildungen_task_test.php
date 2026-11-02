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
 * Tests for link_ausbildungen_task.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\task;

use local_lehrgaengeapi\local\repository\import_run_repository;
use local_lehrgaengeapi\local\repository\usermap_repository;
use local_lehrgaengeapi\local\repository\usertenant_repository;
use local_lehrgaengeapi\local\users\ausbildungen_profile_field;
use local_lehrgaengeapi\local\users\ausbildungen_writer;

/**
 * Tests for link_ausbildungen_task.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class link_ausbildungen_task_test extends \advanced_testcase {
    /**
     * Profile field for every test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        ausbildungen_profile_field::ensure_exists();
    }

    /**
     * Imported participant with tenant FD and the given Ausbildungen.
     *
     * @param string $externalid
     * @param array $ausbildungen [kurzBezeichnung, status] pairs.
     * @return int
     */
    private function participant(string $externalid, array $ausbildungen): int {
        $user = $this->getDataGenerator()->create_user();
        (new usermap_repository())->set_userid($externalid, (int)$user->id);
        (new usertenant_repository())->remember((int)$user->id, 'FD');
        (new ausbildungen_writer())->write((int)$user->id, [
            'id' => $externalid,
            'ausbildungen' => array_map(static fn($a) => [
                'bezeichnung' => 'Lehrgang ' . $a[0],
                'kurzBezeichnung' => $a[0],
                'von' => '2019-01-01',
                'bis' => '2019-01-02',
                'status' => $a[1],
            ], $ausbildungen),
        ]);
        return (int)$user->id;
    }

    /**
     * Run the task as triggered by the given user, swallowing mtrace output.
     *
     * @param int $triggeredby
     * @return void
     */
    private function run_task(int $triggeredby): void {
        $task = new link_ausbildungen_task();
        $task->set_custom_data(['userid' => $triggeredby]);
        ob_start();
        try {
            $task->execute();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * A run links every user with Ausbildungen, logs a 'link' run with the outcome counts,
     * stores one report row per Ausbildung and notifies the triggering user.
     *
     * @covers \local_lehrgaengeapi\task\link_ausbildungen_task::execute
     */
    public function test_execute_links_all_users_and_logs_run(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['shortname' => 'FD-F-ATR_alt', 'enablecompletion' => 1]);
        $anna = $this->participant('T-1', [['F-Atr', 'BESTANDEN'], ['F-Unbekannt', 'BESTANDEN']]);
        $ben = $this->participant('T-2', [['F-Atr', 'TEILNAHMEWUNSCH']]);
        $this->participant('T-3', []); // Empty list: no rows.
        $admin = $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();

        $this->run_task((int)$admin->id);

        $run = $DB->get_record('local_lehrgaengeapi_import_run', [], '*', MUST_EXIST);
        $this->assertSame(import_run_repository::RUNTYPE_LINK, $run->runtype);
        $this->assertSame(import_run_repository::STATUS_SUCCESS, $run->status);
        $this->assertSame((int)$admin->id, (int)$run->triggeredby);
        $this->assertNull($run->year);

        $summary = import_run_repository::decode_summary($run);
        $this->assertSame(2, $summary['users']);
        $this->assertSame(['linked_own' => 1, 'notfound' => 1, 'statusopen' => 1], $summary['outcomes']);

        $this->assertSame(3, $DB->count_records('local_lehrgaengeapi_link_result', ['runid' => $run->id]));
        $this->assertSame(2, $DB->count_records('local_lehrgaengeapi_link_result', ['runid' => $run->id, 'userid' => $anna]));
        $this->assertTrue(is_enrolled(\context_course::instance((int)$course->id), $anna, '', true));
        $this->assertFalse(is_enrolled(\context_course::instance((int)$course->id), $ben, '', true));

        $messages = $sink->get_messages_by_component_and_type('local_lehrgaengeapi', 'importcomplete');
        $this->assertCount(1, $messages);
        $this->assertSame((int)$admin->id, (int)reset($messages)->useridto);
        $this->assertSame(get_string('linkrunsuccesssubject', 'local_lehrgaengeapi'), reset($messages)->subject);
    }

    /**
     * Deleted users are not processed.
     *
     * @covers \local_lehrgaengeapi\task\link_ausbildungen_task::execute
     */
    public function test_execute_skips_deleted_users(): void {
        global $DB;
        $gone = $this->participant('T-9', [['F-Atr', 'BESTANDEN']]);
        delete_user($DB->get_record('user', ['id' => $gone]));

        $this->run_task(0);

        $run = $DB->get_record('local_lehrgaengeapi_import_run', [], '*', MUST_EXIST);
        $this->assertSame(0, import_run_repository::decode_summary($run)['users']);
        $this->assertSame(0, $DB->count_records('local_lehrgaengeapi_link_result'));
    }

    /**
     * While an import holds the lock the run is logged as skipped and the user is told,
     * nothing is linked and nothing is thrown (one-shot action, no adhoc retry).
     *
     * @covers \local_lehrgaengeapi\task\link_ausbildungen_task::execute
     */
    public function test_execute_skips_when_lock_busy(): void {
        global $DB, $CFG;
        $CFG->lock_factory = '\core\lock\file_lock_factory';
        $this->participant('T-1', [['F-Atr', 'BESTANDEN']]);
        $admin = $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();

        $lock = \core\lock\lock_config::get_lock_factory('local_lehrgaengeapi')->get_lock('sync_lehrgaenge', 0);
        $this->assertNotFalse($lock, 'Test setup failed to acquire the lock.');
        try {
            $this->run_task((int)$admin->id);
        } finally {
            $lock->release();
        }

        $run = $DB->get_record('local_lehrgaengeapi_import_run', [], '*', MUST_EXIST);
        $this->assertSame(import_run_repository::STATUS_SKIPPED, $run->status);
        $this->assertSame(import_run_repository::RUNTYPE_LINK, $run->runtype);
        $this->assertSame(0, $DB->count_records('local_lehrgaengeapi_link_result'));
        $messages = $sink->get_messages_by_component_and_type('local_lehrgaengeapi', 'importcomplete');
        $this->assertCount(1, $messages);
        $this->assertSame(get_string('linkrunskippedsubject', 'local_lehrgaengeapi'), reset($messages)->subject);
    }

    /**
     * The task has a readable name.
     *
     * @covers \local_lehrgaengeapi\task\link_ausbildungen_task::get_name
     */
    public function test_get_name(): void {
        $this->assertSame(
            get_string('tasklinkausbildungen', 'local_lehrgaengeapi'),
            (new link_ausbildungen_task())->get_name()
        );
    }

    /**
     * After a run only the results of the newest 10 linking runs remain.
     *
     * @covers \local_lehrgaengeapi\task\link_ausbildungen_task::execute
     */
    public function test_execute_keeps_results_of_the_newest_ten_runs(): void {
        global $DB;
        $runs = new import_run_repository();
        $results = new \local_lehrgaengeapi\local\repository\link_result_repository();
        $oldest = null;
        for ($i = 1; $i <= 10; $i++) {
            $run = $runs->start(null, null, import_run_repository::RUNTYPE_LINK);
            $DB->set_field('local_lehrgaengeapi_import_run', 'timestarted', 1000 + $i, ['id' => $run->id]);
            $results->save_rows((int)$run->id, 1, [[
                'outcome' => 'notfound',
                'kurzbezeichnung' => 'X',
            ]]);
            $oldest = $oldest ?? (int)$run->id;
        }
        $this->participant('T-1', [['F-Atr', 'BESTANDEN']]);

        $this->run_task(0);

        $this->assertSame(0, $DB->count_records('local_lehrgaengeapi_link_result', ['runid' => $oldest]));
        $this->assertSame(10, (int)$DB->count_records_sql(
            'SELECT COUNT(DISTINCT runid) FROM {local_lehrgaengeapi_link_result}'
        ));
    }
}
