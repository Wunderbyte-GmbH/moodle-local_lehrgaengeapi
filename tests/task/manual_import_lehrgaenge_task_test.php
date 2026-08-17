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
 * Tests for manual_import_lehrgaenge_task.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\task;

use local_lehrgaengeapi\local\repository\import_run_repository;

/**
 * Tests for manual_import_lehrgaenge_task.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manual_import_lehrgaenge_task_test extends \advanced_testcase {
    /**
     * Successful run (no real apikey configured, so every tenant short-circuits with a
     * zero summary) logs a success row for the given year/user and sends the triggering
     * user a completion notification.
     *
     * @covers \local_lehrgaengeapi\task\manual_import_lehrgaenge_task::execute
     */
    public function test_execute_success_path_notifies_and_logs_run(): void {
        global $DB;
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();

        $task = new manual_import_lehrgaenge_task();
        $task->set_custom_data(['year' => 2024, 'userid' => (int)$user->id]);
        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertSame(1, $DB->count_records('local_lehrgaengeapi_import_run'));
        $run = $DB->get_record('local_lehrgaengeapi_import_run', [], '*', MUST_EXIST);
        $this->assertSame(import_run_repository::STATUS_SUCCESS, $run->status);
        $this->assertSame(2024, (int)$run->year);
        $this->assertSame((int)$user->id, (int)$run->triggeredby);

        $messages = $sink->get_messages_by_component_and_type('local_lehrgaengeapi', 'importcomplete');
        $this->assertCount(1, $messages);
        $this->assertSame((int)$user->id, (int)reset($messages)->useridto);
    }

    /**
     * When the sync_lehrgaenge lock is already held, execute() must NOT throw (unlike the
     * scheduled task): this is a one-shot user action, so it logs a skipped run and
     * notifies the user instead of relying on Moodle's adhoc task retry mechanism.
     *
     * Uses the file lock factory explicitly - see sync_lehrgaenge_task_test for why the
     * default DB-native factory can't produce a self-conflict within one PHPUnit process.
     *
     * @covers \local_lehrgaengeapi\task\manual_import_lehrgaenge_task::execute
     */
    public function test_execute_skipped_when_lock_busy_does_not_throw(): void {
        global $DB, $CFG;
        $this->resetAfterTest(true);
        $CFG->lock_factory = '\core\lock\file_lock_factory';

        $user = $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();

        $factory = \core\lock\lock_config::get_lock_factory('local_lehrgaengeapi');
        $lock = $factory->get_lock('sync_lehrgaenge', 0);
        $this->assertNotFalse($lock, 'Test setup failed to acquire the lock.');

        try {
            $task = new manual_import_lehrgaenge_task();
            $task->set_custom_data(['year' => 2023, 'userid' => (int)$user->id]);
            ob_start();
            $task->execute(); // Must not throw.
            ob_end_clean();

            $this->assertSame(1, $DB->count_records('local_lehrgaengeapi_import_run'));
            $run = $DB->get_record('local_lehrgaengeapi_import_run', [], '*', MUST_EXIST);
            $this->assertSame(import_run_repository::STATUS_SKIPPED, $run->status);
            $this->assertSame(2023, (int)$run->year);

            $messages = $sink->get_messages_by_component_and_type('local_lehrgaengeapi', 'importcomplete');
            $this->assertCount(1, $messages);
        } finally {
            $lock->release();
        }
    }

    /**
     * A genuine sync failure is logged as an error run and notified, but NOT rethrown
     * (unlike the scheduled task) - a failed one-shot import should not be silently
     * retried later by Moodle's adhoc task retry mechanism.
     *
     * @covers \local_lehrgaengeapi\task\manual_import_lehrgaenge_task::execute
     */
    public function test_execute_error_path_logs_run_and_does_not_rethrow(): void {
        global $DB;
        $this->resetAfterTest(true);

        // Port 1 on loopback is refused instantly by the OS - no real network access
        // needed and no slow timeout to wait out.
        set_config('baseurl', 'http://127.0.0.1:1', 'local_lehrgaengeapi');
        set_config('timeout', 2, 'local_lehrgaengeapi');
        set_config('apikey_hp', 'testkey', 'local_lehrgaengeapi');

        $user = $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();

        $task = new manual_import_lehrgaenge_task();
        $task->set_custom_data(['year' => 2022, 'userid' => (int)$user->id]);
        ob_start();
        $task->execute(); // Must not throw.
        ob_end_clean();

        $this->assertSame(1, $DB->count_records('local_lehrgaengeapi_import_run'));
        $run = $DB->get_record('local_lehrgaengeapi_import_run', [], '*', MUST_EXIST);
        $this->assertSame(import_run_repository::STATUS_ERROR, $run->status);

        $messages = $sink->get_messages_by_component_and_type('local_lehrgaengeapi', 'importcomplete');
        $this->assertCount(1, $messages);
    }

    /**
     * A missing/unknown triggering user (userid 0) still logs the run but sends no
     * notification, instead of failing on a missing recipient.
     *
     * @covers \local_lehrgaengeapi\task\manual_import_lehrgaenge_task::execute
     */
    public function test_execute_without_userid_skips_notification_but_still_logs_run(): void {
        global $DB;
        $this->resetAfterTest(true);

        $sink = $this->redirectMessages();

        $task = new manual_import_lehrgaenge_task();
        $task->set_custom_data(['year' => 2021, 'userid' => 0]);
        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertSame(1, $DB->count_records('local_lehrgaengeapi_import_run'));
        $run = $DB->get_record('local_lehrgaengeapi_import_run', [], '*', MUST_EXIST);
        $this->assertNull($run->triggeredby);

        $this->assertCount(0, $sink->get_messages_by_component_and_type('local_lehrgaengeapi', 'importcomplete'));
    }
}
